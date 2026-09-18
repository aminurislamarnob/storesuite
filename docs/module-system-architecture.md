# Module System — Architecture & Implementation Notes

> Companion to `docs/how-to-add-module.md` (which is task-oriented).
> This document is reviewer-oriented: it walks every file the module
> system touches, explains the design decision behind it, and flags
> non-obvious behaviour. Treat it as a handover dossier — if you're
> the next developer maintaining or extending the module system, read
> this first.
>
> Source: PR [#143](https://github.com/aminurislamarnob/storesuite/pull/143).
> All file references exclude `docs/sample-module/staff-manager/` — that
> directory is kept as a worked example, not as code under review. (It was
> formerly `modules/staff-manager/`; it now lives outside `modules/` so the
> discovery glob no longer treats it as a live module.)

---

## Mental model

A StoreSuite **module** is a self-contained feature shipped under
`modules/<slug>/`. It is:

- **Discovered** at runtime by globbing `modules/*/module.php`.
- **Persisted** as an active/inactive flag in the
  `storesuite_active_modules` option.
- **Booted** (on `storesuite_loaded`) only when active.
- **Surfaced** in the React admin via runtime-registered routes,
  schema-driven settings forms, and per-module top-nav tabs.

The system is intentionally additive: adding or removing a module never
requires editing the core orchestrator, the REST controller, the React
entry, or webpack config. A single core abstract class + a single core
manager + a single REST controller + a single React filter handle
everything.

---

## File-by-file walkthrough

### `includes/Abstracts/Module.php` (NEW)

**Purpose.** The contract every module implements. Defines the lifecycle
(`activate`, `deactivate`, `boot`), the metadata accessors (`get_slug`,
`get_name`, `get_description`, `get_version`), and the optional hooks
modules can override to expose configurable settings, contribute top-nav
tabs, or enqueue their own React bundles.

**Key methods.**

| Method | Required? | When called |
|---|---|---|
| `get_slug()` | ✅ | Whenever the registry needs to key/look up the module. |
| `get_name()` | ✅ | Modules screen card title. |
| `boot()` | ✅ | Once per request on `storesuite_loaded` (only if active). |
| `activate()` / `deactivate()` | optional | Once when the admin toggles the switch. Use for DB schema, seeding options. |
| `has_settings()`, `get_settings_schema()`, `get_settings()`, `update_settings()` | optional | When the REST `/modules/{slug}/settings` endpoints are hit. |
| `get_admin_tabs()` | optional | When `ModulesController::prepare_module()` builds the list payload. Result is sanitized by the controller — see `sanitize_admin_tabs()`. |
| `enqueue_admin_assets()` | optional override | `Assets::enqueue_admin_scripts` invokes it for every active module on the StoreSuite settings page. |

**Design notes.**

- The constructor takes `$file` (the bootstrap's `__FILE__`) because the
  base class can't compute its own path — the abstract lives under
  `includes/Abstracts/` while modules live under `modules/`. The
  bootstrap must pass `__FILE__` so `get_path()` / `get_url()` resolve
  to the module directory, not the abstract's.
- All optional methods have sensible no-op defaults so modules opt
  in to features. A module with only `get_slug`/`get_name`/`boot` is
  fully valid and will show up on the Modules screen with a working
  toggle.
- `enqueue_admin_assets()` has a non-trivial default implementation
  (not a no-op) because every module that ships React benefits from
  the same enqueue logic: read the auto-generated `script.asset.php`,
  declare `storesuite-admin-page` + `wp-hooks` as forced dependencies,
  enqueue. Override only when you need custom CSS or non-standard
  asset paths.

**Gotchas.**

- The default `update_settings()` includes `unset( $data )` to silence
  PHPCS's unused-parameter warning. Subclasses override the method
  entirely, so the param IS used in practice — don't be tempted to
  remove it from the signature.
- `update_settings()` is typed `array $data` — REST already coerces
  the incoming JSON to an array before calling, so this is safe.

---

### `includes/Module/Manager.php` (NEW)

**Purpose.** The orchestrator. Discovers modules, persists active state,
boots active modules, exposes the public query API
(`get_all`/`get_active`/`is_active`), and handles activation/deactivation
side effects.

**Key entry points.**

| Method | Used by |
|---|---|
| `discover()` | Lazy, called from `get_all()`. Glob-based, runs once per request. |
| `get_all()` | `ModulesController::get_items()` for the listing endpoint. |
| `get_active()` | `Manager::boot_active()` and `Assets::enqueue_active_module_assets()`. |
| `activate( $slug )` / `deactivate( $slug )` | `ModulesController::activate_item()` / `deactivate_item()`. |
| `is_active( $slug )` | Anywhere in PHP that wants to feature-flag on a module's state. |

**Design notes.**

- **Lazy discovery.** `discover()` only runs when `get_all()` is called.
  Requests that don't touch the module registry (most frontend pageviews,
  cron, REST endpoints unrelated to modules) pay zero cost.
- **Lifecycle wiring.** The constructor hooks `storesuite_loaded` to
  call `boot_active()`. The Manager itself is instantiated inside
  `StoreSuite::init_plugin()` **before** the `storesuite_loaded` action
  fires (see `includes/StoreSuite.php`) — that ordering is critical;
  if the Manager were constructed later, its hook would miss the
  action entirely.
- **Activation side effects.** `activate()` and `deactivate()` both
  call `flag_rewrite_flush()`, which sets the existing
  `storesuite_flush_rewrite_rules` option that
  `StoreSuite::maybe_flush_rewrite_rules()` already handles. This is
  intentional: a module can register a rewrite endpoint in `boot()`,
  and toggling its activation state needs to flush rewrites for the
  endpoint to start/stop routing.
- **Idempotency.** `activate()` is a no-op if the slug is already in the
  option, but still returns `true`. The REST controller treats `true`
  as "now active" regardless of prior state — UI gets a consistent
  response.
- **External module sources.** The `storesuite_register_modules` filter
  lets third-party add-on plugins inject modules into the registry
  without writing files to `modules/`. The filter fires inside
  `discover()` after the bundled modules are loaded.

**Gotchas.**

- `get_active_slugs()` reads the option, intersects with the actually
  discovered set, and returns only intersected slugs. This means stale
  slugs (module folder deleted but option still references it) are
  silently dropped from queries — no orphan errors, but also no warning.
  If you ever need a reaper, write it as a separate maintenance task.
- `glob()` only matches one level (`modules/*/module.php`). Modules can
  have their own `includes/`, `assets/`, `templates/` subfolders without
  the loader picking up nested files.

---

### `includes/REST/ModulesController.php` (NEW)

**Purpose.** Exposes the module registry over REST so the React admin
can read state, activate/deactivate, and read/write per-module settings.

**Routes.**

| Verb | Path | Handler | Returns |
|---|---|---|---|
| GET | `/storesuite/v1/modules` | `get_items()` | List of all modules with `{slug, name, description, version, active, has_settings, admin_tabs}`. |
| POST | `/storesuite/v1/modules/{slug}/activate` | `activate_item()` | Updated module entry. 404 if slug unknown. |
| POST | `/storesuite/v1/modules/{slug}/deactivate` | `deactivate_item()` | Same. |
| GET | `/storesuite/v1/modules/{slug}/settings` | `get_settings()` | `{schema, values}`. 400 if module exists but `has_settings()` returns false. |
| POST | `/storesuite/v1/modules/{slug}/settings` | `update_settings()` | Updated `{schema, values}`. Body shape: `{ values: { ... } }`. |

**Security model.**

- All routes gated by `current_user_can( 'manage_options' )` — same
  gate as the existing `SettingsController`. Cookie auth + REST nonce
  handled automatically by `apiFetch`.
- Slug regex `[A-Za-z0-9_\-]+` prevents path traversal at the route
  layer.
- Manager validates against `isset( $modules[ $slug ] )` before any
  side effect, returning 404 for unknown slugs.

**Critical helper: `sanitize_admin_tabs()`.**

Third-party modules can return arbitrary shapes from `get_admin_tabs()`.
`sanitize_admin_tabs()` whitelists each entry before the React app sees
it:

- Each entry must be an array with non-empty string `to` and `label`.
- `to` must start with a single `/` (React Router relative path);
  paths starting with `//` are rejected to prevent protocol-relative
  off-site escapes.
- `to` must not contain `<`, `>`, `"`, `'` (defense in depth).
- Only `to` and `label` keys survive — extras are stripped.

This is the boundary that protects the SPA from malformed module input.
**Do not bypass it** by passing `$module->get_admin_tabs()` directly to
the response anywhere else; route everything through
`prepare_module()`.

**Settings flow.**

`update_settings()` accepts `$request->get_json_params()['values']` and
delegates to `Module::update_settings()`. The module owns its sanitizer
(see `Settings::sanitize_field()` in any concrete module). The
controller doesn't know or care about field shapes — that's a deliberate
separation: schema lives in the module, validation lives in the module,
the controller just shuttles data.

**Gotchas.**

- The `args` block on the activate/deactivate routes only declares the
  type of `slug` — it doesn't enforce a pattern; the URL regex does.
- `get_settings()` and `update_settings()` are on the controller AND on
  the Module abstract. Don't confuse them — the abstract methods are
  what subclasses override.

---

### `includes/Assets.php` (MODIFIED)

**What changed.** Added two things to the existing
`enqueue_admin_scripts()` method:

1. At the end of the `'woocommerce_page_storesuite' === $page->id`
   block, a call to `$this->enqueue_active_module_assets()`.
2. The new helper `enqueue_active_module_assets()` walks
   `$container->modules->get_active()` and invokes
   `enqueue_admin_assets()` on each one.

**Why this shape.** Modules don't enqueue themselves on every request —
that would be wasteful and would also require every module to repeat
the "is this the storesuite settings page?" check. Centralising the
gate here means modules' default `enqueue_admin_assets()`
implementation just enqueues unconditionally; the gate happens once,
in core.

**Gotcha (real one — bit us during PR review).** The helper has the
guard:

```php
if ( ! isset( $container->modules ) ) {
    return;
}
```

PHP **does not** consult `__get()` for `isset()` — it consults
`__isset()`. Before commit `2396dd4` added `__isset()` to
`StoreSuite.php`, this guard always returned `false`, silently
skipping every module's bundle enqueue. The visible symptom was
"`/staff-manager` routes to a blank fallback because no React route
is registered." The fix is upstream in `StoreSuite.php` — keep that
in mind if you ever add similar `isset()` guards on container
services elsewhere.

---

### `includes/StoreSuite.php` (MODIFIED)

**What changed.** Three additions to the singleton:

1. **`__isset()` magic method.** Companion to the existing `__get()`.
   Required for the `isset( $container->modules )` guard in
   `Assets::enqueue_active_module_assets()` (and any future similar
   guards) to work. Without it, `isset()` on a magic container property
   always returns false.

2. **Module Manager instantiation inside `init_plugin()`,
   before `do_action( 'storesuite_loaded' )`.** The Manager hooks
   `storesuite_loaded` to call `boot_active()`. It must be constructed
   before the action fires, otherwise its hook never runs.

3. **`storesuite_modules_controller` registered in `register_rest_route()`
   and `init_classes()`.** Standard wiring for a new REST controller.

**Design notes.**

- The Manager is special-cased: it's instantiated in `init_plugin()`
  rather than in `init_classes()` (which runs later, on `init` priority
  4). That's because `storesuite_loaded` fires from `init_plugin()`
  itself, and the Manager must exist before that.
- All other modules' controllers and services follow the existing
  pattern of being constructed in `init_classes()`.

**Gotcha.** The order inside `init_plugin()` is precise:

```php
$this->includes();
$this->init_hooks();
$this->container['modules'] = new Module\Manager();   // <- must be here
do_action( 'storesuite_loaded' );
```

Don't move the Manager construction into `init_classes()` thinking
it's "cleaner" — it'll break module booting.

---

### `src/admin.js` (MODIFIED)

**Purpose.** Mounts the React admin app. After the refactor, this file
owns the runtime route registry.

**Key pieces.**

- `collectModuleRoutes()` — calls
  `applyFilters( 'storesuite_admin_routes', [] )` and validates each
  entry has a string `path` and a function `element`. Bad entries are
  silently dropped (matches what the PHP boundary does for
  `admin_tabs`).
- `NotFound` component — rendered as a catch-all `<Route path="*">`
  inside Layout. Shows the unmatched path, a link back to Modules,
  and a hint about running `npm run build`. Replaces what used to
  be a hard-to-debug blank page.
- The App mounts via `domReady`-style `DOMContentLoaded` listener.
  Module bundles, enqueued as deps of the core script (loaded after
  it but before DCL), have already called `addFilter` by the time
  the App reads `applyFilters` at mount.

**Why `@wordpress/hooks` instead of a bespoke registry.**

An earlier iteration used `window.StoreSuite.registerScreens()`. The
`wp.hooks` filter pattern is what Dokan Pro uses (see
`dokan-dashboard-routes`) and what `@wordpress/plugins`,
`@wordpress/data`, etc. use everywhere in the WP ecosystem. Benefits:

- Any script (bundled or inline) can hook in without learning a
  StoreSuite-specific API.
- `wp-hooks` is now a declared dependency in every module's
  `script.asset.php` (auto-generated by `@wordpress/scripts`) and
  externalized to `window.wp.hooks` — no duplication across bundles.
- Third-party developers already know the API.

**Gotchas.**

- `collectModuleRoutes()` runs in the App component body, so it
  re-evaluates on every render. That's fine: `applyFilters` is cheap
  and the route list doesn't change between renders in practice.
  Don't `useMemo` it — the simplicity is worth more than the
  micro-optimization.
- If a module bundle isn't loaded (PHP didn't enqueue, file missing,
  etc.), its `addFilter` never runs, `applyFilters` returns `[]`,
  and the URL falls through to `NotFound`. The fallback's copy
  explicitly mentions `npm run build` because that's the most
  common cause during development.

