# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

StoreSuite is a WordPress/WooCommerce plugin that provides a frontend store management dashboard. Shop managers and store owners can manage products, orders, coupons, categories, tags, and brands without accessing the WordPress admin panel. Requires WooCommerce as a dependency. Declares WooCommerce HPOS (High-Performance Order Storage) compatibility.

## Development Commands

```bash
# Install dependencies
composer update
npm install

# JavaScript/React development
npm start              # Watch mode (hot reload)
npm run build          # Production build (wp-scripts)

# PHP code quality
composer phpcs         # Run PHP CodeSniffer
composer phpcbf        # Auto-fix PHP code
composer phpcs:report  # Generate PHPCS report file

# JS/CSS linting
npm run lint:js        # Lint JavaScript
npm run lint:css       # Lint CSS/SCSS
npm run format         # Auto-format with Prettier

# Release build (creates distributable ZIP)
bash bin/build.sh
```

## Testing

```bash
composer test                    # PHPUnit (needs WP_CORE_DIR, WC_DIR and WP_DB_* env — see tests/php/bootstrap.php)
cd tests/pw && npm test          # Playwright e2e + REST API suites (see tests/pw/README.md for site setup)
```

- **PHPUnit** (`tests/php/`): integration tests against a real WP + WooCommerce database. Base classes `StoreSuiteTestCase` / `StoreSuiteAjaxTestCase` provide user fixtures, entity factories (`self::factory()->product->create()`), a `do_ajax()` dispatch helper and `capture_redirect()` for redirect-and-exit paths. Test classes are PSR-4 (`PluginizeLab\StoreSuite\Test\`), discovered by `suffix="Test.php"`.
- **Playwright** (`tests/pw/`): self-contained npm project — browser e2e specs (co-located page objects per feature folder, storage-state auth for admin/shop manager/customer) plus HTTP-level REST contract specs in `tests/api` using application passwords. `bin/e2e-provision.sh` seeds any wp-cli-reachable site.
- **CI**: PHPCS + PHPUnit run on every pull request; PHPUnit also runs on pushes to `develop` and `feat/dark-light-mode`. The e2e suite runs nightly in dual lanes (latest WP/WC gates; pinned versions advisory) plus `workflow_dispatch`.

## Architecture

### Plugin Bootstrap

`storesuite.php` is the entry point. It loads Composer autoload, then calls `pluginizelab_storesuite()` which initializes the singleton `StoreSuite` class. Initialization order:

1. `plugins_loaded` — dependency check (WooCommerce must be active), then `includes()` + `init_hooks()`
2. `rest_api_init` — registers REST routes via `SettingsController->register_routes()`
3. `init` (priority 4) — `init_classes()` creates all service instances in `$container`
4. `before_woocommerce_init` — declares HPOS compatibility

### PHP Structure (`includes/`)

- **Namespace:** `PluginizeLab\StoreSuite` with PSR-4 autoloading from `includes/`
- **Main orchestrator:** `StoreSuite.php` — singleton accessed globally via `pluginizelab_storesuite()`. Uses a `$container` array with `__get()` magic method for service access (e.g., `pluginizelab_storesuite()->scripts`)
- **Domain modules:** `Product/`, `Order/`, `Coupon/`, `ProductCategory/`, `ProductTag/`, `ProductBrand/`, `Account/` — each follows a Controller + Manager pattern:
  - **Controllers** register `wp_ajax_storesuite_*` hooks, handle form submission, load templates via `storesuite_get_template_part()`
  - **Managers** contain business logic (CRUD operations using WooCommerce APIs)
  - Some modules also have a Hooks class (e.g., `ProductHooks`, `OrderHooks`) for WordPress/WooCommerce action integrations
- **REST API:** `REST/SettingsController.php` — namespace `storesuite/v1`, base `settings`, handles admin settings CRUD. Permission checks require the `manage_options` capability (admin-only; shop managers are denied)
- **Core services:**
  - `Assets.php` — registers and enqueues scripts/styles; strips theme and disallowed plugin assets on dashboard pages (extensible via `storesuite_allowed_plugin_slugs` and `storesuite_allowed_asset_handles` filters)
  - `Rewrites.php` — registers custom rewrite endpoints for each dashboard sub-page; endpoint slugs are configurable via `get_option('storesuite_myshop_*_endpoint')` with sensible defaults
  - `Main.php` — login redirects, admin access blocking for shop_manager/customer roles, admin bar hiding, CSS variable injection
  - `Dashboard.php` — renders KPI widgets (store performance, top products, top categories, top customers, top coupons) via `storesuite_dashboard_home_widgets` and `storesuite_dashboard_item_solds_widgets` action hooks
  - `Cache.php` — static wrapper around transients/object cache with `storesuite_` prefix and `storesuite` group
  - `DashboardMenu.php` — builds the sidebar navigation via `storesuite_dashboard_navigation` hook; menu items are permission-gated
- **ACF integration:** `Integration/Acf/` — `AcfIntegration` (container key `storesuite_acf_integration`; constructor bails unless ACF is active and the `storesuite_acf_product_fields` setting is not `no`; makes no ACF API calls before render/save because ACF boots on `init` 5, after StoreSuite's `init` 4), `FieldRenderer` (one theme-overridable template per field type under `templates/products/acf/`, base wrapper `field-wrapper.php`, group card `group.php`), `FieldSanitizer` (per-type branches; `null` means "do not write") and `DateFormats` (form ↔ ACF storage formats). Inputs are named `storesuite_acf[<field_key>]`; only fields resolved by ACF's location rules for the product are ever saved. Frontend behaviour (range/colour sync, datepicker, generic `[data-storesuite-media-picker]` media picker, conditional logic from `data-conditions`) lives in `assets/frontend/product-acf.js`. Extension points on the product form:
  - `do_action( 'storesuite_product_form_after_others', int $product_id, bool $is_edit_mode )` — fires in the main column after the "Others" card; `$product_id` is `0` on the add form.
  - `apply_filters( 'storesuite_product_pre_save_validation', WP_Error|null $error, array $data, string $context )` — runs after nonce/capability checks and sanitisation, before anything is written; return a `WP_Error` (one message per failing field) to abort with the messages listed in the form's error dialog. `$context` is `'add'` or `'edit'`.
  - `apply_filters( 'storesuite_sanitize_acf_fields', array $sanitized, array $raw, int $product_id )` — how the controller hands posted `storesuite_acf` values to the integration; nothing is accepted unless a callback returns it.
- **Settings storage:** All plugin settings stored in a single `storesuite_settings` option (serialized array), accessed via `storesuite_get_option_by_key($key)` in `includes/functions.php`
- **Abstract base:** `Abstracts/MyStoreSuiteShortcode.php` for shortcode-based pages
- **Global functions:** `includes/functions.php` — template loading (`storesuite_get_template_part()`), endpoint detection (`storesuite_is_endpoint_url()`), navigation URLs, page checks, logging via `storesuite_log()`, access control redirects

### Two Separate Frontend Stacks

1. **Admin Settings Page (React):** Entry point `src/admin.js` builds to `assets/build/admin/script.js`. Uses `@wordpress/components`, `@wordpress/api-fetch`, `@heroicons/react` for icons, React Router DOM (hash routing). Routes:
   - `/` → `GeneralSettings` — dashboard page selector, sidebar branding, admin access toggle
   - `/dashboard-settings` → `DashboardSettings` — performance box and widget visibility toggles
   - `/appearance-settings` → `ColorsSettings` — predefined palettes and custom color pickers
   - `/pagination-settings` → `PaginationSettings` — items-per-page controls

   Styled with `@wordpress/components` built-in styles and plain CSS (`LayoutStyles.css`). Webpack config in `webpack.config.js` extends `@wordpress/scripts`.

   **Header:** `SettingsHeader` renders the top bar with title, subtitle, and an `actions` slot. The header currently shows a **Documentation** (secondary) and **Support Me** (primary, links to `https://buymeacoffee.com/aiarnob`) button.

   **Nav tabs:** Defined in `Layout.js` as hash-router `<Link>` elements with heroicons — `GearIcon` (General), `Squares2X2Icon` (Dashboard), `PaletteIcon` (Appearance), `CodeBracketSquareIcon` (Pagination).

   **Icons:** All heroicons are re-exported from `src/Components/icons.js` (24/outline). Add new icons there before importing elsewhere.

2. **Frontend Dashboard (jQuery + vanilla JS):** Located in `assets/frontend/`. Scripts include `script.js` (main), `form-handler.js` (CRUD forms with SweetAlert2), `order.js` (order management with selectWoo), `product.js` (product forms). Templates rendered server-side via PHP. Localized data passed via `wp_localize_script()` under `storeSuiteFormHandler`, `StoreSuite_Order`, `StoreSuite_Product` globals.

### Template System (`templates/`)

WooCommerce-style template overrides: checks theme's `my-storesuite/` directory first, falls back to plugin templates. Template loading via `storesuite_get_template_part()` in `includes/functions.php`. Path overridable via `storesuite_set_template_path` filter.

### URL Routing (Frontend Dashboard)

The dashboard page (created on activation with `[storesuite_dashboard]` shortcode) uses WordPress rewrite endpoints. Each sub-page (products, orders, categories, tags, brands, coupons, edit-account-details) is a rewrite endpoint. Pagination uses custom rewrite rules (e.g., `storesuite-dashboard/products/page/2`). The `Rewrites` class resolves conflicts with WooCommerce My Account `orders` query var.

### Key Constants

- `STORESUITE_FILE`, `STORESUITE_PLUGIN_FILE` — main plugin file path
- `STORESUITE_DIR`, `STORESUITE_INC_DIR`, `STORESUITE_TEMPLATE_DIR` — directory paths
- `STORESUITE_PLUGIN_URL`, `STORESUITE_PLUGIN_ASSET` — URL paths for assets
- `STORESUITE_PLUGIN_VERSION` — current version string
- `STORESUITE_LOAD_STYLE`, `STORESUITE_LOAD_SCRIPTS` — can be set to `false` to disable default asset loading

## Coding Standards

- WordPress Coding Standards enforced via PHPCS (`phpcs.xml`)
- PHP 7.4+ minimum, text domain: `storesuite`. The floor is enforced three ways: `PHPCompatibilityWP` in `phpcs.xml` at `testVersion 7.4-`, `config.platform.php` pinned to `7.4.33` in `composer.json` (so `composer update` never locks 8.x-only packages), and a PHP 7.4 leg in the PHPUnit CI matrix. Raising it means bumping `Requires PHP` in both `readme.txt` and the `storesuite.php` header together with all three.
- All functions/hooks/options prefixed with `storesuite_`
- `wc_clean` registered as a custom sanitizing function in PHPCS config
- Yoda conditions disabled, strict comparisons enforced as errors
- File naming convention rule disabled (allows PSR-4 class filenames)
- WordPress `wp-scripts` handles JS/CSS linting

## CI/CD

GitHub Actions (`.github/workflows/deploy.yml`) triggers on git tag push: installs with `--no-dev`, builds assets, creates ZIP via rsync + `.distignore`, uploads as GitHub release artifact, and deploys to WordPress.org via SVN.

## Agent skills

### Issue tracker

Issues are tracked in GitHub Issues on `aminurislamarnob/storesuite` via the `gh` CLI. See `docs/agents/issue-tracker.md`.

### Triage labels

Default vocabulary: `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: `CONTEXT.md` + `docs/adr/` at the repo root (created lazily by `/domain-modeling`). See `docs/agents/domain.md`.
