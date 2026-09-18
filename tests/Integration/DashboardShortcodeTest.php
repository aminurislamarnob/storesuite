<?php
/**
 * Dashboard shortcode integration tests: [storesuite_dashboard] rendering —
 * access gating, per-area permission denial, and query-var → template routing.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Tests\Integration;

use WP_UnitTestCase;

/**
 * Renders the real shortcode with real templates. Routing is driven the same
 * way WordPress does it on a live request: by populating $wp->query_vars
 * before do_shortcode() runs.
 */
class DashboardShortcodeTest extends WP_UnitTestCase {

	/**
	 * Reset query vars so routing state never leaks between tests.
	 */
	public function set_up() {
		parent::set_up();
		$GLOBALS['wp']->query_vars = array();
	}

	/**
	 * Restore query vars for whoever runs next.
	 */
	public function tear_down() {
		$GLOBALS['wp']->query_vars = array();
		parent::tear_down();
	}

	/**
	 * Render the shortcode with the given query vars in place.
	 *
	 * @param array $query_vars Simulated main-request query vars.
	 * @return string Rendered output.
	 */
	private function render( array $query_vars = array() ) {
		$GLOBALS['wp']->query_vars = $query_vars;

		return do_shortcode( '[storesuite_dashboard]' );
	}

	public function test_logged_out_visitor_sees_the_access_denied_message() {
		wp_set_current_user( 0 );

		$this->assertSame( 'You have no permission to view this page', $this->render() );
	}

	public function test_customer_without_dashboard_capability_sees_the_access_denied_message() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'customer' ) ) );

		$this->assertSame( 'You have no permission to view this page', $this->render() );
	}

	public function test_shop_manager_gets_the_categories_screen_for_the_categories_query_var() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );

		$output = $this->render(
			array(
				'pagename'   => 'storesuite-dashboard',
				'categories' => '',
			)
		);

		$this->assertStringContainsString( 'my-storesuite-container', $output, 'The dashboard shell must render.' );
		$this->assertStringContainsString( 'storesuite-dashboard-sidebar', $output );
		$this->assertStringNotContainsString( 'Permission denied', $output );
	}

	public function test_area_denied_by_the_permission_filter_renders_the_no_permission_template() {
		// A user who may enter the dashboard but is denied the categories area.
		// Managers short-circuit storesuite_current_user_can(), so this models
		// a granular-permission role (e.g. from a permissions module).
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'customer' ) ) );
		add_filter(
			'storesuite_user_can',
			function ( $allowed, $area ) {
				return 'access_dashboard' === $area;
			},
			10,
			2
		);

		$output = $this->render(
			array(
				'pagename'   => 'storesuite-dashboard',
				'categories' => '',
			)
		);

		$this->assertStringContainsString( 'Permission denied', $output );
		$this->assertStringNotContainsString( 'my-storesuite-container', $output );
	}
}
