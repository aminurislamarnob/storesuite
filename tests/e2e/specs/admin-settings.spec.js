/**
 * Admin settings React app (wp-admin → WooCommerce → StoreSuite): the SPA
 * mounts, tabs navigate, and the Modules screen lists bundled modules.
 */
const { test, expect } = require( '@playwright/test' );
const {
	STORAGE_STATE,
	getStoreSuiteSetting,
	setStoreSuiteSetting,
} = require( '../helpers' );

const SETTINGS_PAGE = '/wp-admin/admin.php?page=storesuite';

test.use( { storageState: STORAGE_STATE.admin } );

test( 'settings app mounts with content', async ( { page } ) => {
	await page.goto( SETTINGS_PAGE );

	const app = page.locator( '#storesuite-settings' );
	await expect( app ).toBeAttached();
	await expect
		.poll( async () => ( await app.innerHTML() ).length, { timeout: 15000 } )
		.toBeGreaterThan( 100 );
} );

test( 'modules screen lists the Inventory Manager module', async ( { page } ) => {
	await page.goto( `${ SETTINGS_PAGE }#/modules` );

	await expect(
		page.locator( '#storesuite-settings' ).getByText( /inventory manager/i ).first()
	).toBeVisible( { timeout: 15000 } );
} );

test.describe( 'pagination settings save', () => {
	let previous;

	test.beforeAll( () => {
		previous = getStoreSuiteSetting( 'storesuite_product_per_page' );
	} );

	test.afterAll( () => {
		setStoreSuiteSetting( 'storesuite_product_per_page', previous );
	} );

	test( 'a changed value persists through save and reload', async ( { page } ) => {
		await page.goto( `${ SETTINGS_PAGE }#/pagination-settings` );

		const field = page.getByLabel( 'Products per page' );
		await expect( field ).toBeVisible( { timeout: 15000 } );
		await field.fill( '17' );

		await page.getByRole( 'button', { name: 'Save Changes' } ).click();

		// The REST save round-trips before the UI reports success.
		await expect
			.poll( () => getStoreSuiteSetting( 'storesuite_product_per_page' ), { timeout: 10000 } )
			.toBe( '17' );

		await page.reload();
		await expect( page.getByLabel( 'Products per page' ) ).toHaveValue( '17', { timeout: 15000 } );
	} );
} );
