<?php
/**
 * ProductManager tests: creating and updating simple, external, grouped, and
 * virtual products through the frontend form pipeline.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Tests\Product;

use PluginizeLab\StoreSuite\Product\ProductManager;
use WP_UnitTestCase;

/**
 * Tests for PluginizeLab\StoreSuite\Product\ProductManager.
 *
 * `storesuite_save_product()` is the entry point the AJAX controller feeds
 * sanitized form data into; these tests exercise it with the same array
 * shapes and assert on the resulting WC_Product objects.
 */
class ProductManagerTest extends WP_UnitTestCase {

	/**
	 * Manager under test.
	 *
	 * @var ProductManager
	 */
	private $manager;

	/**
	 * Fresh manager per test.
	 */
	public function set_up() {
		parent::set_up();
		$this->manager = new ProductManager();
	}

	/**
	 * Minimal valid form payload; the manager reads description fields
	 * unconditionally, so they must always be present.
	 *
	 * @param array $overrides Fields to override.
	 * @return array
	 */
	private function product_data( array $overrides = array() ) {
		return array_merge(
			array(
				'product_title'             => 'Test Product',
				'product_description'       => 'Long description',
				'product_short_description' => 'Short description',
			),
			$overrides
		);
	}

	public function test_missing_title_returns_wp_error() {
		$result = $this->manager->storesuite_save_product( $this->product_data( array( 'product_title' => '' ) ) );

		$this->assertWPError( $result );
		$this->assertSame( 'no-title', $result->get_error_code() );
	}

	public function test_create_simple_product_persists_core_fields() {
		$product_id = $this->manager->storesuite_save_product(
			$this->product_data(
				array(
					'regular_price'   => '100',
					'sale_price'      => '80',
					'_sku'            => 'TEST-SKU-1',
					'_stock_status'   => 'instock',
					'weight'          => '1.5',
					'_featured'       => 'yes',
				)
			)
		);

		$this->assertIsInt( $product_id );

		$product = wc_get_product( $product_id );
		$this->assertSame( 'simple', $product->get_type() );
		$this->assertSame( 'Test Product', $product->get_name() );
		$this->assertSame( 'Long description', $product->get_description() );
		$this->assertSame( 'Short description', $product->get_short_description() );
		$this->assertSame( 'publish', $product->get_status(), 'Status defaults to publish.' );
		$this->assertSame( '100', $product->get_regular_price() );
		$this->assertSame( '80', $product->get_sale_price() );
		$this->assertSame( '80', $product->get_price(), 'Active price honors the sale price.' );
		$this->assertSame( 'TEST-SKU-1', $product->get_sku() );
		$this->assertSame( 'instock', $product->get_stock_status() );
		$this->assertSame( '1.5', $product->get_weight() );
		$this->assertTrue( $product->get_featured() );
	}

	public function test_create_fires_added_action_and_update_fires_updated_action() {
		$added   = 0;
		$updated = 0;
		add_action(
			'storesuite_new_product_added',
			function () use ( &$added ) {
				++$added;
			}
		);
		add_action(
			'storesuite_product_updated',
			function () use ( &$updated ) {
				++$updated;
			}
		);

		$product_id = $this->manager->storesuite_save_product( $this->product_data() );
		$this->assertSame( 1, $added );
		$this->assertSame( 0, $updated );

		$this->manager->storesuite_save_product(
			$this->product_data(
				array(
					'product_id'    => $product_id,
					'product_title' => 'Renamed Product',
				)
			)
		);

		$this->assertSame( 1, $added, 'Update must not fire the added action.' );
		$this->assertSame( 1, $updated );
		$this->assertSame( 'Renamed Product', wc_get_product( $product_id )->get_name() );
	}

