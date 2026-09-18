# CLAUDE.md

Guidance for Claude Code working in this repository.

## Overview

StoreSuite is a WordPress/WooCommerce plugin: a frontend dashboard letting shop managers and owners manage products, orders, coupons, categories, tags, and brands without the wp-admin panel. Requires WooCommerce; declares HPOS compatibility.

## Commands

```bash
composer update && npm install   # install deps
npm start                        # JS/React watch (hot reload)
npm run build                    # JS/React production build (wp-scripts)
composer phpcs / phpcbf          # PHP lint / auto-fix (WPCS)
npm run lint:js / lint:css       # JS / CSS lint
npm run format                   # Prettier
bash bin/build.sh                # release ZIP
```

## Testing

```bash
composer test                    # PHPUnit — every *Test.php under tests/ (both suites below)
cd tests/pw && npm test          # Playwright e2e + REST API suites (see tests/pw/README.md for site setup)
```

- **PHPUnit, `tests/` suite** (bootstrap `tests/bootstrap.php`, namespace `PluginizeLab\StoreSuite\Tests\`): loads WooCommerce + StoreSuite into a throwaway WP install backed by a local MySQL database (`storesuite_tests`); connection/ABSPATH defaults live in `tests/wp-tests-config.php` and can be overridden via `WP_TESTS_*` env vars — locally set in the gitignored `phpunit.xml`. Keep `wp-phpunit/wp-phpunit` matched to the WP core version. Module tests inject fixture modules via the `storesuite_register_modules` filter and point `storesuite_modules_dir` away from real modules; note `Manager::discover()` uses `include_once`, so only one test per process may discover `tests/fixtures/modules/` from disk.
- **PHPUnit, `tests/php/` suite** (merged from develop; namespace `PluginizeLab\StoreSuite\Test\`, PSR-4 via composer `autoload-dev`): base classes `StoreSuiteTestCase` / `StoreSuiteAjaxTestCase` provide user fixtures, entity factories (`self::factory()->product->create()`), a `do_ajax()` dispatch helper and `capture_redirect()` for redirect-and-exit paths. Its own `tests/php/bootstrap.php` (env `WP_CORE_DIR`, `WC_DIR`, `WP_DB_*`) is what CI uses.
- **Playwright** (`tests/pw/`): self-contained npm project — browser e2e specs (co-located page objects per feature folder, storage-state auth for admin/shop manager/customer) plus HTTP-level REST contract specs in `tests/api` using application passwords. `bin/e2e-provision.sh` seeds any wp-cli-reachable site. An older Playwright suite also lives in `tests/e2e/`.
- **CI**: PHPCS + PHPUnit run on every pull request; PHPUnit also runs on pushes to `develop`. The e2e suite runs nightly in dual lanes (latest WP/WC gates; pinned versions advisory) plus `workflow_dispatch`.

## Architecture

**Bootstrap:** `storesuite.php` → `pluginizelab_storesuite()` boots the singleton `StoreSuite` (`includes/StoreSuite.php`), accessed globally via that function. Services live in a `$container` array exposed by `__get()` (e.g. `pluginizelab_storesuite()->scripts`). Order: `plugins_loaded` (WooCommerce dependency check → `includes()` + `init_hooks()` → construct `Module\Manager` → fire `storesuite_loaded`), `rest_api_init` (REST routes), `init` priority 4 (`init_classes()` builds services), `before_woocommerce_init` (HPOS).

**PHP (`includes/`):** namespace `PluginizeLab\StoreSuite`, PSR-4 from `includes/`. Everything prefixed `storesuite_`.
- **Domain dirs** (`Product/`, `Order/`, `Coupon/`, `ProductCategory/`, `ProductTag/`, `ProductBrand/`, `Account/`): Controller (registers `wp_ajax_storesuite_*`, handles forms, loads templates) + Manager (CRUD via WooCommerce APIs); some add a Hooks class for WP/WC integrations.
- **REST:** `REST/SettingsController.php` — namespace `storesuite/v1`, base `settings`; requires `manage_options` (admin-only).
- **Core services:** `Assets.php` (enqueues; strips theme/disallowed plugin assets on dashboard, filters `storesuite_allowed_plugin_slugs` / `storesuite_allowed_asset_handles`), `Rewrites.php` (dashboard rewrite endpoints; slugs via `storesuite_myshop_*_endpoint` options; resolves the WC My Account `orders` query-var conflict), `Main.php` (login redirects, admin blocking for shop_manager/customer, admin-bar hiding, CSS var injection), dashboard home KPI widgets (React app in `src/dashboard/`, widget registry filterable via the `storesuite_dashboard_analytics_reports_list` JS hook in `src/dashboard/get-reports.js`), `Cache.php` (transient/object-cache wrapper, `storesuite_` prefix / `storesuite` group), `DashboardMenu.php` (sidebar nav via `storesuite_dashboard_navigation`, permission-gated; final list filterable via `storesuite_dashboard_menus`).
- **Settings:** single serialized `storesuite_settings` option; read via `storesuite_get_option_by_key($key)`.
- **Globals (`includes/functions.php`):** `storesuite_get_template_part()`, `storesuite_is_endpoint_url()`, nav URLs, page checks, `storesuite_log()`, access-control redirects.

**Module system (`modules/` + `includes/Module/`):** self-contained, independently activatable features. Modules extend `Abstracts/Module.php` (abstract `get_slug`/`get_name`/`boot`; lifecycle `activate`/`deactivate`). `Module/Manager.php` (`$container['modules']`) lazily discovers `modules/*/module.php` (each must `return` a `Module` instance; filter `storesuite_register_modules` to add more), tracks active slugs in the `storesuite_active_modules` option, and on `storesuite_loaded` boots active modules (firing `storesuite_module_{slug}_loaded`, then `storesuite_modules_loaded`). `docs/sample-module/staff-manager/` is the reference example (kept outside `modules/` so it is not a live, discoverable module; copy it into `modules/` to activate). Layout: `module.php` bootstrap (plugin-style header comment) returns `new ...\Module(__FILE__)`; concrete class under `includes/` in namespace `PluginizeLab\StoreSuite\Modules\<Name>`. **Adding a new module: follow `docs/how-to-add-module.md` — covers DB table, settings, sidebar menu, top-level admin tab, module-owned REST controller, and React screen with full templates.**

**Templates (`templates/`):** WooCommerce-style overrides — theme `my-storesuite/` first, then plugin. Loaded via `storesuite_get_template_part()`; path filter `storesuite_set_template_path`.

**Routing:** dashboard page (activation shortcode `[storesuite_dashboard]`) uses WP rewrite endpoints per sub-page (products, orders, categories, tags, brands, coupons, edit-account-details); pagination via custom rules (`storesuite-dashboard/products/page/2`).

## Two frontend stacks

1. **Admin settings (React):** `src/admin.js` → `assets/build/admin/script.js`. `@wordpress/components` + `api-fetch`, `@heroicons/react`, React Router (hash). Routes: `/` GeneralSettings, `/dashboard-settings`, `/appearance-settings` (palettes/color pickers), `/pagination-settings`. Webpack extends `@wordpress/scripts`. Header `SettingsHeader` (title/subtitle + `actions` slot); nav tabs in `Layout.js`. **Add heroicons to `src/Components/icons.js` (24/outline) before importing elsewhere.**
2. **Frontend dashboard (jQuery + vanilla JS):** `assets/frontend/` — `script.js`, `form-handler.js` (SweetAlert2 CRUD), `order.js` (selectWoo), `product.js`. Server-rendered PHP templates; data via `wp_localize_script()` under `storeSuiteFormHandler`, `StoreSuite_Order`, `StoreSuite_Product`. Note: these files are served raw — not webpack-built or `lint:js`-clean; match the existing var/IIFE style.

## Key constants

`STORESUITE_FILE` / `STORESUITE_PLUGIN_FILE` (main file), `STORESUITE_DIR` / `STORESUITE_INC_DIR` / `STORESUITE_TEMPLATE_DIR`, `STORESUITE_PLUGIN_URL` / `STORESUITE_PLUGIN_ASSET`, `STORESUITE_PLUGIN_VERSION`, `STORESUITE_LOAD_STYLE` / `STORESUITE_LOAD_SCRIPTS` (set `false` to disable default asset loading).

## Standards

WordPress Coding Standards (PHPCS, `phpcs.xml`), PHP 7.4+, text domain `storesuite`, everything prefixed `storesuite_`. `wc_clean` is a registered sanitizer; Yoda off; strict comparisons enforced; PSR-4 class filenames allowed.

## CI/CD

`.github/workflows/deploy.yml` on git tag: installs `--no-dev`, builds, ZIPs (rsync + `.distignore`), uploads a GitHub release, deploys to WordPress.org via SVN.
