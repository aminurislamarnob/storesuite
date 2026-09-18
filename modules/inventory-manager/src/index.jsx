/**
 * Inventory Manager — React entry point.
 *
 * Mounts the app into the shell template's `#storesuite-inventory-app` div.
 */
import { createRoot } from '@wordpress/element';
import './styles/app.css';
import App from './App';

const mount = document.getElementById( 'storesuite-inventory-app' );

if ( mount ) {
	createRoot( mount ).render( <App /> );
}
