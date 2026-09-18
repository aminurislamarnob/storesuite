/**
 * Orders: list rendering with the seeded order, filters present, and the
 * order-details screen.
 */
const { test, expect } = require( '@playwright/test' );
const { STORAGE_STATE, dashboardUrl, uniq } = require( '../helpers' );

test.use( { storageState: STORAGE_STATE.manager } );

test( 'orders list shows the seeded order with status and customer', async ( { page } ) => {
	await page.goto( dashboardUrl( 'orders' ) );

	await expect( page.locator( '#storesuite-dashboard-sidebar' ) ).toBeVisible();
	await expect( page.locator( 'tr#order-row-not-found' ) ).toHaveCount( 0 );

	const row = page.locator( 'tr', { hasText: 'E2E Customer' } ).first();
	await expect( row ).toBeVisible();
	await expect( row ).toContainText( /processing/i );
} );

test( 'order details opens from the list', async ( { page } ) => {
	await page.goto( dashboardUrl( 'orders' ) );

	const row = page.locator( 'tr', { hasText: 'E2E Customer' } ).first();
	const link = row.locator( 'a.order-view' ).first();
	const orderNumber = ( await link.innerText() ).trim();

	await link.click();

	await expect( page ).toHaveURL( /order-details/ );
	await expect( page.locator( '.my-storesuite-page-content' ) ).toContainText(
		orderNumber.replace( '#', '' )
	);
	await expect( page.locator( '.my-storesuite-page-content' ) ).toContainText(
		'E2E Seed Product'
	);
} );

test( 'an order note can be added and deleted on the details screen', async ( { page } ) => {
	const noteText = `E2E note ${ uniq() }`;

	await page.goto( dashboardUrl( 'orders' ) );
	await page
		.locator( 'tr', { hasText: 'E2E Customer' } )
		.first()
		.locator( 'a.order-view' )
		.first()
		.click();
	await expect( page ).toHaveURL( /order-details/ );

	// --- Add (prepends the note to ul.order_notes via admin-ajax) ---
	await page.fill( '#add_order_note', noteText );
	await page.locator( 'button.add-note' ).click();

	const note = page.locator( 'ul.order_notes li', { hasText: noteText } );
	await expect( note ).toBeVisible( { timeout: 10000 } );

	// --- Delete (native confirm dialog, then the row is removed) ---
	page.on( 'dialog', ( dialog ) => dialog.accept() );
	await note.locator( 'a.delete_note' ).dispatchEvent( 'click' );

	await expect( note ).toHaveCount( 0, { timeout: 10000 } );
} );
