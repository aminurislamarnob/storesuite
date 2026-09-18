<?php
/**
 * Analytics\RestPermissions tests: the woocommerce_rest_check_permissions
 * widening that lets shop managers read wc-analytics data from the frontend
 * dashboard.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Tests\Analytics;

use PluginizeLab\StoreSuite\Analytics\RestPermissions;
use WP_UnitTestCase;

/**
 * Tests for PluginizeLab\StoreSuite\Analytics\RestPermissions.
 *
 * grant_read_access() is called directly — it is a plain filter callback, so
 * driving it through apply_filters would only re-test WordPress.
 */
class RestPermissionsTest extends WP_UnitTestCase {

	/**
	 * Instance under test.
	 *
	 * @var RestPermissions
	 */
	private $permissions;

	/**
	 * Fresh instance per test.
	 */
	public function set_up() {
		parent::set_up();
		$this->permissions = new RestPermissions();
	}

	public function test_already_granted_permission_passes_through_untouched() {
		wp_set_current_user( 0 );

		$this->assertTrue( $this->permissions->grant_read_access( true, 'read', 0, 'reports' ) );
	}

	public function test_write_context_is_never_widened() {
		$shop_manager = self::factory()->user->create( array( 'role' => 'shop_manager' ) );
		wp_set_current_user( $shop_manager );

		$this->assertFalse( $this->permissions->grant_read_access( false, 'create', 0, 'reports' ) );
		$this->assertFalse( $this->permissions->grant_read_access( false, 'edit', 0, 'orders' ) );
	}

	public function test_shop_manager_gains_read_access_to_analytics_objects() {
		$shop_manager = self::factory()->user->create( array( 'role' => 'shop_manager' ) );
		wp_set_current_user( $shop_manager );

		foreach ( array( 'reports', 'settings', 'products', 'orders', 'customers', 'product_cat' ) as $object ) {
			$this->assertTrue(
				$this->permissions->grant_read_access( false, 'read', 0, $object ),
				"shop_manager should read '{$object}'."
			);
		}
	}

	public function test_user_without_manage_woocommerce_stays_denied() {
		$customer = self::factory()->user->create( array( 'role' => 'customer' ) );
		wp_set_current_user( $customer );

		$this->assertFalse( $this->permissions->grant_read_access( false, 'read', 0, 'reports' ) );
	}

	public function test_objects_outside_the_allowlist_are_not_widened() {
		$shop_manager = self::factory()->user->create( array( 'role' => 'shop_manager' ) );
		wp_set_current_user( $shop_manager );

		$this->assertFalse( $this->permissions->grant_read_access( false, 'read', 0, 'system_status' ) );
	}

	public function test_allowlist_is_extendable_via_filter() {
		$shop_manager = self::factory()->user->create( array( 'role' => 'shop_manager' ) );
		wp_set_current_user( $shop_manager );

		add_filter(
			'storesuite_analytics_rest_read_objects',
			function ( $objects ) {
				$objects[] = 'my_custom_object';
				return $objects;
			}
		);

		$this->assertTrue( $this->permissions->grant_read_access( false, 'read', 0, 'my_custom_object' ) );
	}
}
