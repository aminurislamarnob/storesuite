<?php
/**
 * PHPUnit bootstrap: boots the WordPress test suite with WooCommerce and StoreSuite loaded.
 *
 * Requires a WordPress core checkout (WP_CORE_DIR, default /tmp/wordpress) and a
 * WooCommerce plugin directory (WC_DIR, default /tmp/woocommerce). See
 * .github/workflows/phpunit.yml for the reference environment.
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

		// Reload capabilities added by the install.
		$GLOBALS['wp_roles'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Standard WooCommerce test-suite reset.
		wp_roles();
	}
);

require $storesuite_wp_phpunit_dir . '/includes/bootstrap.php';
