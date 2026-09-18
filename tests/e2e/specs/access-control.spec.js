/**
 * Access control: who can reach the frontend dashboard and wp-admin.
 */
const { test, expect } = require( '@playwright/test' );
const {
	STORAGE_STATE,
	dashboardUrl,
	getStoreSuiteSetting,
	setStoreSuiteSetting,
} = require( '../helpers' );

test.describe( 'logged-out visitor', () => {
	test.use( { storageState: { cookies: [], origins: [] } } );

	test( 'is redirected away from the dashboard', async ( { page } ) => {
		await page.goto( dashboardUrl( 'products' ) );

		// The access guard redirects to a login screen; whatever the exact
		// destination, no dashboard chrome may be visible.
		await expect( page.locator( '#storesuite-dashboard-sidebar' ) ).toHaveCount( 0 );
		expect( page.url() ).not.toContain( '/storesuite-dashboard/products' );
	} );
} );

test.describe( 'customer', () => {
	test.use( { storageState: STORAGE_STATE.customer } );

	test( 'cannot see the dashboard', async ( { page } ) => {
		await page.goto( dashboardUrl() );

		await expect( page.locator( '#storesuite-dashboard-sidebar' ) ).toHaveCount( 0 );
	} );
} );

test.describe( 'shop manager', () => {
	test.use( { storageState: STORAGE_STATE.manager } );

	test( 'can open the dashboard', async ( { page } ) => {
		await page.goto( dashboardUrl() );

		await expect( page.locator( '#storesuite-dashboard-sidebar' ) ).toBeVisible();
	} );

	test.describe( 'with "prevent admin access" enabled', () => {
		let previous;

		test.beforeAll( () => {
			previous = getStoreSuiteSetting( 'storesuite_prevent_admin_access' );
			setStoreSuiteSetting( 'storesuite_prevent_admin_access', 'yes' );
		} );

		test.afterAll( () => {
			setStoreSuiteSetting( 'storesuite_prevent_admin_access', previous );
		} );

		test( 'is blocked from wp-admin', async ( { page } ) => {
			await page.goto( '/wp-admin/' );

			// Main::block_admin_access redirects blocked roles to the home URL.
			await expect( page ).not.toHaveURL( /\/wp-admin\/?$/ );
		} );
	} );
} );

test.describe( 'administrator', () => {
	test.use( { storageState: STORAGE_STATE.admin } );

	test( 'keeps wp-admin access', async ( { page } ) => {
		await page.goto( '/wp-admin/' );

		await expect( page.locator( '#wpadminbar' ) ).toBeVisible();
		await expect( page ).toHaveURL( /\/wp-admin\// );
	} );
} );
