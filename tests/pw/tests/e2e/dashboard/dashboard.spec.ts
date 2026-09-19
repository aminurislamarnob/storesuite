import { test, expect } from '../../../utils/test';
import { MANAGER_STATE } from '../../../utils/authStates';
import { dashboardPath } from '../../../utils/testData';

test.use( { storageState: MANAGER_STATE } );

test.describe( 'dashboard home', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( `${ dashboardPath }/` );
	} );

	test( 'renders the dashboard shell', async ( { page } ) => {
		await expect( page.locator( '.storesuite-dashboard-header' ) ).toBeVisible();
		await expect( page.locator( '.my-storesuite-sidebar' ) ).toBeVisible();

		// The home content is the analytics app mount; it needs the built
		// JS bundle to fill in, so only assert it is attached here.
		await expect( page.locator( '#storesuite-dashboard-app' ) ).toBeAttached();
	} );

	test( 'sidebar links to every store module', async ( { page } ) => {
		const menu = page.locator( '.storesuite-dashboard-menu' );

		for ( const item of [ 'Products', 'Orders', 'Coupons' ] ) {
			await expect(
				menu.getByRole( 'link', { name: item, exact: false } ).first(),
				`Sidebar must link to ${ item }`
			).toBeVisible();
		}

		// Inventory lives in the collapsed Products submenu, which role
		// locators cannot see (display:none drops it from the a11y tree),
		// so target it by href and expand the parent to reveal it.
		const inventory = menu.locator( 'a[href*="/inventory"]' ).first();
		await expect( inventory ).toBeAttached();
		await menu.getByRole( 'link', { name: 'Products', exact: false } ).first().click();
		await expect( inventory ).toBeVisible();
	} );
} );