---

### `src/Components/Layout.js` (MODIFIED)

**What changed.** Two additions on top of the existing layout shell:

1. **Dynamic top-nav tabs.** A `moduleTabs` state, populated by
   fetching `/storesuite/v1/modules` on mount, merged into
   `BUILT_IN_TABS` immediately before the `/modules` entry so the
   Modules tab stays anchored to the right edge of the nav.

2. **Refresh-on-event subscription.** Listens for the
   `storesuite:modules-changed` window event (dispatched by
   `ModulesSettings.js` after a toggle) and re-fetches the modules
   list. Tab visibility updates instantly without a full reload.

**Why this shape.**

- The modules list is the only network-fetched piece of nav data, so
  Layout owns the fetch directly rather than going through a context.
  If we add a second per-module signal that needs to drive UI, the
  right move is to lift the fetch into a small `ModulesContext` —
  not bigger.
- The `MODULES_CHANGED_EVENT` constant is exported because
  `ModulesSettings.js` imports it. Don't duplicate the string
  literal; use the constant.

**Gotcha.** Module-injected tabs are inserted via array splice at the
index of the `/modules` entry. If you ever rename or remove the
`/modules` built-in route, update `modulesIndex` logic.

---

### `src/Components/ModulesSettings.js` (NEW)

**Purpose.** The "Modules" tab. Renders the discovered modules as a
responsive card grid with name, version badge, description, and an
activation toggle. Each card shows a "Configure" link when the module
has settings.

