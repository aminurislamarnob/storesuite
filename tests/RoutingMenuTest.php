<?php
/**
 * Dashboard routing & navigation tests: Rewrites (endpoints, query vars,
 * custom slugs, endpoint titles, current endpoint resolution) and
 * DashboardMenu (registry, permission gating, admin-access rule, active menu).
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Tests;

use PluginizeLab\StoreSuite\DashboardMenu;
use PluginizeLab\StoreSuite\Rewrites;
use WP_UnitTestCase;

/**
 * Tests for PluginizeLab\StoreSuite\Rewrites and DashboardMenu.
 */
class RoutingMenuTest extends WP_UnitTestCase {

	/*
	|-----------------------------------------------------------------------
	| Rewrites
	|-----------------------------------------------------------------------
	*/

	public function test_every_dashboard_endpoint_becomes_a_public_query_var() {
		$rewrites = new Rewrites();

		$vars = $rewrites->add_query_vars( array() );

		foreach ( array( 'products', 'orders', 'coupons', 'categories', 'tags', 'brands', 'attributes', 'edit-account-details', 'analytics' ) as $endpoint ) {
			$this->assertContains( $endpoint, $vars );
		}
	}

	public function test_endpoint_slugs_are_configurable_via_options() {
		update_option( 'storesuite_myshop_products_endpoint', 'mis-productos' );

		$rewrites = new Rewrites();

		$this->assertSame( 'mis-productos', $rewrites->query_vars['products'], 'The internal key stays "products" but the public slug follows the option.' );
	}

	public function test_query_vars_are_filterable() {
		add_filter(
			'storesuite_query_var_filter',
			function ( $vars ) {
				$vars['custom-endpoint'] = 'custom-endpoint';
				return $vars;
			}
		);

		$rewrites = new Rewrites();

		$this->assertArrayHasKey( 'custom-endpoint', $rewrites->query_vars );
		$this->assertContains( 'custom-endpoint', $rewrites->add_query_vars( array() ) );
	}

	public function test_pagination_rewrite_rules_are_registered_for_list_endpoints() {
		global $wp_rewrite;

		// Plain permalinks generate no rules; use a pretty structure like production.
		$this->set_permalink_structure( '/%postname%/' );

		$rewrites = new Rewrites();
		$rewrites->add_endpoints();
		$wp_rewrite->flush_rules();

		$rules = get_option( 'rewrite_rules' );

		$this->assertArrayHasKey( 'storesuite-dashboard/products/page/([^/]+)/?$', $rules );
		$this->assertArrayHasKey( 'storesuite-dashboard/orders/page/([^/]+)/?$', $rules );
		$this->assertArrayHasKey( 'storesuite-dashboard/coupons/page/([^/]+)/?$', $rules );
		$this->assertStringContainsString( 'paged=$matches[1]', $rules['storesuite-dashboard/products/page/([^/]+)/?$'] );
	}

	public function test_get_current_endpoint_reads_the_main_query_vars() {
		global $wp;

		$rewrites = new Rewrites();

		$this->assertSame( '', $rewrites->get_current_endpoint(), 'No endpoint set means empty string.' );

		$wp->query_vars['orders'] = '';
		$this->assertSame( 'orders', $rewrites->get_current_endpoint() );
		unset( $wp->query_vars['orders'] );
	}

	public function test_endpoint_titles_cover_known_endpoints_and_are_filterable() {
		$rewrites = new Rewrites();

		$this->assertSame( 'All Products', $rewrites->get_endpoint_title( 'products' ) );
		$this->assertSame( 'Coupons', $rewrites->get_endpoint_title( 'coupons' ) );
		$this->assertSame( '', $rewrites->get_endpoint_title( 'nonexistent' ) );

		add_filter(
			'storesuite_endpoint_products_title',
			function () {
				return 'My Catalog';
			}
		);
		$this->assertSame( 'My Catalog', $rewrites->get_endpoint_title( 'products' ) );
	}

	public function test_edit_order_title_includes_the_order_number() {
		global $wp;

		$order = wc_create_order();
		$order->save();

		$rewrites                    = new Rewrites();
		$wp->query_vars['edit-order'] = (string) $order->get_id();

		$this->assertSame( 'Edit Order #' . $order->get_order_number(), $rewrites->get_endpoint_title( 'edit-order' ) );

		unset( $wp->query_vars['edit-order'] );
	}

