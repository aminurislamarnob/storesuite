<?php
/**
 * Category / Brand / Tag listing tests: hierarchy flattening with depth,
 * pagination, search filtering, and cache busting on term changes.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Tests\Taxonomy;

use PluginizeLab\StoreSuite\Cache;
use PluginizeLab\StoreSuite\ProductBrand\Brands;
use PluginizeLab\StoreSuite\ProductCategory\Categories;
use PluginizeLab\StoreSuite\ProductTag\Tags;
use WP_UnitTestCase;

/**
 * Tests for the Categories, Brands, and Tags listing services.
 *
 * Categories and Brands share the same hierarchy/flatten/cache design, so
 * the deep coverage lives on Categories and Brands gets a mirror smoke test.
 */
class TaxonomyListingTest extends WP_UnitTestCase {

	/**
	 * Create a product_cat term.
	 *
	 * @param string $name   Term name.
	 * @param int    $parent Parent term ID.
	 * @param array  $extra  Extra term args (e.g. description).
	 * @return int Term ID.
	 */
	private function create_category( $name, $parent = 0, array $extra = array() ) {
		return self::factory()->term->create(
			array_merge(
				array(
					'taxonomy' => 'product_cat',
					'name'     => $name,
					'parent'   => $parent,
				),
				$extra
			)
		);
	}

	/*
	|-----------------------------------------------------------------------
	| Categories
	|-----------------------------------------------------------------------
	*/

	public function test_categories_are_flattened_depth_first_with_depth_and_parent() {
		$parent = $this->create_category( 'Clothing' );
		$child  = $this->create_category( 'Shirts', $parent );
		$this->create_category( 'Books' );

		$result = ( new Categories() )->get_paginated_categories_with_children( 10, 1 );

		// WooCommerce seeds an "Uncategorized" default category on install.
		$names = wp_list_pluck( array_column( $result->categories, 'category' ), 'name' );
		$this->assertContains( 'Clothing', $names );
		$this->assertContains( 'Shirts', $names );

		$by_name = array();
		foreach ( $result->categories as $row ) {
			$by_name[ $row['category']->name ] = $row;
		}

		$this->assertSame( 0, $by_name['Clothing']['depth'] );
		$this->assertNull( $by_name['Clothing']['parent'] );
		$this->assertSame( 1, $by_name['Shirts']['depth'] );
		$this->assertSame( 'Clothing', $by_name['Shirts']['parent']->name );

		// The child immediately follows its parent in the flattened order.
		$order = array_keys( $by_name );
		$this->assertSame( array_search( 'Clothing', $order, true ) + 1, array_search( 'Shirts', $order, true ) );
		$this->assertSame( $child, $by_name['Shirts']['category']->term_id );
	}

	public function test_categories_are_paginated() {
		// Count what already exists (WooCommerce may seed "Uncategorized",
		// and the framework's between-class cleanup may have removed it).
		$preexisting = (int) wp_count_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );

		for ( $i = 1; $i <= 5; $i++ ) {
			$this->create_category( 'Cat ' . $i );
		}

		$total    = $preexisting + 5;
		$page_one = ( new Categories() )->get_paginated_categories_with_children( 4, 1 );
		$page_two = ( new Categories() )->get_paginated_categories_with_children( 4, 2 );

