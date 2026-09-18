<?php
/**
 * Inventory Manager StockRepository tests: the stock list query (filters,
 * search, pagination), item description, and quantity mutation + logging.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Tests\InventoryManager;

use PluginizeLab\StoreSuite\Modules\InventoryManager\Installer;
use PluginizeLab\StoreSuite\Modules\InventoryManager\StockLog;
use PluginizeLab\StoreSuite\Modules\InventoryManager\StockRepository;
use WC_Product_Simple;
use WC_Product_Variable;
use WC_Product_Variation;
use WP_UnitTestCase;

/**
 * Tests for PluginizeLab\StoreSuite\Modules\InventoryManager\StockRepository.
 */
class StockRepositoryTest extends WP_UnitTestCase {

	/**
	 * Start every test with an empty movement log.
	 */
	public function set_up() {
		parent::set_up();

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( 'DELETE FROM ' . Installer::stock_log_table() );
	}

	/**
	 * Create a published, stock-managed simple product.
	 *
	 * @param string $title Product name.
	 * @param int    $qty   Stock quantity.
	 * @param array  $extra Optional sku / low_stock_amount / status / managed.
	 * @return WC_Product_Simple
	 */
	private function create_product( $title, $qty, array $extra = array() ) {
		$product = new WC_Product_Simple();
		$product->set_name( $title );
		$product->set_regular_price( '10' );
		$product->set_manage_stock( $extra['managed'] ?? true );
		if ( $extra['managed'] ?? true ) {
			$product->set_stock_quantity( $qty );
		}
		if ( isset( $extra['sku'] ) ) {
			$product->set_sku( $extra['sku'] );
		}
		if ( isset( $extra['low_stock_amount'] ) ) {
			$product->set_low_stock_amount( $extra['low_stock_amount'] );
		}
		if ( isset( $extra['status'] ) ) {
			$product->set_status( $extra['status'] );
		}
		$product->save();

		return $product;
	}

	/*
	|-----------------------------------------------------------------------
	| get_paginated()
	|-----------------------------------------------------------------------
	*/

	public function test_list_contains_only_published_stock_managed_items() {
		$managed = $this->create_product( 'Managed', 5 );
		$this->create_product( 'Unmanaged', 0, array( 'managed' => false ) );
		$this->create_product( 'Draft Managed', 5, array( 'status' => 'draft' ) );

		$result = StockRepository::get_paginated();

		$this->assertSame( 1, $result['total'] );
		$this->assertSame( $managed->get_id(), $result['items'][0]['id'] );
	}

	public function test_list_paginates_ordered_by_title() {
		$this->create_product( 'Cherry', 1 );
		$this->create_product( 'Apple', 1 );
		$this->create_product( 'Banana', 1 );

		$page_one = StockRepository::get_paginated(
			array(
				'per_page' => 2,
				'paged'    => 1,
			)
		);
		$page_two = StockRepository::get_paginated(
			array(
				'per_page' => 2,
				'paged'    => 2,
			)
		);

		$this->assertSame( 3, $page_one['total'] );
		$this->assertSame( 2, $page_one['total_pages'] );
		$this->assertSame( array( 'Apple', 'Banana' ), wp_list_pluck( $page_one['items'], 'name' ) );
		$this->assertSame( array( 'Cherry' ), wp_list_pluck( $page_two['items'], 'name' ) );
	}

	public function test_search_matches_title_and_sku() {
		$this->create_product( 'Blue Widget', 5 );
		$this->create_product( 'Plain Thing', 5, array( 'sku' => 'WIDGET-99' ) );
		$this->create_product( 'Unrelated', 5 );

		$result = StockRepository::get_paginated( array( 'search' => 'widget' ) );

		$names = wp_list_pluck( $result['items'], 'name' );
		sort( $names );
		$this->assertSame( array( 'Blue Widget', 'Plain Thing' ), $names );
	}

	public function test_stock_status_filter() {
		$this->create_product( 'In Stock', 5 );
		$out = $this->create_product( 'Gone', 0 );

		$result = StockRepository::get_paginated( array( 'stock_status' => 'outofstock' ) );

		$this->assertSame( 1, $result['total'] );
		$this->assertSame( $out->get_id(), $result['items'][0]['id'] );
	}

