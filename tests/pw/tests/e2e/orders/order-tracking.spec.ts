import { test, expect } from '../../../utils/test';
import { MANAGER_STATE } from '../../../utils/authStates';
import { createOrderViaApi, isPluginActive } from '../../../utils/apiUtils';
import { OrderTrackingPage } from './orderTrackingPage';

test.use( { storageState: MANAGER_STATE } );

/** The enabled carrier seeded by bin/e2e-provision.sh. */
const CARRIER = 'e2e-carrier';

test.describe( 'shipment tracking on orders', () => {
	test.beforeAll( async () => {
		test.skip(
			! ( await isPluginActive( 'woo-advanced-shipment-tracking' ) ),
			'Advanced Shipment Tracking is not active on this site.'
		);
	} );

	test( 'tracking is added and deleted from the order details page', async ( { page } ) => {
		const orderId = await createOrderViaApi( 'processing' );
		const trackingNumber = `E2E-${ Date.now() }`;
		const tracking = new OrderTrackingPage( page );

		await tracking.gotoOrder( orderId );
		await expect( tracking.card ).toBeVisible();
		await expect( tracking.card ).toContainText( 'No tracking added yet.' );

		// Required fields are checked before anything is sent.
		await tracking.openModalFromCard();
		await tracking.submit();
		await expect( tracking.modal.locator( '.storesuite-order-tracking-error' ) ).toBeVisible();

		await tracking.fill( trackingNumber, CARRIER, false );
		await tracking.submit();

		// Status untouched, so the card updates in place.
		await expect( tracking.modal ).toBeHidden();
		await expect( tracking.items ).toHaveCount( 1 );
		await expect( tracking.items.first() ).toContainText( 'E2E Carrier' );
		await expect( tracking.items.first() ).toContainText( trackingNumber );
		await expect( tracking.items.first().locator( '.storesuite-tracking-link' ) ).toHaveAttribute(
			'href',
			new RegExp( trackingNumber )
		);

		// The orders list shows the same shipment.
		await tracking.gotoOrders();
		await expect( tracking.cell( orderId ) ).toContainText( trackingNumber );

		await tracking.gotoOrder( orderId );
		await tracking.deleteFirst();
		await expect( tracking.items ).toHaveCount( 0 );
		await expect( tracking.card ).toContainText( 'No tracking added yet.' );
	} );

	test( 'the orders list action adds tracking and completes the order', async ( { page } ) => {
		const orderId = await createOrderViaApi( 'processing' );
		const trackingNumber = `E2E-${ Date.now() }`;
		const tracking = new OrderTrackingPage( page );

		await tracking.gotoOrders();
		await tracking.openModalFromRow( orderId );
		await expect( tracking.modal.locator( '#storesuite-order-tracking-title' ) ).toContainText( `#${ orderId }` );

		await tracking.fill( trackingNumber, CARRIER, true );
		await Promise.all( [ page.waitForEvent( 'load' ), tracking.submit() ] );

		// The status changed, so the list reloaded with the new badge.
		await expect( tracking.row( orderId ) ).toContainText( /completed/i );
		await expect( tracking.cell( orderId ) ).toContainText( trackingNumber );
	} );
} );
