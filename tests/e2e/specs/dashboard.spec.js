/**
 * Dashboard shell: sidebar navigation renders, submenus expand, links route
 * to the right endpoints, and the active state follows the current page.
 */
const { test, expect } = require( '@playwright/test' );
const { STORAGE_STATE, dashboardUrl } = require( '../helpers' );

test.use( { storageState: STORAGE_STATE.manager } );

test( 'sidebar lists the core store areas', async ( { page } ) => {
	await page.goto( dashboardUrl() );

	const menu = page.locator( '.storesuite-dashboard-menu' );
	await expect( menu ).toBeVisible();

	// Top-level items are visible immediately.
	for ( const item of [ 'Dashboard', 'Products', 'Orders', 'Coupons', 'Analytics', 'Account' ] ) {
		await expect(
			menu.getByRole( 'link', { name: item, exact: true } ),
			`Sidebar must offer ${ item }`
		).toBeVisible();
	}

	// Taxonomy screens live in the Products submenu — present in the DOM but
	// hidden until the parent expands, so they are outside the accessibility
	// tree and must be matched as plain elements, not by role.
	for ( const item of [ 'Categories', 'Brands', 'Tags' ] ) {
		await expect(
			menu.locator( '.submenu a' ).filter( { hasText: item } ).first(),
			`Products submenu must offer ${ item }`
		).toBeAttached();
	}
} );

test( 'products submenu expands and navigates to the product list', async ( { page } ) => {
	await page.goto( dashboardUrl() );

	const menu = page.locator( '.storesuite-dashboard-menu' );

	// Clicking the parent toggles the submenu open instead of navigating.
	await menu.getByRole( 'link', { name: 'Products', exact: true } ).click();

	const allProducts = menu.getByRole( 'link', { name: 'All Products', exact: true } );
	await expect( allProducts ).toBeVisible();
	await allProducts.click();

	await expect( page ).toHaveURL( /\/storesuite-dashboard\/products\/?/ );
	await expect( page.locator( '#storesuite-dashboard-sidebar' ) ).toBeVisible();

	// The submenu entry for the current endpoint is highlighted.
	await expect(
		page.locator( '.storesuite-dashboard-menu .submenu a.active' ).first()
	).toContainText( 'All Products' );
} );

test( 'active module (Inventory Manager) appears in the navigation', async ( { page } ) => {
	await page.goto( dashboardUrl() );

	await expect(
		page.locator( '.storesuite-dashboard-menu' ).getByText( /inventory/i ).first()
	).toBeAttached();
} );
