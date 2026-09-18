<?php
/**
 * REST\ChangelogController tests: permission gating and the readme.txt
 * changelog parser exposed at GET /storesuite/v1/changelog.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Tests\REST;

use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Dispatches real REST requests so route registration, the permission
 * callback, and the parser all run together. The parser reads the plugin's
 * actual readme.txt, so assertions compare against that file rather than a
 * fixture — the endpoint's job is to faithfully reflect it.
 */
class ChangelogControllerTest extends WP_UnitTestCase {

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
	 * Fresh REST server per test.
	 */
	public function set_up() {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/**
	 * Drop the REST server for whoever runs next.
	 */
	public function tear_down() {
		global $wp_rest_server;
		$wp_rest_server = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		parent::tear_down();
	}

	/**
	 * GET /storesuite/v1/changelog through the real REST server.
	 *
	 * @return \WP_REST_Response
	 */
	private function dispatch() {
		return rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/storesuite/v1/changelog' ) );
	}

	public function test_logged_out_request_is_rejected_with_401() {
		wp_set_current_user( 0 );

		$this->assertSame( 401, $this->dispatch()->get_status() );
	}

	public function test_shop_manager_without_manage_options_is_rejected_with_403() {
		wp_set_current_user( self::$shop_manager_id );

		$this->assertSame( 403, $this->dispatch()->get_status() );
	}

	public function test_response_reports_the_running_plugin_version() {
		wp_set_current_user( self::$admin_id );

		$response = $this->dispatch();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( STORESUITE_PLUGIN_VERSION, $response->get_data()['current_version'] );
	}

	public function test_releases_are_parsed_from_readme_newest_first() {
		wp_set_current_user( self::$admin_id );

		$releases = $this->dispatch()->get_data()['releases'];

		$this->assertNotEmpty( $releases, 'The shipped readme.txt has a changelog; parsing it must yield releases.' );

		// The first release block in readme.txt must be the first item returned.
		$readme = file_get_contents( STORESUITE_DIR . '/readme.txt' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		preg_match( '/^==\s*Changelog\s*==.*?^=\s*(.+?)\s*=\s*$/msi', $readme, $matches );

		$this->assertSame( $matches[1], $releases[0]['version'] );
	}

	public function test_release_entries_are_trimmed_lines_without_bullet_markers() {
		wp_set_current_user( self::$admin_id );

		$releases = $this->dispatch()->get_data()['releases'];

		foreach ( $releases as $release ) {
			$this->assertNotEmpty( $release['entries'], "Release {$release['version']} must not be empty." );

			foreach ( $release['entries'] as $entry ) {
				$this->assertIsString( $entry );
				$this->assertNotSame( '', trim( $entry ) );
				$this->assertStringStartsNotWith( '* ', $entry, 'Leading bullet markers are stripped.' );
			}
		}
	}
}
