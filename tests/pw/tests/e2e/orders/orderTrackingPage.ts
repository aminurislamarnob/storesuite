import type { Locator, Page } from '@playwright/test';
import { dashboardPath } from '../../../utils/testData';

/**
 * Page object for shipment tracking on the order details page and the
 * orders list (Advanced Shipment Tracking integration).
 */
export class OrderTrackingPage {
	readonly page: Page;
	readonly card: Locator;
	readonly items: Locator;
	readonly modal: Locator;

	constructor( page: Page ) {
		this.page = page;
		this.card = page.locator( '.storesuite-order-tracking' );
		this.items = this.card.locator( '.storesuite-tracking-item:not(.storesuite-tracking-empty)' );
		this.modal = page.locator( '#storesuite-order-tracking-modal' );
	}

	async gotoOrder( orderId: number ): Promise< void > {
		await this.page.goto( `${ dashboardPath }/order-details/${ orderId }/` );
	}

	async gotoEditOrder( orderId: number ): Promise< void > {
		await this.page.goto( `${ dashboardPath }/edit-order/${ orderId }/` );
	}

	async gotoOrders(): Promise< void > {
		await this.page.goto( `${ dashboardPath }/orders/` );
	}

	/** The orders list row of an order. */
	row( orderId: number ): Locator {
		return this.page
			.locator( '.my-storesuite-tbl tbody tr.storesuite-list-row' )
			.filter( { hasText: `#${ orderId }` } );
	}

	/** The tracking cell of an order's row in the orders list. */
	cell( orderId: number ): Locator {
		return this.page.locator( `.storesuite-tracking-cell[data-order-id="${ orderId }"]` );
	}

	async openModalFromCard(): Promise< void > {
		await this.card.locator( '.storesuite-add-tracking' ).click();
		await this.modal.waitFor( { state: 'visible' } );
	}

	async openModalFromRow( orderId: number ): Promise< void > {
		const row = this.row( orderId );
		await row.locator( '.storesuite-dropdown-icon' ).click();
		await row.locator( '.storesuite-add-tracking' ).click();
		await this.modal.waitFor( { state: 'visible' } );
	}

	async fill( trackingNumber: string, carrier: string, markAsShipped: boolean ): Promise< void > {
		await this.modal.locator( '#storesuite-tracking-number' ).fill( trackingNumber );
		await this.modal.locator( '#storesuite-tracking-provider' ).selectOption( carrier );
		const markShipped = this.modal.locator( '#storesuite-tracking-mark-shipped' );
		if ( ( await markShipped.isChecked() ) !== markAsShipped ) {
			// The visual switch is the label; the checkbox itself is hidden.
			await this.modal.locator( 'label[for="storesuite-tracking-mark-shipped"]' ).click();
		}
	}

	async submit(): Promise< void > {
		await this.modal.locator( '.storesuite-order-tracking-submit' ).click();
	}

	/** Delete the first shipment in the card and confirm the dialog. */
	async deleteFirst(): Promise< void > {
		await this.items.first().locator( '.storesuite-delete-tracking' ).click();
		await this.page.locator( '.swal2-popup .swal2-confirm' ).click();
	}
}
