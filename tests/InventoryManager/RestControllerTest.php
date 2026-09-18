<?php
/**
 * Inventory Manager REST tests: permissions, the stock list, single and bulk
 * stock updates, the movement log feed, and the cached low-stock feed.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Tests\InventoryManager;

use PluginizeLab\StoreSuite\Cache;
use PluginizeLab\StoreSuite\Modules\InventoryManager\Installer;
use PluginizeLab\StoreSuite\Modules\InventoryManager\RestController;
use PluginizeLab\StoreSuite\Modules\InventoryManager\StockLog;
use WC_Product_Simple;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Tests for the storesuite/v1/inventory endpoints, dispatched through a real
 * REST server. The routes normally register from Module::boot(); the module
 * is not active in the test install, so each test registers them on
 * rest_api_init the same way boot() does.
 */
class RestControllerTest extends WP_UnitTestCase {

	/**
	 * User with manage_woocommerce (passes storesuite_current_user_can).
	 *
	 * @var int
	 */
	private static $shop_manager_id;

	/**
	 * User without inventory access.
	 *
	 * @var int
	 */
	private static $customer_id;

	/**
	 * Create shared users once for the class.
	 *
	 * @param \WP_UnitTest_Factory $factory Fixture factory.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$shop_manager_id = $factory->user->create( array( 'role' => 'shop_manager' ) );
		self::$customer_id     = $factory->user->create( array( 'role' => 'customer' ) );
	}

	/**
	 * Fresh REST server with the inventory routes; clean log + cache.
	 */
	public function set_up() {
		parent::set_up();

		global $wpdb, $wp_rest_server;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( 'DELETE FROM ' . Installer::stock_log_table() );
		Cache::delete( RestController::CACHE_KEY );

		$wp_rest_server = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		add_action(
			'rest_api_init',
			function () {
				( new RestController() )->register_routes();
			}
		);

		wp_set_current_user( self::$shop_manager_id );
	}

	/**
	 * Drop the REST server global.
	 */
	public function tear_down() {
		global $wp_rest_server;
		$wp_rest_server = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		parent::tear_down();
	}

	/**
	 * Dispatch a request through the real REST server.
	 *
	 * @param string $method HTTP method.
	 * @param string $route  Route below /storesuite/v1.
	 * @param array  $params Request params.
	 * @return \WP_REST_Response
	 */
	private function dispatch( $method, $route, array $params = array() ) {
		$request = new WP_REST_Request( $method, '/storesuite/v1' . $route );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Create a published, stock-managed product.
	 *
	 * @param string $title Product name.
	 * @param int    $qty   Stock quantity.
	 * @return WC_Product_Simple
	 */
	private function create_product( $title, $qty ) {
		$product = new WC_Product_Simple();
		$product->set_name( $title );
		$product->set_regular_price( '10' );
		$product->set_manage_stock( true );
		$product->set_stock_quantity( $qty );
		$product->save();

		return $product;
	}

	/*
	|-----------------------------------------------------------------------
	| Permissions
	|-----------------------------------------------------------------------
	*/

	public function test_inventory_routes_require_the_manage_inventory_area() {
		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->dispatch( 'GET', '/inventory' )->get_status() );

		wp_set_current_user( self::$customer_id );
		$this->assertSame( 403, $this->dispatch( 'GET', '/inventory' )->get_status() );
		$this->assertSame( 403, $this->dispatch( 'GET', '/inventory/log' )->get_status() );
		$this->assertSame( 403, $this->dispatch( 'GET', '/inventory/low-stock' )->get_status() );

		wp_set_current_user( self::$shop_manager_id );
		$this->assertSame( 200, $this->dispatch( 'GET', '/inventory' )->get_status(), 'manage_woocommerce holders pass every StoreSuite area.' );
	}

	/*
	|-----------------------------------------------------------------------
	| GET /inventory
	|-----------------------------------------------------------------------
	*/

	public function test_list_returns_items_low_total_and_pagination_headers() {
		$this->create_product( 'Healthy', 10 );
		$this->create_product( 'Nearly Gone', 1 ); // Below the default threshold of 2.

		$response = $this->dispatch( 'GET', '/inventory' );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 2, $data['total'] );
		$this->assertCount( 2, $data['items'] );
		$this->assertSame( 1, $data['totals']['low'], 'The low-stock banner total counts only low items.' );

