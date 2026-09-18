import { defineConfig, devices } from '@playwright/test';
import dotenv from 'dotenv';
import path from 'path';

dotenv.config( { path: path.join( __dirname, '.env' ), quiet: true } );

const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1:9999';
const IS_CI = !! process.env.CI && process.env.CI !== 'false';
const NO_SETUP = process.env.NO_SETUP === 'true';
const PW_CHROMIUM_PATH = process.env.PW_CHROMIUM_PATH;

/**
 * One config, projects as an ordered setup pipeline (auth_setup runs before
 * e2e_tests). NO_SETUP=true skips the pipeline so already-authenticated
 * storage states are reused while iterating locally.
 */
export default defineConfig( {
	testDir: './tests',
	outputDir: './test-results',

	// The smoke suite shares one seeded data set; a single worker keeps
	// specs deterministic. Revisit when specs own their data.
	workers: 1,
	fullyParallel: false,

	retries: IS_CI ? 2 : 0,
	forbidOnly: IS_CI,
	timeout: 60_000,
	expect: { timeout: 10_000 },

	reporter: IS_CI
		? [ [ 'list' ], [ 'html', { open: 'never' } ], [ 'github' ] ]
		: [ [ 'html', { open: 'never' } ], [ 'list' ] ],

	use: {
		baseURL: BASE_URL,
		trace: 'on-first-retry',
		screenshot: 'only-on-failure',
		navigationTimeout: 30_000,
		...( PW_CHROMIUM_PATH
			? { launchOptions: { executablePath: PW_CHROMIUM_PATH } }
			: {} ),
	},

	projects: [
		{
			name: 'auth_setup',
			testMatch: /_auth\.setup\.ts/,
		},
		{
			name: 'e2e_tests',
			testMatch: /e2e\/.*\.spec\.ts/,
			dependencies: NO_SETUP ? [] : [ 'auth_setup' ],
			use: { ...devices[ 'Desktop Chrome' ] },
		},
		{
			// HTTP-level REST contract tests; authenticate with application
			// passwords, no browser and no storage state involved.
			name: 'api_tests',
			testMatch: /api\/.*\.spec\.ts/,
			expect: { timeout: 5_000 },
		},
	],
} );
