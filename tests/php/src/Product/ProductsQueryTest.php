<?php
/**
 * Tests for the products list query layer: filters and sorting (issue #189).
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test\Product;

use PluginizeLab\StoreSuite\Product\Products;
use PluginizeLab\StoreSuite\Test\StoreSuiteTestCase;

/**
 * @covers \PluginizeLab\StoreSuite\Product\Products
 * @group storesuite-product
 */
class ProductsQueryTest extends StoreSuiteTestCase {

	/**
	 * Run the query and return the matched product IDs.
	 *
	 * @param array  $filters     Filters array.
	 * @param string $search_term Search term.
	 * @return int[]
	 */
	private function query_ids( $filters = array(), $search_term = '' ) {
		$result = ( new Products() )->get_paginated_products( 1, $search_term, $filters );

		return wp_list_pluck( $result->products->posts, 'ID' );
	}

	public function test_status_filter_honors_allow_list() {
		$published = self::factory()->product->create( array( 'name' => 'Published One' ) );
		$draft_id  = self::factory()->product->create(
			array(
				'name'   => 'Draft One',
				'status' => 'draft',
			)
		)->get_id();

		$ids = $this->query_ids( array( 'status' => 'draft' ) );
		$this->assertContains( $draft_id, $ids );
		$this->assertNotContains( $published->get_id(), $ids );

		// A status outside the allow-list is ignored: both products stay visible.
		$ids = $this->query_ids( array( 'status' => 'trash' ) );
		$this->assertContains( $published->get_id(), $ids );
		$this->assertContains( $draft_id, $ids );
	}

	public function test_price_range_filters() {
		$cheap  = self::factory()->product->create(
			array(
				'name'          => 'Cheap',
				'regular_price' => '5',
			)
		)->get_id();
		$medium = self::factory()->product->create(
			array(
				'name'          => 'Medium',
				'regular_price' => '20',
			)
		)->get_id();
		$dear   = self::factory()->product->create(
			array(
				'name'          => 'Expensive',
				'regular_price' => '80',
			)
		)->get_id();

		$ids = $this->query_ids(
			array(
				'price_min' => '10',
				'price_max' => '50',
			)
		);
		$this->assertSame( array( $medium ), array_values( $ids ) );

		$ids = $this->query_ids( array( 'price_min' => '10' ) );
		$this->assertContains( $medium, $ids );
		$this->assertContains( $dear, $ids );
		$this->assertNotContains( $cheap, $ids );

		$ids = $this->query_ids( array( 'price_max' => '10' ) );
		$this->assertSame( array( $cheap ), array_values( $ids ) );
	}

	public function test_created_date_range_filter() {
		$old_id = self::factory()->post->create(
			array(
				'post_type'   => 'product',
				'post_status' => 'publish',
				'post_date'   => '2020-01-15 10:00:00',
			)
		);
		$new_id = self::factory()->post->create(
			array(
				'post_type'   => 'product',
				'post_status' => 'publish',
				'post_date'   => '2024-06-15 10:00:00',
			)
		);

		$ids = $this->query_ids(
			array(
				'date_from' => '2024-01-01',
				'date_to'   => '2024-12-31',
			)
		);
		$this->assertContains( $new_id, $ids );
		$this->assertNotContains( $old_id, $ids );
	}

	public function test_price_sorting_keeps_products_without_price_meta() {
		$low  = self::factory()->product->create(
			array(
				'name'          => 'Low',
				'regular_price' => '5',
			)
		)->get_id();
		$high = self::factory()->product->create(
			array(
				'name'          => 'High',
				'regular_price' => '50',
			)
		)->get_id();

		$grouped = self::factory()->product->create(
			array(
				'type' => 'grouped',
				'name' => 'No Price Grouped',
			)
		);

		$ids = $this->query_ids(
			array(
				'orderby' => 'price',
				'order'   => 'asc',
			)
		);

		$this->assertContains( $grouped->get_id(), $ids, 'Products without _price must not be dropped by the price sort.' );
		$this->assertLessThan( array_search( $high, $ids, true ), array_search( $low, $ids, true ) );
		$this->assertSame( $grouped->get_id(), end( $ids ), 'Products without _price must sort last, not by an arbitrary meta value.' );
	}

	/**
	 * Regression: the old OR (EXISTS / NOT EXISTS) meta sort joined postmeta
	 * without a key, so unmanaged products were ordered by whatever meta row
	 * MySQL picked (usually _price) and landed above managed ones.
	 */
	public function test_stock_sorting_orders_managed_products_and_lists_unmanaged_last() {
		$few  = self::factory()->product->create(
			array(
				'name'           => 'Few In Stock',
				'regular_price'  => '900',
				'manage_stock'   => true,
				'stock_quantity' => 3,
			)
		)->get_id();
		$many = self::factory()->product->create(
			array(
				'name'           => 'Many In Stock',
				'regular_price'  => '1',
				'manage_stock'   => true,
				'stock_quantity' => 40,
			)
		)->get_id();

		// Unmanaged, but with a high price so a keyless join would rank it first.
		$unmanaged = self::factory()->product->create(
			array(
				'name'          => 'Aaa Unmanaged',
				'regular_price' => '5000',
			)
		)->get_id();

		$desc = $this->query_ids(
			array(
				'orderby' => 'stock',
				'order'   => 'desc',
			)
		);
		$this->assertSame( array( $many, $few, $unmanaged ), array_values( array_intersect( $desc, array( $many, $few, $unmanaged ) ) ) );
		$this->assertSame( $unmanaged, end( $desc ) );

		$asc = $this->query_ids(
			array(
				'orderby' => 'stock',
				'order'   => 'asc',
			)
		);
		$this->assertSame( array( $few, $many, $unmanaged ), array_values( array_intersect( $asc, array( $few, $many, $unmanaged ) ) ) );
		$this->assertSame( $unmanaged, end( $asc ), 'Unmanaged products must sort last in both directions.' );
	}

	public function test_unknown_orderby_is_ignored() {
		self::factory()->product->create( array( 'name' => 'Any Product' ) );

		$result = ( new Products() )->get_paginated_products(
			1,
			'',
			array(
				'orderby' => 'evil_column',
				'order'   => 'asc',
			)
		);

		$this->assertSame( 1, $result->found_posts );
	}

	public function test_search_matches_sku() {
		self::factory()->product->create(
			array(
				'name' => 'Alpha Widget',
				'sku'  => 'AAA-111',
			)
		);
		$target = self::factory()->product->create(
			array(
				'name' => 'Beta Widget',
				'sku'  => 'ZZZ-999',
			)
		)->get_id();

		$ids = $this->query_ids( array(), 'ZZZ-999' );
		$this->assertContains( $target, $ids );
	}
}
