/**
 * Account: the edit-account-details form saves via AJAX and persists.
 */
const { test, expect } = require( '@playwright/test' );
const { STORAGE_STATE, dashboardUrl, expectSwal, uniq } = require( '../helpers' );

test.use( { storageState: STORAGE_STATE.manager } );

test( 'account details save and persist', async ( { page } ) => {
	const marker = `E2E-${ uniq().slice( -6 ) }`;

	await page.goto( dashboardUrl( 'edit-account-details' ) );
	await expect( page.locator( '#storesuite-edit-account-form' ) ).toBeVisible();

	await page.fill( '#account_first_name', marker );
	await page.fill( '#account_last_name', 'Manager' );
	await page.fill( '#account_display_name', `${ marker } Manager` );
	await page.locator( '#storesuite-edit-account-form button[type="submit"]' ).first().click();

	await expectSwal( page, /(saved|updated|success)/i );

	await page.goto( dashboardUrl( 'edit-account-details' ) );
	await expect( page.locator( '#account_first_name' ) ).toHaveValue( marker );
} );

test( 'an email display name is rejected', async ( { page } ) => {
	await page.goto( dashboardUrl( 'edit-account-details' ) );

	await page.fill( '#account_display_name', 'leak@example.com' );
	await page.locator( '#storesuite-edit-account-form button[type="submit"]' ).first().click();

	await expectSwal( page, /privacy/i );
} );