**Key behaviours.**

- Per-card busy state (`busySlug`) so flipping one toggle doesn't
  block others.
- On successful toggle:
  1. Updates the local `modules` state with the response.
  2. Dispatches `storesuite:modules-changed` so Layout refreshes the
     top-nav tabs.
  3. If the module exposes settings or admin tabs, schedules a
     `window.location.reload()` after 600ms. The reload is necessary
     because PHP enqueues module bundles on page load — a newly active
     module's React surface isn't reachable until the next page load.
     The 600ms delay lets the snackbar render before the reload.
- Snackbar on success (green check icon) or error (red exclamation
  icon).

**Layout decisions.** Cards use `grid-template-columns: repeat(auto-fill,
minmax(320px, 1fr))` so the grid reflows from one column to four-plus
based on viewport width. Active cards get a subtle green border accent
via the `.is-active` class.

**Gotcha.** The "Configure" link is disabled when the module is
inactive (no point in editing settings for a disabled module). The
button visual state matches.

---

### `src/Components/ModuleSettings.js` (NEW)

**Purpose.** The per-module Configure form, reached via
`#/modules/{slug}`. Renders a form generically from the schema returned
by `GET /storesuite/v1/modules/{slug}/settings`.

**Generic field renderer.** The `renderField` helper switches on the
schema's `type`:

