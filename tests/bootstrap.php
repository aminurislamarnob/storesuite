<?php
/**
 * PHPUnit bootstrap: loads the WordPress test suite (wp-phpunit) with
 * WooCommerce and StoreSuite active. Runs against a local MySQL — see
 * tests/wp-tests-config.php for the connection settings (`composer test`).
 *
 * @package StoreSuite
 */

$storesuite_plugin_root = dirname( __DIR__ );

require_once $storesuite_plugin_root . '/vendor/autoload.php';

$storesuite_wp_phpunit_dir = getenv( 'WP_PHPUNIT__DIR' );
if ( ! $storesuite_wp_phpunit_dir ) {
	$storesuite_wp_phpunit_dir = $storesuite_plugin_root . '/vendor/wp-phpunit/wp-phpunit';
}

if ( ! defined( 'WP_TESTS_CONFIG_FILE_PATH' ) ) {
	define( 'WP_TESTS_CONFIG_FILE_PATH', __DIR__ . '/wp-tests-config.php' );
}

require_once $storesuite_wp_phpunit_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	function () use ( $storesuite_plugin_root ) {
		// WooCommerce must load first: StoreSuite bails on plugins_loaded when
		// the WooCommerce class is absent. CI points WC_DIR at a standalone
		// checkout; locally it is the sibling plugin directory.
		$storesuite_wc_dir = getenv( 'WC_DIR' );
		if ( ! $storesuite_wc_dir ) {
			$storesuite_wc_dir = dirname( $storesuite_plugin_root ) . '/woocommerce';
		}
		require rtrim( $storesuite_wc_dir, '/' ) . '/woocommerce.php';
		require $storesuite_plugin_root . '/storesuite.php';
	}
);

// WooCommerce normally installs on plugin activation, which never runs in the
// test suite — install manually so its roles/caps and tables exist.
tests_add_filter(
	'setup_theme',
	function () {
		WC_Install::install();
		$GLOBALS['wp_roles'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		wp_roles();
	}
);

require $storesuite_wp_phpunit_dir . '/includes/bootstrap.php';

require __DIR__ . '/fixtures/FixtureModule.php';
require __DIR__ . '/Integration/StoreSuiteAjaxTestCase.php';

// Neutralize core/plugin/theme update checks. Ajax tests fire `admin_init`
// (as admin-ajax.php does), which re-runs wp_version_check() and friends on
// nearly every test because the caching transients roll back with each test's
// DB transaction — ~3s of api.wordpress.org traffic per test. Serving a fresh
// "already checked" payload via the pre_site_transient filters makes every
// checker return before it builds a request.
$storesuite_tests_no_updates = function () {
	return (object) array(
		'last_checked'    => time(),
		'updates'         => array(),
		'response'        => array(),
		'translations'    => array(),
		'version_checked' => $GLOBALS['wp_version'],
	);
};
add_filter( 'pre_site_transient_update_core', $storesuite_tests_no_updates );
add_filter( 'pre_site_transient_update_plugins', $storesuite_tests_no_updates );
add_filter( 'pre_site_transient_update_themes', $storesuite_tests_no_updates );

// Belt and braces: any other outbound HTTP fails fast instead of hanging the
// suite, so tests stay deterministic and offline-safe.
add_filter(
	'pre_http_request',
	function ( $pre, $args, $url ) {
		unset( $pre, $args );
		return new WP_Error( 'http_request_blocked', 'External HTTP is disabled in the test suite: ' . $url );
	},
	PHP_INT_MAX,
	3
);
