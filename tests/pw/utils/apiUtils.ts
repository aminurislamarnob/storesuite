import { request, type APIRequestContext } from '@playwright/test';

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

	const order = await response.json();
	await api.dispose();

	return order.id as number;
}

/**
 * Product fields accepted when seeding through the WooCommerce REST API.
 */
export interface ProductSeed {
	name: string;
	sku?: string;
	regular_price?: string;
	sale_price?: string;
	status?: string;
	manage_stock?: boolean;
	stock_quantity?: number;
	date_created?: string;
}

/**
 * Create a product through the WooCommerce REST API so a spec owns its own
 * data instead of mutating the shared seed set.
 */
export async function createProductViaApi( product: ProductSeed ): Promise< number > {
	const api = await apiContext( adminAuth() );

	const response = await api.post( '/wp-json/wc/v3/products', {
		data: { type: 'simple', ...product },
	} );

	if ( ! response.ok() ) {
		throw new Error( `Product creation failed: ${ response.status() } ${ await response.text() }` );
	}

	const created = await response.json();
	await api.dispose();

	return created.id as number;
}

/**
 * Permanently delete products seeded by a spec. Safe to call with ids that
 * were already removed, so it can run unconditionally in a cleanup hook.
 */
export async function deleteProductsViaApi( ids: number[] ): Promise< void > {
	if ( ! ids.length ) {
		return;
	}

	const api = await apiContext( adminAuth() );

	for ( const id of ids ) {
		await api.delete( `/wp-json/wc/v3/products/${ id }`, { params: { force: true } } );
	}

	await api.dispose();
}

/**
 * Read a product back through the REST API to assert what was actually
 * persisted, independent of what the list page renders.
 */
export async function getProductViaApi( id: number ): Promise< Record< string, any > > {
	const api = await apiContext( adminAuth() );

	const response = await api.get( `/wp-json/wc/v3/products/${ id }` );

	if ( ! response.ok() ) {
		throw new Error( `Product fetch failed: ${ response.status() } ${ await response.text() }` );
	}

	const product = await response.json();
	await api.dispose();

	return product;
}

/**
 * Read the StoreSuite settings object through the plugin's own REST route.
 */
export async function getSettingsViaApi(): Promise< Record< string, any > > {
	const api = await apiContext( adminAuth() );
	const response = await api.get( '/wp-json/storesuite/v1/settings' );

	if ( ! response.ok() ) {
		throw new Error( `Settings fetch failed: ${ response.status() } ${ await response.text() }` );
	}

	const settings = await response.json();
	await api.dispose();

	return settings;
}

/**
 * Patch StoreSuite settings. Specs that change shared site state this way
 * must restore the previous value when they finish.
 */
export async function updateSettingsViaApi( data: Record< string, string > ): Promise< void > {
	const api = await apiContext( adminAuth() );
	const response = await api.post( '/wp-json/storesuite/v1/settings', { data } );

	if ( ! response.ok() ) {
		throw new Error( `Settings update failed: ${ response.status() } ${ await response.text() }` );
	}

	await api.dispose();
}