		$headers = $response->get_headers();
		$this->assertSame( '2', $headers['X-WP-Total'] );
		$this->assertSame( '1', $headers['X-WP-TotalPages'] );
	}

	public function test_list_low_only_filter() {
		$this->create_product( 'Healthy', 10 );
		$low = $this->create_product( 'Nearly Gone', 1 );

		$data = $this->dispatch( 'GET', '/inventory', array( 'low_only' => true ) )->get_data();

		$this->assertSame( 1, $data['total'] );
		$this->assertSame( $low->get_id(), $data['items'][0]['id'] );
	}

	/*
	|-----------------------------------------------------------------------
	| POST /inventory/{id}/stock
	|-----------------------------------------------------------------------
	*/

	public function test_set_item_stock_updates_logs_and_returns_the_fresh_item() {
		$product = $this->create_product( 'Adjustable', 3 );

		$response = $this->dispatch( 'POST', '/inventory/' . $product->get_id() . '/stock', array( 'qty' => 9 ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 9, $data['item']['stock_qty'] );
		$this->assertSame( 9, wc_get_product( $product->get_id() )->get_stock_quantity() );

		$log = StockLog::query( array( 'product_id' => $product->get_id() ) );
		$this->assertSame( 'manual', $log['items'][0]['change_type'] );
	}

	public function test_set_item_stock_returns_400_for_unknown_products() {
		$response = $this->dispatch( 'POST', '/inventory/999999/stock', array( 'qty' => 9 ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'storesuite_no_product', $response->as_error()->get_error_code() );
	}

	/*
	|-----------------------------------------------------------------------
	| POST /inventory/bulk
	|-----------------------------------------------------------------------
	*/

	public function test_bulk_update_supports_set_increase_and_decrease() {
		$a = $this->create_product( 'Bulk A', 5 );
		$b = $this->create_product( 'Bulk B', 5 );

		$data = $this->dispatch(
			'POST',
			'/inventory/bulk',
			array(
				'ids' => array( $a->get_id(), $b->get_id() ),
				'op'  => 'increase',
				'qty' => 3,
			)
		)->get_data();
		$this->assertSame( 2, $data['updated'] );
		$this->assertSame( 8, wc_get_product( $a->get_id() )->get_stock_quantity() );
		$this->assertSame( 8, wc_get_product( $b->get_id() )->get_stock_quantity() );

		$this->dispatch(
			'POST',
			'/inventory/bulk',
			array(
				'ids' => array( $a->get_id() ),
				'op'  => 'decrease',
				'qty' => 100,
			)
		);
		$this->assertSame( 0, wc_get_product( $a->get_id() )->get_stock_quantity(), 'Decrease clamps at zero.' );

		$this->dispatch(
			'POST',
			'/inventory/bulk',
			array(
				'ids' => array( $b->get_id() ),
				'op'  => 'set',
				'qty' => 1,
			)
		);
		$this->assertSame( 1, wc_get_product( $b->get_id() )->get_stock_quantity() );

		// Bulk changes are logged with their own change type.
		$log = StockLog::query( array( 'product_id' => $a->get_id() ) );
		$this->assertSame( 'bulk', $log['items'][0]['change_type'] );
	}

	public function test_bulk_update_skips_unknown_ids_and_rejects_an_empty_selection() {
		$a = $this->create_product( 'Bulk A', 5 );

		$data = $this->dispatch(
			'POST',
			'/inventory/bulk',
			array(
				'ids' => array( $a->get_id(), 999999 ),
				'op'  => 'set',
				'qty' => 2,
			)
		)->get_data();
		$this->assertSame( 1, $data['updated'], 'Unknown IDs are skipped, not counted.' );

		$response = $this->dispatch( 'POST', '/inventory/bulk', array( 'ids' => array() ) );
		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'storesuite_no_products', $response->as_error()->get_error_code() );
	}

	/*
	|-----------------------------------------------------------------------
	| GET /inventory/log
	|-----------------------------------------------------------------------
	*/

	public function test_log_rows_are_enriched_with_product_and_user_names() {
		$product = $this->create_product( 'Logged', 5 );
		$this->dispatch( 'POST', '/inventory/' . $product->get_id() . '/stock', array( 'qty' => 4 ) );

		$data = $this->dispatch( 'GET', '/inventory/log', array( 'product' => $product->get_id() ) )->get_data();

		$this->assertSame( 1, $data['total'] );
		$row = $data['items'][0];
		$this->assertSame( 'Logged', $row['product_name'] );
		$this->assertSame( get_userdata( self::$shop_manager_id )->display_name, $row['user_name'] );
		$this->assertSame( 5, $row['qty_before'] );
		$this->assertSame( 4, $row['qty_after'] );
	}

	/*
	|-----------------------------------------------------------------------
	| GET /inventory/low-stock (cached feed)
	|-----------------------------------------------------------------------
	*/

	public function test_low_stock_feed_lists_low_items_and_primes_the_cache() {
		$this->create_product( 'Healthy', 10 );
		$low = $this->create_product( 'Nearly Gone', 1 );

		$data = $this->dispatch( 'GET', '/inventory/low-stock' )->get_data();

		$this->assertCount( 1, $data );
		$this->assertSame( $low->get_id(), $data[0]['id'] );
		$this->assertTrue( Cache::has( RestController::CACHE_KEY ), 'The feed result is cached for the dashboard widget.' );
	}

	public function test_low_stock_feed_serves_from_cache_until_a_stock_change_busts_it() {
		$product = $this->create_product( 'Nearly Gone', 1 );

		// Poison the cache on purpose: a cached response must win…
		Cache::set( RestController::CACHE_KEY, array( array( 'id' => 424242 ) ), HOUR_IN_SECONDS );
		$data = $this->dispatch( 'GET', '/inventory/low-stock' )->get_data();
		$this->assertSame( 424242, $data[0]['id'] );

		// …until any stock change busts it via the registered hooks.
		RestController::register_cache_busting();
		wc_update_product_stock( $product, 0 );
		$this->assertFalse( Cache::has( RestController::CACHE_KEY ) );

		$data = $this->dispatch( 'GET', '/inventory/low-stock' )->get_data();
		$this->assertSame( $product->get_id(), $data[0]['id'] );
	}
}
