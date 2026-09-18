# How to add a new StoreSuite module

This document is a build-by-numbers guide for an AI agent (or human developer)
to add a new opt-in module to StoreSuite, mirroring every feature shipped by
the `staff-manager` sample.

Treat `docs/sample-module/staff-manager/` as the canonical reference. It is a
worked example kept for reference (not a live, discoverable module — it lives
outside `modules/`). Read it before starting — every pattern in this doc is
implemented there. To turn it into a live module, copy it back to
`modules/staff-manager/`.

---

## 1. Mental model

A module is a self-contained feature shipped under `modules/<slug>/`. It is
discovered, activated, and deactivated independently from the rest of the
plugin. The pieces a fully-featured module can use:

| Concern | Where it lives | Required? |
|---|---|---|
| Disk bootstrap | `modules/<slug>/module.php` | ✅ |
| Concrete `Module` class | `modules/<slug>/includes/Module.php` | ✅ |
| Custom DB table | `modules/<slug>/includes/Installer.php` | optional |
| Configurable settings (Modules → Configure) | `modules/<slug>/includes/Settings.php` | optional |
| Rewrite endpoint (`/storesuite-dashboard/<endpoint>/`) | `boot()` + `register_endpoint()` | optional |
| Dashboard sidebar menu item | `storesuite_dashboard_menus` filter | optional |
| Top-level admin tab | `get_admin_tabs()` + React route | optional |
| Module-owned REST routes | dedicated controller registered on `rest_api_init` | optional |
| Module-owned React screen | `modules/<slug>/src/index.js` + `modules/<slug>/src/admin/<Name>Admin.js` | optional |

Skip any piece the module doesn't need. None of them — except the bootstrap
and the `Module` class — are mandatory.

---

## 2. Naming conventions

For a module called **Customer Manager**:

- Slug (kebab-case, directory name, REST identifier): `customer-manager`
- PHP namespace segment (PascalCase): `CustomerManager`
- Full namespace: `PluginizeLab\StoreSuite\Modules\CustomerManager`
- Option keys: `storesuite_customer_manager_*` (e.g. `_settings`, `_db_version`, `_screen`)
- DB table: `{$wpdb->prefix}storesuite_customers` (plural noun matching the domain)
- React screen component: `CustomerManagerAdmin.js`
- React route: `/customer-manager`

Apply the same shape consistently. Throughout this doc, replace
`customer-manager` / `CustomerManager` with your module's names.

---

## 3. Files to create

### 3.1 Bootstrap — `modules/customer-manager/module.php`

Single source of truth for the module's WP-style header (the Modules screen
reads `Module Name` / `Description` / `Version` indirectly via the class).
Must `return` an instance of the module class with `__FILE__` passed in.

```php
<?php
/**
 * Module Name: Customer Manager
 * Description: <One-sentence summary shown on the Modules screen.>
 * Version: 1.0.0
 * Author: StoreSuite
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/includes/Installer.php';        // optional
require_once __DIR__ . '/includes/Settings.php';         // optional
require_once __DIR__ . '/includes/ScreenController.php'; // optional
require_once __DIR__ . '/includes/Module.php';

return new \PluginizeLab\StoreSuite\Modules\CustomerManager\Module( __FILE__ );
```

Only `require_once` the files the module actually uses.

### 3.2 Concrete Module class — `modules/customer-manager/includes/Module.php`

Extend `PluginizeLab\StoreSuite\Abstracts\Module`. Implement `get_slug()`,
`get_name()`, and `boot()`. Override anything else from the abstract that you
need.

