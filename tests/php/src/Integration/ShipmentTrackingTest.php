<?php
/**
 * Tests for the Advanced Shipment Tracking order endpoints.
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test\Integration;

use PluginizeLab\StoreSuite\Integration\ShipmentTrackingIntegration;
use PluginizeLab\StoreSuite\Test\StoreSuiteAjaxTestCase;

/**
 * @covers \PluginizeLab\StoreSuite\Integration\ShipmentTrackingIntegration
 * @group storesuite-order
 * @group storesuite-ajax
 * @group storesuite-shipment-tracking
 */
class ShipmentTrackingTest extends StoreSuiteAjaxTestCase {

	use ShipmentTrackingTestHelpers;

	/**
	 * Set up the test fixture.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->require_ast();
		$this->insert_carrier( 'usps', 'USPS' );
		$this->insert_carrier( 'ups', 'UPS', false );
	}

	/**
	 * Valid add-tracking fields for an order.
	 *
	 * @param \WC_Order $order     The order.
	 * @param array     $overrides Fields to override.
	 * @return array
	 */
	private function tracking_fields( $order, array $overrides = array() ) {
		return array_merge(
			array(
				'order_id'          => $order->get_id(),
				'tracking_number'   => '9400100000000000000000',
				'tracking_provider' => 'usps',
				'date_shipped'      => '2026-09-30',
				'mark_order_as'     => '',
			),
			$overrides
		);
	}

	// -------------------------------------------------------------------------
	// Adding.
	// -------------------------------------------------------------------------

	public function test_shop_manager_adds_tracking() {
		$this->_setRole( 'shop_manager' );
		$order = self::factory()->order->create();

		$response = $this->add_tracking_ajax( $this->tracking_fields( $order ) );

		$this->assertTrue( $response['success'] );
		$this->assertFalse( $response['data']['status_changed'] );
		$this->assertStringContainsString( '9400100000000000000000', $response['data']['items_html'] );
		$this->assertStringContainsString( '9400100000000000000000', $response['data']['cell_html'] );

		$items = ast_get_tracking_items( $order->get_id() );
		$this->assertCount( 1, $items );
		$this->assertSame( '9400100000000000000000', $items[0]['tracking_number'] );
		$this->assertSame( 'usps', $items[0]['tracking_provider'] );
		$this->assertSame( 'USPS', $items[0]['formatted_tracking_provider'] );
		$this->assertSame( '2026-09-30', gmdate( 'Y-m-d', (int) $items[0]['date_shipped'] ) );
		$this->assertSame( 'processing', wc_get_order( $order->get_id() )->get_status() );
	}

	public function test_administrator_adds_tracking() {
		$this->_setRole( 'administrator' );
		$order = self::factory()->order->create();

		$response = $this->add_tracking_ajax( $this->tracking_fields( $order ) );

		$this->assertTrue( $response['success'] );
		$this->assertCount( 1, ast_get_tracking_items( $order->get_id() ) );
	}

	public function test_second_tracking_number_is_kept_alongside_the_first() {
		$this->_setRole( 'shop_manager' );
		$order = self::factory()->order->create();

		$this->add_tracking_ajax( $this->tracking_fields( $order, array( 'tracking_number' => 'FIRST-1' ) ) );
		$this->add_tracking_ajax( $this->tracking_fields( $order, array( 'tracking_number' => 'SECOND-2' ) ) );

		$this->assertSame(
			array( 'FIRST-1', 'SECOND-2' ),
			wp_list_pluck( ast_get_tracking_items( $order->get_id() ), 'tracking_number' )
		);
	}

	public function test_mark_as_shipped_completes_the_order() {
		$this->_setRole( 'shop_manager' );
		$order = self::factory()->order->create();

		$response = $this->add_tracking_ajax( $this->tracking_fields( $order, array( 'mark_order_as' => 'shipped' ) ) );

		$this->assertTrue( $response['success'] );
		$this->assertTrue( $response['data']['status_changed'] );
		$this->assertSame( 'completed', wc_get_order( $order->get_id() )->get_status() );
	}

	public function test_mark_as_partially_shipped_when_the_status_is_enabled() {
		$this->_setRole( 'shop_manager' );
		$this->set_ast_setting( 'wc_ast_status_partial_shipped', 1 );
		// AST registers the status at boot, before this test switched it on.
		add_filter(
			'wc_order_statuses',
			static function ( $statuses ) {
				$statuses['wc-partial-shipped'] = 'Partially Shipped';
				return $statuses;
			}
		);
		$order = self::factory()->order->create();

		$response = $this->add_tracking_ajax( $this->tracking_fields( $order, array( 'mark_order_as' => 'partial_shipped' ) ) );

		$this->assertTrue( $response['success'] );
		$this->assertTrue( $response['data']['status_changed'] );
		$this->assertSame( 'partial-shipped', wc_get_order( $order->get_id() )->get_status() );
	}

	public function test_empty_date_defaults_to_today() {
		$this->_setRole( 'shop_manager' );
		$order = self::factory()->order->create();

		$response = $this->add_tracking_ajax( $this->tracking_fields( $order, array( 'date_shipped' => '' ) ) );

		$this->assertTrue( $response['success'] );
		$this->assertCount( 1, ast_get_tracking_items( $order->get_id() ) );
	}

	// -------------------------------------------------------------------------
	// Rejections: nothing may be written.
	// -------------------------------------------------------------------------

	public function test_customer_cannot_add_tracking() {
		$this->_setRole( 'customer' );
		$order = self::factory()->order->create();

		$response = $this->add_tracking_ajax( $this->tracking_fields( $order ) );

		$this->assertFalse( $response['success'] );
		$this->assertCount( 0, ast_get_tracking_items( $order->get_id() ) );
	}