	/*
	|-----------------------------------------------------------------------
	| DashboardMenu
	|-----------------------------------------------------------------------
	*/

	public function test_menu_registry_contains_core_entries_with_required_shape() {
		$menus = ( new DashboardMenu() )->get_dashboard_menus();

		foreach ( array( 'dashboard', 'products', 'orders', 'coupons', 'analytics', 'edit-account-details', 'wp_dashboard', 'logout' ) as $key ) {
			$this->assertArrayHasKey( $key, $menus );
			$this->assertArrayHasKey( 'title', $menus[ $key ] );
			$this->assertArrayHasKey( 'url', $menus[ $key ] );
			$this->assertArrayHasKey( 'permission', $menus[ $key ] );
		}

		$this->assertArrayHasKey( 'add-new-product', $menus['products']['submenu'] );
		$this->assertArrayHasKey( 'add-new-order', $menus['orders']['submenu'] );
	}

	public function test_menu_registry_is_filterable() {
		add_filter(
			'storesuite_dashboard_menus',
			function ( $menus ) {
				unset( $menus['coupons'] );
				$menus['custom'] = array(
					'title'      => 'Custom',
					'icon'       => '',
					'url'        => '#',
					'permission' => 'manage_woocommerce',
				);
				return $menus;
			}
		);

		$menus = ( new DashboardMenu() )->get_dashboard_menus();

		$this->assertArrayNotHasKey( 'coupons', $menus );
		$this->assertArrayHasKey( 'custom', $menus );
	}

	public function test_analytics_stock_report_follows_the_stock_management_option() {
		update_option( 'woocommerce_manage_stock', 'yes' );
		$menus = ( new DashboardMenu() )->get_dashboard_menus();
		$this->assertArrayHasKey( 'stock', $menus['analytics']['submenu'] );

		update_option( 'woocommerce_manage_stock', 'no' );
		$menus = ( new DashboardMenu() )->get_dashboard_menus();
		$this->assertArrayNotHasKey( 'stock', $menus['analytics']['submenu'] );
	}

	/**
	 * Render the sidebar for the current user and return the HTML.
	 *
	 * @return string
	 */
	private function render_menu() {
		ob_start();
		( new DashboardMenu() )->add_dashboard_navigations();
		return ob_get_clean();
	}

	public function test_rendered_menu_hides_items_the_user_cannot_access() {
		$shop_manager = self::factory()->user->create( array( 'role' => 'shop_manager' ) );
		$customer     = self::factory()->user->create( array( 'role' => 'customer' ) );

		wp_set_current_user( $shop_manager );
		$html = $this->render_menu();
		$this->assertStringContainsString( 'Products', $html );
		$this->assertStringContainsString( 'Orders', $html );

		// Customers lack manage_woocommerce: every gated item disappears.
		wp_set_current_user( $customer );
		$html = $this->render_menu();
		$this->assertStringNotContainsString( 'Products', $html );
		$this->assertStringNotContainsString( 'Orders', $html );
	}

	public function test_wp_dashboard_item_is_hidden_from_non_admins_when_admin_access_is_prevented() {
		update_option( 'storesuite_settings', array( 'storesuite_prevent_admin_access' => 'yes' ) );

		$shop_manager = self::factory()->user->create( array( 'role' => 'shop_manager' ) );
		wp_set_current_user( $shop_manager );
		$this->assertStringNotContainsString( 'WP Dashboard', $this->render_menu() );

		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		$this->assertStringContainsString( 'WP Dashboard', $this->render_menu(), 'Admins keep the WP Dashboard link.' );
	}

	public function test_active_menu_maps_child_endpoints_to_their_parent() {
		global $wp;

		$menu = new DashboardMenu();

		$this->assertSame( 'dashboard', $menu->get_active_menu(), 'No endpoint means the dashboard home.' );

		$wp->query_vars['edit-product'] = '12';
		$this->assertSame( 'products', $menu->get_active_menu() );
		unset( $wp->query_vars['edit-product'] );

		$wp->query_vars['add-new-coupon'] = '';
		$this->assertSame( 'coupons', $menu->get_active_menu() );
		unset( $wp->query_vars['add-new-coupon'] );

		$wp->query_vars['orders'] = '';
		$this->assertSame( 'orders', $menu->get_active_menu(), 'Top-level endpoints map to themselves.' );
		unset( $wp->query_vars['orders'] );
	}
}
