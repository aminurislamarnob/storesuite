import { test, expect } from '../../../utils/test';
import { MANAGER_STATE } from '../../../utils/authStates';
import { createProductViaApi, deleteProductsViaApi } from '../../../utils/apiUtils';
import { ProductsPage } from './productsPage';

test.use( { storageState: MANAGER_STATE } );

/**
 * The advanced filter drawer on the Products list: each filter on its own,
 * combined with search and sorting, the active-filter badge, and reset.
 *
 * The specs drive the off-canvas form the way a user does rather than
 * hand-building query strings, so the drawer markup is covered too.
 */
test.describe( 'products filter drawer', () => {
	const seeded: number[] = [];
	const CHEAP = 'Filter Subject Cheap';
	const MID = 'Filter Subject Mid';
	const PRICEY = 'Filter Subject Pricey';
	const DRAFTED = 'Filter Subject Drafted';
	const VINTAGE = 'Filter Subject Vintage';

	test.beforeAll( async () => {
		seeded.push(
			await createProductViaApi( { name: CHEAP, sku: 'FILT-CHEAP', regular_price: '3' } ),
			await createProductViaApi( { name: MID, sku: 'FILT-MID', regular_price: '150' } ),
			await createProductViaApi( { name: PRICEY, sku: 'FILT-PRICEY', regular_price: '900' } ),
			await createProductViaApi( {
				name: DRAFTED,
				sku: 'FILT-DRAFT',
				regular_price: '160',
				status: 'draft',
			} ),
			await createProductViaApi( {
				name: VINTAGE,
				sku: 'FILT-VINTAGE',
				regular_price: '170',
				date_created: '2020-01-15T10:00:00',
			} )
		);
	} );

	test.afterAll( async () => {
		await deleteProductsViaApi( seeded );
	} );

	test( 'price range filter narrows the list from both ends', async ( { page } ) => {
		const products = new ProductsPage( page );
		await products.goto();

		await products.openFilters();
		await products.filterForm.locator( 'input[name="price_min"]' ).fill( '100' );
		await products.filterForm.locator( 'input[name="price_max"]' ).fill( '500' );
		await products.applyFilters();

		await expect( products.row( MID ) ).toHaveCount( 1 );
		await expect( products.row( CHEAP ) ).toHaveCount( 0 );
		await expect( products.row( PRICEY ) ).toHaveCount( 0 );
	} );

	test( 'a minimum price alone keeps everything above it', async ( { page } ) => {
		const products = new ProductsPage( page );
		await products.goto();

		await products.openFilters();
		await products.filterForm.locator( 'input[name="price_min"]' ).fill( '800' );
		await products.applyFilters();

		await expect( products.row( PRICEY ) ).toHaveCount( 1 );
		await expect( products.row( MID ) ).toHaveCount( 0 );
		await expect( products.row( CHEAP ) ).toHaveCount( 0 );
	} );

	test( 'status filter lists drafts only', async ( { page } ) => {
		const products = new ProductsPage( page );
		await products.goto();

		await products.openFilters();
		await products.filterForm.locator( 'select[name="post_status"]' ).selectOption( 'draft' );
		await products.applyFilters();

		await expect( products.row( DRAFTED ) ).toHaveCount( 1 );
		await expect( products.row( MID ) ).toHaveCount( 0 );
	} );

	test( 'created-date range excludes products outside it', async ( { page } ) => {
		const products = new ProductsPage( page );
		await products.goto();

		await products.openFilters();
		await products.filterForm.locator( 'input[name="date_from"]' ).fill( '2020-01-01' );
		await products.filterForm.locator( 'input[name="date_to"]' ).fill( '2020-12-31' );
		await products.applyFilters();

		// Only the back-dated product falls inside the window.
		await expect( products.row( VINTAGE ) ).toHaveCount( 1 );
		await expect( products.row( MID ) ).toHaveCount( 0 );
	} );

	test( 'the toggle badge counts every active filter', async ( { page } ) => {
		const products = new ProductsPage( page );
		await products.goto();

		// No filters yet — no badge.
		await expect( products.filterCount ).toHaveCount( 0 );

		await products.openFilters();
		await products.filterForm.locator( 'input[name="price_min"]' ).fill( '1' );
		await products.filterForm.locator( 'input[name="price_max"]' ).fill( '1000' );
		await products.filterForm.locator( 'select[name="post_status"]' ).selectOption( 'draft' );
		await products.applyFilters();

		// price_min + price_max + post_status = 3 (search is excluded).
		await expect( products.filterCount ).toHaveText( '3' );
	} );

	test( 'reset clears the filters and the badge', async ( { page } ) => {
		const products = new ProductsPage( page );
		await products.goto( '?price_min=100&price_max=500' );
		await expect( products.filterCount ).toHaveText( '2' );

		await products.openFilters();
		await products.filterDrawer.getByRole( 'link', { name: 'Reset' } ).click();
		await page.waitForLoadState();

		await expect( products.filterCount ).toHaveCount( 0 );
		await expect( products.row( CHEAP ) ).toHaveCount( 1 );
		await expect( products.row( PRICEY ) ).toHaveCount( 1 );
	} );

	test( 'filters survive sorting and stay in the pagination links', async ( { page } ) => {
		const products = new ProductsPage( page );
		await products.goto();

		await products.openFilters();
		await products.filterForm.locator( 'input[name="price_min"]' ).fill( '100' );
		await products.applyFilters();

		// Sorting from a filtered list keeps the filter and resets to page 1.
		await products.table.locator( 'a.storesuite-sort-link', { hasText: 'Price' } ).click();
		await page.waitForLoadState();

		expect( page.url() ).toContain( 'price_min=100' );
		expect( page.url() ).toContain( 'orderby=price' );
		await expect( products.filterCount ).toHaveText( '1' );

		// Cheap products stay filtered out after the sort.
		await expect( products.row( CHEAP ) ).toHaveCount( 0 );
		const names = await products.rowNames();
		expect( names ).toContain( MID );

		// Ascending price order puts the cheaper of the two survivors first.
		expect( names.indexOf( MID ) ).toBeLessThan( names.indexOf( PRICEY ) );
	} );
} );
