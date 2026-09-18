<?php
/**
 * Tests for the dashboard rewrite endpoints and query vars.
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test;

use PluginizeLab\StoreSuite\Rewrites;

/**
 * @covers \PluginizeLab\StoreSuite\Rewrites
 * @group storesuite-rewrites
 */
class RewritesTest extends StoreSuiteTestCase {

	public function test_all_dashboard_endpoints_have_query_vars() {
		$rewrites = new Rewrites();

		// NOTE: the 'inventory' endpoint arrives with the Tier 1 branch
		// (feat/inventory-view); its assertions live in that branch's tests.
		$expected = array(
			'analytics',
			'products',
			'import-products',
			'add-new-product',
			'edit-product',
			'orders',
			'add-new-order',
			'edit-order',
			'order-details',
			'categories',
			'add-new-category',
			'edit-category',
			'tags',
			'add-new-tag',
			'edit-tag',
			'brands',
			'add-new-brand',
			'edit-brand',
			'attributes',
			'add-new-attribute',
			'edit-attribute',
			'attribute-terms',
			'coupons',
			'add-new-coupon',
			'edit-coupon',
			'edit-account-details',
			'notifications',
		);

		foreach ( $expected as $endpoint ) {
			$this->assertArrayHasKey( $endpoint, $rewrites->query_vars, "Missing query var for endpoint '{$endpoint}'." );
		}
	}

	public function test_endpoint_slugs_are_option_configurable() {
		update_option( 'storesuite_myshop_products_endpoint', 'artikelen' );

		$rewrites = new Rewrites();

		$this->assertSame( 'artikelen', $rewrites->query_vars['products'] );
	}

	public function test_query_vars_are_added_to_wp() {
		$rewrites = new Rewrites();

		$vars = $rewrites->add_query_vars( array( 'existing' ) );

		$this->assertContains( 'existing', $vars );
		$this->assertContains( 'products', $vars );
		$this->assertContains( 'edit-account-details', $vars );
	}

	public function test_pagination_rewrite_rules_use_dashboard_page_slug() {
		$this->create_dashboard_page();

		$rewrites = new Rewrites();
		$rewrites->add_endpoints();

		global $wp_rewrite;

		foreach ( array( 'products', 'orders', 'coupons', 'categories', 'tags', 'brands' ) as $list ) {
			$this->assertArrayHasKey(
				'storesuite-dashboard/' . $list . '/page/([^/]+)/?$',
				$wp_rewrite->extra_rules_top,
				"Missing pagination rewrite rule for '{$list}'."
			);
		}
	}

	public function test_wc_my_account_orders_conflict_resolved_on_dashboard_page() {
		$page_id = $this->create_dashboard_page();
		update_option( 'storesuite_myshopdashboard_page_id', $page_id );

		$rewrites = new Rewrites();

		// On the dashboard page, the WooCommerce My Account 'orders' query var
		// must give way to the StoreSuite orders endpoint.
		$GLOBALS['post'] = get_post( $page_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Simulating the main query context.

		$result = $rewrites->resolve_wocommerce_my_acc_query_conflict(
			array(
				'orders'       => 'orders',
				'edit-address' => 'edit-address',
			)
		);
		$this->assertArrayNotHasKey( 'orders', $result );
		$this->assertArrayHasKey( 'edit-address', $result );

		// Anywhere else WooCommerce keeps its endpoint.
		$other_page_id   = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$GLOBALS['post'] = get_post( $other_page_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Simulating the main query context.

		$result = $rewrites->resolve_wocommerce_my_acc_query_conflict( array( 'orders' => 'orders' ) );
		$this->assertArrayHasKey( 'orders', $result );

		unset( $GLOBALS['post'] );
	}
}