| Schema `type` | React control |
|---|---|
| `toggle` | `ToggleControl` |
| `select` | `SelectControl` (options from `field.options`) |
| `number` | `TextControl` with `type="number"`, `min`, `max` |
| `text` (default) | `TextControl` |

Adding a new field type means:

1. Add a case in `renderField`.
2. Add a corresponding case in `Settings::sanitize_field()` in any
   module that uses the new type.
3. Document the new type in `docs/how-to-add-module.md` §3.4.

**The back-affordance.** A chevron-left button at the start of the
"Module Settings" title routes to `/modules`. It uses
`@heroicons/react/24/outline`'s `ChevronLeftIcon` (re-exported via
`src/Components/icons.js`).

**404 handling.** If the slug doesn't exist or the module doesn't
expose settings, the GET returns 404 → the component sets `notFound`
state → renders a fallback card with a "Back to modules" button. No
blank page.

---

### `src/Components/icons.js` (MODIFIED)

**What changed.** Re-exports two new heroicons: `PuzzlePieceIcon` (used
on the Modules tab + module card icon) and `ChevronLeftIcon` (used on
the Module Settings back affordance).

**Why this file exists at all.** Centralising icon imports gives one
place to audit which icons we ship and lets tree-shaking work
consistently. Don't import heroicons directly in component files
unless you're inside a module bundle (where this file isn't
reachable).