		$this->assertSame( $total, $page_one->total );
		$this->assertSame( ceil( $total / 4 ), $page_one->max_num_pages );
		$this->assertCount( 4, $page_one->categories );
		$this->assertCount( min( 4, $total - 4 ), $page_two->categories );
	}

	public function test_category_search_matches_name_slug_and_description() {
		$this->create_category( 'Gadgets' );
		$this->create_category( 'Plain', 0, array( 'description' => 'contains gadget word' ) );
		$this->create_category( 'Unrelated' );

		$result = ( new Categories() )->get_paginated_categories_with_children( 10, 1, 'gadget' );

		$names = wp_list_pluck( array_column( $result->categories, 'category' ), 'name' );
		sort( $names );
		$this->assertSame( array( 'Gadgets', 'Plain' ), $names );
	}

	public function test_category_list_is_cached_and_busted_by_core_term_hooks() {
		new Categories(); // Wires the cache-busting hooks.

		( new Categories() )->get_paginated_categories_with_children( 10, 1 );
		$this->assertTrue( Cache::has( 'flat_categories' ), 'First unfiltered listing primes the cache.' );

		// Creating a term through core fires created_product_cat, which must
		// invalidate the cached flat list.
		$this->create_category( 'Fresh' );
		$this->assertFalse( Cache::has( 'flat_categories' ), 'Core term creation must bust the cache.' );

		$result = ( new Categories() )->get_paginated_categories_with_children( 10, 1 );
		$names  = wp_list_pluck( array_column( $result->categories, 'category' ), 'name' );
		$this->assertContains( 'Fresh', $names );
	}

	public function test_category_search_bypasses_the_cache() {
		( new Categories() )->get_paginated_categories_with_children( 10, 1 ); // Primes cache.
		$this->create_category( 'Newest' );
		Cache::set( 'flat_categories', array(), HOUR_IN_SECONDS ); // Poison the cache on purpose.

		$result = ( new Categories() )->get_paginated_categories_with_children( 10, 1, 'newest' );

		$this->assertSame( 1, $result->total, 'Search results must come from live terms, not the cache.' );
	}

	/*
	|-----------------------------------------------------------------------
	| Brands (mirror of Categories)
	|-----------------------------------------------------------------------
	*/

	public function test_brands_flatten_paginate_and_expose_lookups() {
		$parent = self::factory()->term->create(
			array(
				'taxonomy' => 'product_brand',
				'name'     => 'Acme',
			)
		);
		self::factory()->term->create(
			array(
				'taxonomy' => 'product_brand',
				'name'     => 'Acme Pro',
				'parent'   => $parent,
			)
		);

		$brands = new Brands();
		$result = $brands->get_paginated_brands_with_children( 10, 1 );

		$this->assertSame( 2, $result->total );
		$this->assertSame( 'Acme', $result->brands[0]['brand']->name );
		$this->assertSame( 1, $result->brands[1]['depth'] );
		$this->assertSame( 'Acme', $result->brands[1]['parent']->name );

		$this->assertSame( 'Acme', $brands->get_brand_by_id( $parent )->name );
		$this->assertCount( 2, $brands->get_product_brands() );
	}

	public function test_brand_cache_is_busted_by_core_term_hooks() {
		new Brands();

		( new Brands() )->get_paginated_brands_with_children( 10, 1 );
		$this->assertTrue( Cache::has( 'flat_brands' ) );

		self::factory()->term->create(
			array(
				'taxonomy' => 'product_brand',
				'name'     => 'Later Brand',
			)
		);

		$this->assertFalse( Cache::has( 'flat_brands' ) );
	}

	/*
	|-----------------------------------------------------------------------
	| Tags
	|-----------------------------------------------------------------------
	*/

	public function test_tags_are_paginated_alphabetically() {
		foreach ( array( 'zeta', 'alpha', 'mid' ) as $name ) {
			self::factory()->term->create(
				array(
					'taxonomy' => 'product_tag',
					'name'     => $name,
				)
			);
		}

		$tags   = new Tags();
		$result = $tags->get_paginated_tags( 2, 1 );

		$this->assertSame( 3, (int) $result->total );
		$this->assertSame( 2.0, $result->max_num_pages );
		$this->assertSame( array( 'alpha', 'mid' ), wp_list_pluck( $result->tags, 'name' ) );

		$page_two = $tags->get_paginated_tags( 2, 2 );
		$this->assertSame( array( 'zeta' ), wp_list_pluck( $page_two->tags, 'name' ) );
	}

	public function test_tags_can_be_searched_and_fetched_by_id() {
		$tag_id = self::factory()->term->create(
			array(
				'taxonomy' => 'product_tag',
				'name'     => 'summer-sale',
			)
		);
		self::factory()->term->create(
			array(
				'taxonomy' => 'product_tag',
				'name'     => 'winter',
			)
		);

		$tags = new Tags();

		$result = $tags->get_paginated_tags( 10, 1, 'summer' );
		$this->assertSame( array( 'summer-sale' ), wp_list_pluck( $result->tags, 'name' ) );

		$this->assertSame( 'summer-sale', $tags->get_tag_by_id( $tag_id )->name );
	}
}
