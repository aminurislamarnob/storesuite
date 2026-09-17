import { test, expect } from '../../../utils/test';
import { MANAGER_STATE } from '../../../utils/authStates';
import { seed } from '../../../utils/testData';
import { ProductsPage } from './productsPage';

test.use( { storageState: MANAGER_STATE } );

test.describe( 'products list', () => {
	test( 'shows the seeded products with their SKUs', async ( { page } ) => {
		const products = new ProductsPage( page );
		await products.goto();

		const hoodie = products.row( seed.products.hoodie.name );
		await expect( hoodie ).toHaveCount( 1 );
		await expect( hoodie ).toContainText( seed.products.hoodie.sku );
	} );

	test( 'every row has a bulk checkbox and select-all toggles them', async ( { page } ) => {
		const products = new ProductsPage( page );
		await products.goto();

		const rowCount = await products.rows.count();
		expect( rowCount ).toBeGreaterThan( 0 );

		// Regression: the per-row checkbox partial must render in every row.
		await expect( products.rowCheckboxes ).toHaveCount( rowCount );

		await products.selectAll.check();
		for ( const checkbox of await products.rowCheckboxes.all() ) {
			await expect( checkbox ).toBeChecked();
		}

		// Unchecking one row flips select-all into its indeterminate state.
		await products.rowCheckboxes.first().uncheck();
		await expect( products.selectAll ).toHaveJSProperty( 'indeterminate', true );
	} );

	test( 'search finds a product by SKU', async ( { page } ) => {
		const products = new ProductsPage( page );
		await products.goto();

		await products.searchFor( seed.products.mug.sku );

		await expect( products.row( seed.products.mug.name ) ).toHaveCount( 1 );
		await expect( products.row( seed.products.hoodie.name ) ).toHaveCount( 0 );
	} );

	test( 'sorting by name orders the rows', async ( { page } ) => {
		const products = new ProductsPage( page );
		await products.goto( '?orderby=title&order=asc' );

		// Site-agnostic: whatever products exist, the page must render them A→Z
		// and the seeded mug must sort before the seeded scarf.
		const names = await products.rowNames();
		expect( names.length ).toBeGreaterThan( 1 );
		const sorted = [ ...names ].sort( ( a, b ) => a.localeCompare( b, undefined, { sensitivity: 'base' } ) );
		expect( names ).toEqual( sorted );
		expect( names.indexOf( seed.products.mug.name ) ).toBeLessThan( names.indexOf( seed.products.scarf.name ) );
		await expect(
			products.table.locator( 'a.storesuite-sort-link[aria-sort="ascending"]' )
		).toContainText( 'Name' );
	} );

	test( 'inline price edit saves and re-renders the row', async ( { page } ) => {
		const products = new ProductsPage( page );
		await products.goto();

		const row = products.row( seed.products.hoodie.name );
		const priceCell = products.inlineCell( row, 'price' );

		await priceCell.click();
		const editor = priceCell.locator( '.storesuite-inline-editor' );
		await expect( editor ).toBeVisible();

		await editor.locator( 'input[name="regular_price"]' ).fill( '47' );
		await editor.locator( 'input[name="regular_price"]' ).press( 'Enter' );

		// The AJAX response swaps in a re-rendered row with a saved flash.
		const newRow = products.row( seed.products.hoodie.name );
		await expect( newRow ).toHaveClass( /storesuite-inline-saved-flash/ );
		await expect( products.inlineCell( newRow, 'price' ) ).toContainText( '47' );
	} );
} );
