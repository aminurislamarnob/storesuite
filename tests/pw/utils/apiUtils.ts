import { request, type APIRequestContext, type APIResponse } from '@playwright/test';

/**
 * REST helpers authenticated with WordPress application passwords
 * (bin/e2e-provision.sh creates them; the site must allow them over HTTP,
 * which WP_ENVIRONMENT_TYPE=local does).
 */
const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1:9999';

function basicAuth( username: string, appPassword: string ): string {
	return 'Basic ' + Buffer.from( `${ username }:${ appPassword }` ).toString( 'base64' );
}

export const adminAuth = () =>
	basicAuth( process.env.ADMIN_USER || 'admin', process.env.ADMIN_APP_PASSWORD || '' );

export const managerAuth = () =>
	basicAuth( process.env.MANAGER_USER || 'manager', process.env.MANAGER_APP_PASSWORD || '' );

/**
 * Parse a JSON response body, tolerating PHP notices a debug site may print
 * ahead of the JSON (WP_DEBUG_DISPLAY on a local install).
 */
export async function parseJson< T = unknown >( response: APIResponse ): Promise< T > {
	const body = await response.text();
	const start = Math.min(
		...[ body.indexOf( '{' ), body.indexOf( '[' ) ].filter( ( i ) => i >= 0 )
	);

	return JSON.parse( Number.isFinite( start ) ? body.slice( start ) : body ) as T;
}

export async function apiContext( auth?: string ): Promise<APIRequestContext> {
	return request.newContext( {
		baseURL: BASE_URL,
		extraHTTPHeaders: auth ? { Authorization: auth } : {},
		// Inside a test, new request contexts inherit the test's storageState;
		// a logged-in cookie alongside Basic auth makes WordPress demand a
		// REST nonce and reject the request. Force a cookie-free context.
		storageState: { cookies: [], origins: [] },
	} );
}

/**
 * Create an order through the WooCommerce REST API — the seeding path for
 * e2e specs that need data of their own (Dokan's ApiUtils pattern).
 */
export async function createOrderViaApi( status = 'processing' ): Promise<number> {
	const api = await apiContext( adminAuth() );

	const response = await api.post( '/wp-json/wc/v3/orders', {
		data: {
			status,
			billing: { first_name: 'Api', last_name: 'Seeded' },
		},
	} );

	if ( ! response.ok() ) {
		throw new Error( `Order creation failed: ${ response.status() } ${ await response.text() }` );
	}

	const order = await parseJson< { id: number } >( response );
	await api.dispose();

	return order.id as number;
}

/**
 * Create a simple product through the WooCommerce REST API for specs that
 * need a product of their own to edit. Returns the product id.
 */
export async function createProductViaApi( name: string ): Promise<number> {
	const api = await apiContext( adminAuth() );

	const response = await api.post( '/wp-json/wc/v3/products', {
		data: { name, type: 'simple', regular_price: '10' },
	} );

	if ( ! response.ok() ) {
		throw new Error( `Product creation failed: ${ response.status() } ${ await response.text() }` );
	}

	const product = await parseJson< { id: number } >( response );
	await api.dispose();

	return product.id as number;
}

/**
 * Whether a plugin is active, read through the WordPress REST plugins
 * endpoint (administrator application password). Lets a spec skip itself
 * when an optional plugin is not installed on the site under test.
 */
export async function isPluginActive( slug: string ): Promise<boolean> {
	const api = await apiContext( adminAuth() );
	const response = await api.get( `/wp-json/wp/v2/plugins?search=${ encodeURIComponent( slug ) }` );

	if ( ! response.ok() ) {
		await api.dispose();
		return false;
	}

	const plugins = await parseJson< Array< { plugin: string; status: string } > >( response );
	await api.dispose();

	return plugins.some( ( p ) => p.plugin.startsWith( `${ slug }/` ) && p.status === 'active' );
}
