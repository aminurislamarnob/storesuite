/**
 * Playwright E2E configuration.
 *
 * Runs against the local Herd WordPress install (https://westore-headless.test,
 * override with E2E_BASE_URL) with StoreSuite + WooCommerce active. Test users and seed data are created
 * by tests/e2e/global-setup.js via wp-cli; logins are performed once in
 * tests/e2e/auth.setup.js and reused through storage states.
 *
 * Run with: npm run test:e2e
 */
const { defineConfig, devices } = require( '@playwright/test' );

module.exports = defineConfig( {
	testDir: './tests/e2e/specs',
	globalSetup: require.resolve( './tests/e2e/global-setup.js' ),
	/* The suite mutates one shared WordPress site — run serially. */
	fullyParallel: false,
	workers: 1,
	retries: 0,
	timeout: 30000,
	reporter: [ [ 'list' ] ],
	use: {
		baseURL: process.env.E2E_BASE_URL || 'https://westore-headless.test',
		/* Herd serves a self-signed certificate. */
		ignoreHTTPSErrors: true,
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
	},
	projects: [
		{
			name: 'setup',
			testDir: './tests/e2e',
			testMatch: /auth\.setup\.js/,
			use: { ...devices[ 'Desktop Chrome' ] },
		},
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
			dependencies: [ 'setup' ],
		},
	],
} );
