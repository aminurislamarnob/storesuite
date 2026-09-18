<?php
/**
 * Access-control tests: Main's capability granting and admin-bar rules, and
 * the storesuite_current_user_can() permission helper.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Tests;

use PluginizeLab\StoreSuite\Main;
use WP_UnitTestCase;

/**
 * Tests for PluginizeLab\StoreSuite\Main and storesuite_current_user_can().
 *
 * The hard-redirecting methods (block_admin_access, redirect_after_login,
 * redirect_if_not_logged_in_manager) call wp_safe_redirect() + exit and can't
 * run inside PHPUnit; their decision inputs (capabilities, options, filters)
 * are what's covered here.
 */
class MainTest extends WP_UnitTestCase {

	/**
	 * Clean settings per test.
	 */
	public function set_up() {
		parent::set_up();

		delete_option( 'storesuite_settings' );
	}

	/*
	|-----------------------------------------------------------------------
	| Capability granting (user_has_cap)
	|-----------------------------------------------------------------------
	*/

	public function test_managers_implicitly_hold_every_granular_storesuite_capability() {
		new Main(); // Wires the user_has_cap filter.

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );

		$this->assertTrue( current_user_can( 'storesuite_manage_inventory' ) );
		$this->assertTrue( current_user_can( 'storesuite_anything_at_all' ), 'Any storesuite_-prefixed cap is granted on demand to managers.' );
		$this->assertFalse( current_user_can( 'some_other_cap' ), 'Non-StoreSuite caps are untouched.' );
	}

	public function test_customers_gain_no_granular_capabilities() {
		new Main();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'customer' ) ) );

		$this->assertFalse( current_user_can( 'storesuite_manage_inventory' ) );
	}

	/*
	|-----------------------------------------------------------------------
	| storesuite_current_user_can()
	|-----------------------------------------------------------------------
	*/

	public function test_area_check_passes_managers_unconditionally() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );

		// Even a filter that denies everything cannot restrict managers.
		add_filter( 'storesuite_user_can', '__return_false' );

		$this->assertTrue( storesuite_current_user_can( 'products' ) );
	}

	public function test_area_check_honors_a_granular_capability_for_staff_users() {
		$user = self::factory()->user->create( array( 'role' => 'customer' ) );
		wp_set_current_user( $user );

		$this->assertFalse( storesuite_current_user_can( 'products' ) );

		get_user_by( 'id', $user )->add_cap( 'storesuite_products' );
		// wp_set_current_user() is a no-op for the already-current ID, keeping
		// the stale capability set — bounce through 0 to force a fresh WP_User.
		wp_set_current_user( 0 );
		wp_set_current_user( $user );

		$this->assertTrue( storesuite_current_user_can( 'products' ) );
	}

	public function test_area_check_lets_the_filter_decide_for_non_managers() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'customer' ) ) );

		add_filter(
			'storesuite_user_can',
			function ( $allowed, $area, $user_id, $object_id ) {
				return 'orders' === $area && 5 === $object_id;
			},
			10,
			4
		);

		$this->assertTrue( storesuite_current_user_can( 'orders', 5 ) );
		$this->assertFalse( storesuite_current_user_can( 'orders', 6 ) );
		$this->assertFalse( storesuite_current_user_can( 'products', 5 ) );
	}

	/*
	|-----------------------------------------------------------------------
	| Admin bar
	|-----------------------------------------------------------------------
	*/

	public function test_admin_bar_is_hidden_only_when_admin_access_is_prevented() {
		$main = new Main();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );

		$this->assertTrue( $main->hide_admin_bar( true ), 'Setting off: the incoming value passes through.' );

		update_option( 'storesuite_settings', array( 'storesuite_prevent_admin_access' => 'yes' ) );
		$this->assertFalse( $main->hide_admin_bar( true ) );

		wp_set_current_user( 0 );
		$this->assertTrue( $main->hide_admin_bar( true ), 'Logged-out visitors keep the incoming value.' );
	}

	/*
	|-----------------------------------------------------------------------
	| My Account dashboard button
	|-----------------------------------------------------------------------
	*/

	public function test_dashboard_button_renders_only_for_managers() {
		$main = new Main();
		update_option( 'storesuite_settings', array( 'storesuite_dashboard_page_id' => self::factory()->post->create( array( 'post_type' => 'page' ) ) ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
		ob_start();
		$main->add_storesuite_dashboard_btn();
		$this->assertStringContainsString( 'storesuite-dashboard-btn', ob_get_clean() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'customer' ) ) );
		ob_start();
		$main->add_storesuite_dashboard_btn();
		$this->assertSame( '', ob_get_clean() );
	}
}
