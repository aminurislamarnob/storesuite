/**
 * Product tags and brands: add + delete through the dashboard forms. They
 * share the categories' form/AJAX design, so one lifecycle each is enough.
 */
const { test, expect } = require( '@playwright/test' );
const { STORAGE_STATE, dashboardUrl, expectSwal, confirmSwal, uniq } = require( '../helpers' );

test.use( { storageState: STORAGE_STATE.manager } );

test( 'tag lifecycle: add then delete', async ( { page } ) => {
	const name = `E2E Tag ${ uniq() }`;
	const listUrl = `${ dashboardUrl( 'tags' ) }?search_by=${ encodeURIComponent( name ) }`;

	await page.goto( dashboardUrl( 'add-new-tag' ) );
	await page.fill( '#storesuite-add-tag #name', name );
	await page.click( '#storesuite-add-tag button[type="submit"]' );
	await expectSwal( page, /successfully created/i );

	await page.goto( listUrl );
	const row = page.locator( 'tr', { hasText: name } ).first();
	await expect( row ).toBeVisible();

	// Dispatch the click directly — the dropdown menu animates open and
	// coordinate-based clicks race the animation.
	await row.locator( '.storesuite-delete-tag' ).dispatchEvent( 'click' );
	await confirmSwal( page );
	await expectSwal( page, /successfully deleted/i );
} );

test( 'brand lifecycle: add then delete', async ( { page } ) => {
	const name = `E2E Brand ${ uniq() }`;
	const listUrl = `${ dashboardUrl( 'brands' ) }?search_by=${ encodeURIComponent( name ) }`;

	await page.goto( dashboardUrl( 'add-new-brand' ) );
	await page.fill( '#product_brand_name', name );
	await page.click( '#storesuite-add-brand button[type="submit"]' );
	await expectSwal( page, /successfully created/i );

	await page.goto( listUrl );
	const row = page.locator( 'tr', { hasText: name } ).first();
	await expect( row ).toBeVisible();

	// Dispatch the click directly — the dropdown menu animates open and
	// coordinate-based clicks race the animation.
	await row.locator( '.storesuite-delete-brand' ).dispatchEvent( 'click' );
	await confirmSwal( page );
	await expectSwal( page, /successfully deleted/i );
} );
