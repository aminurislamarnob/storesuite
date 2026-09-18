<?php
/**
 * Inventory Manager StockLog tests: manual recording, WC stock-hook capture,
 * order reduce/restore attribution (no duplicate rows), querying, and purge.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Tests\InventoryManager;

use PluginizeLab\StoreSuite\Modules\InventoryManager\Installer;
use PluginizeLab\StoreSuite\Modules\InventoryManager\Settings;
use PluginizeLab\StoreSuite\Modules\InventoryManager\StockLog;
use WC_Product_Simple;
use WP_UnitTestCase;

/**
 * Tests for PluginizeLab\StoreSuite\Modules\InventoryManager\StockLog.
 */
class StockLogTest extends WP_UnitTestCase {

	/**
	 * Empty log + default settings per test.
	 */
	public function set_up() {
		parent::set_up();

		delete_option( Settings::OPTION_KEY );

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( 'DELETE FROM ' . Installer::stock_log_table() );
	}

	/**
	 * Create a published, stock-managed product.
	 *
	 * @param int $qty Stock quantity.
	 * @return WC_Product_Simple
	 */
	private function create_product( $qty ) {
		$product = new WC_Product_Simple();
		$product->set_name( 'Logged Product' );
		$product->set_regular_price( '10' );
		$product->set_manage_stock( true );
		$product->set_stock_quantity( $qty );
		$product->save();

		return $product;
	}

	/**
	 * Query log rows for one product.
	 *
	 * @param int $product_id Product ID.
	 * @return array
	 */
	private function rows_for( $product_id ) {
		return StockLog::query( array( 'product_id' => $product_id ) )['items'];
	}