---

### `src/Components/LayoutStyles.css` (MODIFIED)

**What was added.** Four blocks at the bottom of the file:

1. **Modules grid** (`.storesuite-modules-grid`,
   `.storesuite-module-card*`). Responsive grid + card chrome + active
   state accent.
2. **Per-card actions row** (`.storesuite-module-card__actions`,
   `.storesuite-module-card__footer`). Layout for toggle + Configure
   button.
3. **Module Settings back chevron**
   (`.storesuite-module-settings-back`). 22×22 button with a 14×14
   icon, `#f0f0f1` background at rest, `#dcdcde` on hover/focus.

The CSS follows the existing file's flat-section convention — no
preprocessor, no nesting, plain selectors prefixed `.storesuite-`.

---

### `webpack.config.js` (MODIFIED)

**What changed.** Three additions:

1. **Module entry auto-discovery.** Globs `modules/*/src/index.js` and
   adds an entry per match. The entry **key** uses `../../` (e.g.,
   `'../../modules/inventory-manager/assets/build/script'`) so the output,
   normally written under `assets/build/`, escapes that directory and
   lands inside the module's own `modules/<slug>/assets/build/script.js`.
   This trick is borrowed from Dokan Pro's `webpack-entries.js` and
   keeps modules portable — distributing a module folder includes its
   own build artifacts.

