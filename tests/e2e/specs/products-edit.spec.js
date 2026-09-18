/**
 * Products: edit an existing product through the dashboard form, and delete
 * one from the list via the row action.
 */
const { test, expect } = require( '@playwright/test' );
const {
	STORAGE_STATE,
	dashboardUrl,
	expectSwal,
	confirmSwal,
	uniq,
	settleReload,
	createProduct,
	wpEval,
} = require( '../helpers' );

test.use( { storageState: STORAGE_STATE.manager } );

test( 'an existing product can be edited', async ( { page } ) => {
	const name = `E2E Edit Product ${ uniq() }`;
	const renamed = `${ name } Renamed`;
	const id = createProduct( name, '10' );

	await page.goto( dashboardUrl( 'edit-product' ) + id );

	await expect( page.locator( '#product_title' ) ).toHaveValue( name );
	await page.fill( '#product_title', renamed );
	await page.fill( '#regular_price', '42.00' );
	await page.locator( 'button[name="save_product"]' ).first().click();

	await expectSwal( page, /success/i );

	// Persisted: reload the edit screen and check both fields.
	await page.goto( dashboardUrl( 'edit-product' ) + id );
	await expect( page.locator( '#product_title' ) ).toHaveValue( renamed );
	await expect( page.locator( '#regular_price' ) ).toHaveValue( '42.00' );

	// Fixture hygiene: remove the product so runs don't accumulate rows.
	wpEval( `wp_delete_post( ${ id }, true );` );
} );

test( 'a product can be deleted from the list', async ( { page } ) => {
	const name = `E2E Delete Product ${ uniq() }`;
	createProduct( name, '10' );
	const listUrl = `${ dashboardUrl( 'products' ) }?search_by=${ encodeURIComponent( name ) }`;

	await page.goto( listUrl );
	const row = page.locator( 'tr', { hasText: name } ).first();
	await expect( row ).toBeVisible();

	// Dispatch the click directly — the dropdown menu animates open and
	// coordinate-based clicks race the animation.
	await row.locator( '.storesuite-delete-product' ).dispatchEvent( 'click' );
	await confirmSwal( page );
	await expectSwal( page, /deleted/i );

	// The success handler reloads the list; let it finish before navigating.
	await settleReload( page );
	await page.goto( listUrl );
	await expect( page.locator( 'tr', { hasText: name } ) ).toHaveCount( 0 );
} );
