/**
 * Product categories: full add → list → edit → delete lifecycle through the
 * frontend dashboard forms (AJAX + SweetAlert2 feedback).
 */
const { test, expect } = require( '@playwright/test' );
const { STORAGE_STATE, dashboardUrl, expectSwal, confirmSwal, uniq, settleReload } = require( '../helpers' );

test.use( { storageState: STORAGE_STATE.manager } );

/**
 * Open the categories list filtered to the given name (the list is paginated,
 * so a fresh term may not be on page one without the search filter).
 *
 * @param {import('@playwright/test').Page} page Playwright page.
 * @param {string} name Category name to search for.
 */
async function gotoFiltered( page, name ) {
	await page.goto( `${ dashboardUrl( 'categories' ) }?search_by=${ encodeURIComponent( name ) }` );
}

test( 'category lifecycle: add, edit, delete', async ( { page } ) => {
	const name = `E2E Category ${ uniq() }`;
	const renamed = `${ name } Renamed`;

	// --- Add ---
	await page.goto( dashboardUrl( 'add-new-category' ) );
	await page.fill( '#product_category_name', name );
	await page.fill( '#product_category_description', 'Created by Playwright.' );
	await page.click( '#storesuite-add-category button[type="submit"]' );
	await expectSwal( page, /successfully created/i );

	// --- Appears in the (filtered) list ---
	await gotoFiltered( page, name );
	const row = page.locator( 'tr', { hasText: name } ).first();
	await expect( row ).toBeVisible();

	// --- Edit (navigate via the row action's href; the dropdown menu itself
	// is animation-driven and flaky to click through) ---
	const editHref = await row.locator( 'a[href*="edit-category"]' ).first().getAttribute( 'href' );
	await page.goto( editHref );
	await expect( page.locator( '#product_category_name' ) ).toHaveValue( name );
	await page.fill( '#product_category_name', renamed );
	await page.click( 'form[id*="category"] button[type="submit"]' );
	await expectSwal( page, /successfully updated/i );

	// --- Delete ---
	await gotoFiltered( page, renamed );
	const renamedRow = page.locator( 'tr', { hasText: renamed } ).first();
	await expect( renamedRow ).toBeVisible();
	// Dispatch the click directly — the dropdown menu animates open and
	// coordinate-based clicks race the animation.
	await renamedRow.locator( '.storesuite-delete-category' ).dispatchEvent( 'click' );

	// SweetAlert2 confirmation, then success.
	await confirmSwal( page );
	await expectSwal( page, /successfully deleted/i );

	// The success handler reloads the list; let it finish before navigating.
	await settleReload( page );
	await gotoFiltered( page, renamed );
	await expect( page.locator( 'tr', { hasText: renamed } ) ).toHaveCount( 0 );
} );

test( 'category form requires a name', async ( { page } ) => {
	await page.goto( dashboardUrl( 'add-new-category' ) );
	await page.click( '#storesuite-add-category button[type="submit"]' );

	// Client-side validation marks the field inline; no request is sent.
	await expect( page.locator( '.storesuite-field-error' ).first() ).toBeVisible();
} );
