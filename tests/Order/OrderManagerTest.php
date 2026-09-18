<?php
/**
 * OrderManager tests: order listing filters (status, customer, month,
 * channel, pagination), month filter options, order actions, and the
 * echo-style column renderers.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Tests\Order;

use PluginizeLab\StoreSuite\Order\OrderManager;
use WP_UnitTestCase;

/**
 * Tests for PluginizeLab\StoreSuite\Order\OrderManager.
 *
 * Orders are created through wc_create_order() so both HPOS and legacy
 * storage work; assertions go through the same wc_get_orders() pipeline the
 * frontend order list uses.
 */
class OrderManagerTest extends WP_UnitTestCase {

	/**
	 * Manager under test.
	 *
	 * @var OrderManager
	 */
	private $manager;

	/**
	 * Fresh manager per test.
	 */
	public function set_up() {
		parent::set_up();
		$this->manager = new OrderManager();
	}

	/**
	 * Create a persisted order.
	 *
	 * @param array $props Optional: status, customer_id, created_via, date_created (timestamp).
	 * @return \WC_Order
	 */
	private function create_order( array $props = array() ) {
		$order = wc_create_order();

		if ( isset( $props['status'] ) ) {
			$order->set_status( $props['status'] );
		}
		if ( isset( $props['customer_id'] ) ) {
			$order->set_customer_id( $props['customer_id'] );
		}
		if ( isset( $props['created_via'] ) ) {
			$order->set_created_via( $props['created_via'] );
		}
		if ( isset( $props['date_created'] ) ) {
			$order->set_date_created( $props['date_created'] );
		}

		$order->save();

		return $order;
	}

	/*
	|-----------------------------------------------------------------------
	| get_all_orders()
	|-----------------------------------------------------------------------
	*/

	public function test_get_all_orders_returns_paginated_results() {
		for ( $i = 0; $i < 3; $i++ ) {
			$this->create_order();
		}

		$page_one = $this->manager->get_all_orders( 2, 1 );
		$page_two = $this->manager->get_all_orders( 2, 2 );

		$this->assertSame( 3, $page_one->total );
		$this->assertSame( 2, $page_one->max_num_pages );
		$this->assertCount( 2, $page_one->orders );
		$this->assertCount( 1, $page_two->orders );
	}

	public function test_orders_are_filtered_by_status() {
		$completed = $this->create_order( array( 'status' => 'completed' ) );
		$this->create_order( array( 'status' => 'pending' ) );

		$result = $this->manager->get_all_orders( 10, 1, array( 'order_status' => 'completed' ) );

		$this->assertSame( 1, $result->total );
		$this->assertSame( $completed->get_id(), $result->orders[0]->get_id() );
	}

	public function test_orders_are_filtered_by_customer() {
		$customer_id = self::factory()->user->create( array( 'role' => 'customer' ) );
		$mine        = $this->create_order( array( 'customer_id' => $customer_id ) );
		$this->create_order();

		$result = $this->manager->get_all_orders( 10, 1, array( '_customer_user' => (string) $customer_id ) );

		$this->assertSame( 1, $result->total );
		$this->assertSame( $mine->get_id(), $result->orders[0]->get_id() );
	}

	public function test_orders_are_filtered_by_month() {
		$in_january  = $this->create_order( array( 'date_created' => strtotime( '2026-01-15 10:00:00' ) ) );
		$this->create_order( array( 'date_created' => strtotime( '2026-03-15 10:00:00' ) ) );

		$result = $this->manager->get_all_orders( 10, 1, array( 'm' => '202601' ) );

		$this->assertSame( 1, $result->total );
		$this->assertSame( $in_january->get_id(), $result->orders[0]->get_id() );
	}

	public function test_malformed_month_filter_is_ignored() {
		$this->create_order();

		$result = $this->manager->get_all_orders( 10, 1, array( 'm' => 'not-a-month' ) );

		$this->assertSame( 1, $result->total, 'A malformed m param must not filter anything out.' );
	}

