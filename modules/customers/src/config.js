/* global storeSuiteCustomersConfig */

/**
 * Runtime config injected by includes/Assets.php as `storeSuiteCustomersConfig`.
 */
const defaults = {
	assetsPath: '',
	customersUrl: '',
	customersPath: '',
	orderDetailsPath: '',
	restBase: '',
	wcApiRoot: '',
	nonce: '',
	settings: {},
};

const config =
	typeof storeSuiteCustomersConfig !== 'undefined'
		? { ...defaults, ...storeSuiteCustomersConfig }
		: defaults;

export default config;
