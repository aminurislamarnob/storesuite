<?php
/**
 * REST\ModulesController tests: permissions, module listing, activate /
 * deactivate endpoints, per-module settings, and admin-tab sanitization.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Tests\REST;

use PluginizeLab\StoreSuite\Module\Manager;
use PluginizeLab\StoreSuite\Tests\Fixtures\FixtureModule;
use ReflectionClass;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Dispatches real REST requests against the routes StoreSuite registers on
 * `rest_api_init`, so the full stack (route args, permission callbacks,
 * handlers) is exercised.
 *
 * The controller resolves the module manager from the StoreSuite singleton
 * (`pluginizelab_storesuite()->modules`), whose discovery is memoized per
 * instance. Each test therefore resets that manager's registry via reflection
 * and re-injects fixtures through the `storesuite_register_modules` filter.
 */
class ModulesControllerTest extends WP_UnitTestCase {

	const FAKE_DEP = 'fake-dependency/fake-dependency.php';

	/**
	 * User ID with manage_options.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * User ID with manage_woocommerce but not manage_options.
	 *
	 * @var int
	 */
	private static $shop_manager_id;

	/**
	 * Create shared users once for the class.
	 *
	 * @param \WP_UnitTest_Factory $factory Fixture factory.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$admin_id        = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$shop_manager_id = $factory->user->create( array( 'role' => 'shop_manager' ) );
	}

	/**
	 * Fresh REST server + a clean singleton module registry per test.
	 */
	public function set_up() {
		parent::set_up();

		delete_option( Manager::ACTIVE_OPTION );

		global $wp_rest_server;
		$wp_rest_server = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->reset_singleton_manager();
	}

	/**
	 * Leave the singleton manager empty-but-resettable for whoever runs next.
	 */
	public function tear_down() {
		$this->reset_singleton_manager();
		delete_option( Manager::ACTIVE_OPTION );

		global $wp_rest_server;
		$wp_rest_server = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		parent::tear_down();
	}

	/**
	 * Blank out the singleton manager's memoized discovery so the filters this
	 * test adds are honored on the next `get_all()`.
	 */
	private function reset_singleton_manager() {
		$manager    = pluginizelab_storesuite()->modules;
		$reflection = new ReflectionClass( Manager::class );

		$discovered = $reflection->getProperty( 'discovered' );
		$discovered->setAccessible( true );
		$discovered->setValue( $manager, false );

		$modules = $reflection->getProperty( 'modules' );
		$modules->setAccessible( true );
		$modules->setValue( $manager, array() );
	}

	/**
	 * Register fixture modules with the singleton manager and log in as admin.
	 *
	 * @param FixtureModule[] $modules Fixtures to expose over REST.
	 */
	private function install_fixtures( array $modules ) {
		add_filter(
			'storesuite_modules_dir',
			function () {
				return sys_get_temp_dir() . '/storesuite-tests-no-such-dir';
			}
		);
		add_filter(
			'storesuite_register_modules',
			function ( $registered ) use ( $modules ) {
				foreach ( $modules as $module ) {
					$registered[ $module->get_slug() ] = $module;
				}
				return $registered;
			}
		);

		wp_set_current_user( self::$admin_id );
	}

	/**
	 * Dispatch a request through the real REST server.
	 *
	 * @param string $method HTTP method.
	 * @param string $route  Route below the namespace, e.g. '/modules'.
	 * @param array  $body   JSON body for POST requests.
	 * @return \WP_REST_Response
	 */
	private function dispatch( $method, $route, array $body = array() ) {
		$request = new WP_REST_Request( $method, '/storesuite/v1' . $route );

		if ( $body ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( wp_json_encode( $body ) );
		}

		return rest_get_server()->dispatch( $request );
	}

	/*
	|-----------------------------------------------------------------------
	| Permissions
	|-----------------------------------------------------------------------
	*/

	public function test_logged_out_request_is_rejected_with_401() {
		$this->install_fixtures( array( new FixtureModule( 'plain' ) ) );
		wp_set_current_user( 0 );

		$response = $this->dispatch( 'GET', '/modules' );

		$this->assertSame( 401, $response->get_status() );
	}

	public function test_shop_manager_without_manage_options_is_rejected_with_403() {
		$this->install_fixtures( array( new FixtureModule( 'plain' ) ) );
		wp_set_current_user( self::$shop_manager_id );

		$response = $this->dispatch( 'GET', '/modules' );

		$this->assertSame( 403, $response->get_status() );
	}

	/*
	|-----------------------------------------------------------------------
	| GET /modules
	|-----------------------------------------------------------------------
	*/

	public function test_get_items_returns_module_metadata_and_active_state() {
		$active   = new FixtureModule( 'active-mod' );
		$inactive = new FixtureModule( 'inactive-mod' );
		$this->install_fixtures( array( $active, $inactive ) );

		pluginizelab_storesuite()->modules->activate( 'active-mod' );

		$response = $this->dispatch( 'GET', '/modules' );
		$this->assertSame( 200, $response->get_status() );

		$items = $response->get_data();
		$this->assertCount( 2, $items );

		$by_slug = array_column( $items, null, 'slug' );
		$this->assertTrue( $by_slug['active-mod']['active'] );
		$this->assertFalse( $by_slug['inactive-mod']['active'] );
		$this->assertSame( 'Fixture: active-mod', $by_slug['active-mod']['name'] );
		$this->assertFalse( $by_slug['active-mod']['has_settings'] );
		$this->assertSame( array(), $by_slug['active-mod']['admin_tabs'] );
	}

