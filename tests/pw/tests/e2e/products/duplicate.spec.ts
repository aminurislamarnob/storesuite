import { test, expect } from '../../../utils/test';
import { MANAGER_STATE } from '../../../utils/authStates';
import { dashboardPath, seed } from '../../../utils/testData';
import { ProductsPage } from './productsPage';

test.use( { storageState: MANAGER_STATE } );

test.describe( 'duplicate row action', () => {
	test( 'duplicating a product opens the draft copy in the edit form', async ( { page } ) => {
		const products = new ProductsPage( page );
		await products.goto();

		const row = products.row( seed.products.mug.name );
		await row.locator( '.storesuite-dropdown-icon' ).click();
		await Promise.all( [
			page.waitForURL( /\/edit-product\/\d+/ ),
			row.getByRole( 'button', { name: 'Duplicate' } ).click(),
		] );

		await expect( page.locator( 'input#product_title' ).first() ).toHaveValue(
			`${ seed.products.mug.name } (Copy)`
		);
	} );

	test( 'duplicating a coupon opens the draft copy with a suffixed code', async ( { page } ) => {
		await page.goto( `${ dashboardPath }/coupons/` );

		const row = page
			.locator( 'tr.single-coupon-item' )
			.filter( { has: page.locator( 'td.tbl-coupon-code', { hasText: new RegExp( `^\\s*${ seed.coupon }\\s*$` ) } ) } )
			.first();
		await row.locator( '.storesuite-dropdown-icon' ).click();
		await Promise.all( [
			page.waitForURL( /\/edit-coupon\/\d+/ ),
			row.getByRole( 'button', { name: 'Duplicate' } ).click(),
		] );

		await expect( page.locator( 'input[name="coupon_code"]' ) ).toHaveValue( /^welcome10-copy/ );
	} );
} );