```php
<?php
namespace PluginizeLab\StoreSuite\Modules\CustomerManager;

use PluginizeLab\StoreSuite\Abstracts\Module as BaseModule;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Module extends BaseModule {

    const ENDPOINT = 'customers';

    public function get_slug()        { return 'customer-manager'; }
    public function get_name()        { return __( 'Customer Manager', 'storesuite' ); }
    public function get_description() { return __( '<short paragraph>', 'storesuite' ); }
    public function get_version()     { return '1.0.0'; }

    /** One-shot on activation. The Manager flags a rewrite flush after this returns. */
    public function activate() {
        Installer::install();
    }

    /** Runs on storesuite_loaded when the module is active. */
    public function boot() {
        Installer::maybe_upgrade();

        add_action( 'init', array( $this, 'register_endpoint' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_filter( 'storesuite_query_var_filter', array( $this, 'register_query_var' ) );
        add_filter( 'storesuite_dashboard_menus', array( $this, 'register_menu' ), 20 );
        add_action( 'storesuite_load_custom_template', array( $this, 'load_template' ) );
    }

    /**
     * Permanent teardown — runs only from the plugin's root uninstall.php.
     * Drop tables and delete options here, NEVER in deactivate().
     */
    public function uninstall() {
        Installer::uninstall();
        delete_option( Settings::OPTION_KEY );
        delete_option( ScreenController::OPTION_KEY );
    }

    public function register_endpoint() {
        add_rewrite_endpoint( self::ENDPOINT, EP_PAGES );
    }

    /**
     * Render the front-end page for `/storesuite-dashboard/<endpoint>/`.
     * The core dashboard shortcode fires `storesuite_load_custom_template`
     * for any request that doesn't match a built-in query var. Claim it when
     * your endpoint var is present, or the sidebar link renders a blank page.
     */
    public function load_template( $query_vars ) {
        if ( ! is_array( $query_vars ) || ! isset( $query_vars[ self::ENDPOINT ] ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            storesuite_get_template_part( 'global/no-permission' );
            return;
        }
        $template = $this->get_path() . '/templates/customers.php';
        if ( file_exists( $template ) ) {
            include $template;
        }
    }

    public function register_query_var( $vars ) {
        if ( is_array( $vars ) ) {
            $vars[ self::ENDPOINT ] = self::ENDPOINT;
        }
        return $vars;
    }

    public function register_rest_routes() {
        ( new ScreenController() )->register_routes();
    }

    public function register_menu( $menus ) {
        if ( ! is_array( $menus ) ) {
            return $menus;
        }
        $menus['customers'] = array(
            'title'      => __( 'Customers', 'storesuite' ),
            // Resolve against the configured dashboard page permalink — do NOT
            // hard-code `/storesuite-dashboard/`, which breaks if the page is
            // renamed or uses a different permalink structure.
            'url'        => storesuite_get_navigation_url( self::ENDPOINT ),
            'permission' => 'manage_woocommerce',
            'icon'       => '<svg ...>...</svg>', // inline SVG, no JS wrapper
        );
        return $menus;
    }

    // --- Settings hooks (delete if no settings) ---
    public function has_settings()         { return true; }
    public function get_settings_schema()  { return Settings::get_schema(); }
    public function get_settings()         { return Settings::get(); }
    public function update_settings( array $data ) { return Settings::update( $data ); }

    // --- Top-level tab hook (delete if no top-level tab) ---
    public function get_admin_tabs() {
        return array(
            array( 'to' => '/customer-manager', 'label' => __( 'Customers', 'storesuite' ) ),
        );
    }
}
```

### 3.3 Installer (custom DB table) — `modules/customer-manager/includes/Installer.php`

Skip if the module doesn't need a table. Schema-version pattern lets future
plugin updates ship schema changes without forcing a re-toggle.

```php
<?php
namespace PluginizeLab\StoreSuite\Modules\CustomerManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Installer {

    const SCHEMA_VERSION        = '1.0.0';
    const SCHEMA_VERSION_OPTION = 'storesuite_customer_manager_db_version';

    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'storesuite_customers';
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table_name      = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        // dbDelta is fussy: two spaces after PRIMARY KEY, lowercase types,
        // no trailing comma after the last column or index.
        $sql = "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            UNIQUE KEY user_id (user_id),
            KEY status (status)
        ) {$charset_collate};";

        dbDelta( $sql );

        update_option( self::SCHEMA_VERSION_OPTION, self::SCHEMA_VERSION );
    }

    public static function maybe_upgrade() {
        $installed = get_option( self::SCHEMA_VERSION_OPTION );
        if ( version_compare( (string) $installed, self::SCHEMA_VERSION, '<' ) ) {
            self::install();
        }
    }

    public static function uninstall() {
        global $wpdb;
        $table_name = self::table_name();
        $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore
        delete_option( self::SCHEMA_VERSION_OPTION );
    }
}
```

