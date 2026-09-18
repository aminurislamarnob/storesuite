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
