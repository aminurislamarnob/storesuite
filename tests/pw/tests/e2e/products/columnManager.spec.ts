import { test, expect } from '../../../utils/test';
import { MANAGER_STATE } from '../../../utils/authStates';
import { ProductsPage } from './productsPage';

test.use( { storageState: MANAGER_STATE } );

/**
 * The "Columns" toolbar dropdown hides a column immediately and the choice
 * survives a reload because it is stored in user meta.
 */
test.describe( 'column manager', () => {
	test( 'hiding a column persists across reloads and can be reset', async ( { page } ) => {
		const products = new ProductsPage( page );
		await products.goto();

		const manager = page.locator( '.storesuite-column-manager[data-table="products"]' );
		const skuHeader = page.locator( 'th[data-col="sku"]' );
		await expect( skuHeader ).toBeVisible();

		await manager.locator( '.storesuite-column-manager-toggle' ).click();
		const skuBox = manager.locator( 'input[value="sku"]' );
		await expect( skuBox ).toBeVisible();

		const saved = page.waitForResponse( ( response ) =>
			response.url().includes( 'admin-ajax.php' ) &&
			response.request().postData()?.includes( 'storesuite_save_hidden_columns' ) === true
		);
		await skuBox.uncheck();
		await saved;

		await expect( skuHeader ).toBeHidden();
		await expect( page.locator( 'td[data-col="sku"]' ).first() ).toBeHidden();

		await page.reload();
		await expect( page.locator( 'th[data-col="sku"]' ) ).toBeHidden();
		await expect( manager.locator( '.storesuite-column-manager-count' ) ).toHaveText( '1' );

		// The locked Name column cannot be switched off.
		await manager.locator( '.storesuite-column-manager-toggle' ).click();
		await expect( manager.locator( 'input[value="name"]' ) ).toBeDisabled();

		const reset = page.waitForResponse( ( response ) =>
			response.url().includes( 'admin-ajax.php' ) &&
			response.request().postData()?.includes( 'storesuite_save_hidden_columns' ) === true
		);
		await manager.locator( '.storesuite-column-manager-reset' ).click();
		await reset;

		await expect( page.locator( 'th[data-col="sku"]' ) ).toBeVisible();
		await page.reload();
		await expect( page.locator( 'th[data-col="sku"]' ) ).toBeVisible();
	} );
} );