**Don't drop the table on `deactivate()`** — preserve user data. Drops belong
in the module's `uninstall()` method (shown in the `Module` class above),
which the plugin's root `uninstall.php` invokes via
`Module\Manager::uninstall_all()` when StoreSuite is deleted.

### 3.4 Settings (Modules → Configure form) — `modules/customer-manager/includes/Settings.php`

Schema-driven. The React Modules screen renders fields generically from the
schema, so just defining the schema gets a working UI. Supported types:
`toggle`, `text`, `number`, `select`.

```php
<?php
namespace PluginizeLab\StoreSuite\Modules\CustomerManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Settings {

    const OPTION_KEY = 'storesuite_customer_manager_settings';

    public static function get_schema() {
        return array(
            'enable_loyalty' => array(
                'type'        => 'toggle',
                'label'       => __( 'Enable loyalty tracking', 'storesuite' ),
                'description' => __( '...', 'storesuite' ),
                'default'     => false,
            ),
            'default_segment' => array(
                'type'        => 'select',
                'label'       => __( 'Default segment', 'storesuite' ),
                'default'     => 'standard',
                'options'     => array(
                    'standard' => __( 'Standard', 'storesuite' ),
                    'vip'      => __( 'VIP', 'storesuite' ),
                ),
            ),
            'min_orders_for_vip' => array(
                'type'    => 'number',
                'label'   => __( 'Min orders for VIP', 'storesuite' ),
                'default' => 5,
                'min'     => 0,
                'max'     => 1000,
            ),
            'welcome_email_subject' => array(
                'type'    => 'text',
                'label'   => __( 'Welcome email subject', 'storesuite' ),
                'default' => '',
            ),
        );
    }

    public static function get_defaults() {
        $defaults = array();
        foreach ( self::get_schema() as $key => $field ) {
            $defaults[ $key ] = $field['default'] ?? '';
        }
        return $defaults;
    }

    public static function get() {
        $stored = get_option( self::OPTION_KEY, array() );
        if ( ! is_array( $stored ) ) {
            $stored = array();
        }
        return array_merge( self::get_defaults(), $stored );
    }

    public static function update( array $data ) {
        $schema = self::get_schema();
        $clean  = self::get();
        foreach ( $schema as $key => $field ) {
            if ( ! array_key_exists( $key, $data ) ) {
                continue;
            }
            $clean[ $key ] = self::sanitize_field( $data[ $key ], $field );
        }
        update_option( self::OPTION_KEY, $clean );
        return $clean;
    }

    private static function sanitize_field( $value, array $field ) {
        switch ( $field['type'] ?? 'text' ) {
            case 'toggle':
                return (bool) $value;
            case 'number':
                $value = (int) $value;
                if ( isset( $field['min'] ) ) { $value = max( (int) $field['min'], $value ); }
                if ( isset( $field['max'] ) ) { $value = min( (int) $field['max'], $value ); }
                return $value;
            case 'select':
                $options = (array) ( $field['options'] ?? array() );
                $value   = (string) $value;
                return array_key_exists( $value, $options ) ? $value : ( $field['default'] ?? '' );
            case 'text':
            default:
                return sanitize_text_field( (string) $value );
        }
    }
}
```

### 3.5 Module-owned REST controller — `modules/customer-manager/includes/ScreenController.php`

Skip if the module doesn't need its own endpoints (the core
`ModulesController` already exposes activation + Configure settings). Use this
when your top-level admin screen has its own fields, or when external code
needs to read/write module-specific data.

