import { test, expect } from '../../../utils/test';
import { MANAGER_STATE } from '../../../utils/authStates';
import { dashboardPath } from '../../../utils/testData';

test.use( { storageState: MANAGER_STATE } );

test.describe( 'product categories', () => {
	test( 'a category can be created from the add form', async ( { page } ) => {
		const name = `E2E Category ${ Date.now() }`;

		await page.goto( `${ dashboardPath }/add-new-category/` );

		const form = page.locator( 'form#storesuite-add-category' );
		await form.locator( 'input[name="product_category_name"]' ).fill( name );
		await form
			.locator( 'textarea[name="product_category_description"], input[name="product_category_description"]' )
			.fill( 'Created by the e2e suite' );
		await form.locator( 'button[type="submit"]' ).click();

		await expect( page.locator( '.swal2-popup' ) ).toBeVisible();
		await expect( page.locator( '.swal2-popup' ) ).toContainText( /success/i );

		await page.goto( `${ dashboardPath }/categories/` );
		await expect(
			page.locator( '.my-storesuite-tbl tbody tr' ).filter( { hasText: name } )
		).toHaveCount( 1 );
	} );

	test( 'the categories list renders with bulk checkboxes', async ( { page } ) => {
		await page.goto( `${ dashboardPath }/categories/` );

		const rows = page.locator( '.my-storesuite-tbl tbody tr' );
		expect( await rows.count() ).toBeGreaterThan( 0 );

		await expect( page.locator( '.storesuite-bulk-select-all' ) ).toBeVisible();
		await expect( page.locator( '.storesuite-bulk-cb' ).first() ).toBeVisible();
	} );
} );
