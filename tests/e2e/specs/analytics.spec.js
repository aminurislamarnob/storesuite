/**
 * Analytics: the React reports app mounts on the dashboard analytics endpoint
 * and is gated to manage_woocommerce users.
 */
const { test, expect } = require( '@playwright/test' );
const { STORAGE_STATE, dashboardUrl } = require( '../helpers' );

test.describe( 'shop manager', () => {
	test.use( { storageState: STORAGE_STATE.manager } );

	test( 'analytics app mounts and renders content', async ( { page } ) => {
		await page.goto( dashboardUrl( 'analytics' ) );

		const app = page.locator( '#storesuite-analytics-app' );
		await expect( app ).toBeAttached();

		// The React bundle must hydrate the mount node with real content.
		await expect
			.poll( async () => ( await app.innerHTML() ).length, { timeout: 15000 } )
			.toBeGreaterThan( 100 );
	} );
} );

test.describe( 'customer', () => {
	test.use( { storageState: STORAGE_STATE.customer } );

	test( 'is redirected away from analytics', async ( { page } ) => {
		await page.goto( dashboardUrl( 'analytics' ) );

		await expect( page.locator( '#storesuite-analytics-app' ) ).toHaveCount( 0 );
	} );
} );
