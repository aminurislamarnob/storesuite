/**
 * Coupons: add through the dashboard form, verify in the list, delete via the
 * row dropdown with SweetAlert2 confirmation.
 */
const { test, expect } = require( '@playwright/test' );
const {
	STORAGE_STATE,
	dashboardUrl,
	expectSwal,
	confirmSwal,
	uniq,
	settleReload,
	createCoupon,
	wpEval,
} = require( '../helpers' );

test.use( { storageState: STORAGE_STATE.manager } );

test( 'coupon lifecycle: add, list, delete', async ( { page } ) => {
	const code = `e2e-coupon-${ uniq() }`;
	const listUrl = `${ dashboardUrl( 'coupons' ) }?search_by=${ encodeURIComponent( code ) }`;

	// --- Add ---
	await page.goto( dashboardUrl( 'add-new-coupon' ) );
	await page.fill( '#coupon_code', code );
	await page.fill( '#coupon_amount', '12.50' );
	await page.fill( '#description', 'Created by Playwright.' );
	await page.click( '#storesuite-add-coupon button[type="submit"]' );
	await expectSwal( page, /successfully created/i );
	// The add handler redirects to the coupons list after the popup.
	await settleReload( page );

	// --- Appears in the list ---
	await page.goto( listUrl );
	const row = page.locator( 'tr', { hasText: code } ).first();
	await expect( row ).toBeVisible();

	// --- Delete (soft delete with confirmation). Dispatch the click directly:
	// the dropdown menu animates open and coordinate clicks race it. ---
	await row.locator( '.storesuite-delete-coupon' ).dispatchEvent( 'click' );
	await confirmSwal( page );
	await expectSwal( page, /successfully deleted/i );

	// The success handler reloads the list; let it finish before navigating.
	await settleReload( page );
	await page.goto( listUrl );
	await expect( page.locator( 'tr', { hasText: code } ) ).toHaveCount( 0 );
} );

test( 'an existing coupon can be edited', async ( { page } ) => {
	const code = `e2e-edit-coupon-${ uniq() }`;
	const id = createCoupon( code, '5' );

	await page.goto( dashboardUrl( 'edit-coupon' ) + id );

	await expect( page.locator( '#coupon_code' ) ).toHaveValue( code );
	await page.fill( '#coupon_amount', '25' );
	await page.click( '#storesuite-edit-coupon button[type="submit"]' );
	await expectSwal( page, /(updated|success)/i );
	// The edit handler redirects to the coupons list after the popup.
	await settleReload( page );

	// Persisted: reload the edit screen and check the amount.
	await page.goto( dashboardUrl( 'edit-coupon' ) + id );
	await expect( page.locator( '#coupon_amount' ) ).toHaveValue( '25' );

	// Fixture hygiene: remove the coupon so runs don't accumulate rows.
	wpEval( `wp_delete_post( ${ id }, true );` );
} );

test( 'coupon form requires a code', async ( { page } ) => {
	await page.goto( dashboardUrl( 'add-new-coupon' ) );
	await page.fill( '#coupon_amount', '5' );
	await page.click( '#storesuite-add-coupon button[type="submit"]' );

	// Client-side validation marks the field inline; no request is sent.
	await expect( page.locator( '.storesuite-field-error' ).first() ).toBeVisible();
} );
