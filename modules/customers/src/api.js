/**
 * API helpers for the Customer CRM app.
 *
 * Talks to two surfaces:
 *  - WooCommerce Analytics REST (wc-analytics/reports/*) for customer and order
 *    data (includes guests via wc_customer_lookup).
 *  - The module's own REST (storesuite/v1/customers/*) for notes and tags.
 */
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import config from './config';

let configured = false;

/**
 * Configure api-fetch for the frontend: root URL + REST nonce. Idempotent.
 */
export function setupApi() {
	if ( configured ) {
		return;
	}
	if ( config.wcApiRoot ) {
		apiFetch.use( apiFetch.createRootURLMiddleware( config.wcApiRoot ) );
	}
	if ( config.nonce ) {
		apiFetch.use( apiFetch.createNonceMiddleware( config.nonce ) );
	}
	configured = true;
}

/**
 * Fetch a page of customers from the analytics report.
 *
 * @param {Object} query Query args (page, per_page, search, orderby, order, ...).
 * @return {Promise<{items:Array,total:number}>} Rows + total count header.
 */
export async function fetchCustomers( query = {} ) {
	setupApi();
	const path = addQueryArgs( '/wc-analytics/reports/customers', {
		per_page: 25,
		page: 1,
		...query,
	} );

	const response = await apiFetch( { path, parse: false } );
	const total = parseInt( response.headers.get( 'X-WP-Total' ), 10 ) || 0;
	const items = await response.json();
	return { items, total };
}

/**
 * Fetch a single customer record by analytics customer id.
 *
 * @param {number} id Customer id.
 * @return {Promise<Object|null>} The record or null.
 */
export async function fetchCustomer( id ) {
	setupApi();
	const items = await apiFetch( {
		path: addQueryArgs( '/wc-analytics/reports/customers', {
			customers: id,
			per_page: 1,
		} ),
	} );
	return Array.isArray( items ) && items.length ? items[ 0 ] : null;
}

/**
 * Fetch a customer's orders from the analytics orders report.
 *
 * @param {number} id       Customer id.
 * @param {Object} extra    Extra query args.
 * @return {Promise<Array>} Order rows.
 */
export async function fetchCustomerOrders( id, extra = {} ) {
	setupApi();
	return apiFetch( {
		path: addQueryArgs( '/wc-analytics/reports/orders', {
			customers: id,
			per_page: 25,
			orderby: 'date',
			order: 'desc',
			...extra,
		} ),
	} );
}

/**
 * Fetch a registered customer's billing/shipping via the WC customers endpoint.
 *
 * @param {number} userId WP user id.
 * @return {Promise<Object|null>} Customer or null (guests have no user id).
 */
export async function fetchWcCustomer( userId ) {
	setupApi();
	if ( ! userId ) {
		return null;
	}
	try {
		return await apiFetch( { path: `/wc/v3/customers/${ userId }` } );
	} catch ( e ) {
		return null;
	}
}

/* -------------------------------------------------------------------------
 * Module REST: notes & tags
 * ---------------------------------------------------------------------- */

export function fetchNotes( customerId ) {
	setupApi();
	return apiFetch( { path: `/storesuite/v1/customers/${ customerId }/notes` } );
}

export function addNote( customerId, note ) {
	setupApi();
	return apiFetch( {
		path: `/storesuite/v1/customers/${ customerId }/notes`,
		method: 'POST',
		data: { note },
	} );
}

export function deleteNote( noteId ) {
	setupApi();
	return apiFetch( {
		path: `/storesuite/v1/customers/notes/${ noteId }`,
		method: 'DELETE',
	} );
}

export function fetchCustomerTags( customerId ) {
	setupApi();
	return apiFetch( { path: `/storesuite/v1/customers/${ customerId }/tags` } );
}

export function setCustomerTags( customerId, tags ) {
	setupApi();
	return apiFetch( {
		path: `/storesuite/v1/customers/${ customerId }/tags`,
		method: 'PUT',
		data: { tags },
	} );
}
