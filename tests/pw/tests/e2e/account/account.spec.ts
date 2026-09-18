import { test, expect } from '../../../utils/test';
import { MANAGER_STATE } from '../../../utils/authStates';
import { dashboardPath } from '../../../utils/testData';

test.use( { storageState: MANAGER_STATE } );

test.describe( 'account details', () => {
	test( 'the account form saves and persists changes', async ( { page } ) => {
		await page.goto( `${ dashboardPath }/edit-account-details/` );

		const form = page.locator( 'form#storesuite-edit-account-form' );
		await expect( form ).toBeVisible();

		// The handler requires first/last/display name and email client-side;
		// fixed values keep the spec idempotent across runs.
		await form.locator( 'input[name="account_first_name"]' ).fill( 'Morgan' );
		await form.locator( 'input[name="account_last_name"]' ).fill( 'Manager' );
		await form.locator( 'input[name="account_display_name"]' ).fill( 'Morgan Manager' );
		await form.locator( 'button[type="submit"]' ).first().click();

		await expect( page.locator( '.swal2-popup' ) ).toBeVisible();
		await expect( page.locator( '.swal2-popup' ) ).toContainText( /success|updated/i );

		await page.goto( `${ dashboardPath }/edit-account-details/` );
		await expect(
			page.locator( 'form#storesuite-edit-account-form input[name="account_first_name"]' )
		).toHaveValue( 'Morgan' );
	} );
} );
