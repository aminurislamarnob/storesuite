<?php
/**
 * HandlePaginations tests: the settings-driven per-page filters for every
 * dashboard list (products, orders, categories, tags, brands, coupons).
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Tests;

use PluginizeLab\StoreSuite\HandlePaginations;
use WP_UnitTestCase;

/**
 * Tests for PluginizeLab\StoreSuite\HandlePaginations.
 */
class HandlePaginationsTest extends WP_UnitTestCase {

	/**
	 * Map of filter hook => settings key backing it.
	 *
	 * @var array<string,string>
	 */
	private const FILTER_MAP = array(
		'storesuite_products_per_page'   => 'storesuite_product_per_page',
		'storesuite_orders_per_page'     => 'storesuite_order_per_page',
		'storesuite_categories_per_page' => 'storesuite_category_per_page',
		'storesuite_tags_per_page'       => 'storesuite_tag_per_page',
		'storesuite_brands_per_page'     => 'storesuite_brand_per_page',
		'storesuite_coupons_per_page'    => 'storesuite_coupon_per_page',
	);

	/**
	 * Clean settings and wire the filters per test.
	 */
	public function set_up() {
		parent::set_up();

		delete_option( 'storesuite_settings' );
		new HandlePaginations();
	}

	public function test_every_per_page_filter_falls_back_to_the_incoming_default() {
		foreach ( self::FILTER_MAP as $hook => $key ) {
			$this->assertSame( 10, apply_filters( $hook, 10 ), "{$hook} must pass the default through when no setting is stored." );
		}
	}

	public function test_every_per_page_filter_prefers_its_stored_setting() {
		$settings = array();
		$expected = array();
		$i        = 0;
		foreach ( self::FILTER_MAP as $hook => $key ) {
			$settings[ $key ]  = (string) ( 11 + $i ); // Stored as strings by the settings screen.
			$expected[ $hook ] = 11 + $i;
			++$i;
		}
		update_option( 'storesuite_settings', $settings );

		foreach ( $expected as $hook => $value ) {
			$this->assertSame( $value, apply_filters( $hook, 10 ), "{$hook} must return its stored setting as an int." );
		}
	}

	public function test_zero_and_garbage_settings_fall_back_to_the_default() {
		update_option(
			'storesuite_settings',
			array(
				'storesuite_product_per_page' => '0',
				'storesuite_order_per_page'   => 'not-a-number',
			)
		);

		$this->assertSame( 10, apply_filters( 'storesuite_products_per_page', 10 ), 'Zero is not a usable page size.' );
		$this->assertSame( 10, apply_filters( 'storesuite_orders_per_page', 10 ), 'Non-numeric values cast to 0 and fall back.' );
	}
}
