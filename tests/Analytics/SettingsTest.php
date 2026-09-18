<?php
/**
 * Analytics\Settings tests: the JS settings payload for the analytics React
 * app — order-status shaping, option preloading, the per-capability preload
 * transient, and role-change cache invalidation.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Tests\Analytics;

use PluginizeLab\StoreSuite\Analytics\Settings;
use ReflectionMethod;
use WP_UnitTestCase;

/**
 * Tests for PluginizeLab\StoreSuite\Analytics\Settings.
 *
 * The REST preload (`rest_preload_api_request` against /wc-analytics/*) is
 * kept out of these tests by pre-seeding the per-capability transient — the
 * cached branch is deterministic; the cold branch depends on which wc-admin
 * features the WooCommerce build registers.
 */
class SettingsTest extends WP_UnitTestCase {

	/**
	 * Instance under test.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Fresh instance + admin user per test.
	 */
	public function set_up() {
		parent::set_up();

		$this->settings = new Settings();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Resolve the private per-capability transient key for the current user.
	 *
	 * @return string
	 */
	private function cache_key() {
		$method = new ReflectionMethod( Settings::class, 'preload_cache_key' );
		$method->setAccessible( true );

		return $method->invoke( $this->settings );
	}

	/**
	 * Seed the preload transient so get_settings() never issues REST requests.
	 *
	 * @param array $payload Endpoint-keyed payload to cache.
	 */
	private function seed_preload_cache( array $payload = array() ) {
		$payload = $payload ? $payload : array(
			'performanceIndicators' => array( 'seeded' => 'indicators' ),
			'leaderboards'          => array( 'seeded' => 'leaderboards' ),
		);

		set_transient( $this->cache_key(), $payload, 300 );
	}

	public function test_order_statuses_are_exposed_without_the_wc_prefix() {
		$this->seed_preload_cache();

		$statuses = $this->settings->get_settings()['orderStatuses'];

		$this->assertArrayHasKey( 'completed', $statuses );
		$this->assertArrayHasKey( 'processing', $statuses );
		foreach ( array_keys( $statuses ) as $key ) {
			$this->assertStringStartsNotWith( 'wc-', $key );
		}
	}

	public function test_saved_but_unregistered_statuses_are_surfaced_with_a_readable_label() {
		$this->seed_preload_cache();
		update_option( 'woocommerce_excluded_report_order_statuses', array( 'completed', 'ghost-status' ) );

		$unregistered = $this->settings->get_settings()['unregisteredOrderStatuses'];

		$this->assertSame( array( 'ghost-status' => 'Ghost-status' ), $unregistered );
	}

	public function test_current_user_data_reflects_the_logged_in_user() {
		$this->seed_preload_cache();

		$data = $this->settings->get_settings()['currentUserData'];

		$this->assertSame( get_current_user_id(), $data['id'] );
		$this->assertContains( 'administrator', $data['roles'] );
	}

	public function test_preload_options_hydrate_woocommerce_settings() {
		$this->seed_preload_cache();
		update_option( 'woocommerce_currency', 'EUR' );

		$options = $this->settings->get_settings()['preloadOptions'];

		$this->assertSame( 'EUR', $options['woocommerce_currency'] );
		$this->assertArrayHasKey( 'woocommerce_default_date_range', $options );
	}

	public function test_payload_is_filterable_via_storesuite_analytics_settings() {
		$this->seed_preload_cache();

		add_filter(
			'storesuite_analytics_settings',
			function ( $settings ) {
				$settings['injected'] = true;
				return $settings;
			}
		);

		$this->assertTrue( $this->settings->get_settings()['injected'] );
	}

	public function test_data_endpoints_are_served_from_the_seeded_transient() {
		$this->seed_preload_cache();

		$endpoints = $this->settings->get_settings()['dataEndpoints'];

		$this->assertSame( array( 'seeded' => 'indicators' ), $endpoints['performanceIndicators'] );
		$this->assertSame( array( 'seeded' => 'leaderboards' ), $endpoints['leaderboards'] );
	}

	public function test_requesting_a_subset_returns_only_those_endpoints() {
		$this->seed_preload_cache();

		$endpoints = $this->settings->get_settings( array( 'leaderboards' ) )['dataEndpoints'];

		$this->assertSame( array( 'leaderboards' ), array_keys( $endpoints ) );
	}

	public function test_cache_key_is_shared_across_users_with_identical_capabilities() {
		$key_first = $this->cache_key();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertSame( $key_first, $this->cache_key(), 'Same role set → same transient; storage scales with role sets, not users.' );
	}

	public function test_cache_key_differs_between_capability_sets() {
		$admin_key = $this->cache_key();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );

		$this->assertNotSame( $admin_key, $this->cache_key() );
	}

	public function test_invalidate_user_cache_deletes_the_users_preload_transient() {
		$this->seed_preload_cache();
		$key = $this->cache_key();

		$this->assertIsArray( get_transient( $key ) );

		Settings::invalidate_user_cache( get_current_user_id() );

		$this->assertFalse( get_transient( $key ), 'A role change must not leave a payload cached against the old capabilities.' );
	}

	public function test_role_change_actions_are_wired_to_cache_invalidation() {
		$this->assertNotFalse( has_action( 'set_user_role', array( Settings::class, 'invalidate_user_cache' ) ) );
		$this->assertNotFalse( has_action( 'add_user_role', array( Settings::class, 'invalidate_user_cache' ) ) );
		$this->assertNotFalse( has_action( 'remove_user_role', array( Settings::class, 'invalidate_user_cache' ) ) );
	}
}
