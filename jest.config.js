const defaultConfig = require( '@wordpress/scripts/config/jest-unit.config' );
const presetConfig = require( '@wordpress/jest-preset-default/jest-preset' );

// The preset is flattened into this config instead of referenced via the
// `preset` key: Jest concatenates a preset's setupFiles *before* the
// config's, but our window shim must run before the preset's setup-globals
// (which writes to `window`, absent in node-environment test files).
module.exports = {
	...presetConfig,
	...defaultConfig,
	preset: undefined,
	transform: defaultConfig.transform || presetConfig.transform,
	// Only the JS unit suite — keeps Jest away from the PHPUnit suite in
	// tests/ and the Playwright specs in tests/e2e/.
	testMatch: [ '<rootDir>/tests/js/**/?(*.)test.js' ],
	moduleNameMapper: {
		...presetConfig.moduleNameMapper,
		// The @woocommerce/* packages are webpack externals (window.wc.*) and
		// are not installed as npm dependencies, so Jest needs stand-ins.
		'^@woocommerce\\/(.*)$': '<rootDir>/tests/js/__mocks__/woocommerce/$1.js',
	},
	setupFiles: [
		'<rootDir>/tests/js/setup-window-shim.js',
		...presetConfig.setupFiles,
	],
	setupFilesAfterEnv: [
		...presetConfig.setupFilesAfterEnv,
		'<rootDir>/tests/js/setup-tests.js',
	],
};
