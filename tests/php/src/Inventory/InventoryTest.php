<?php
/**
 * Tests for the Inventory view data layer (issue #188).
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test\Inventory;

use PluginizeLab\StoreSuite\Inventory\InventoryManager;
use PluginizeLab\StoreSuite\Rewrites;
use PluginizeLab\StoreSuite\Test\StoreSuiteTestCase;

/**
 * @covers \PluginizeLab\StoreSuite\Inventory\InventoryManager
 * @group storesuite-inventory
 */
class InventoryTest extends StoreSuiteTestCase {

	/**
	 * Create a stock-managed simple product.
	 *
	 * @param int      $stock     Stock quantity.
	 * @param int|null $threshold Per-product low stock amount (null = store default).
	 * @return \WC_Product
	 */
	private function create_stocked_product( $stock, $threshold = null ) {
		return self::factory()->product->create(
			array(
				'manage_stock'     => true,
				'stock_quantity'   => $stock,
				'low_stock_amount' => $threshold,
			)
		);
	}

	public function test_low_stock_ids_use_store_default_threshold() {
		update_option( 'woocommerce_notify_low_stock_amount', 3 );

		$low_id  = $this->create_stocked_product( 2 )->get_id();
		$edge_id = $this->create_stocked_product( 3 )->get_id();
		$ok_id   = $this->create_stocked_product( 10 )->get_id();

		$ids = ( new InventoryManager() )->get_low_stock_product_ids();

		$this->assertContains( $low_id, $ids );
		$this->assertContains( $edge_id, $ids, 'Quantity equal to the threshold counts as low stock.' );
		$this->assertNotContains( $ok_id, $ids );
	}

	public function test_low_stock_ids_prefer_per_product_threshold() {
		update_option( 'woocommerce_notify_low_stock_amount', 2 );

		// Qty 8 with per-product threshold 10 → low; qty 8 with store default 2 → not low.
		$custom_id  = $this->create_stocked_product( 8, 10 )->get_id();
		$default_id = $this->create_stocked_product( 8 )->get_id();

		$ids = ( new InventoryManager() )->get_low_stock_product_ids();

		$this->assertContains( $custom_id, $ids );
		$this->assertNotContains( $default_id, $ids );
	}

	public function test_low_stock_ids_fall_back_to_restrictive_set_when_empty() {
		update_option( 'woocommerce_notify_low_stock_amount', 2 );
		$this->create_stocked_product( 50 );

		$manager = new InventoryManager();
		$this->assertSame( array( 0 ), $manager->get_low_stock_product_ids() );

		$result = $manager->get_paginated_inventory( 1, '', array( 'low_stock' => 1 ) );
		$this->assertSame( 0, $result->found_posts );
	}

	public function test_low_stock_filter_restricts_inventory_list() {
		update_option( 'woocommerce_notify_low_stock_amount', 3 );

		$low_id = $this->create_stocked_product( 1 )->get_id();
		$ok_id  = $this->create_stocked_product( 20 )->get_id();

		$result = ( new InventoryManager() )->get_paginated_inventory( 1, '', array( 'low_stock' => 1 ) );
		$ids    = wp_list_pluck( $result->products->posts, 'ID' );

		$this->assertContains( $low_id, $ids );
		$this->assertNotContains( $ok_id, $ids );
	}

	public function test_inventory_per_page_filter_is_applied() {
		$this->create_stocked_product( 5 );
		$this->create_stocked_product( 6 );
		$this->create_stocked_product( 7 );

		add_filter( 'storesuite_inventory_per_page', array( $this, 'return_two' ) );
		$result = ( new InventoryManager() )->get_paginated_inventory( 1, '', array() );
		remove_filter( 'storesuite_inventory_per_page', array( $this, 'return_two' ) );

		$this->assertSame( 2, $result->per_page );
		$this->assertSame( 2, $result->products->max_num_pages );
	}

	/**
	 * Filter callback: two items per page.
	 *
	 * @return int
	 */
	public function return_two() {
		return 2;
	}

	public function test_inventory_query_var_is_registered() {
		$rewrites = new Rewrites();

		$this->assertArrayHasKey( 'inventory', $rewrites->query_vars );
		$this->assertSame( 'inventory', $rewrites->query_vars['inventory'] );
	}
}