	/**
	 * Empty the log table mid-test (product/order creation also fires the
	 * captured stock hooks; rows land in the same second, so row order among
	 * them is not deterministic — starting from empty keeps assertions exact).
	 */
	private function clear_log() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( 'DELETE FROM ' . Installer::stock_log_table() );
	}

	/*
	|-----------------------------------------------------------------------
	| record()
	|-----------------------------------------------------------------------
	*/

	public function test_record_inserts_a_row_and_returns_its_id() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );

		$row_id = StockLog::record( 42, 10, 7, 'manual', '#123', 'a note' );

		$this->assertGreaterThan( 0, $row_id );

		$rows = $this->rows_for( 42 );
		$this->assertCount( 1, $rows );
		$this->assertSame( 10, (int) $rows[0]['qty_before'] );
		$this->assertSame( 7, (int) $rows[0]['qty_after'] );
		$this->assertSame( 'manual', $rows[0]['change_type'] );
		$this->assertSame( '#123', $rows[0]['reference'] );
		$this->assertSame( 'a note', $rows[0]['note'] );
		$this->assertSame( get_current_user_id(), (int) $rows[0]['user_id'] );
	}

	public function test_record_is_a_noop_when_the_log_is_disabled() {
		update_option( Settings::OPTION_KEY, array( 'enable_stock_log' => false ) );

		$this->assertSame( 0, StockLog::record( 42, 10, 7 ) );
		$this->assertCount( 0, $this->rows_for( 42 ) );
	}

	/*
	|-----------------------------------------------------------------------
	| WooCommerce hook capture
	|-----------------------------------------------------------------------
	*/

	public function test_register_adds_no_capture_hooks_when_the_log_is_disabled() {
		update_option( Settings::OPTION_KEY, array( 'enable_stock_log' => false ) );

		$log = new StockLog();
		$log->register();

		$this->assertFalse( has_action( 'woocommerce_product_set_stock', array( $log, 'on_stock_set' ) ) );
	}

	public function test_wc_stock_changes_are_captured_as_adjustments() {
		( new StockLog() )->register();

		$product = $this->create_product( 10 );
		// Product creation itself fires the stock hook; start clean from here.
		$this->clear_log();

		wc_update_product_stock( $product, 3 );

		$rows = $this->rows_for( $product->get_id() );
		$this->assertCount( 1, $rows );
		$this->assertSame( 'adjustment', $rows[0]['change_type'] );
		$this->assertNull( $rows[0]['qty_before'], 'The generic hook does not know the previous value.' );
		$this->assertSame( 3, (int) $rows[0]['qty_after'] );
	}

	public function test_suppress_skips_the_next_hook_capture_for_a_product() {
		( new StockLog() )->register();

		$product = $this->create_product( 10 );
		$this->clear_log();

		StockLog::suppress( $product->get_id() );
		wc_update_product_stock( $product, 3 );

		$this->assertCount( 0, $this->rows_for( $product->get_id() ), 'A suppressed change must not be hook-logged.' );

		// The flag is one-shot: the next change is captured again.
		wc_update_product_stock( $product, 4 );
		$this->assertCount( 1, $this->rows_for( $product->get_id() ) );
	}

	public function test_order_stock_reduction_writes_one_row_attributed_to_the_order() {
		( new StockLog() )->register();

		$product = $this->create_product( 10 );
		$order   = wc_create_order();
		$order->add_product( wc_get_product( $product->get_id() ), 3 );
		$order->save();
		$this->clear_log();

		wc_reduce_stock_levels( $order );

		// WC fires the generic set-stock hook AND the order-item hook for the
		// same change; the log must merge them into a single attributed row.
		$rows = $this->rows_for( $product->get_id() );
		$this->assertCount( 1, $rows );
		$this->assertSame( 'order_reduce', $rows[0]['change_type'] );
		$this->assertSame( 10, (int) $rows[0]['qty_before'] );
		$this->assertSame( 7, (int) $rows[0]['qty_after'] );
		$this->assertSame( '#' . $order->get_order_number(), $rows[0]['reference'] );
	}

	public function test_order_stock_restore_writes_one_attributed_row() {
		( new StockLog() )->register();

		$product = $this->create_product( 10 );
		$order   = wc_create_order();
		$order->add_product( wc_get_product( $product->get_id() ), 3 );
		$order->save();
		wc_reduce_stock_levels( $order );
		$this->clear_log();

		wc_increase_stock_levels( $order );

		$rows = $this->rows_for( $product->get_id() );
		$this->assertCount( 1, $rows );
		$this->assertSame( 'order_restore', $rows[0]['change_type'] );
		$this->assertSame( 7, (int) $rows[0]['qty_before'] );
		$this->assertSame( 10, (int) $rows[0]['qty_after'] );
		$this->assertSame( '#' . $order->get_order_number(), $rows[0]['reference'] );
	}

	/*
	|-----------------------------------------------------------------------
	| query() & purge()
	|-----------------------------------------------------------------------
	*/

	public function test_query_filters_by_product_and_paginates() {
		for ( $i = 1; $i <= 3; $i++ ) {
			StockLog::record( 100, $i, $i + 1 );
		}
		StockLog::record( 200, 1, 2 );

		$all = StockLog::query();
		$this->assertSame( 4, $all['total'] );

		$filtered = StockLog::query(
			array(
				'product_id' => 100,
				'per_page'   => 2,
				'paged'      => 1,
			)
		);
		$this->assertSame( 3, $filtered['total'] );
		$this->assertSame( 2, $filtered['total_pages'] );
		$this->assertCount( 2, $filtered['items'] );

		$page_two = StockLog::query(
			array(
				'product_id' => 100,
				'per_page'   => 2,
				'paged'      => 2,
			)
		);
		$this->assertCount( 1, $page_two['items'] );
	}

	public function test_purge_deletes_rows_past_the_retention_window() {
		global $wpdb;

		$old_id  = StockLog::record( 100, 5, 4 );
		StockLog::record( 100, 4, 3 );

		// Age one row past the default 180-day retention.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			Installer::stock_log_table(),
			array( 'created_at' => gmdate( 'Y-m-d H:i:s', time() - 200 * DAY_IN_SECONDS ) ),
			array( 'id' => $old_id )
		);

		( new StockLog() )->purge();

		$this->assertSame( 1, StockLog::query( array( 'product_id' => 100 ) )['total'] );
	}

	public function test_purge_keeps_everything_when_retention_is_zero() {
		global $wpdb;

		update_option( Settings::OPTION_KEY, array( 'log_retention_days' => 0 ) );

		$old_id = StockLog::record( 100, 5, 4 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			Installer::stock_log_table(),
			array( 'created_at' => gmdate( 'Y-m-d H:i:s', time() - 2000 * DAY_IN_SECONDS ) ),
			array( 'id' => $old_id )
		);

		( new StockLog() )->purge();

		$this->assertSame( 1, StockLog::query( array( 'product_id' => 100 ) )['total'], '0 means keep forever.' );
	}
}
