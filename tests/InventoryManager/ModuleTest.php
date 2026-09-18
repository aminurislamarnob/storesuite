<?php
/**
 * Inventory Manager Module tests: metadata, the dashboard integration filters
 * (query var, capability map, menu, endpoint title), the settings surface,
 * template permission gating, and uninstall.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Tests\InventoryManager;

use PluginizeLab\StoreSuite\Modules\InventoryManager\Emails\Manager as EmailManager;
use PluginizeLab\StoreSuite\Modules\InventoryManager\Installer;
use PluginizeLab\StoreSuite\Modules\InventoryManager\Module;
use PluginizeLab\StoreSuite\Modules\InventoryManager\Settings;
use WP_UnitTestCase;

/**
 * Tests for PluginizeLab\StoreSuite\Modules\InventoryManager\Module.
 *
 * The module is not activated through the Manager here; a directly
 * constructed instance is enough to test its pure integration surface.
 */
class ModuleTest extends WP_UnitTestCase {

	/**
	 * Build a module instance pointing at the real bootstrap file.
	 *
	 * @return Module
	 */
	private function make_module() {
		return new Module( STORESUITE_DIR . '/modules/inventory-manager/module.php' );
	}

	public function test_module_metadata() {
		$module = $this->make_module();

		$this->assertSame( 'inventory-manager', $module->get_slug() );
		$this->assertNotEmpty( $module->get_name() );
		$this->assertSame( array( 'woocommerce/woocommerce.php' ), $module->get_requires() );
		$this->assertTrue( $module->has_settings() );
		$this->assertSame( Settings::get_schema(), $module->get_settings_schema() );
	}

	public function test_dashboard_integration_filters_merge_and_pass_through_bad_input() {
		$module = $this->make_module();

		$this->assertSame(
			array(
				'existing'  => 'existing',
				'inventory' => 'inventory',
			),
			$module->register_query_var( array( 'existing' => 'existing' ) )
		);
		$this->assertSame( 'not-an-array', $module->register_query_var( 'not-an-array' ) );

		$map = $module->register_endpoint_area( array( 'products' => 'products' ) );
		$this->assertSame( 'manage_inventory', $map['inventory'] );

		$areas = $module->register_capability( array() );
		$this->assertArrayHasKey( 'manage_inventory', $areas );
	}

	public function test_menu_entry_contains_both_views_gated_on_the_inventory_capability() {
		$menus = $this->make_module()->register_menu( array() );

		$this->assertArrayHasKey( 'inventory', $menus );
		$this->assertSame( 'storesuite_manage_inventory', $menus['inventory']['permission'] );
		$this->assertArrayHasKey( 'stock-list', $menus['inventory']['submenu'] );
		$this->assertArrayHasKey( 'movement-log', $menus['inventory']['submenu'] );
		$this->assertStringContainsString( 'view=log', $menus['inventory']['submenu']['movement-log']['url'] );
	}

	public function test_endpoint_title_follows_the_view_query_arg() {
		$module = $this->make_module();

		$this->assertSame( 'Stock list', $module->endpoint_title( '' ) );

		$_GET['view'] = 'log';
		$this->assertSame( 'Movement log', $module->endpoint_title( '' ) );

		$_GET['view'] = 'unknown';
		$this->assertSame( 'Stock list', $module->endpoint_title( '' ), 'Unknown views fall back to the stock list.' );

		unset( $_GET['view'] );
	}

	/**
	 * Render load_template() output for the current user.
	 *
	 * @param array $query_vars Query vars to pass.
	 * @return string
	 */
	private function render_template( array $query_vars ) {
		ob_start();
		$this->make_module()->load_template( $query_vars );
		return ob_get_clean();
	}

	public function test_load_template_renders_nothing_off_the_inventory_endpoint() {
		$this->assertSame( '', $this->render_template( array( 'products' => '' ) ) );
	}

	public function test_load_template_shows_no_permission_to_users_without_the_area() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'customer' ) ) );

		$this->assertStringContainsString( 'Permission denied', $this->render_template( array( 'inventory' => '' ) ) );
	}

	public function test_load_template_renders_the_inventory_screen_for_managers() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );

		$html = $this->render_template( array( 'inventory' => '' ) );

		$this->assertNotSame( '', $html );
		$this->assertStringNotContainsString( 'Permission denied', $html );
	}

	public function test_activate_stamps_the_schema_version() {
		delete_option( Installer::SCHEMA_VERSION_OPTION );

		// The table already exists (bootstrap), so activate() is DDL-free here.
		$this->make_module()->activate();

		$this->assertSame( Installer::SCHEMA_VERSION, get_option( Installer::SCHEMA_VERSION_OPTION ) );
	}

	public function test_deactivate_clears_the_scheduled_actions() {
		$module = $this->make_module();
		$module->deactivate();

		if ( function_exists( 'as_next_scheduled_action' ) ) {
			$this->assertFalse( as_next_scheduled_action( EmailManager::DIGEST_HOOK, array(), EmailManager::AS_GROUP ) );
		}
		$this->assertFalse( wp_next_scheduled( EmailManager::DIGEST_HOOK ) );
	}

	/**
	 * Kept LAST: uninstall() drops the stock-log table (DDL commits the test
	 * transaction), so it re-installs immediately and creates no other data.
	 */
	public function test_uninstall_drops_the_table_and_deletes_every_module_option() {
		global $wpdb;

		// The WP test framework rewrites DROP TABLE → DROP TEMPORARY TABLE via
		// a 'query' filter, which would leave the real table in place; detach
		// the rewrites so uninstall() runs its actual DDL.
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );

		update_option( Settings::OPTION_KEY, array( 'enable_stock_log' => false ) );
		update_option( EmailManager::QUEUE_OPTION, array( 1 ) );
		update_option( EmailManager::ALERT_SETTINGS_OPTION, array( 'enabled' => 'yes' ) );
		update_option( EmailManager::DIGEST_SETTINGS_OPTION, array( 'enabled' => 'yes' ) );

		$this->make_module()->uninstall();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->assertNull( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', Installer::stock_log_table() ) ) );
		$this->assertFalse( get_option( Installer::SCHEMA_VERSION_OPTION ) );
		$this->assertFalse( get_option( Settings::OPTION_KEY ) );
		$this->assertFalse( get_option( EmailManager::QUEUE_OPTION ) );
		$this->assertFalse( get_option( EmailManager::ALERT_SETTINGS_OPTION ) );
		$this->assertFalse( get_option( EmailManager::DIGEST_SETTINGS_OPTION ) );

		// Restore the table for the rest of the suite; the options above were
		// deleted for real (the DROP committed this test's transaction), which
		// is fine — tests treat absent options as the clean state.
		Installer::install();
	}
}
