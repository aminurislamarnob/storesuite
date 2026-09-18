# StoreSuite Test Suite — How It Works

This document explains how the PHPUnit test suite in `tests/` is put together,
what happens when you run it, and how to add your own tests.

## Quick start

```bash
composer test
```

That runs `vendor/bin/phpunit`, which reads `phpunit.xml` (your local,
gitignored copy) or `phpunit.xml.dist` (the committed default). Both point at
`tests/bootstrap.php` and pick up every `*Test.php` file under `tests/`.

## What kind of tests are these?

These are **WordPress integration tests**, not plain unit tests. Each test runs
inside a real (throwaway) WordPress install with WooCommerce and StoreSuite
loaded, backed by a real local MySQL database (`storesuite_tests`). That means
tests can call `update_option()`, `add_filter()`, `do_action()`, etc. and they
behave exactly like they do on a live site.

The machinery that makes this possible is the
[`wp-phpunit/wp-phpunit`](https://github.com/wp-phpunit/wp-phpunit) package —
the same test framework WordPress core uses, installed via Composer.

## Suite map — what lives where

| Area | Files | Covers |
| --- | --- | --- |
| Browser E2E (Playwright) | `e2e/specs/*.spec.js` | Real-browser flows against the local Herd site — see "Browser E2E suite" below |
| JS unit (Jest) | `js/**/*.test.js` | React settings app + dashboard/analytics JS logic — see "JS unit suite" below |
| End-to-end flows | `Integration/PluginBootTest.php`, `Integration/*AjaxTest.php`, `Integration/DashboardShortcodeTest.php` | Full plugin boot wiring (container, hooks, REST routes, shortcode); category/coupon/account form flows through real `wp_ajax_storesuite_*` dispatch (nonce → capability → validation → persistence → lifecycle actions); shortcode access gating and query-var template routing |
| Module system | `Module/ManagerTest.php` | Discovery, dependency gating, activate/deactivate lifecycle, boot ordering, registry hygiene (detailed below) |
| REST API | `REST/ModulesControllerTest.php`, `REST/SettingsControllerTest.php`, `REST/ChangelogControllerTest.php` | Permissions (401/403), module list/activate/deactivate/settings, admin-tab sanitization, settings CRUD, readme changelog parsing |
| Analytics | `Analytics/RestPermissionsTest.php`, `Analytics/SettingsTest.php` | wc-analytics read-access widening for shop managers; JS settings payload, per-capability preload transient, role-change invalidation |
| Orders | `Order/OrderManagerTest.php` | Listing filters (status/customer/month/channel), pagination, months dropdown (HPOS + legacy), order actions, column renderers |
| Products | `Product/ProductManagerTest.php`, `Product/ProductExporterTest.php` | CRUD via WC APIs, CSV export |
| Coupons | `Coupon/CouponManagerTest.php` | Coupon CRUD/listing |
| Taxonomies | `Taxonomy/TaxonomyListingTest.php` | Category/brand/tag hierarchy flattening, pagination, search, cache busting |
| Inventory Manager module | `InventoryManager/*Test.php` | Module lifecycle, installer/settings, stock repository/log, REST controller |
| Core services | `CacheTest.php`, `MainTest.php`, `FunctionsTest.php`, `HelperTest.php`, `HandlePaginationsTest.php`, `InstallerUpgraderTest.php`, `RoutingMenuTest.php`, `DashboardPageTest.php` | Cache wrapper, access control, global helpers, pagination math, install/upgrade routines, rewrites + dashboard menu, dashboard-page asset stripping |

## The boot sequence (what happens on `composer test`)

Everything is wired up in `tests/bootstrap.php`, in this order:

1. **Composer autoloader** — loads StoreSuite's PSR-4 classes and dev
   dependencies.
2. **Locate wp-phpunit** — defaults to `vendor/wp-phpunit/wp-phpunit`; can be
   overridden with the `WP_PHPUNIT__DIR` env var.
3. **Point at the test config** — `tests/wp-tests-config.php` holds the DB
   credentials and ABSPATH for the throwaway install. Every setting can be
   overridden with `WP_TESTS_*` env vars (locally these live in the gitignored
   `phpunit.xml`).
4. **Queue the plugins** — a `muplugins_loaded` callback loads WooCommerce
   *first* (StoreSuite bails on `plugins_loaded` if the `WooCommerce` class is
   missing), then `storesuite.php`.
5. **Install WooCommerce** — WooCommerce normally creates its tables and roles
   on plugin *activation*, which never fires in a test run. A `setup_theme`
   callback calls `WC_Install::install()` manually and reloads the roles.
6. **Boot WordPress** — wp-phpunit's `includes/bootstrap.php` installs a fresh
   WP into the test database and fires the normal load sequence, so
   StoreSuite's `plugins_loaded` / `init` hooks all run for real.
7. **Load fixtures** — `tests/fixtures/FixtureModule.php` and the
   `StoreSuiteAjaxTestCase` base class are required so tests can use them.
8. **Neutralize update checks & block HTTP** — Ajax tests fire `admin_init`
   (mimicking admin-ajax.php), which would re-run WordPress's
   core/plugin/theme update checks on nearly every test (the caching
   transients roll back with each test's DB transaction — ~3s of
   api.wordpress.org traffic each). The bootstrap serves an "already checked"
   payload via the `pre_site_transient_update_*` filters and fails any other
   outbound HTTP fast via `pre_http_request`, keeping the suite deterministic
   and offline-safe.

## AJAX integration tests

`tests/Integration/*AjaxTest.php` extend `StoreSuiteAjaxTestCase`
(`tests/Integration/StoreSuiteAjaxTestCase.php`, loaded by the bootstrap),
which wraps wp-phpunit's `WP_Ajax_UnitTestCase`:

```php
$response = $this->dispatch(
    'storesuite_add_coupon',                       // wp_ajax_ action name
    $this->nonce_field( 'add_coupon' ) + array(    // storesuite_add_coupon_nonce
        'coupon_code' => 'SUMMER15',
    )
);
$this->assertTrue( $response['success'] );
```

`dispatch()` populates `$_POST`, fires the real `wp_ajax_*` hook the way
admin-ajax.php does (including `admin_init`), catches the `wp_die()` that
`wp_send_json_*()` ends with, and returns the decoded JSON. `nonce_field()`
builds the `storesuite_{action}_nonce` / `_storesuite_{action}_` pair the
plugin's forms use. Note that category *delete* uses its own nonce action stem
(`_storesuite_delete_nonce_`), so that test builds its nonce by hand.

## Test isolation — why tests don't leak into each other

Every test class extends `WP_UnitTestCase`, which gives two big guarantees:

- **Database rollback.** Each test runs inside a MySQL transaction that is
  rolled back afterwards. Any option, post, or user a test creates simply
  disappears.
- **Hook restoration.** All `add_filter()` / `add_action()` calls made during a
  test are undone afterwards. Tests can safely hook anything.

On top of that, `ManagerTest::set_up()` deletes the two options the module
Manager writes (`storesuite_active_modules`, `storesuite_flush_rewrite_rules`)
so every test starts from a blank slate even within the same transaction.

## The fixtures

### `FixtureModule` — a counting, configurable test double

`tests/fixtures/FixtureModule.php` extends the real `Abstracts\Module`, but its
slug and required plugins are passed to the constructor, and every lifecycle
method (`boot`, `activate`, `deactivate`, `uninstall`) just increments a public
counter:

```php
$module = new FixtureModule( 'plain' );
$manager->activate( 'plain' );
$this->assertSame( 1, $module->activate_calls ); // hook ran exactly once
```

Those counters are what make idempotency tests meaningful — e.g. "activating
twice must run the hook only once".

It also exposes public properties that feed the corresponding `Module`
accessors, so REST tests can shape a module without subclassing:

```php
$module->admin_tabs      = array( ... ); // returned by get_admin_tabs()
$module->has_settings    = true;         // returned by has_settings()
$module->settings_schema = array( ... ); // returned by get_settings_schema()
$module->settings_values = array( ... ); // returned by get_settings(); update_settings()
                                         // writes back here, dropping keys not in the schema
```

### Disk fixtures and the `include_once` rule ⚠️

`Manager::discover()` loads each `modules/<slug>/module.php` with
`include_once`, and PHP returns a file's `return` value **only the first time**
it is included — a second `include_once` of the same file returns `true`.

Consequence: **each disk fixture directory can be scanned by exactly one test
per PHPUnit process.** That's why there are two separate fixture trees:

| Directory                       | Returns module | Used by (one test each)                                    |
| ------------------------------- | -------------- | ---------------------------------------------------------- |
| `fixtures/modules/alpha/`       | `alpha`        | `test_discovery_finds_module_bootstraps_on_disk`           |
| `fixtures/modules-merge/beta/`  | `beta`         | `test_register_modules_filter_merges_with_disk_discovered_modules` |

If you write a new test that needs disk discovery, **create a new fixture
directory** — never reuse an existing one.

## The two injection points every test relies on

The Manager was built with two filters that make it testable without ever
touching the real `modules/` directory:

1. **`storesuite_modules_dir`** — where the Manager scans for modules. Most
   tests point it at a nonexistent directory so *nothing* is loaded from disk.
2. **`storesuite_register_modules`** — receives the slug ⇒ instance map after
   the disk scan; tests use it to inject `FixtureModule` instances.

The `make_manager()` helper in `ManagerTest` bundles both:

```php
$manager = $this->make_manager( array( new FixtureModule( 'plain' ) ) );
```

One more trick: `activate_fake_dependency()` marks a fake plugin as "active"
just by writing its basename into the `active_plugins` option. This works
because core `is_plugin_active()` only reads that option — it never checks
whether the file exists.

## What `ManagerTest` covers, group by group

### 1. Dependency gating
A module can declare required plugins via `get_requires()`.

- `activate()` refuses (returns `false`, persists nothing, runs no hook) when
  a required plugin is inactive.
- `get_missing_requirements()` lists only the inactive requirements, returns
  `[]` once they're active, and returns `[]` (not an error) for unknown slugs.
- Activation succeeds once the requirement is met.
- `boot_active()` silently *skips* (doesn't fatal on) an active module whose
  dependency was deactivated later — but keeps it listed as active.

### 2. Activate / deactivate lifecycle & idempotency
- Unknown slugs return `false` and persist nothing.
- `activate()` persists the slug, runs the module's `activate()` hook once,
  and fires `storesuite_module_activated` with `( $slug, $instance )`.
- Re-activating is a no-op that still returns `true` (no duplicate slug, no
  re-run hook). Same idea for deactivating an inactive module.
- `deactivate()` mirrors all of that, firing `storesuite_module_deactivated`
  with `( $slug, $instance )`.
- Both activate **and** deactivate set the `storesuite_flush_rewrite_rules`
  flag so rewrite rules refresh on the next request. (The deactivate test
  deletes the flag left over from activation first, so the assertion can only
  be satisfied by `deactivate()` itself.)

### 3. Boot ordering & lifecycle actions
- `boot_active()` boots only active modules and fires
  `storesuite_module_{slug}_loaded` per module, then
  `storesuite_modules_loaded` once at the end.

### 4. Registry / active-option hygiene
- `get_active_slugs()` hides slugs that no longer exist on disk, **but** the
  stored option keeps them — so a module that reappears comes back active.
- Values returned from the `storesuite_register_modules` filter are
  re-validated: strings, `null`, and modules with an empty slug are dropped.
- The first registration of a slug wins; a later duplicate never clobbers it.
- The filter *merges* with disk-discovered modules rather than replacing them.
- `uninstall_all()` runs `uninstall()` on every discovered module, active or
  not (it's meant for `uninstall.php`, where everything should clean up).

## Writing a new test — checklist

1. Create `tests/<Area>/SomethingTest.php` in namespace
   `PluginizeLab\StoreSuite\Tests\<Area>`, extending `WP_UnitTestCase`.
   The `*Test.php` suffix is what PHPUnit discovers.
2. Reset any options/state your code-under-test writes in `set_up()`
   (call `parent::set_up()` first).
3. Inject fixtures through filters instead of touching real modules or the
   real `modules/` directory.
4. Need disk discovery? Make a **new** fixture directory (see the
   `include_once` rule above).
5. Assert on *behavior you can observe*: return values, options, fired
   actions, and fixture call counters.
6. Run `composer test`, then `vendor/bin/phpcs tests/...` — test code follows
   the same WPCS standard as the plugin.

## JS unit suite (Jest)

```bash
npm run test:unit
```

Runs `wp-scripts test-unit-js` (Jest + jsdom) over `tests/js/` — no database,
no browser, no local site needed. It covers the JS that has real logic in it:

- **`js/context/`** — `SettingsContext` (fetch/save against
  `/storesuite/v1/settings`, loading/saving state, success & error notices,
  the `useSettings`-outside-provider guard).
- **`js/components/`** — admin settings components rendered with React
  Testing Library (`PaginationSettings` form round-trip, `SettingsHeader`
  logo/icon/actions variants, `ColorPreview` palette wiring).
- **`js/dashboard/`** — the frontend-dashboard React app's pure logic:
  admin.php → frontend URL remapping, the `storesuite_dashboard_analytics_reports_list`
  widget filter, `lazyWithRetry` chunk-recovery, `getAdminSetting` sources.
- **`js/analytics/`** — the analytics app's URL remapping (including the
  wc-admin `/customers` special case), capture-phase link interception, and
  the default-date-range fallback chain.

Configuration lives in `jest.config.js`. Things to know before adding tests:

- **`@woocommerce/*` packages are webpack externals** (`window.wc.*` at
  runtime) and are not installed via npm. Jest maps them to the stand-ins in
  `js/__mocks__/woocommerce/` — add a file there if you import a new one.
- **`window.location` cannot be stubbed in jsdom.** Change the URL with
  `window.history.replaceState( null, '', path )` instead; a test that must
  stub `location.reload` opts into the node environment via a
  `@jest-environment node` docblock and builds its own minimal `window`
  (see `js/dashboard/lazy-with-retry.test.js` and `setup-window-shim.js`).
- **`@wordpress/jest-console` fails tests on unexpected `console` calls.**
  For expected noise (e.g. `@wordpress/components` deprecation warnings),
  replace the console method with a plain function and restore it —
  `jest.spyOn` would hand back the preset's existing spy, which still
  records the calls.
- The vanilla jQuery files in `assets/frontend/` are intentionally not unit
  tested — they are server-rendered-page glue and are covered end-to-end by
  the Playwright suite.

## Browser E2E suite (Playwright)

```bash
npm run test:e2e
```

Unlike the PHPUnit suite (throwaway install, transactions rolled back), the
E2E suite drives a real Chromium browser against the live local Herd site at
`https://westore-headless.test` — StoreSuite must be installed there and the
site reachable. Everything else is self-bootstrapping:

- **`playwright.config.js`** — baseURL, self-signed-cert tolerance, one
  worker (the suite mutates one shared site), a `setup` project that runs
  before the specs.
- **`tests/e2e/global-setup.js`** — wp-cli: activates StoreSuite, flushes
  rewrites, creates the `e2e-admin` / `e2e-manager` / `e2e-customer` users,
  and runs `tests/e2e/seed.php` (idempotent: one stock-managed
  "E2E Seed Product" and one processing seed order).
- **`tests/e2e/auth.setup.js`** — logs each persona in through the
  WooCommerce My Account form (this site's redirect plugin 302s
  wp-login.php there) and saves storage states under `tests/e2e/.auth/`
  (gitignored).

What the specs cover:

| Spec | Flows |
| --- | --- |
| `access-control` | Logged-out redirect, customer denial, manager dashboard access, wp-admin blocking (toggles `storesuite_prevent_admin_access` around the test and restores it) |
| `dashboard` | Sidebar registry, submenu expand/navigate, active states, module nav entry |
| `products` | List + toolbar, search filtering, add simple product via the form |
| `products-edit` | Edit an existing product (persisted), delete from the list row action |
| `categories` | Add → search-filtered list → edit → delete (SweetAlert2 confirm), client-side required-field validation |
| `tags-brands` | Add + delete lifecycles for both taxonomies |
| `coupons` | Add → list → soft delete, edit (persisted), required-code validation |
| `orders` | Seeded order in the list, order-details screen, add + delete order note |
| `account` | Save + persist account details, email-as-display-name rejection |
| `analytics` | React app mounts for managers, customers redirected |
| `inventory` | Module app mount + REST fetch, stock-list search, log view, customer denial |
| `modules-admin` | Deactivate → re-activate the Inventory Manager toggle, verified against the persisted option (with a wp-cli re-activate safety net) |
| `admin-settings` | wp-admin React app mounts, Modules screen lists Inventory Manager, pagination setting save round-trip (restored afterwards) |

Hard-won stability notes, so nobody re-discovers them:

- **Names/slugs are timestamped** (`uniq()`), so re-runs never collide, and
  list assertions go through each screen's `?search_by=` filter because the
  lists are paginated.
- **Row-action dropdowns animate open** (jQuery slideToggle); coordinate
  clicks race the animation. Delete buttons are triggered with
  `dispatchEvent('click')` and edit links are followed via their `href`.
- **SweetAlert2 popups animate** with a scale transform, so even forced
  coordinate clicks can land on the backdrop and dismiss the dialog. Confirm
  through the library's test API instead — `Swal.clickConfirm()` via the
  `confirmSwal()` helper.
- **Fixture hygiene**: specs that create products/coupons delete them at the
  end (`wpEval()`), otherwise repeated runs push the seed data off page one
  of the newest-first lists.
- **Delete success handlers call `window.location.reload()`** after a row
  fade-out; `settleReload()` waits it out before the next `goto`, or the
  navigation aborts with `net::ERR_ABORTED`.
- **Hidden submenu links are outside the accessibility tree** — match them
  as plain elements, `getByRole` won't find them until expanded.

## Troubleshooting

- **DB connection errors:** the suite needs a local MySQL with a
  `storesuite_tests` database; credentials come from `tests/wp-tests-config.php`
  or `WP_TESTS_*` env vars in your gitignored `phpunit.xml`.
- **Weird failures after a WP upgrade:** keep `wp-phpunit/wp-phpunit` matched
  to the WP core version (`composer update wp-phpunit/wp-phpunit`).
- **A disk-discovery test suddenly gets no module:** two tests are scanning
  the same fixture directory — the second `include_once` returns `true`
  instead of the module. Give each test its own directory.