2. **`@storesuite/components` alias.** Resolves to `src/Components/`.
   Module bundles can import from this stable path today (currently
   bundled and tree-shaken per module) and the same imports will
   keep working if/when the alias is later flipped to a webpack
   external. No module source changes required at that point.

3. **No change to externals.** The existing `@woocommerce/*` mappings
   are untouched. `@wordpress/scripts` already externalizes
   `@wordpress/*` to `window.wp.*` so module bundles don't duplicate
   them.

**Why glob discovery vs. manual entries.** Dokan Pro maintains a
hand-curated `webpack-entries.js` listing every module bundle. That's
viable when modules ship from a separate Pro repo (lite-vs-pro split),
but for a single-product plugin like StoreSuite, glob discovery means
adding a module never requires editing webpack config. The trade-off
is that adding a module requires restarting `npm run start` so the
config re-reads.

**Gotchas (real ones, from PR iteration).**

- The first attempt used `'../modules/<slug>/...'` (single `../`) and
  the output landed at `assets/modules/<slug>/assets/build/script.js`
  — only one level up. Two levels is needed to escape `assets/build/`.
- `npm run start` (`webpack --watch`) does **not** reload
  `webpack.config.js`. If you change paths or add a new module while
  the dev server is running, restart it.

---

### `docs/how-to-add-module.md` (NEW)

**Purpose.** Build-by-numbers guide for adding a new module. Optimized
for AI agents and humans who already understand the architecture and
need a checklist with templates.

**Relationship to this document.** That doc is *task-oriented* (here's
how to do X). This doc is *reviewer-oriented* (here's why X is shaped
this way). Both are needed — keep them in sync when the underlying
code changes.

---

### `CLAUDE.md` (MODIFIED)

**What changed.** The Module system paragraph now ends with a pointer
to `docs/how-to-add-module.md` so future AI agents discover it from the
project's canonical entry point. No architectural change.

---

### `composer.lock` (MODIFIED)

Dependency refresh from a remote commit (`c11ee85`). Not architecturally
meaningful. Mentioned here only for completeness.

---

## Cross-cutting design themes

### 1. Module sovereignty

Each module owns its slice end-to-end: its PHP class, its DB schema, its
REST routes (optional), its React source, its CSS. Core never imports
from `modules/<slug>/`; the only edge that crosses into a module is the
abstract class extension and the optional `enqueue_admin_assets()`
default behaviour. Conversely, modules don't import from core `src/` —
they go through `@storesuite/components` or directly from
`@wordpress/*`.

### 2. Sensible boundary defaults

Every cross-trust boundary has a sanitizer:

- **REST input → PHP.** `Settings::sanitize_field()` enforces declared
  types per field. Unknown keys are dropped silently.
- **Module PHP → REST output.** `ModulesController::sanitize_admin_tabs()`
  whitelists `{to, label}` and rejects anything else.
- **PHP REST → React.** `collectModuleRoutes()` in `src/admin.js`
  validates each filter entry has a string `path` and function
  `element`. Bad entries are silently dropped.

Adding a new boundary? Add a new sanitizer.

### 3. WP-native extensibility primitives

We use `@wordpress/hooks` (not a bespoke `window.*` registry) for routes
because that's what every other WP-ecosystem plugin uses. We use the
existing `storesuite_dashboard_menus` filter for sidebar items because
that's what's already in place. We use option storage with
`storesuite_<slug>_*` keys (not custom tables for settings) because that's
the WP convention. None of this is novel — it's deliberately boring.

### 4. Build artifacts colocate with the module

Module React bundles output to `modules/<slug>/assets/build/`. Reasons:

- A module folder is a portable unit; the build ships with it.
- Cleaning core `assets/build/` doesn't touch module artifacts.
- Matches Dokan Pro's distribution model.

This requires the `../../` entry-key trick in `webpack.config.js`. If
you're new and the path looks weird, that's why.