```php
<?php
namespace PluginizeLab\StoreSuite\Modules\CustomerManager;

use WP_REST_Controller;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ScreenController extends WP_REST_Controller {

    const OPTION_KEY = 'storesuite_customer_manager_screen';

    public function __construct() {
        $this->namespace = 'storesuite/v1';
        $this->rest_base = 'customer-manager/screen';
    }

    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_screen' ),
                    'permission_callback' => array( $this, 'permissions_check' ),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'update_screen' ),
                    'permission_callback' => array( $this, 'permissions_check' ),
                ),
            )
        );
    }

    public static function get_defaults() {
        return array(
            'landing_heading' => __( 'Welcome', 'storesuite' ),
            // …other fields…
        );
    }

    public static function get() {
        $stored = get_option( self::OPTION_KEY, array() );
        if ( ! is_array( $stored ) ) {
            $stored = array();
        }
        return array_merge( self::get_defaults(), $stored );
    }

    public function get_screen( $request ) {
        return rest_ensure_response( self::get() );
    }

    public function update_screen( $request ) {
        $current = self::get();
        $clean   = $current;

        // Sanitize each known field. Unknown params are ignored.
        if ( $request->has_param( 'landing_heading' ) ) {
            $clean['landing_heading'] = sanitize_text_field(
                (string) $request->get_param( 'landing_heading' )
            );
        }
        // …repeat per field…

        update_option( self::OPTION_KEY, $clean );
        return rest_ensure_response( $clean );
    }

    public function permissions_check( $request ) {
        return current_user_can( 'manage_options' );
    }
}
```

### 3.6 React screen (module-isolated)

Module React code lives **inside the module**, not in core `src/`. Webpack
discovers every `modules/<slug>/src/index.js` at build time and emits a
separate bundle to `modules/<slug>/assets/build/script.js` — the build
artifact lives in the module's own directory so the module is portable as a
standalone unit (Dokan Pro convention). The abstract
`Module::enqueue_admin_assets()` enqueues it when the module is active and
the StoreSuite settings page is the current screen.

**Layout on disk:**

```
modules/customer-manager/
├── module.php
├── includes/                   # PHP
├── assets/build/               # webpack output — gitignored
│   ├── script.js
│   └── script.asset.php
└── src/
    ├── index.js                # entry — contributes routes via wp.hooks
    └── admin/
        └── CustomerManagerAdmin.js
```

**`modules/customer-manager/src/index.js`** — register routes via the
`storesuite_admin_routes` `@wordpress/hooks` filter:

```js
import { addFilter } from '@wordpress/hooks';
import CustomerManagerAdmin from './admin/CustomerManagerAdmin';

addFilter(
    'storesuite_admin_routes',
    'storesuite/customer-manager',
    ( routes ) => {
        if ( ! Array.isArray( routes ) ) {
            return routes;
        }
        routes.push( {
            path: '/customer-manager',
            element: CustomerManagerAdmin,
        } );
        return routes;
    }
);
```

**`modules/customer-manager/src/admin/CustomerManagerAdmin.js`** —
standard React component. See `docs/sample-module/staff-manager/src/admin/StaffManagerAdmin.js`
for a complete reference (fetch on mount, form, snackbar on save).

**Imports the module bundle may use freely** (all externalized to
`window.wp.*` or `window.React*` by `@wordpress/scripts`, so they don't
inflate the bundle):

- `@wordpress/element`, `@wordpress/i18n`, `@wordpress/components`,
  `@wordpress/api-fetch`, `@wordpress/data`, `@wordpress/notices`,
  `@wordpress/hooks`, `@wordpress/dom-ready`
- React, ReactDOM
- `@storesuite/components` (alias to `src/Components`) — for shared
  StoreSuite primitives. Today these are bundled; later they can be flipped
  to externals without touching module source.

**Imports to avoid** (they bloat the bundle):

- `@heroicons/react/24/outline` — fine in small doses (~1 KB per icon,
  tree-shaken) but each icon ships in every module that uses it.
- `react-router-dom` — not externalized; use plain `<a href="#/path">` for
  intra-app navigation in module screens.

### 3.7 React route registration

