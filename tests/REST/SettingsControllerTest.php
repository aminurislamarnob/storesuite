<?php
/**
 * SettingsController and ChangelogController REST tests: permissions,
 * settings persistence and sanitization, and readme changelog parsing.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Tests\REST;

use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Tests for the storesuite/v1 settings and changelog endpoints, dispatched
 * through a real REST server so route registration and permission callbacks
 * are covered too.
 */
class SettingsControllerTest extends WP_UnitTestCase {

	/**
	 * User ID with manage_options.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * User ID without manage_options.
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
	 * Fresh REST server and clean settings per test; default to admin.
	 */
	public function set_up() {
		parent::set_up();

		delete_option( 'storesuite_settings' );

		global $wp_rest_server;
		$wp_rest_server = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		wp_set_current_user( self::$admin_id );
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
	 * @param string $route  Route below the namespace.
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

	public function test_settings_require_manage_options() {
		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->dispatch( 'GET', '/settings' )->get_status() );

		wp_set_current_user( self::$shop_manager_id );
		$this->assertSame( 403, $this->dispatch( 'GET', '/settings' )->get_status() );
		$this->assertSame( 403, $this->dispatch( 'POST', '/settings', array( 'storesuite_product_per_page' => '5' ) )->get_status() );
	}

	public function test_changelog_requires_manage_options() {
		wp_set_current_user( self::$shop_manager_id );

		$this->assertSame( 403, $this->dispatch( 'GET', '/changelog' )->get_status() );
	}

	/*
	|-----------------------------------------------------------------------
	| GET/POST /settings
	|-----------------------------------------------------------------------
	*/

	public function test_get_settings_returns_the_stored_option() {
		update_option( 'storesuite_settings', array( 'storesuite_product_per_page' => '12' ) );

		$response = $this->dispatch( 'GET', '/settings' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 'storesuite_product_per_page' => '12' ), $response->get_data() );
	}

	public function test_update_settings_persists_only_the_sent_keys() {
		update_option( 'storesuite_settings', array( 'storesuite_order_per_page' => '20' ) );

		$response = $this->dispatch( 'POST', '/settings', array( 'storesuite_product_per_page' => '15' ) );

		$this->assertSame( 200, $response->get_status() );

		$stored = get_option( 'storesuite_settings' );
		$this->assertSame( '15', $stored['storesuite_product_per_page'] );
		$this->assertSame( '20', $stored['storesuite_order_per_page'], 'Untouched keys must survive a partial update.' );
	}

	public function test_ai_field_toggles_persist_and_invalid_values_fail_schema_validation() {
		$response = $this->dispatch(
			'POST',
			'/settings',
			array(
				'storesuite_ai_field_title'       => 'no',
				'storesuite_ai_field_description' => 'yes',
			)
		);
		$this->assertSame( 200, $response->get_status() );

		$stored = get_option( 'storesuite_settings' );
		$this->assertSame( 'no', $stored['storesuite_ai_field_title'] );
		$this->assertSame( 'yes', $stored['storesuite_ai_field_description'] );

		// The route args are derived from the item schema, so a value outside
		// the yes/no enum is rejected by REST validation before the handler
		// (and its "anything but no means yes" normalization) ever runs.
		$response = $this->dispatch( 'POST', '/settings', array( 'storesuite_ai_field_title' => 'anything-else' ) );
		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $response->as_error()->get_error_code() );
	}

	public function test_invalid_palette_mode_is_rejected_silently() {
		$this->dispatch( 'POST', '/settings', array( 'storesuite_color_palette_mode' => 'neon-chaos' ) );

		$stored = get_option( 'storesuite_settings' );
		$this->assertArrayNotHasKey( 'storesuite_color_palette_mode', (array) $stored );

		$this->dispatch( 'POST', '/settings', array( 'storesuite_color_palette_mode' => 'custom' ) );
		$this->assertSame( 'custom', get_option( 'storesuite_settings' )['storesuite_color_palette_mode'] );
	}

	public function test_sidebar_logo_must_be_a_real_image_attachment() {
		// A bogus ID is dropped (and clears any previous value).
		update_option( 'storesuite_settings', array( 'storesuite_dashboard_sidebar_logo_id' => 5 ) );
		$this->dispatch( 'POST', '/settings', array( 'storesuite_dashboard_sidebar_logo_id' => 999999 ) );
		$this->assertArrayNotHasKey( 'storesuite_dashboard_sidebar_logo_id', (array) get_option( 'storesuite_settings' ) );

		// A real image attachment is accepted.
		$attachment_id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$this->dispatch( 'POST', '/settings', array( 'storesuite_dashboard_sidebar_logo_id' => $attachment_id ) );
		$this->assertSame( $attachment_id, get_option( 'storesuite_settings' )['storesuite_dashboard_sidebar_logo_id'] );
	}

	public function test_color_values_accept_hex_and_fall_back_to_plain_text() {
		$this->dispatch(
			'POST',
			'/settings',
			array(
				'storesuite_color_button_text'       => '#ff0000',
				'storesuite_color_button_background' => 'not-a-hex<script>',
			)
		);

		$stored = get_option( 'storesuite_settings' );
		$this->assertSame( '#ff0000', $stored['storesuite_color_button_text'] );
		$this->assertSame( 'not-a-hex', $stored['storesuite_color_button_background'], 'Non-hex values are stripped by sanitize_text_field.' );
	}

	/*
	|-----------------------------------------------------------------------
	| GET /changelog
	|-----------------------------------------------------------------------
	*/

	public function test_changelog_parses_releases_from_readme() {
		$response = $this->dispatch( 'GET', '/changelog' );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( STORESUITE_PLUGIN_VERSION, $data['current_version'] );
		$this->assertNotEmpty( $data['releases'], 'readme.txt ships a changelog, so releases must parse.' );

		$release = $data['releases'][0];
		$this->assertArrayHasKey( 'version', $release );
		$this->assertArrayHasKey( 'entries', $release );
		$this->assertNotEmpty( $release['entries'] );
		$this->assertStringNotContainsString( '* ', substr( $release['entries'][0], 0, 2 ), 'Bullet markers are stripped.' );
	}
}
