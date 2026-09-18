<?php
/**
 * Plugin boot integration tests: the full load sequence wired up by
 * storesuite.php — singleton, container services, lifecycle actions, AJAX
 * hook registration, REST routes, and the dashboard shortcode.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Tests\Integration;

use PluginizeLab\StoreSuite\Module\Manager;
use PluginizeLab\StoreSuite\StoreSuite;
use WP_UnitTestCase;

/**
 * Everything here asserts on state produced by the real bootstrap in
 * tests/bootstrap.php: WooCommerce loads, StoreSuite loads, plugins_loaded /
 * init / rest_api_init all fire — the same order a live site runs.
 */
class PluginBootTest extends WP_UnitTestCase {

	public function test_singleton_accessor_always_returns_the_same_instance() {
		$this->assertInstanceOf( StoreSuite::class, pluginizelab_storesuite() );
		$this->assertSame( pluginizelab_storesuite(), pluginizelab_storesuite() );
	}

	public function test_lifecycle_actions_fired_during_boot() {
		$this->assertGreaterThanOrEqual( 1, did_action( 'storesuite_loaded' ) );
		$this->assertGreaterThanOrEqual( 1, did_action( 'storesuite_modules_loaded' ), 'Active modules boot on storesuite_loaded.' );
	}

	public function test_core_constants_are_defined() {
		foreach ( array( 'STORESUITE_FILE', 'STORESUITE_DIR', 'STORESUITE_INC_DIR', 'STORESUITE_TEMPLATE_DIR', 'STORESUITE_PLUGIN_VERSION' ) as $constant ) {
			$this->assertTrue( defined( $constant ), "{$constant} must be defined at boot." );
		}
	}

	public function test_container_exposes_services_through_magic_get() {
		$plugin = pluginizelab_storesuite();

		$this->assertInstanceOf( Manager::class, $plugin->modules );
		$this->assertInstanceOf( \PluginizeLab\StoreSuite\Assets::class, $plugin->scripts );
		$this->assertInstanceOf( \PluginizeLab\StoreSuite\Cache::class, $plugin->cache );
		$this->assertInstanceOf( \PluginizeLab\StoreSuite\Order\OrderManager::class, $plugin->storesuite_order_manager );
		$this->assertInstanceOf( \PluginizeLab\StoreSuite\Coupon\CouponManager::class, $plugin->storesuite_coupon_manager );
	}

	public function test_dashboard_shortcode_is_registered() {
		$this->assertTrue( shortcode_exists( 'storesuite_dashboard' ) );
	}

	public function test_domain_ajax_endpoints_are_registered() {
		$actions = array(
			'wp_ajax_storesuite_add_product_category',
			'wp_ajax_storesuite_edit_product_category',
			'wp_ajax_storesuite_delete_product_category',
			'wp_ajax_storesuite_add_product_tag',
			'wp_ajax_storesuite_add_product_brand',
			'wp_ajax_storesuite_add_coupon',
			'wp_ajax_storesuite_delete_coupon',
			'wp_ajax_storesuite_add_product_action',
			'wp_ajax_storesuite_delete_product',
			'wp_ajax_storesuite_add_order_note',
			'wp_ajax_storesuite_create_order',
			'wp_ajax_storesuite_save_account_details',
			'wp_ajax_storesuite_bulk_edit_products',
			'wp_ajax_storesuite_product_export',
		);

		foreach ( $actions as $action ) {
			$this->assertNotFalse( has_action( $action ), "{$action} must have a handler." );
		}
	}

	public function test_rest_namespace_and_routes_are_registered() {
		global $wp_rest_server;
		$wp_rest_server = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/storesuite/v1', $routes );
		$this->assertArrayHasKey( '/storesuite/v1/settings', $routes );
		$this->assertArrayHasKey( '/storesuite/v1/modules', $routes );
		$this->assertArrayHasKey( '/storesuite/v1/changelog', $routes );

		$wp_rest_server = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	public function test_woocommerce_is_loaded_before_storesuite() {
		$this->assertTrue( class_exists( 'WooCommerce' ), 'StoreSuite bails without WooCommerce; the suite must run with it.' );
		$this->assertTrue( function_exists( 'WC' ) );
	}
}
