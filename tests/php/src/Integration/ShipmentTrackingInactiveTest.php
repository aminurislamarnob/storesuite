<?php
/**
 * Tests that the shipment tracking integration stays out of the way without
 * Advanced Shipment Tracking.
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test\Integration;

use PluginizeLab\StoreSuite\Integration\ShipmentTrackingIntegration;
use PluginizeLab\StoreSuite\Test\StoreSuiteTestCase;

/**
 * Runs only when AST is not loaded (no AST_DIR), the opposite of ShipmentTrackingTest.
 *
 * @covers \PluginizeLab\StoreSuite\Integration\ShipmentTrackingIntegration
 * @group storesuite-order
 * @group storesuite-shipment-tracking
 */
class ShipmentTrackingInactiveTest extends StoreSuiteTestCase {

	public function test_nothing_is_registered_without_ast() {
		if ( ShipmentTrackingIntegration::is_ast_active() ) {
			$this->markTestSkipped( 'Advanced Shipment Tracking is loaded.' );
		}

		$this->assertFalse( has_action( 'wp_ajax_' . ShipmentTrackingIntegration::ADD_ACTION ) );
		$this->assertFalse( has_action( 'wp_ajax_' . ShipmentTrackingIntegration::DELETE_ACTION ) );
		$this->assertSame( array(), apply_filters( 'storesuite_order_list_columns', array() ) );
		$this->assertSame( '', storesuite_get_order_status_class( 'delivered' ) );

		$order = self::factory()->order->create();

		ob_start();
		do_action( 'storesuite_order_list_row_actions', $order );
		$this->assertStringNotContainsString( 'storesuite-add-tracking', ob_get_clean() );
	}
}
