# StoreSuite Playwright end-to-end tests

E2e coverage of the frontend dashboard — access control, orders (filters +
bulk actions), coupons, categories, the account form and the wp-admin
settings app — plus HTTP-level REST contract tests in `tests/api`.
(Products/inventory specs and `feature-map.yml` arrive with the Tier 1
branch, which extends this suite.)

## Layout

```
tests/pw/
├── playwright.config.ts      One config; projects form the pipeline auth_setup → e2e_tests
├── bin/e2e-provision.sh      wp-cli provisioning: users, permalinks, seed products/coupon
├── utils/
│   ├── test.ts               Import { test, expect } from here, never from @playwright/test
│   ├── authStates.ts         Storage-state paths for admin / shop manager / customer
│   └── testData.ts           Test users and the seeded data the specs rely on
├── tests/e2e/
│   ├── _auth.setup.ts        Logs each role in once, saves storage states
│   └── <feature>/            Co-located <feature>.spec.ts + optional <feature>Page.ts
└── tests/api/                REST contract specs (application-password auth,
                              //COVERAGE_TAG comments name the routes covered)
```

The `api_tests` project needs the application passwords the provisioning
script prints — copy them into `.env` (`ADMIN_APP_PASSWORD`,
`MANAGER_APP_PASSWORD`). E2e specs that need their own data (e.g. the order
bulk action) seed it through the WooCommerce REST API via
`utils/apiUtils.ts` instead of mutating the shared seed.

## Running against wp-env (Docker)

```bash
cd tests/pw
npm install
npx wp-env start                                  # uses .wp-env.json
WP_CLI="npx wp-env run cli wp" bash bin/e2e-provision.sh
cp .env.example .env                              # BASE_URL=http://localhost:9999
npm test
```

## Running against any WordPress site

Point wp-cli at a site that has the WooCommerce and StoreSuite plugin
directories installed, then:

```bash
bash tests/pw/bin/e2e-provision.sh                # seeds users + data
cd tests/pw && cp .env.example .env               # set BASE_URL
npm install && npm test
```

In sandboxed environments without browser downloads, set `PW_CHROMIUM_PATH`
in `.env` to an existing Chromium binary.

## Conventions

- **Page objects are co-located** with their spec (`products/productsPage.ts`),
  selectors prefer roles and stable `storesuite-*` classes — no XPath.
- **Storage states**: specs opt into a role with
  `test.use( { storageState: MANAGER_STATE } )`; nothing logs in per test.
- **`NO_SETUP=true`** skips the auth pipeline for fast local iteration when
  `playwright/.auth/*.json` already exist.
- **Seed data is shared** across specs and described in `utils/testData.ts`;
  specs must not delete it. Data a spec creates (e.g. coupons) uses
  run-unique names.
- The suite runs with one worker until specs own their data.
