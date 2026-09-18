<?php
/**
 * Tests for the order list query layer.
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test\Order;

use PluginizeLab\StoreSuite\Order\OrderManager;
use PluginizeLab\StoreSuite\Test\StoreSuiteTestCase;

/**
 * @covers \PluginizeLab\StoreSuite\Order\OrderManager
 * @group storesuite-order
 */
class OrderQueryTest extends StoreSuiteTestCase {

	/**
	 * Run get_all_orders() and return the matched order IDs.
	 *
	 * @param array $filters Filters array.
	 * @return int[]
	 */
	private function query_ids( $filters = array() ) {
		$result = ( new OrderManager() )->get_all_orders( 10, 1, $filters );

		return array_map(
			function ( $order ) {
				return $order->get_id();
			},
			$result->orders
		);
	}

	public function test_orders_are_paginated() {
		self::factory()->order->create();
		self::factory()->order->create();
		self::factory()->order->create();

		$result = ( new OrderManager() )->get_all_orders( 2, 1, array() );

		$this->assertSame( 3, $result->total );
		$this->assertSame( 2, $result->max_num_pages );
		$this->assertCount( 2, $result->orders );
	}

	public function test_status_filter() {
		$processing = self::factory()->order->create( array( 'status' => 'processing' ) );
		$completed  = self::factory()->order->create( array( 'status' => 'completed' ) );

		$ids = $this->query_ids( array( 'order_status' => 'completed' ) );

		$this->assertContains( $completed->get_id(), $ids );
		$this->assertNotContains( $processing->get_id(), $ids );
	}

	public function test_customer_filter() {
		$mine   = self::factory()->order->create( array( 'customer_id' => $this->customer_id ) );
		$guests = self::factory()->order->create();

		$ids = $this->query_ids( array( '_customer_user' => (string) $this->customer_id ) );

		$this->assertContains( $mine->get_id(), $ids );
		$this->assertNotContains( $guests->get_id(), $ids );
	}

	public function test_sales_channel_filter() {
		$online = self::factory()->order->create( array( 'created_via' => 'checkout' ) );
		$pos    = self::factory()->order->create( array( 'created_via' => 'pos' ) );

		$ids = $this->query_ids( array( 'order_channel' => 'pos' ) );

		$this->assertContains( $pos->get_id(), $ids );
		$this->assertNotContains( $online->get_id(), $ids );
	}
}