**Not needed in core.** Module routes register themselves at runtime via the
`storesuite_admin_routes` filter. Core `src/admin.js` calls
`applyFilters( 'storesuite_admin_routes', [] )` at mount time and renders a
`<Route>` for each entry. Adding a new module never requires editing
`src/admin.js` or `webpack.config.js`.

---

## 4. What you do NOT need to touch

- `includes/StoreSuite.php` — no central registration.
- `includes/Module/Manager.php` — discovery is automatic via `glob()`.
- `includes/REST/ModulesController.php` — the generic endpoints already cover
  activate/deactivate and the schema-driven Configure form.
- `includes/Abstracts/Module.php` — only edit if you're proposing a new
  cross-cutting extension point.
- `includes/Assets.php` — active modules' admin bundles are enqueued
  automatically via `Module::enqueue_admin_assets()`.
- `src/admin.js` — module routes register themselves at runtime via the
  `storesuite_admin_routes` `@wordpress/hooks` filter.
- `webpack.config.js` — `modules/*/src/index.js` is auto-discovered as a
  webpack entry.

If you find yourself editing any of those for a single module, stop and
reconsider — there is almost certainly a hook you can use instead.

---

## 5. Build, verify, commit

After the PHP files are in place:

```bash
npm install        # only on a fresh checkout
npm run build      # bundles the React app — required if you touched src/
```

Quick sanity checks an agent should run:

```bash
php -l modules/<slug>/module.php
php -l modules/<slug>/includes/Module.php
# …repeat for each PHP file you created…
```

Smoke test:

1. Activate the module from the Modules tab.
2. Confirm:
   - A row appears in `storesuite_active_modules`.
   - The DB table exists (if you defined one) — `SHOW TABLES LIKE 'wp_storesuite_<plural>'`.
   - The dashboard sidebar shows the new menu item.
   - The top-nav tab appears (if `get_admin_tabs()` returns one).
   - `Modules → Configure` renders your settings schema (if any).
   - Visiting `/storesuite-dashboard/<endpoint>/` renders your `load_template()`
     output (not a 404 or blank page) — flush rewrites if needed.
3. Deactivate. Confirm the menu/tab vanish, the option no longer lists the
   slug, and the DB table is preserved.
4. Delete the plugin (or run its uninstall). Confirm your `uninstall()` ran:
   the DB table is dropped and the module's options are gone.

---

## 6. Extension points cheat sheet

PHP actions and filters the module commonly hooks:

| Hook | Type | When |
|---|---|---|
| `storesuite_loaded` | action | All services constructed; safe to register hooks. Already used by Manager to call `boot()`. |
| `storesuite_dashboard_menus` | filter | Add a sidebar item. |
| `storesuite_query_var_filter` | filter | Expose a new endpoint to `Rewrites`. |
| `init` | action | Add rewrite endpoint via `add_rewrite_endpoint()`. |
| `storesuite_load_custom_template` | action | Render the front-end page for your endpoint (fires for unmatched query vars). |
| `rest_api_init` | action | Register module-owned REST controllers. |
| `storesuite_module_<slug>_loaded` | action | Other modules can react to yours booting. |
| `storesuite_module_activated` / `_deactivated` | action | Audit logs, cache busts. |
| `storesuite_modules_dir` | filter | Override modules root path. |
| `storesuite_register_modules` | filter | Inject extra modules from an add-on plugin. |

Module abstract methods to override (all optional unless noted):

| Method | Default | Purpose |
|---|---|---|
| `get_slug()` | — | **Required.** Kebab-case identifier. |
| `get_name()` | — | **Required.** Human label. |
| `boot()` | — | **Required.** Register hooks. |
| `get_description()` | `''` | Modules screen card body. |
| `get_version()` | `'1.0.0'` | Modules screen version badge. |
| `get_requires()` | `[]` | Plugin basenames the module depends on. Enforced by the Manager: activation is refused and boot is skipped while any are inactive. |
| `activate()` | no-op | One-shot setup (DB table, seed options). |
| `deactivate()` | no-op | One-shot teardown (NEVER drop data). |
| `uninstall()` | no-op | Permanent teardown (drop tables, delete options). Runs from root `uninstall.php`. |
| `has_settings()` | `false` | Show Configure link on the card. |
| `get_settings_schema()` | `[]` | Drives the Configure form. |
| `get_settings()` | `[]` | Current values with defaults merged. |
| `update_settings( array )` | `get_settings()` | Sanitize + persist. |
| `get_admin_tabs()` | `[]` | Inject top-level admin tabs. |

