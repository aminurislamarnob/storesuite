<?php
/**
 * Tests for the order list bulk actions.
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test\Order;

use PluginizeLab\StoreSuite\Test\StoreSuiteTestCase;

/**
 * @covers \PluginizeLab\StoreSuite\Order\OrderController::handle_order_bulk_actions
 * @group storesuite-order
 */
class OrderBulkActionsTest extends StoreSuiteTestCase {

	public function set_up() {
		parent::set_up();

		// The bulk handler redirects back to the orders endpoint URL.
		$this->create_dashboard_page();
	}

	/**
	 * Run the bulk action handler and return the redirect's query args.
	 *
	 * @param string $action      Bulk action name.
	 * @param array  $order_ids   Order IDs to act on.
	 * @param bool   $valid_nonce Whether to send a valid nonce.
	 * @return array Query args of the captured redirect URL.
	 */
	private function run_bulk( $action, $order_ids, $valid_nonce = true ) {
		$_POST = array(
			'storesuite_bulk_action_nonce' => $valid_nonce ? wp_create_nonce( 'storesuite_order_bulk_action' ) : 'bad-nonce',
			'action'                       => $action,
			'bulk_order_ids'               => $order_ids,
		);

		$controller = \pluginizelab_storesuite()->storesuite_order_controller;

		$redirect = $this->capture_redirect(
			function () use ( $controller ) {
				$controller->handle_order_bulk_actions();
			}
		);

		$_POST = array();

		$query = array();
		if ( $redirect ) {
			wp_parse_str( (string) wp_parse_url( $redirect, PHP_URL_QUERY ), $query );
		}

		return $query;
	}

	public function test_bulk_mark_completed_updates_orders() {
		wp_set_current_user( $this->admin_id );
		$order_a = self::factory()->order->create();
		$order_b = self::factory()->order->create();

		// A non-existent ID must be skipped without aborting the batch.
		// (Result counts in the redirect arrive with the Tier 1 branch.)
		$this->run_bulk( 'mark_completed', array( $order_a->get_id(), $order_b->get_id(), 999999 ) );

		$this->assertSame( 'completed', wc_get_order( $order_a->get_id() )->get_status() );
		$this->assertSame( 'completed', wc_get_order( $order_b->get_id() )->get_status() );
	}

	public function test_bulk_trash_soft_deletes_orders() {
		wp_set_current_user( $this->admin_id );
		$order = self::factory()->order->create();

		$this->run_bulk( 'trash', array( $order->get_id() ) );

		$this->assertSame( 'trash', wc_get_order( $order->get_id() )->get_status() );
	}

	public function test_bulk_action_rejected_without_valid_nonce() {
		wp_set_current_user( $this->admin_id );
		$order = self::factory()->order->create( array( 'status' => 'processing' ) );

		$this->run_bulk( 'mark_completed', array( $order->get_id() ), false );

		$this->assertSame( 'processing', wc_get_order( $order->get_id() )->get_status() );
	}

	public function test_bulk_action_requires_manage_woocommerce() {
		wp_set_current_user( $this->admin_id );
		$order = self::factory()->order->create( array( 'status' => 'processing' ) );

		wp_set_current_user( $this->customer_id );
		$this->run_bulk( 'mark_completed', array( $order->get_id() ) );

		$this->assertSame( 'processing', wc_get_order( $order->get_id() )->get_status() );
	}

	public function test_unknown_bulk_action_skips_everything() {
		wp_set_current_user( $this->admin_id );
		$order = self::factory()->order->create( array( 'status' => 'processing' ) );

		$this->run_bulk( 'mark_exploded', array( $order->get_id() ) );

		$this->assertSame( 'processing', wc_get_order( $order->get_id() )->get_status() );
	}
}