### 5. Activation reloads the page (deliberate)

`ModulesSettings.js` reloads the page after activating a module that
exposes settings or admin tabs. The reload is **not** about cache —
it's about loading the module bundle, which PHP can only enqueue on
the next page render. Without the reload, the user would click the
newly-visible "Staff" tab and route to a blank `NotFound` page because
the module's `addFilter` hasn't run.

The reload is debounced by 600ms so the success snackbar renders
first. This is a small UX wrinkle we accepted in exchange for true
module isolation — see the "What you actually pay" section of the
performance writeup in PR discussion if curious.

---

## When you're modifying this code

### Adding a new abstract method to `Module.php`

1. Default to a no-op so existing modules don't break.
2. If the new method is called from the REST controller, surface it in
   `prepare_module()` so the React app sees it.
3. If it's called from PHP elsewhere (e.g., a frontend enqueue),
   add the call site to `Assets.php` or wherever appropriate, gated
   by the relevant page check.
4. Update `docs/how-to-add-module.md` §6 (extension points cheat sheet).
5. Update this document's "File-by-file" entry for `Module.php`.

### Adding a new REST route on `ModulesController`

1. Register the route in `register_routes()` with a
   `permission_callback`.
2. Add the handler method.
3. Reuse `resolve_settings_module()` style helpers if you need
   slug-validation boilerplate.
4. If the response surfaces module-provided data, route it through
   `prepare_module()` or a similar whitelisting helper — do not
   serialize raw module output.

### Adding a new field type to settings forms

1. Add a `case` in `ModuleSettings.js`'s `renderField()`.
2. Add a `case` in any module's `Settings::sanitize_field()` that
   uses the new type.
3. Document the new type in `docs/how-to-add-module.md` §3.4.

### Adding a new built-in admin route

1. Add `<Route>` declaration in `src/admin.js` inside the Layout's
   children, **before** the module routes map. (Modules' filter-driven
   routes should not be able to shadow built-in routes.)
2. Add a `BUILT_IN_TABS` entry in `Layout.js` if it needs a top-nav
   tab. Decide whether module-injected tabs should sit before or after
   the new tab and update `modulesIndex` logic accordingly.
3. Add an icon to `src/Components/icons.js` (24/outline variant from
   heroicons).

---

## Open improvements (intentionally deferred)

Documented here so they don't get lost:

1. **Per-module `uninstall.php` hook.** Currently, deactivating a
   module preserves its DB table (correct, by WP convention). When a
   module is permanently removed, that table orphans. Future work:
   add a way for modules to register cleanup logic that runs on
   plugin uninstall.
2. **Cross-module Redux store registration.** Dokan Pro registers a
   `@wordpress/data` store per module (e.g., `dokan/vendor-staff`).
   Premature for StoreSuite today; revisit when modules need to share
   state.
3. **Module dependency declaration.** `Module::get_requires()`
   currently returns an empty array — reserved for a future "this
   module needs WooCommerce 9.x" / "this module needs another module"
   check.
4. **Component-extraction alias as a runtime external.** Today,
   `@storesuite/components` bundles core components into every module
   that uses them. The alias is set up so that flipping to an
   external (via `@wordpress/dependency-extraction-webpack-plugin` or
   a hand-written external mapping) is a one-line change — no module
   source updates required.

---

## Reading order for new maintainers

If you've never touched this code, read in this order:

1. `docs/how-to-add-module.md` — get a concrete example fixed in your
   head.
2. `includes/Abstracts/Module.php` — the contract.
3. `includes/Module/Manager.php` — how the contract is honored.
4. `includes/REST/ModulesController.php` — how it's surfaced to the UI.
5. `src/admin.js` + `src/Components/ModulesSettings.js` +
   `src/Components/ModuleSettings.js` — the UI itself.
6. `webpack.config.js` — the magic that makes module bundles ship.
7. This document — to understand *why* anything looks the way it does.