REST endpoints already provided by core (no work needed):

| Verb | Path | Purpose |
|---|---|---|
| `GET` | `/storesuite/v1/modules` | List every discovered module. |
| `POST` | `/storesuite/v1/modules/{slug}/activate` | Toggle on. |
| `POST` | `/storesuite/v1/modules/{slug}/deactivate` | Toggle off. |
| `GET` | `/storesuite/v1/modules/{slug}/settings` | Schema + values for Configure form. |
| `POST` | `/storesuite/v1/modules/{slug}/settings` | Persist Configure form. |

---

## 7. Common gotchas

- **`__FILE__` in `module.php` is mandatory.** The base class needs it to
  resolve `get_path()` / `get_url()`. Don't pass `__DIR__` or hardcode paths.
- **PSR-4 does not autoload `modules/`.** Use explicit `require_once` in
  `module.php`. Do not add the modules directory to `composer.json`.
- **`get_slug()` must match the folder name.** Slug `customer-manager` requires
  directory `modules/customer-manager/`.
- **dbDelta is strict about formatting.** Two spaces after `PRIMARY KEY`,
  lowercase types, no trailing commas, exactly one space between column name
  and type. Don't reformat the SQL.
- **Don't call `flush_rewrite_rules()` in `activate()`.** The Manager already
  flags the existing `storesuite_flush_rewrite_rules` option on every
  activation/deactivation; `StoreSuite::maybe_flush_rewrite_rules()` handles
  the flush on the next `init`.
- **Activation is not the boot path.** `activate()` runs once when the admin
  toggles the switch. `boot()` runs on every request when the module is
  active. Don't register hooks in `activate()` and don't run schema work in
  `boot()` (use `maybe_upgrade()` instead).
- **Module-owned REST controllers must register on `rest_api_init`,** not at
  construction time — otherwise the controller class isn't loaded yet on
  non-REST requests.
- **Don't add a React route inside a conditional.** Routes in `admin.js` are
  always declared; the tab visibility is what's conditional. The route exists
  even when inactive so direct navigation to a stale URL doesn't blow up.
- **Settings versus Screen settings.** Two different surfaces:
  - **Modules → Configure** uses the schema-driven `Settings` flow and stores
    in `storesuite_<slug>_settings`. Generic UI, no custom React.
  - **Top-level tab** uses your own `ScreenController` and stores in
    `storesuite_<slug>_screen`. Custom React. Use this when the module wants
    a richer, opinionated UI.
  Pick one per concern; don't duplicate the same field in both.

---

## 8. Done-when checklist

- [ ] `modules/<slug>/module.php` returns a `Module` instance with `__FILE__`.
- [ ] `Module` class implements `get_slug()`, `get_name()`, `boot()`.
- [ ] (Optional) `Installer::install()` runs on `activate()` and
      `maybe_upgrade()` runs at the top of `boot()`.
- [ ] (Optional) `Settings::get_schema()` returns at least one field; module
      overrides `has_settings()` → true and the four settings hooks.
- [ ] (Optional) `get_admin_tabs()` returns `[ { to, label } ]` and a matching
      `<Route>` exists in `src/admin.js` with a real React component.
- [ ] (Optional) Module-owned REST controllers are registered on
      `rest_api_init` inside `boot()`.
- [ ] `php -l` is clean on every PHP file touched.
- [ ] `npm run build` succeeds.
- [ ] Smoke test in §5 passes.
- [ ] No edits to `includes/StoreSuite.php`, `includes/Module/Manager.php`,
      `includes/REST/ModulesController.php`, or `includes/Abstracts/Module.php`
      unless you're proposing a new cross-cutting extension point.
