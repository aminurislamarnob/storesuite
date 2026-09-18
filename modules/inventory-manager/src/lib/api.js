/**
 * REST helpers — thin wrappers around @wordpress/api-fetch for the four
 * inventory endpoints. Root URL + nonce come from the localized config the
 * PHP enqueue provides on `window.StoreSuiteInventory`.
 */
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

const cfg = window.StoreSuiteInventory || {};

apiFetch.use( apiFetch.createRootURLMiddleware( cfg.root || '/wp-json/' ) );
if ( cfg.nonce ) {
	apiFetch.use( apiFetch.createNonceMiddleware( cfg.nonce ) );
}

const BASE = '/storesuite/v1/inventory';

// GET the paginated stock list.
export function getStock( params ) {
	return apiFetch( { path: addQueryArgs( BASE, params ) } );
}

// POST a new quantity for one item.
export function setStock( id, qty ) {
	return apiFetch( {
		path: `${ BASE }/${ id }/stock`,
		method: 'POST',
		data: { qty },
	} );
}

// POST a bulk change (set / increase / decrease) across selected items.
export function bulkUpdate( ids, op, qty ) {
	return apiFetch( {
		path: `${ BASE }/bulk`,
		method: 'POST',
		data: { ids, op, qty },
	} );
}

// GET the movement log.
export function getLog( params ) {
	return apiFetch( { path: addQueryArgs( `${ BASE }/log`, params ) } );
}
