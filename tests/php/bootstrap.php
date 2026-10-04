<?php
/**
 * PHPUnit bootstrap: boots the WordPress test suite with WooCommerce and StoreSuite loaded.
 *
 * Requires a WordPress core checkout (WP_CORE_DIR, default /tmp/wordpress) and a
 * WooCommerce plugin directory (WC_DIR, default /tmp/woocommerce). An Advanced
 * Custom Fields directory (ACF_DIR, default /tmp/advanced-custom-fields) is
 * loaded when present, and so is an Advanced Shipment Tracking directory
 * (AST_DIR, default /tmp/woo-advanced-shipment-tracking). See .github/workflows/phpunit.yml for the reference
 * environment.
 *
 * @package StoreSuite\Tests
 */

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

$storesuite_wp_phpunit_dir = getenv( 'WP_PHPUNIT__DIR' );
if ( ! $storesuite_wp_phpunit_dir ) {
	$storesuite_wp_phpunit_dir = dirname( __DIR__, 2 ) . '/vendor/wp-phpunit/wp-phpunit';
}

if ( ! getenv( 'WP_PHPUNIT__TESTS_CONFIG' ) ) {
	putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-tests-config.php' );
}

require_once $storesuite_wp_phpunit_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	function () {
		$wc_dir = getenv( 'WC_DIR' );
		if ( ! $wc_dir ) {
			$wc_dir = '/tmp/woocommerce';
		}

		require rtrim( $wc_dir, '/' ) . '/woocommerce.php';

		// Advanced Custom Fields is optional: the ACF integration tests skip
		// themselves when it is absent (see StoreSuiteFixtures::require_acf()).
		$acf_dir = getenv( 'ACF_DIR' );
		if ( ! $acf_dir ) {
			$acf_dir = '/tmp/advanced-custom-fields';
		}
		if ( file_exists( rtrim( $acf_dir, '/' ) . '/acf.php' ) ) {
			require rtrim( $acf_dir, '/' ) . '/acf.php';
		}

		// Advanced Shipment Tracking is optional too: its integration tests skip
		// themselves when it is absent (see ShipmentTrackingTestHelpers::require_ast()).
		$ast_dir = getenv( 'AST_DIR' );
		if ( ! $ast_dir ) {
			$ast_dir = '/tmp/woo-advanced-shipment-tracking';
		}
		$ast_file = rtrim( $ast_dir, '/' ) . '/woocommerce-advanced-shipment-tracking.php';
		if ( file_exists( $ast_file ) ) {
			// AST only boots when WooCommerce is listed as an active plugin, and
			// the suite loads WooCommerce directly instead of activating it.
			add_filter(
				'option_active_plugins',
				function ( $plugins ) {
					$plugins   = is_array( $plugins ) ? $plugins : array();
					$plugins[] = 'woocommerce/woocommerce.php';
					return array_unique( $plugins );
				}
			);
			require $ast_file;
		}

		require dirname( __DIR__, 2 ) . '/storesuite.php';

		// Keep the suite hermetic: core update checks phone home to
		// wordpress.org on admin_init (which every AJAX dispatch fires) and
		// turn into test errors on hosts without outbound network access.
		remove_action( 'admin_init', '_maybe_update_core' );
		remove_action( 'admin_init', '_maybe_update_plugins' );
		remove_action( 'admin_init', '_maybe_update_themes' );
	}
);

// Install WooCommerce (tables, product type terms, roles) before the test run.
tests_add_filter(
	'setup_theme',
	function () {
		if ( class_exists( 'WC_Install' ) ) {
			WC_Install::install();
		}

		// The HPOS "newly installed" check runs on admin_init and issues a
		// self-joined orders query that MySQL cannot execute against the test
		// suite's TEMPORARY tables ("Can't reopen table"). Mark the install as
		// not-new so the check is skipped during AJAX tests.
		update_option( 'woocommerce_newly_installed', 'no' );

		// Advanced Shipment Tracking runs its upgrade routine on admin requests
		// (which every AJAX dispatch is) and that routine downloads carrier
		// logos. Mark its data as current so the suite stays offline.
		update_option( 'wc_advanced_shipment_tracking', '4.5' );

		// Reload capabilities added by the install.
		$GLOBALS['wp_roles'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Standard WooCommerce test-suite reset.
		wp_roles();
	}
);

require $storesuite_wp_phpunit_dir . '/includes/bootstrap.php';
