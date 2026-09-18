/**
 * Products: list rendering, search, and adding a simple product through the
 * frontend dashboard form.
 */
const { test, expect } = require( '@playwright/test' );
const { STORAGE_STATE, dashboardUrl, uniq, wpEval } = require( '../helpers' );

test.use( { storageState: STORAGE_STATE.manager } );

test( 'product list renders with toolbar and product rows', async ( { page } ) => {
	await page.goto( dashboardUrl( 'products' ) );

	await expect( page.locator( '.storesuite-products-toolbar' ) ).toBeVisible();
	await expect( page.locator( '#search_by' ) ).toBeVisible();
	await expect( page.locator( '#bulk-action-selector-products' ) ).toBeVisible();

	// The list is newest-first and paginated, so assert rows generically —
	// the search test below pins down a specific product.
	await expect( page.locator( 'tbody tr' ).first() ).toBeVisible();
} );

test( 'search filters the product list', async ( { page } ) => {
	await page.goto( dashboardUrl( 'products' ) );

	await page.fill( '#search_by', 'E2E Seed Product' );
	await page.locator( '#search_by' ).press( 'Enter' );

	await expect( page.getByText( 'E2E Seed Product' ).first() ).toBeVisible();

	// A nonsense term yields no matching rows.
	await page.fill( '#search_by', 'zzz-no-such-product-zzz' );
	await page.locator( '#search_by' ).press( 'Enter' );
	await expect( page.getByText( 'E2E Seed Product' ) ).toHaveCount( 0 );
} );

test( 'a simple product can be added through the dashboard form', async ( { page } ) => {
	const name = `E2E Product ${ uniq() }`;

	await page.goto( dashboardUrl( 'add-new-product' ) );
	await expect( page.locator( '#storesuite-add-product' ) ).toBeVisible();

	await page.fill( '#product_title', name );
	await page.fill( '#regular_price', '19.99' );
	await page.locator( '#storesuite-add-product button[name="save_product"]' ).first().click();

	// The form either reports success via SweetAlert2 or redirects to the
	// edit screen; in both cases the product must exist in the list after.
	await page
		.locator( '.swal2-popup' )
		.waitFor( { timeout: 10000 } )
		.catch( () => {} );

	await page.goto( dashboardUrl( 'products' ) );
	await page.fill( '#search_by', name );
	await page.locator( '#search_by' ).press( 'Enter' );
	await expect( page.getByText( name ).first() ).toBeVisible();

	// Fixture hygiene: remove the UI-created product so repeated runs don't
	// accumulate rows and push the seed data off page one.
	wpEval(
		`$p = get_page_by_title( '${ name }', OBJECT, 'product' ); if ( $p ) { wp_delete_post( $p->ID, true ); }`
	);
} );