	public function test_explicit_duplicate_slug_is_rejected() {
		$this->manager->storesuite_save_product( $this->product_data( array( 'product_slug' => 'taken-slug' ) ) );

		$result = $this->manager->storesuite_save_product(
			$this->product_data(
				array(
					'product_title' => 'Another Product',
					'product_slug'  => 'taken-slug',
				)
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'slug-exists', $result->get_error_code() );
	}

	public function test_auto_generated_slug_collision_gets_a_suffix_instead_of_an_error() {
		$first  = $this->manager->storesuite_save_product( $this->product_data() );
		$second = $this->manager->storesuite_save_product( $this->product_data() );

		$this->assertIsInt( $second, 'Same title without an explicit slug must not error.' );
		$this->assertNotSame(
			wc_get_product( $first )->get_slug(),
			wc_get_product( $second )->get_slug(),
			'WordPress appends a numeric suffix to the second slug.'
		);
	}

	public function test_updating_a_product_may_keep_its_own_slug() {
		$product_id = $this->manager->storesuite_save_product( $this->product_data( array( 'product_slug' => 'my-slug' ) ) );

		$result = $this->manager->storesuite_save_product(
			$this->product_data(
				array(
					'product_id'   => $product_id,
					'product_slug' => 'my-slug',
				)
			)
		);

		$this->assertSame( $product_id, $result, 'Re-saving with its own slug is not a duplicate.' );
	}

	public function test_external_product_saves_url_and_button_and_is_always_in_stock() {
		$product_id = $this->manager->storesuite_save_product(
			$this->product_data(
				array(
					'post_type'     => 'external',
					'_product_url'  => 'https://example.com/buy',
					'_button_text'  => 'Buy elsewhere',
					'_stock_status' => 'outofstock',
				)
			)
		);

		$product = wc_get_product( $product_id );
		$this->assertSame( 'external', $product->get_type() );
		$this->assertSame( 'https://example.com/buy', $product->get_product_url() );
		$this->assertSame( 'Buy elsewhere', $product->get_button_text() );
		$this->assertSame( 'instock', $product->get_stock_status(), 'External products are forced in stock.' );
	}

	public function test_grouped_product_sets_children_and_blanks_prices() {
		$child_a = $this->manager->storesuite_save_product( $this->product_data( array( 'regular_price' => '10' ) ) );
		$child_b = $this->manager->storesuite_save_product( $this->product_data( array( 'regular_price' => '20' ) ) );

		$product_id = $this->manager->storesuite_save_product(
			$this->product_data(
				array(
					'post_type'        => 'grouped',
					'regular_price'    => '999',
					'grouped_products' => array( $child_a, $child_b ),
				)
			)
		);

		$product = wc_get_product( $product_id );
		$this->assertSame( 'grouped', $product->get_type() );
		$this->assertSame( array( $child_a, $child_b ), $product->get_children() );
		$this->assertSame( '', $product->get_regular_price(), 'Grouped products carry no own price.' );
	}

	public function test_virtual_product_clears_shipping_dimensions() {
		$product_id = $this->manager->storesuite_save_product(
			$this->product_data(
				array(
					'_virtual' => 'yes',
					'weight'   => '5',
					'length'   => '10',
				)
			)
		);

		$product = wc_get_product( $product_id );
		$this->assertTrue( $product->get_virtual() );
		$this->assertSame( '', $product->get_weight() );
		$this->assertSame( '', $product->get_length() );
	}

	public function test_stock_management_persists_quantity() {
		update_option( 'woocommerce_manage_stock', 'yes' );

		$product_id = $this->manager->storesuite_save_product(
			$this->product_data(
				array(
					'_manage_stock'   => 'yes',
					'_stock_quantity' => '7',
					'_backorders'     => 'notify',
				)
			)
		);

		$product = wc_get_product( $product_id );
		$this->assertTrue( $product->get_manage_stock() );
		$this->assertSame( 7, $product->get_stock_quantity() );
		$this->assertSame( 'notify', $product->get_backorders() );
	}

	public function test_terms_are_assigned_from_ids() {
		$category = self::factory()->term->create( array( 'taxonomy' => 'product_cat' ) );
		$tag      = self::factory()->term->create( array( 'taxonomy' => 'product_tag' ) );

		$product_id = $this->manager->storesuite_save_product(
			$this->product_data(
				array(
					'product_category' => array( $category ),
					'product_tags'     => array( $tag ),
				)
			)
		);

		$product = wc_get_product( $product_id );
		$this->assertSame( array( $category ), $product->get_category_ids() );
		$this->assertSame( array( $tag ), $product->get_tag_ids() );
	}

	public function test_get_brands_returns_assigned_brand_terms() {
		$brand = self::factory()->term->create(
			array(
				'taxonomy' => 'product_brand',
				'name'     => 'Acme',
			)
		);

		$product_id = $this->manager->storesuite_save_product( $this->product_data( array( 'product_brand' => array( $brand ) ) ) );

		$names = $this->manager->get_brands( $product_id, 'names' );
		$this->assertSame( array( 'Acme' ), $names );
	}

	public function test_create_product_falls_back_to_draft_for_bogus_status() {
		$product = $this->manager->create_product(
			array(
				'name'   => 'Status Test',
				'status' => 'no-such-status',
			)
		);

		$this->assertSame( 'draft', $product->get_status() );
	}
}