	public function test_orders_are_filtered_by_sales_channel() {
		$pos = $this->create_order( array( 'created_via' => 'pos-rest-api' ) );
		$this->create_order( array( 'created_via' => 'checkout' ) );

		$result = $this->manager->get_all_orders( 10, 1, array( 'order_channel' => 'pos-rest-api' ) );

		$this->assertSame( 1, $result->total );
		$this->assertSame( $pos->get_id(), $result->orders[0]->get_id() );
	}

	/*
	|-----------------------------------------------------------------------
	| get_months_filter_options()
	|-----------------------------------------------------------------------
	*/

	public function test_months_filter_defaults_to_the_current_month_when_no_orders_exist() {
		$options = $this->manager->get_months_filter_options();

		$this->assertCount( 1, $options );
		$this->assertSame( gmdate( 'Y' ), $options[0]->year );
	}

	public function test_months_filter_spans_from_oldest_order_to_the_current_month() {
		$this->create_order( array( 'date_created' => strtotime( '-2 months' ) ) );

		$options = $this->manager->get_months_filter_options();

		$this->assertGreaterThanOrEqual( 3, count( $options ), 'Two months ago through now is at least 3 buckets.' );

		$newest = $options[0];
		$oldest = end( $options );
		$this->assertSame( wp_date( 'Y' ), $newest->year, 'Options are newest-first.' );
		$this->assertSame( wp_date( 'Y', strtotime( '-2 months' ) ), $oldest->year );
		$this->assertSame( wp_date( 'n', strtotime( '-2 months' ) ), $oldest->month );
	}

	/*
	|-----------------------------------------------------------------------
	| Order actions & column renderers
	|-----------------------------------------------------------------------
	*/

	public function test_available_order_actions_include_the_defaults_and_are_filterable() {
		$actions = OrderManager::get_available_order_actions_for_order( null );

		$this->assertArrayHasKey( 'send_order_details', $actions );
		$this->assertArrayHasKey( 'regenerate_download_permissions', $actions );

		add_filter(
			'woocommerce_order_actions',
			function ( $actions ) {
				$actions['custom_action'] = 'Custom';
				return $actions;
			}
		);

		$this->assertArrayHasKey( 'custom_action', OrderManager::get_available_order_actions_for_order( null ) );
	}

	public function test_customer_column_prefers_billing_name_over_company() {
		$order = $this->create_order();
		$order->set_billing_first_name( 'Jane' );
		$order->set_billing_last_name( 'Doe' );
		$order->set_billing_company( 'Acme Inc' );
		$order->save();

		ob_start();
		$this->manager->get_order_customer_column_value( $order );
		$this->assertSame( 'Jane Doe', ob_get_clean() );
	}

	public function test_customer_column_falls_back_to_company() {
		$order = $this->create_order();
		$order->set_billing_company( 'Acme Inc' );
		$order->save();

		ob_start();
		$this->manager->get_order_customer_column_value( $order );
		$this->assertSame( 'Acme Inc', ob_get_clean() );
	}

	public function test_billing_address_column_renders_dash_when_empty() {
		$order = $this->create_order();

		ob_start();
		$this->manager->get_billing_address_column_value( $order );
		$this->assertSame( '&ndash;', ob_get_clean() );
	}

	public function test_order_date_column_shows_relative_time_for_recent_orders() {
		$order = $this->create_order( array( 'date_created' => time() - HOUR_IN_SECONDS ) );

		ob_start();
		$this->manager->get_order_date_column_value( $order );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'ago', $output );
		$this->assertStringContainsString( '<time', $output );
	}

	public function test_order_date_column_shows_a_date_for_older_orders() {
		$order = $this->create_order( array( 'date_created' => strtotime( '-10 days' ) ) );

		ob_start();
		$this->manager->get_order_date_column_value( $order );
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'ago', $output );
		$this->assertStringContainsString( wp_date( 'Y', strtotime( '-10 days' ) ), $output );
	}
}
