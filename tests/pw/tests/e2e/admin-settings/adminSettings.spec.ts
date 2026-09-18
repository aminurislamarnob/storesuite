import { test, expect } from '../../../utils/test';
import { ADMIN_STATE } from '../../../utils/authStates';

test.use( { storageState: ADMIN_STATE } );

/**
 * The wp-admin settings React app (hash-routed, mounted on
 * #storesuite-settings under WooCommerce → StoreSuite).
 */
test.describe( 'admin settings app', () => {
	test( 'renders the settings app with its navigation tabs', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=storesuite' );

		const app = page.locator( '#storesuite-settings' );
		await expect( app ).toBeVisible();

		for ( const tab of [ 'General', 'Appearance', 'Pagination', 'AI' ] ) {
			await expect(
				app.getByRole( 'link', { name: tab, exact: false } ).first(),
				`Settings navigation must include ${ tab }`
			).toBeVisible();
		}
	} );

	test( 'pagination settings save and persist', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=storesuite#/pagination-settings' );

		const productsPerPage = page.getByLabel( 'Products per page' );
		await expect( productsPerPage ).toBeVisible();
		const previous = await productsPerPage.inputValue();

		// The save is an async apiFetch POST with no navigation; wait for the
		// request to finish (and succeed) before reloading, or the reload can
		// abort it and the persistence assertion races the write.
		const saveSettings = async ( value: string ) => {
			await productsPerPage.fill( value );
			const [ response ] = await Promise.all( [
				page.waitForResponse(
					( r ) => r.url().includes( '/storesuite/v1/settings' ) && 'POST' === r.request().method()
				),
				page.getByRole( 'button', { name: 'Save Changes' } ).click(),
			] );
			expect( response.ok(), 'The settings save request must succeed.' ).toBeTruthy();
		};

		await saveSettings( '12' );

		// Reload and confirm the value came back from the REST API.
		await page.reload();
		await expect( page.getByLabel( 'Products per page' ) ).toHaveValue( '12' );

		// Leave the shared site as we found it.
		await saveSettings( previous );
	} );
} );