	public function test_bad_nonce_is_rejected() {
		$this->_setRole( 'shop_manager' );
		$order = self::factory()->order->create();

		$response = $this->add_tracking_ajax( $this->tracking_fields( $order, array( 'security' => 'not-a-nonce' ) ) );

		$this->assertFalse( $response['success'] );
		$this->assertCount( 0, ast_get_tracking_items( $order->get_id() ) );
	}

	/**
	 * @dataProvider invalid_fields_provider
	 *
	 * @param array $overrides Fields that make the request invalid.
	 */
	public function test_invalid_input_is_rejected( array $overrides ) {
		$this->_setRole( 'shop_manager' );
		$order = self::factory()->order->create();

		$response = $this->add_tracking_ajax( $this->tracking_fields( $order, $overrides ) );

		$this->assertFalse( $response['success'] );
		$this->assertNotEmpty( $response['data']['error'] );
		$this->assertCount( 0, ast_get_tracking_items( $order->get_id() ) );
		$this->assertSame( 'processing', wc_get_order( $order->get_id() )->get_status() );
	}

	/**
	 * @return array[]
	 */
	public function invalid_fields_provider() {
		return array(
			'empty tracking number'      => array( array( 'tracking_number' => '   ' ) ),
			'missing carrier'            => array( array( 'tracking_provider' => '' ) ),
			'carrier that is disabled'   => array( array( 'tracking_provider' => 'ups' ) ),
			'carrier that is unknown'    => array( array( 'tracking_provider' => 'no-such-carrier' ) ),
			'date that is not a date'    => array( array( 'date_shipped' => 'yesterday-ish' ) ),
			'date that does not exist'   => array( array( 'date_shipped' => '2026-02-31' ) ),
			'partial shipped when off'   => array( array( 'mark_order_as' => 'partial_shipped' ) ),
			'status that is not offered' => array( array( 'mark_order_as' => 'refunded' ) ),
		);
	}

	public function test_unknown_order_is_rejected() {
		$this->_setRole( 'shop_manager' );

		$response = $this->add_tracking_ajax(
			array(
				'order_id'          => 999999,
				'tracking_number'   => 'X1',
				'tracking_provider' => 'usps',
			)
		);

		$this->assertFalse( $response['success'] );
	}

	// -------------------------------------------------------------------------
	// Deleting.
	// -------------------------------------------------------------------------

	public function test_shop_manager_deletes_tracking() {
		$this->_setRole( 'shop_manager' );
		$order = self::factory()->order->create();

		$this->add_tracking_ajax( $this->tracking_fields( $order, array( 'tracking_number' => 'KEEP-1' ) ) );
		$this->add_tracking_ajax( $this->tracking_fields( $order, array( 'tracking_number' => 'DROP-2' ) ) );
		$items = ast_get_tracking_items( $order->get_id() );

		$response = $this->delete_tracking_ajax(
			array(
				'order_id'    => $order->get_id(),
				'tracking_id' => $items[1]['tracking_id'],
			)
		);

		$this->assertTrue( $response['success'] );
		$this->assertStringNotContainsString( 'DROP-2', $response['data']['items_html'] );
		$this->assertSame(
			array( 'KEEP-1' ),
			wp_list_pluck( ast_get_tracking_items( $order->get_id() ), 'tracking_number' )
		);
	}

	public function test_customer_cannot_delete_tracking() {
		$this->_setRole( 'shop_manager' );
		$order = self::factory()->order->create();
		$this->add_tracking_ajax( $this->tracking_fields( $order ) );
		$items = ast_get_tracking_items( $order->get_id() );

		$this->_setRole( 'customer' );
		$response = $this->delete_tracking_ajax(
			array(
				'order_id'    => $order->get_id(),
				'tracking_id' => $items[0]['tracking_id'],
			)
		);

		$this->assertFalse( $response['success'] );
		$this->assertCount( 1, ast_get_tracking_items( $order->get_id() ) );
	}

	public function test_deleting_an_unknown_entry_is_rejected() {
		$this->_setRole( 'shop_manager' );
		$order = self::factory()->order->create();
		$this->add_tracking_ajax( $this->tracking_fields( $order ) );

		$response = $this->delete_tracking_ajax(
			array(
				'order_id'    => $order->get_id(),
				'tracking_id' => 'not-a-tracking-id',
			)
		);

		$this->assertFalse( $response['success'] );
		$this->assertCount( 1, ast_get_tracking_items( $order->get_id() ) );
	}

	// -------------------------------------------------------------------------
	// Activation.
	// -------------------------------------------------------------------------

	public function test_integration_is_off_when_ast_pro_is_active() {
		$this->assertTrue( ShipmentTrackingIntegration::is_ast_active() );

		add_filter(
			'option_active_plugins',
			static function ( $plugins ) {
				$plugins[] = 'ast-pro/ast-pro.php';
				return $plugins;
			}
		);

		$this->assertFalse( ShipmentTrackingIntegration::is_ast_active() );
	}

	// -------------------------------------------------------------------------
	// Order status badges.
	// -------------------------------------------------------------------------

	public function test_ast_order_statuses_have_badge_styles() {
		$this->assertSame( 'info', storesuite_get_order_status_class( 'partial-shipped' ) );
		$this->assertSame( 'info', storesuite_get_order_status_class( 'updated-tracking' ) );
		$this->assertSame( 'success', storesuite_get_order_status_class( 'delivered' ) );
	}
}