	public function test_get_items_sanitizes_module_admin_tabs() {
		$module             = new FixtureModule( 'tabbed' );
		$module->admin_tabs = array(
			array(
				'to'    => '/good',
				'label' => 'Good',
				'extra' => 'dropped',
			),
			array(
				'to'    => 'https://evil.example/phish',
				'label' => 'Absolute URL',
			),
			array(
				'to'    => '//evil.example',
				'label' => 'Protocol-relative',
			),
			array(
				'to'    => '/has"quote',
				'label' => 'HTML chars',
			),
			array( 'to' => '/missing-label' ),
			'not-an-array',
		);
		$this->install_fixtures( array( $module ) );

		$response = $this->dispatch( 'GET', '/modules' );
		$items    = $response->get_data();

		$this->assertSame(
			array(
				array(
					'to'    => '/good',
					'label' => 'Good',
				),
			),
			$items[0]['admin_tabs'],
			'Only rooted, HTML-safe { to, label } pairs may cross the REST boundary.'
		);
	}

	/*
	|-----------------------------------------------------------------------
	| POST /modules/{slug}/activate and /deactivate
	|-----------------------------------------------------------------------
	*/

	public function test_activate_unknown_module_returns_404() {
		$this->install_fixtures( array() );

		$response = $this->dispatch( 'POST', '/modules/ghost/activate' );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'storesuite_module_not_found', $response->as_error()->get_error_code() );
	}

	public function test_activate_with_unmet_requirements_returns_400_and_names_the_missing_plugins() {
		$this->install_fixtures( array( new FixtureModule( 'needs-dep', array( self::FAKE_DEP ) ) ) );

		$response = $this->dispatch( 'POST', '/modules/needs-dep/activate' );

		$this->assertSame( 400, $response->get_status() );

		$error = $response->as_error();
		$this->assertSame( 'storesuite_module_requirements_unmet', $error->get_error_code() );
		$this->assertSame( array( self::FAKE_DEP ), $error->get_error_data()['missing'] );
		$this->assertFalse( pluginizelab_storesuite()->modules->is_active( 'needs-dep' ) );
	}

	public function test_activate_returns_the_updated_module_item() {
		$this->install_fixtures( array( new FixtureModule( 'plain' ) ) );

		$response = $this->dispatch( 'POST', '/modules/plain/activate' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['active'] );
		$this->assertTrue( pluginizelab_storesuite()->modules->is_active( 'plain' ) );
	}

	public function test_deactivate_unknown_module_returns_404() {
		$this->install_fixtures( array() );

		$response = $this->dispatch( 'POST', '/modules/ghost/deactivate' );

		$this->assertSame( 404, $response->get_status() );
	}

	public function test_deactivate_returns_the_updated_module_item() {
		$this->install_fixtures( array( new FixtureModule( 'plain' ) ) );
		pluginizelab_storesuite()->modules->activate( 'plain' );

		$response = $this->dispatch( 'POST', '/modules/plain/deactivate' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( $response->get_data()['active'] );
		$this->assertFalse( pluginizelab_storesuite()->modules->is_active( 'plain' ) );
	}

	/*
	|-----------------------------------------------------------------------
	| GET/POST /modules/{slug}/settings
	|-----------------------------------------------------------------------
	*/

	/**
	 * A fixture module exposing one toggle setting.
	 *
	 * @return FixtureModule
	 */
	private function make_settings_module() {
		$module                  = new FixtureModule( 'configurable' );
		$module->has_settings    = true;
		$module->settings_schema = array(
			'enable_widget' => array(
				'type'    => 'toggle',
				'label'   => 'Enable widget',
				'default' => false,
			),
		);
		$module->settings_values = array( 'enable_widget' => false );

		return $module;
	}

	public function test_get_settings_returns_schema_and_values() {
		$this->install_fixtures( array( $this->make_settings_module() ) );

		$response = $this->dispatch( 'GET', '/modules/configurable/settings' );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'Fixture: configurable', $data['name'] );
		$this->assertArrayHasKey( 'enable_widget', $data['schema'] );
		$this->assertSame( array( 'enable_widget' => false ), $data['values'] );
	}

	public function test_get_settings_for_module_without_settings_returns_400() {
		$this->install_fixtures( array( new FixtureModule( 'plain' ) ) );

		$response = $this->dispatch( 'GET', '/modules/plain/settings' );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'storesuite_module_no_settings', $response->as_error()->get_error_code() );
	}

	public function test_update_settings_persists_known_keys_and_drops_unknown_ones() {
		$module = $this->make_settings_module();
		$this->install_fixtures( array( $module ) );

		$response = $this->dispatch(
			'POST',
			'/modules/configurable/settings',
			array(
				'values' => array(
					'enable_widget' => true,
					'not_in_schema' => 'ignored',
				),
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 'enable_widget' => true ), $response->get_data()['values'] );
		$this->assertSame( array( 'enable_widget' => true ), $module->settings_values );
	}

	public function test_update_settings_with_missing_values_key_saves_an_empty_set() {
		$module = $this->make_settings_module();
		$this->install_fixtures( array( $module ) );

		$response = $this->dispatch( 'POST', '/modules/configurable/settings', array( 'unrelated' => 1 ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array(), $response->get_data()['values'] );
	}
}
