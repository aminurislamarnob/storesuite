/**
 * Inventory Manager module: the /inventory dashboard endpoint the active
 * module registers — React app mount, REST data loading, stock list content,
 * and the stock-log view.
 */
const { test, expect } = require( '@playwright/test' );
const { STORAGE_STATE, dashboardUrl } = require( '../helpers' );

test.describe( 'shop manager', () => {
	test.use( { storageState: STORAGE_STATE.manager } );

	test( 'inventory app mounts and loads stock data over REST', async ( { page } ) => {
		const restResponse = page.waitForResponse(
			( response ) =>
				response.url().includes( '/storesuite/v1/inventory' ) && response.ok(),
			{ timeout: 15000 }
		);

		await page.goto( dashboardUrl( 'inventory' ) );

		const app = page.locator( '#storesuite-inventory-app' );
		await expect( app ).toBeAttached();

		// The module's REST controller must answer the app's initial fetch.
		await restResponse;

		// And the React app must hydrate the mount node with real content.
		await expect
			.poll( async () => ( await app.innerHTML() ).length, { timeout: 15000 } )
			.toBeGreaterThan( 100 );
	} );

	test( 'stock list search finds the seeded product', async ( { page } ) => {
		await page.goto( dashboardUrl( 'inventory' ) );

		// The list is paginated; filter through the app's own search box.
		const search = page.getByPlaceholder( /search product or sku/i );
		await expect( search ).toBeVisible( { timeout: 15000 } );
		await search.fill( 'E2E Seed Product' );

		await expect(
			page.locator( '#storesuite-inventory-app' ).getByText( 'E2E Seed Product' ).first()
		).toBeVisible( { timeout: 15000 } );
	} );

	test( 'stock log view renders', async ( { page } ) => {
		await page.goto( `${ dashboardUrl( 'inventory' ) }?view=log` );

		const app = page.locator( '#storesuite-inventory-app' );
		await expect( app ).toBeAttached();
		await expect
			.poll( async () => ( await app.innerHTML() ).length, { timeout: 15000 } )
			.toBeGreaterThan( 100 );
	} );

	test( 'sidebar exposes the inventory views', async ( { page } ) => {
		await page.goto( dashboardUrl( 'inventory' ) );

		const menu = page.locator( '.storesuite-dashboard-menu' );
		await expect( menu.locator( 'a[href*="inventory"]' ).first() ).toBeAttached();
	} );
} );

test.describe( 'customer', () => {
	test.use( { storageState: STORAGE_STATE.customer } );

	test( 'cannot reach the inventory screen', async ( { page } ) => {
		await page.goto( dashboardUrl( 'inventory' ) );

		await expect( page.locator( '#storesuite-inventory-app' ) ).toHaveCount( 0 );
	} );
} );
