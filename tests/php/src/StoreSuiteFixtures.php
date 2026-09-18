<?php
/**
 * Shared fixtures and helpers for the StoreSuite test cases.
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test;

use PluginizeLab\StoreSuite\Test\Factories\StoreSuiteFactory;

/**
 * Fixtures shared by StoreSuiteTestCase and StoreSuiteAjaxTestCase.
 *
 * Lives in a trait because the AJAX base must extend WP_Ajax_UnitTestCase and
 * therefore cannot inherit from StoreSuiteTestCase.
 */
trait StoreSuiteFixtures {

	/**
	 * Administrator user fixture, created fresh for every test.
	 *
	 * @var int
	 */
	protected $admin_id;

	/**
	 * Shop manager user fixture, created fresh for every test.
	 *
	 * @var int
	 */
	protected $shop_manager_id;

	/**
	 * Customer user fixture, created fresh for every test.
	 *
	 * @var int
	 */
	protected $customer_id;

	/**
	 * Shared factory instance.
	 *
	 * @var StoreSuiteFactory|null
	 */
	protected static $storesuite_factory = null;

	/**
	 * Replace the core factory with the StoreSuite one.
	 *
	 * Adds `product` and `coupon` entity factories on top of everything
	 * WP_UnitTest_Factory already provides (post, user, term, ...).
	 *
	 * @return StoreSuiteFactory
	 */
	protected static function factory() {
		if ( ! static::$storesuite_factory ) {
			static::$storesuite_factory = new StoreSuiteFactory();
		}

		return static::$storesuite_factory;
	}

	/**
	 * Create the standard user fixtures (admin, shop manager, customer).
	 *
	 * The database transaction rollback in tear_down() removes them again.
	 *
	 * @return void
	 */
	protected function create_storesuite_users() {
		$this->admin_id        = static::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->shop_manager_id = static::factory()->user->create( array( 'role' => 'shop_manager' ) );
		$this->customer_id     = static::factory()->user->create( array( 'role' => 'customer' ) );
	}

	/**
	 * Write one key into the serialized storesuite_settings option.
	 *
	 * Mirrors how the plugin stores settings, so storesuite_get_option_by_key()
	 * picks the value up.
	 *
	 * @param string $key   Setting key, e.g. 'storesuite_prevent_admin_access'.
	 * @param mixed  $value Setting value.
	 * @return void
	 */
	protected function set_storesuite_option( $key, $value ) {
		$settings         = get_option( 'storesuite_settings', array() );
		$settings         = is_array( $settings ) ? $settings : array();
		$settings[ $key ] = $value;

		update_option( 'storesuite_settings', $settings );
	}

	/**
	 * Create a dashboard page with the [storesuite_dashboard] shortcode and
	 * register it as the plugin's dashboard page.
	 *
	 * @return int Page ID.
	 */
	protected function create_dashboard_page() {
		$page_id = static::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'StoreSuite Dashboard',
				'post_content' => '[storesuite_dashboard]',
			)
		);

		$this->set_storesuite_option( 'storesuite_dashboard_page_id', $page_id );

		return $page_id;
	}

	/**
	 * Run a callback and capture the URL it redirects to.
	 *
	 * Production code paths end redirects with exit, which would kill the test
	 * process. The wp_redirect filter runs before the Location header is sent,
	 * so throwing there unwinds the stack back into the test after all side
	 * effects (status updates, deletes) have already happened.
	 *
	 * @param callable $callback Code expected to call wp_redirect()/wp_safe_redirect().
	 * @return string|null The redirect target, or null when no redirect happened.
	 */
	protected function capture_redirect( callable $callback ) {
		$captured    = null;
		$interceptor = function ( $location ) use ( &$captured ) {
			$captured = $location;
			throw new StoreSuiteRedirectException( (string) $location );
		};

		add_filter( 'wp_redirect', $interceptor, 1 );

		try {
			$callback();
		} catch ( StoreSuiteRedirectException $e ) {
			unset( $e ); // Expected control flow: the redirect was intercepted.
		} finally {
			remove_filter( 'wp_redirect', $interceptor, 1 );
		}

		return $captured;
	}

	/**
	 * REST server instance for the current test.
	 *
	 * @var \WP_REST_Server|null
	 */
	protected $rest_server = null;

	/**
	 * Boot a fresh REST server and fire rest_api_init so the plugin's routes
	 * (registered by StoreSuite::register_rest_route()) are available.
	 *
	 * @return \WP_REST_Server
	 */
	protected function set_up_rest_server() {
		global $wp_rest_server;

		$wp_rest_server    = new \WP_REST_Server(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Standard REST test-suite setup.
		$this->rest_server = $wp_rest_server;

		do_action( 'rest_api_init', $wp_rest_server );

		return $wp_rest_server;
	}

	/**
	 * Dispatch a REST request and return the response.
	 *
	 * @param string $method HTTP method.
	 * @param string $route  Route, e.g. '/storesuite/v1/settings'.
	 * @param array  $params Request parameters.
	 * @return \WP_REST_Response
	 */
	protected function do_rest_request( $method, $route, $params = array() ) {
		if ( ! $this->rest_server ) {
			$this->set_up_rest_server();
		}

		$request = new \WP_REST_Request( $method, $route );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return rest_do_request( $request );
	}

	/**
	 * Drop the per-test REST server so it cannot leak into the next test.
	 *
	 * @return void
	 */
	protected function reset_rest_server() {
		$GLOBALS['wp_rest_server'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Standard REST test-suite reset.
		$this->rest_server         = null;
	}

	/**
	 * Rebuild the wp_roles singleton.
	 *
	 * The transaction rollback restores the roles option, but in-memory
	 * add_cap()/remove_cap() mutations survive on the singleton and would leak
	 * into the next test without this reset.
	 *
	 * @return void
	 */
	protected function reset_role_singleton() {
		$GLOBALS['wp_roles'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Standard test-suite role reset.
		wp_roles();
	}
}
