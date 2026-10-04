/* global storeSuiteCustomersConfig */

// Set the webpack public path for async chunks before other imports.
if (
	typeof storeSuiteCustomersConfig !== 'undefined' &&
	storeSuiteCustomersConfig.assetsPath
) {
	// eslint-disable-next-line camelcase, no-undef
	__webpack_public_path__ = storeSuiteCustomersConfig.assetsPath;
}

import { createRoot } from '@wordpress/element';
import App from './app';
import './style.scss';

const rootElement = document.getElementById( 'storesuite-customers-app' );

if ( rootElement ) {
	createRoot( rootElement ).render( <App /> );
}
