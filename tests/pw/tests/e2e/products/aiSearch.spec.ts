import { test, expect } from '../../../utils/test';
import { MANAGER_STATE } from '../../../utils/authStates';
import { ProductsPage } from './productsPage';

test.use( { storageState: MANAGER_STATE } );

/**
 * AI natural-language search. The box only renders when the site has an AI
 * provider connected through the WordPress AI Client, so the spec skips
 * itself on sites without one. When present, the AJAX answer is mocked so
 * the run never depends on a live model.
 */
test.describe( 'AI product search', () => {
	test( 'a typed request becomes an ordinary filtered list URL', async ( { page } ) => {
		const products = new ProductsPage( page );
		await products.goto();

		const box = page.locator( '#storesuite-ai-search' );
		test.skip( ( await box.count() ) === 0, 'No AI provider connected on this site.' );

		await page.route( '**/admin-ajax.php', async ( route ) => {
			const body = route.request().postData() || '';
			if ( ! body.includes( 'storesuite_ai_product_search' ) ) {
				return route.continue();
			}
			const url = new URL( page.url() );
			url.search = '?stock_status=outofstock&price_max=10';
			await route.fulfill( {
				contentType: 'application/json',
				body: JSON.stringify( {
					success: true,
					data: { filters: { stock_status: 'outofstock', price_max: '10' }, url: url.toString() },
				} ),
			} );
		} );

		await box.locator( '#storesuite-ai-search-query' ).fill( 'out of stock products under $10' );
		await Promise.all( [
			page.waitForURL( /stock_status=outofstock/ ),
			box.locator( '.storesuite-ai-search-submit' ).click(),
		] );

		await expect( page ).toHaveURL( /price_max=10/ );
		await expect( products.filterCount ).toHaveText( '2' );
	} );
} );
