<?php
/**
 * Access-control tests for the Main service class.
 *
 * The post-login destination is a pure filter and is asserted directly; the
 * wp-admin blocking path redirects and exits, so it runs through
 * capture_redirect(). Browser-level behaviour (the login form actually
 * rendering) belongs to the Playwright e2e suite.
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test;

/**
 * @covers \PluginizeLab\StoreSuite\Main
 * @group storesuite-access-control
 */
class MainTest extends StoreSuiteTestCase {

	/**
	 * The Main instance registered in the plugin container.
	 *
	 * @return \PluginizeLab\StoreSuite\Main
	 */
	private function main() {
		return \pluginizelab_storesuite()->storesuite_main;
	}

	public function test_admin_bar_hidden_when_admin_access_is_prevented() {
		wp_set_current_user( $this->customer_id );

		$this->set_storesuite_option( 'storesuite_prevent_admin_access', 'yes' );
		$this->assertFalse( $this->main()->hide_admin_bar( true ) );

		$this->set_storesuite_option( 'storesuite_prevent_admin_access', 'no' );
		$this->assertTrue( $this->main()->hide_admin_bar( true ) );
	}

	public function test_admin_bar_untouched_for_logged_out_visitors() {
		wp_set_current_user( 0 );
		$this->set_storesuite_option( 'storesuite_prevent_admin_access', 'yes' );

		$this->assertTrue( $this->main()->hide_admin_bar( true ) );
		$this->assertFalse( $this->main()->hide_admin_bar( false ) );
	}

	public function test_my_account_dashboard_button_requires_manage_woocommerce() {
		$this->create_dashboard_page();

		wp_set_current_user( $this->shop_manager_id );
		ob_start();
		$this->main()->add_storesuite_dashboard_btn();
		$html = ob_get_clean();
		$this->assertStringContainsString( 'storesuite-dashboard-btn', $html );

		wp_set_current_user( $this->customer_id );
		ob_start();
		$this->main()->add_storesuite_dashboard_btn();
		$html = ob_get_clean();
		$this->assertSame( '', $html, 'Customers must not see the StoreSuite dashboard button.' );
	}

	public function test_login_redirect_leaves_url_alone_when_no_user_logged_in() {
		// Core applies login_redirect on every render of the wp-login.php form
		// with a WP_Error user; hijacking that sent the login page to My Account.
		$this->create_dashboard_page();
		$form_url = 'https://example.org/wp-admin/';
		$error    = new \WP_Error( 'empty_username', 'No username.' );

		$this->assertSame( $form_url, $this->main()->redirect_after_login( $form_url, $error ) );
		$this->assertSame( $form_url, $this->main()->filter_login_redirect( $form_url, '', $error ) );
		$this->assertSame( $form_url, $this->main()->redirect_after_login( $form_url, '' ) );
		$this->assertSame( $form_url, $this->main()->redirect_after_login( $form_url, new \WP_User( 0 ) ) );
	}

	public function test_login_redirect_sends_admins_to_wp_admin() {
		$this->create_dashboard_page();

		$this->assertSame( admin_url(), $this->main()->redirect_after_login( '', get_user_by( 'id', $this->admin_id ) ) );
	}

	public function test_login_redirect_sends_managers_to_storesuite_dashboard() {
		$this->create_dashboard_page();
		$manager = get_user_by( 'id', $this->shop_manager_id );

		$this->assertSame( storesuite_get_navigation_url(), $this->main()->redirect_after_login( '', $manager ) );
		$this->assertSame( storesuite_get_navigation_url(), $this->main()->filter_login_redirect( '', '', $manager ) );
	}

	public function test_login_redirect_sends_managers_to_my_account_without_dashboard_page() {
		$this->set_storesuite_option( 'storesuite_dashboard_page_id', 0 );

		$this->assertSame(
			wc_get_page_permalink( 'myaccount' ),
			$this->main()->redirect_after_login( '', get_user_by( 'id', $this->shop_manager_id ) )
		);
	}

	public function test_login_redirect_sends_customers_to_my_account() {
		$this->create_dashboard_page();

		$this->assertSame(
			wc_get_page_permalink( 'myaccount' ),
			$this->main()->redirect_after_login( '', get_user_by( 'id', $this->customer_id ) )
		);
	}

	public function test_blocked_manager_lands_on_storesuite_dashboard() {
		$this->create_dashboard_page();
		$this->set_storesuite_option( 'storesuite_prevent_admin_access', 'yes' );
		$this->set_admin_request( $this->shop_manager_id );

		$this->assertSame( storesuite_get_navigation_url(), $this->capture_redirect( array( $this->main(), 'block_admin_access' ) ) );
	}

	public function test_blocked_manager_lands_on_home_without_dashboard_page() {
		$this->set_storesuite_option( 'storesuite_dashboard_page_id', 0 );
		$this->set_storesuite_option( 'storesuite_prevent_admin_access', 'yes' );
		$this->set_admin_request( $this->shop_manager_id );

		$this->assertSame( home_url(), $this->capture_redirect( array( $this->main(), 'block_admin_access' ) ) );
	}

	public function test_blocked_customer_lands_on_home() {
		$this->create_dashboard_page();
		$this->set_storesuite_option( 'storesuite_prevent_admin_access', 'yes' );
		$this->set_admin_request( $this->customer_id );

		$this->assertSame( home_url(), $this->capture_redirect( array( $this->main(), 'block_admin_access' ) ) );
	}

	public function test_admin_access_not_blocked_for_administrators_or_when_disabled() {
		$this->create_dashboard_page();

		$this->set_storesuite_option( 'storesuite_prevent_admin_access', 'yes' );
		$this->set_admin_request( $this->admin_id );
		$this->assertNull( $this->capture_redirect( array( $this->main(), 'block_admin_access' ) ) );

		$this->set_storesuite_option( 'storesuite_prevent_admin_access', 'no' );
		$this->set_admin_request( $this->shop_manager_id );
		$this->assertNull( $this->capture_redirect( array( $this->main(), 'block_admin_access' ) ) );
	}

	/**
	 * Pretend the given user is loading a regular wp-admin screen.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	private function set_admin_request( $user_id ) {
		wp_set_current_user( $user_id );
		$GLOBALS['pagenow'] = 'index.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Simulating an admin screen request.
	}
}
