import { test, expect } from '../../../utils/test';
import { MANAGER_STATE } from '../../../utils/authStates';
import { createProductViaApi, deleteProductsViaApi } from '../../../utils/apiUtils';
import { dashboardPath } from '../../../utils/testData';
import { ProductsPage } from './productsPage';

test.use( { storageState: MANAGER_STATE } );

/**
 * Undo / edit history: an inline edit shows a toast with an Undo link that
 * reverts the change in place, and the History page lists the batch.
 */
test.describe( 'edit history', () => {
	const seeded: number[] = [];

	test.afterAll( async () => {
		await deleteProductsViaApi( seeded );
	} );

	test( 'inline edit toast undoes the change in place', async ( { page } ) => {
		const name = `History Subject ${ Date.now() }`;
		seeded.push(
			await createProductViaApi( { name, sku: `HIST-${ Date.now() }`, regular_price: '30' } )
		);

		const products = new ProductsPage( page );
		await products.goto();

		const editor = await products.openInlineEditor( products.row( name ), 'sku' );
		await editor.locator( 'input[name="value"]' ).fill( 'HIST-EDITED' );
		await editor.locator( 'input[name="value"]' ).press( 'Enter' );

		await expect( products.inlineCell( products.row( name ), 'sku' ) ).toContainText( 'HIST-EDITED' );

		const toast = page.locator( '.storesuite-undo-toast' );
		await expect( toast ).toBeVisible();
		await toast.locator( '.storesuite-undo-toast-button' ).click();

		await expect( products.inlineCell( products.row( name ), 'sku' ) ).not.toContainText( 'HIST-EDITED' );
		await expect( products.inlineCell( products.row( name ), 'sku' ) ).toContainText( 'HIST-' );
	} );

	test( 'the History page lists the change and its undo', async ( { page } ) => {
		await page.goto( `${ dashboardPath }/edit-history/` );

		const rows = page.locator( 'tr.storesuite-history-row' );
		await expect( rows.first() ).toBeVisible();

		const undoRow = rows.filter( { has: page.locator( '.storesuite-badge', { hasText: /^Undo$/ } ) } ).filter( { hasText: 'SKU changed on' } );
		await expect( undoRow.first() ).toBeVisible();
		await expect( rows.filter( { has: page.locator( '.storesuite-badge', { hasText: /^Undone$/ } ) } ).first() ).toBeVisible();
	} );
} );
