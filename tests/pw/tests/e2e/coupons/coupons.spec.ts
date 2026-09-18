import { test, expect } from '../../../utils/test';
import { MANAGER_STATE } from '../../../utils/authStates';
import { dashboardPath, seed } from '../../../utils/testData';

test.use( { storageState: MANAGER_STATE } );

test.describe( 'coupons', () => {
	test( 'list shows the seeded coupon', async ( { page } ) => {
		await page.goto( `${ dashboardPath }/coupons/` );

		await expect(
			page.locator( 'tr.single-coupon-item' ).filter( { hasText: seed.coupon } )
		).toHaveCount( 1 );
	} );

	test( 'a coupon can be created from the add form', async ( { page } ) => {
		// Idempotent across runs: the code is unique per run.
		const code = `e2e-${ Date.now() }`;

		await page.goto( `${ dashboardPath }/add-new-coupon/` );
		const form = page.locator( 'form#storesuite-add-coupon' );
		await form.locator( 'input[name="coupon_code"]' ).fill( code );
		await form.locator( 'input[name="coupon_amount"]' ).fill( '5' );
		await form.locator( 'button[type="submit"]' ).click();

		// The form submits over AJAX and reports success via SweetAlert.
		await expect( page.locator( '.swal2-popup' ) ).toBeVisible();
		await expect( page.locator( '.swal2-popup' ) ).toContainText( /success/i );

		await page.goto( `${ dashboardPath }/coupons/` );
		await expect(
			page.locator( 'tr.single-coupon-item' ).filter( { hasText: code } )
		).toHaveCount( 1 );
	} );
} );
