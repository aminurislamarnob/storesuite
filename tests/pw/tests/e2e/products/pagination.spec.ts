import { test, expect } from '../../../utils/test';
import { MANAGER_STATE } from '../../../utils/authStates';
import { getSettingsViaApi, updateSettingsViaApi } from '../../../utils/apiUtils';
import { dashboardPath, seed } from '../../../utils/testData';

test.use( { storageState: MANAGER_STATE } );

/**
 * Filters, search and sort must survive pagination (issue #189).
 *
 * Products per page is squeezed down so the seeded catalogue paginates,
 * then restored — the setting is shared site state.
 */
test.describe( 'list pagination preserves list state', () => {
	let previousPerPage = '';

	test.beforeAll( async () => {
		previousPerPage = ( await getSettingsViaApi() ).storesuite_product_per_page ?? '';
		await updateSettingsViaApi( { storesuite_product_per_page: '2' } );
	} );

	test.afterAll( async () => {
		await updateSettingsViaApi( { storesuite_product_per_page: previousPerPage } );
	} );

	test( 'page links carry the active filter, search and sort', async ( { page } ) => {
		await page.goto(
			`${ dashboardPath }/products/?price_min=1&orderby=price&order=asc`
		);

		const links = page.locator( '.storesuite-pagination a[href]' );
		await expect( links.first(), 'the list must paginate' ).toBeVisible();

		const href = await links.first().getAttribute( 'href' );
		const url = new URL( href!, page.url() );

		expect( url.searchParams.get( 'price_min' ) ).toBe( '1' );
		expect( url.searchParams.get( 'orderby' ) ).toBe( 'price' );
		expect( url.searchParams.get( 'order' ) ).toBe( 'asc' );

		// Regression: an esc_url()'d pagination base turns the '&#038;' entity
		// into a URL fragment, truncating the query string and leaving a junk
		// '#038;...' tail on every page link.
		expect( url.hash, 'page links must carry no junk fragment' ).toBe( '' );
	} );

	test( 'the filtered result set still applies on page 2', async ( { page } ) => {
		// price_min=12 keeps four of the five seeded products, so the list
		// spans two pages at two rows per page.
		await page.goto( `${ dashboardPath }/products/page/2/?price_min=12` );

		const rows = page.locator( 'tbody tr.single-product-item' );
		await expect( rows.first() ).toBeVisible();

		// Every row on page 2 must still respect the filter...
		const prices = await rows
			.locator( 'td.storesuite-inline-cell[data-inline-field="price"]' )
			.allInnerTexts();
		expect( prices.length ).toBeGreaterThan( 0 );
		for ( const price of prices ) {
			const value = Number( price.replace( /[^\d.]/g, '' ) );
			expect( value, `page-2 row price ${ price } must be >= 12` ).toBeGreaterThanOrEqual( 12 );
		}

		// ...and the product priced below the filter never appears.
		await expect(
			rows.filter( { hasText: seed.products.stickers.name } ),
			'a product below the price filter must not survive to page 2'
		).toHaveCount( 0 );
	} );
} );
