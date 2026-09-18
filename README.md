## Developer Guidelines

### For dev. environment

Run the following command for development environment.

```
composer update
npm install
npm run build
```

### For production environment
Run the following command for production environment to ignore the dev dependencies.

```
composer update --no-dev
npm install
npm run build
```

### Build Release
Set execution permission to the script file by `chmod +x bin/build.sh` command. Now, Run the following bash script.
```
bin/build.sh
```

### Testing

Full details for both suites (boot sequence, fixtures, how to add tests, troubleshooting) live in [`tests/README.md`](tests/README.md).

#### PHP integration tests (PHPUnit + wp-phpunit)

Runs the `tests/` suite inside a throwaway WordPress install with WooCommerce and StoreSuite loaded, backed by a real local MySQL database.

```
composer test
```

Requirements:
- A local MySQL server with an empty `storesuite_tests` database (the suite installs WordPress into it and truncates it between runs — never point it at a real database).
- Connection settings come from `tests/wp-tests-config.php` and can be overridden with `WP_TESTS_*` env vars — locally these go in the gitignored `phpunit.xml` (copy `phpunit.xml.dist` and add a `<php>` block with `WP_TESTS_DB_HOST`, `WP_TESTS_DB_NAME`, `WP_TESTS_DB_USER`, `WP_TESTS_DB_PASSWORD`, `WP_TESTS_ABSPATH`).
- Keep `wp-phpunit/wp-phpunit` matched to your WordPress core version (`composer update wp-phpunit/wp-phpunit`).

#### JS unit tests (Jest)

Covers the React admin settings app and the dashboard/analytics JS logic in `src/` — runs in jsdom with no database or local site required.

```
npm run test:unit
```

#### Browser E2E tests (Playwright)

Drives a real Chromium browser through the frontend dashboard and the wp-admin settings app against your **live local site** — unlike the PHPUnit suite, this runs on the actual install, not a throwaway one.

```
npm install
npx playwright install chromium   # first time only
npm run test:e2e
```

Requirements:
- The local site must be up with WooCommerce installed and wp-cli available; the suite's global setup activates StoreSuite, creates its own `e2e-*` test users, and seeds its fixture data automatically (idempotent — safe to re-run).
- Run from the plugin root directory, otherwise Playwright won't find `playwright.config.js`.
- The base URL is set in `playwright.config.js` — adjust it if your local site isn't served at that address.