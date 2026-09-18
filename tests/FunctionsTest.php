<?php
/**
 * Global helper function tests (includes/functions.php): settings access,
 * AI field gating, navigation URLs, and status label/class maps.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Tests;

use WP_UnitTestCase;

/**
 * Tests for the storesuite_* helpers in includes/functions.php.
 *
 * Only pure/option-driven helpers are covered here. Helpers that depend on
 * the main query or template context (storesuite_is_dashboard_page,
 * storesuite_is_page, redirects) need a front-end request fixture and are
 * intentionally out of scope — storesuite_is_dashboard_page also memoizes in
 * a static, which would leak across tests.
 */
class FunctionsTest extends WP_UnitTestCase {

	/**
	 * Clear the settings option the helpers read.
	 */
	public function set_up() {
		parent::set_up();
		delete_option( 'storesuite_settings' );
	}

	/*
	|-----------------------------------------------------------------------
	| storesuite_get_option_by_key()
	|-----------------------------------------------------------------------
	*/

	public function test_get_option_by_key_returns_value_from_the_serialized_settings() {
		update_option( 'storesuite_settings', array( 'some_key' => 'some-value' ) );

		$this->assertSame( 'some-value', storesuite_get_option_by_key( 'some_key' ) );
	}

	public function test_get_option_by_key_returns_empty_string_when_key_or_option_is_absent() {
		$this->assertSame( '', storesuite_get_option_by_key( 'anything' ), 'Missing option entirely.' );

		update_option( 'storesuite_settings', array( 'other' => 1 ) );
		$this->assertSame( '', storesuite_get_option_by_key( 'anything' ), 'Missing key inside the option.' );
	}

	public function test_get_option_by_key_preserves_falsy_stored_values() {
		update_option(
			'storesuite_settings',
			array(
				'zero'  => 0,
				'empty' => '',
			)
		);

		$this->assertSame( 0, storesuite_get_option_by_key( 'zero' ), 'array_key_exists semantics: stored 0 must come back, not the missing-key fallback.' );
		$this->assertSame( '', storesuite_get_option_by_key( 'empty' ) );
	}

	/*
	|-----------------------------------------------------------------------
	| storesuite_is_ai_field_enabled()
	|-----------------------------------------------------------------------
	*/

	public function test_ai_fields_default_to_enabled() {
		$this->assertTrue( storesuite_is_ai_field_enabled( 'title' ) );
		$this->assertTrue( storesuite_is_ai_field_enabled( 'description' ) );
	}

	public function test_ai_field_is_disabled_only_by_an_explicit_no() {
		update_option(
			'storesuite_settings',
			array(
				'storesuite_ai_field_title'       => 'no',
				'storesuite_ai_field_description' => 'yes',
			)
		);

		$this->assertFalse( storesuite_is_ai_field_enabled( 'title' ) );
		$this->assertTrue( storesuite_is_ai_field_enabled( 'description' ) );
	}

	public function test_unknown_ai_field_is_reported_disabled() {
		$this->assertFalse( storesuite_is_ai_field_enabled( 'no-such-field' ) );
	}

	/*
	|-----------------------------------------------------------------------
	| storesuite_get_navigation_url()
	|-----------------------------------------------------------------------
	*/

	/**
	 * Point the dashboard-page setting at a fresh published page.
	 *
	 * @return int Page ID.
	 */
	private function create_dashboard_page() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'My Shop',
			)
		);
		update_option( 'storesuite_settings', array( 'storesuite_dashboard_page_id' => $page_id ) );

		return $page_id;
	}

	public function test_navigation_url_is_empty_when_no_dashboard_page_is_configured() {
		$this->assertSame( '', storesuite_get_navigation_url() );
		$this->assertSame( '', storesuite_get_navigation_url( 'products' ) );
	}

	public function test_navigation_url_returns_the_dashboard_page_permalink() {
		$page_id = $this->create_dashboard_page();

		$this->assertSame( rtrim( get_permalink( $page_id ), '/' ), storesuite_get_navigation_url() );
	}

	public function test_navigation_url_appends_the_endpoint_with_a_trailing_slash() {
		$page_id = $this->create_dashboard_page();
		$base    = rtrim( get_permalink( $page_id ), '/' );

		$this->assertSame( $base . '/products/', storesuite_get_navigation_url( 'products' ) );
	}

	/*
	|-----------------------------------------------------------------------
	| Status label / class maps
	|-----------------------------------------------------------------------
	*/

	public function test_post_status_returns_label_map_or_single_label() {
		$this->assertSame( 'Online', storesuite_get_post_status( 'publish' ) );
		$this->assertSame( '', storesuite_get_post_status( 'no-such-status' ) );

		$all = storesuite_get_post_status();
		$this->assertIsArray( $all );
		$this->assertArrayHasKey( 'draft', $all );
	}

	public function test_post_status_class_maps_statuses_to_badge_classes() {
		$this->assertSame( 'success', storesuite_get_post_status_class( 'publish' ) );
		$this->assertSame( 'warning', storesuite_get_post_status_class( 'pending' ) );
		$this->assertSame( '', storesuite_get_post_status_class( 'no-such-status' ) );
	}

	public function test_order_status_class_maps_wc_statuses_to_badge_classes() {
		// The map is keyed by the unprefixed WooCommerce OrderStatus enum
		// values ('completed'), not the 'wc-completed' post-status form.
		$this->assertSame( 'success', storesuite_get_order_status_class( 'completed' ) );
		$this->assertSame( 'danger', storesuite_get_order_status_class( 'cancelled' ) );
		$this->assertSame( '', storesuite_get_order_status_class( 'no-such-status' ) );
	}

	public function test_status_maps_are_filterable() {
		add_filter(
			'storesuite_get_post_status',
			function ( $statuses ) {
				$statuses['custom'] = 'Custom Label';
				return $statuses;
			}
		);

		$this->assertSame( 'Custom Label', storesuite_get_post_status( 'custom' ) );
	}
}
