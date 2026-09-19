import { test, expect } from '../../../utils/test';
import { MANAGER_STATE } from '../../../utils/authStates';
import { dashboardPath } from '../../../utils/testData';
import { createOrderViaApi } from '../../../utils/apiUtils';

test.use( { storageState: MANAGER_STATE } );

/**
 * Orders seeded by bin/e2e-provision.sh: Alice (processing), Bob
 * (completed), Carol (on-hold, via pos).
 */
test.describe( 'orders list', () => {
	test( 'shows the seeded orders with their customers', async ( { page } ) => {
		await page.goto( `${ dashboardPath }/orders/` );

		const rows = page.locator( '.my-storesuite-tbl tbody tr.storesuite-list-row' );
		expect( await rows.count() ).toBeGreaterThanOrEqual( 3 );

		for ( const name of [ 'Alice', 'Bob', 'Carol' ] ) {
			await expect( rows.filter( { hasText: name } ).first() ).toBeVisible();
		}
	} );

	test( 'status filter narrows the list', async ( { page } ) => {
		await page.goto( `${ dashboardPath }/orders/?order_status=completed` );

		const rows = page.locator( '.my-storesuite-tbl tbody tr.storesuite-list-row' );

		await expect( rows.filter( { hasText: 'Bob' } ).first() ).toBeVisible();
		await expect( rows.filter( { hasText: 'Alice' } ) ).toHaveCount( 0 );
	} );

	test( 'sales channel filter narrows the list', async ( { page } ) => {
		await page.goto( `${ dashboardPath }/orders/?order_channel=pos` );

		const rows = page.locator( '.my-storesuite-tbl tbody tr.storesuite-list-row' );

		await expect( rows.filter( { hasText: 'Carol' } ).first() ).toBeVisible();
		await expect( rows.filter( { hasText: 'Alice' } ) ).toHaveCount( 0 );
	} );

	test( 'bulk action marks an order completed', async ( { page } ) => {
		// Own data: a fresh processing order created through the WC REST API,
		// so this spec stays idempotent across runs.
		const orderId = await createOrderViaApi( 'processing' );

		await page.goto( `${ dashboardPath }/orders/` );

		const row = page
			.locator( '.my-storesuite-tbl tbody tr.storesuite-list-row' )
			.filter( { hasText: `#${ orderId }` } );
		await expect( row ).toHaveCount( 1 );

		await row.locator( 'input.storesuite-bulk-cb' ).check();

		// The action select and Apply button live in the toolbar, associated
		// with the POST form through their form="..." attribute.
		await page.locator( '#bulk-action-selector-top' ).selectOption( 'mark_completed' );
		await page.locator( 'button#doaction' ).click();

		// The handler redirects back with result counts for the notice.
		await expect( page ).toHaveURL( /updated=1/ );
		await expect(
			page
				.locator( '.my-storesuite-tbl tbody tr.storesuite-list-row' )
				.filter( { hasText: `#${ orderId }` } )
		).toContainText( /completed/i );
	} );
} );