	public function test_low_only_uses_per_product_amount_with_default_threshold_fallback() {
		// The module's default low-stock threshold is 2.
		$low_by_default  = $this->create_product( 'Low Default', 1 );
		$this->create_product( 'Healthy', 10 );
		$low_by_own_rule = $this->create_product( 'Low Custom', 5, array( 'low_stock_amount' => 6 ) );

		$result = StockRepository::get_paginated( array( 'low_only' => true ) );

		$ids = wp_list_pluck( $result['items'], 'id' );
		sort( $ids );
		$expected = array( $low_by_default->get_id(), $low_by_own_rule->get_id() );
		sort( $expected );
		$this->assertSame( $expected, $ids );

		// The unfiltered list carries the same signal per row via is_low.
		$all    = StockRepository::get_paginated();
		$by_name = array();
		foreach ( $all['items'] as $item ) {
			$by_name[ $item['name'] ] = $item;
		}
		$this->assertTrue( $by_name['Low Default']['is_low'] );
		$this->assertFalse( $by_name['Healthy']['is_low'] );
		$this->assertTrue( $by_name['Low Custom']['is_low'] );
	}

	public function test_stock_managed_variations_are_listed() {
		$parent = new WC_Product_Variable();
		$parent->set_name( 'Variable Parent' );
		$parent->save();

		$variation = new WC_Product_Variation();
		$variation->set_parent_id( $parent->get_id() );
		$variation->set_regular_price( '5' );
		$variation->set_manage_stock( true );
		$variation->set_stock_quantity( 4 );
		$variation->set_status( 'publish' );
		$variation->save();

		$result = StockRepository::get_paginated();

		$this->assertSame( 1, $result['total'], 'Only the managed variation qualifies; the parent itself does not manage stock.' );
		$this->assertSame( 'variation', $result['items'][0]['type'] );
		$this->assertStringEndsWith( (string) $parent->get_id(), $result['items'][0]['edit_url'], 'Variations link to the parent product editor.' );
	}

	/*
	|-----------------------------------------------------------------------
	| describe()
	|-----------------------------------------------------------------------
	*/

	public function test_describe_returns_the_row_shape_and_null_for_unknown_ids() {
		$product = $this->create_product( 'Described', 7, array( 'sku' => 'DESC-1' ) );

		$row = StockRepository::describe( $product->get_id() );

		$this->assertSame( 'Described', $row['name'] );
		$this->assertSame( 'DESC-1', $row['sku'] );
		$this->assertSame( 7, $row['stock_qty'] );
		$this->assertSame( 2, $row['low_stock'], 'No per-product amount set: the module default (2) applies.' );
		$this->assertFalse( $row['is_low'] );

		$this->assertNull( StockRepository::describe( 999999 ) );
	}

	/*
	|-----------------------------------------------------------------------
	| set_quantity()
	|-----------------------------------------------------------------------
	*/

	public function test_set_quantity_updates_stock_and_writes_one_manual_log_row() {
		$product = $this->create_product( 'Adjustable', 3 );

		$result = StockRepository::set_quantity( $product->get_id(), 8, 'manual' );

		$this->assertSame( 8, $result );
		$this->assertSame( 8, wc_get_product( $product->get_id() )->get_stock_quantity() );

		// Exactly one row: the explicit log, with the WC-hook capture suppressed.
		$log = StockLog::query( array( 'product_id' => $product->get_id() ) );
		$this->assertSame( 1, $log['total'] );
		$this->assertSame( 'manual', $log['items'][0]['change_type'] );
		$this->assertSame( 3, (int) $log['items'][0]['qty_before'] );
		$this->assertSame( 8, (int) $log['items'][0]['qty_after'] );
	}

	public function test_set_quantity_turns_on_stock_management_when_off() {
		$product = $this->create_product( 'Not Managed Yet', 0, array( 'managed' => false ) );

		StockRepository::set_quantity( $product->get_id(), 5 );

		$fresh = wc_get_product( $product->get_id() );
		$this->assertTrue( $fresh->get_manage_stock() );
		$this->assertSame( 5, $fresh->get_stock_quantity() );
	}

	public function test_set_quantity_returns_an_error_for_unknown_products() {
		$result = StockRepository::set_quantity( 999999, 5 );

		$this->assertWPError( $result );
		$this->assertSame( 'storesuite_no_product', $result->get_error_code() );
	}
}
