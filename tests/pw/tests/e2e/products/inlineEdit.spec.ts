import { test, expect } from '../../../utils/test';
import { MANAGER_STATE } from '../../../utils/authStates';
import {
	createProductViaApi,
	deleteProductsViaApi,
	getProductViaApi,
} from '../../../utils/apiUtils';
import { seed } from '../../../utils/testData';
import { ProductsPage } from './productsPage';

test.use( { storageState: MANAGER_STATE } );

/**
 * Inline cell editing on the Products list, beyond the price round trip
 * covered in products.spec.ts: the remaining cell types, the validation
 * rejections, and the two cancel paths.
 *
 * Each spec seeds its own product through the REST API so the shared seed
 * set stays untouched and the assertions do not depend on run order.
 */
test.describe( 'products inline cell editing', () => {
	const seeded: number[] = [];

	async function seedProduct( suffix: string, overrides = {} ): Promise< string > {
		const name = `Inline Subject ${ suffix }`;
		seeded.push(
			await createProductViaApi( {
				name,
				sku: `INLINE-${ suffix }`,
				regular_price: '30',
				manage_stock: true,
				stock_quantity: 9,
				...overrides,
			} )
		);
		return name;
	}

	test.afterAll( async () => {
		await deleteProductsViaApi( seeded );
	} );

	test( 'SKU cell saves a new value', async ( { page } ) => {
		const name = await seedProduct( 'SKU' );
		const products = new ProductsPage( page );
		await products.goto();

		const editor = await products.openInlineEditor( products.row( name ), 'sku' );
		await editor.locator( 'input[name="value"]' ).fill( 'INLINE-SKU-EDITED' );
		await editor.locator( 'input[name="value"]' ).press( 'Enter' );

		const saved = products.inlineCell( products.row( name ), 'sku' );
		await expect( saved ).toContainText( 'INLINE-SKU-EDITED' );
		await expect( saved ).toHaveAttribute( 'data-inline-value', 'INLINE-SKU-EDITED' );
	} );

	test( 'stock quantity cell saves and re-renders the stock badge', async ( { page } ) => {
		const name = await seedProduct( 'STOCK' );
		const products = new ProductsPage( page );
		await products.goto();

		const editor = await products.openInlineEditor( products.row( name ), 'stock_quantity' );
		await editor.locator( 'input[name="value"]' ).fill( '42' );
		await editor.locator( 'input[name="value"]' ).press( 'Enter' );

		// The row is replaced by the server-rendered partial, so the badge
		// text is proof the whole round trip ran, not just the input value.
		await expect( products.row( name ) ).toContainText( 'In stock(42)' );
	} );

	test( 'status cell offers only the editable statuses and saves a draft', async ( { page } ) => {
		const name = await seedProduct( 'STATUS' );
		const products = new ProductsPage( page );
		await products.goto();

		const editor = await products.openInlineEditor( products.row( name ), 'status' );
		const select = editor.locator( 'select[name="value"]' );

		// Only the whitelisted statuses are offered — no 'trash' or 'private'.
		const options = await select.locator( 'option' ).evaluateAll( ( nodes ) =>
			nodes.map( ( node ) => ( node as HTMLOptionElement ).value ).sort()
		);
		expect( options ).toEqual( [ 'draft', 'pending', 'publish' ] );

		await select.selectOption( 'draft' );
		await select.press( 'Enter' );

		await expect( products.row( name ).locator( '.storesuite-badge' ).first() ).toContainText(
			'Draft',
			{ ignoreCase: true }
		);
	} );

	test( 'a sale price at or above the regular price is rejected', async ( { page } ) => {
		const name = await seedProduct( 'SALE' );
		const products = new ProductsPage( page );
		await products.goto();

		const editor = await products.openInlineEditor( products.row( name ), 'price' );
		await editor.locator( 'input[name="regular_price"]' ).fill( '30' );
		await editor.locator( 'input[name="sale_price"]' ).fill( '30' );
		await editor.locator( 'input[name="sale_price"]' ).press( 'Enter' );

		const dialog = page.locator( '.swal2-popup' );
		await expect( dialog ).toBeVisible();
		await expect( dialog ).toContainText( /sale price/i );
		await dialog.locator( '.swal2-confirm' ).click();

		// Nothing was persisted: the cell still shows the original price and
		// the product carries no sale price.
		await expect( products.inlineCell( products.row( name ), 'price' ) ).toContainText( '30' );
		const persisted = await getProductViaApi( seeded[ seeded.length - 1 ] );
		expect( persisted.sale_price ).toBe( '' );
	} );

	test( 'a duplicate SKU is rejected and the original is kept', async ( { page } ) => {
		const name = await seedProduct( 'DUPE' );
		const productId = seeded[ seeded.length - 1 ];
		const products = new ProductsPage( page );
		await products.goto();

		const editor = await products.openInlineEditor( products.row( name ), 'sku' );
		// Collides with a product seeded by the provisioning script.
		await editor.locator( 'input[name="value"]' ).fill( seed.products.hoodie.sku );
		await editor.locator( 'input[name="value"]' ).press( 'Enter' );

		const dialog = page.locator( '.swal2-popup' );
		await expect( dialog ).toBeVisible();
		await expect( dialog ).toContainText( /sku/i );
		await dialog.locator( '.swal2-confirm' ).click();

		const persisted = await getProductViaApi( productId );
		expect( persisted.sku ).toBe( 'INLINE-DUPE' );
	} );

	test( 'Escape and click-away both cancel without saving', async ( { page } ) => {
		const name = await seedProduct( 'CANCEL' );
		const productId = seeded[ seeded.length - 1 ];
		const products = new ProductsPage( page );
		await products.goto();

		// Escape restores the cell.
		let editor = await products.openInlineEditor( products.row( name ), 'sku' );
		await editor.locator( 'input[name="value"]' ).fill( 'NEVER-SAVED-ESC' );
		await editor.locator( 'input[name="value"]' ).press( 'Escape' );
		await expect( editor ).toHaveCount( 0 );
		await expect( products.inlineCell( products.row( name ), 'sku' ) ).toContainText(
			'INLINE-CANCEL'
		);

		// Clicking outside the editor restores it too.
		editor = await products.openInlineEditor( products.row( name ), 'sku' );
		await editor.locator( 'input[name="value"]' ).fill( 'NEVER-SAVED-BLUR' );
		// Click a neutral spot inside the list (the admin bar covers the top
		// of the page, so the heading is not a reliable click target).
		await products.table.locator( 'thead th' ).first().click();
		await expect( editor ).toHaveCount( 0 );
		await expect( products.inlineCell( products.row( name ), 'sku' ) ).toContainText(
			'INLINE-CANCEL'
		);

		const persisted = await getProductViaApi( productId );
		expect( persisted.sku ).toBe( 'INLINE-CANCEL' );
	} );

	test( 'a product not managing stock has no editable stock cell', async ( { page } ) => {
		const products = new ProductsPage( page );
		await products.goto();

		// Green Scarf is seeded without stock management.
		const row = products.row( seed.products.scarf.name );
		await expect( row ).toHaveCount( 1 );
		await expect( products.inlineCell( row, 'stock_quantity' ) ).toHaveCount( 0 );
		// Its other cells are still editable.
		await expect( products.inlineCell( row, 'price' ) ).toHaveCount( 1 );
	} );
} );
