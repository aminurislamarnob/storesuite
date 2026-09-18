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

		// WooCommerce's "newly installed" admin_init pass (HPOS enablement)
		// self-joins the orders table, which MySQL 8 cannot do against the
		// TEMPORARY tables the test suite creates ("Can't reopen table").
		// Every Ajax test fires admin_init, so mark the install as settled.
		update_option( 'woocommerce_newly_installed', 'no' );

		$GLOBALS['wp_roles'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		wp_roles();
	}
);

require $storesuite_wp_phpunit_dir . '/includes/bootstrap.php';

require __DIR__ . '/fixtures/FixtureModule.php';

// WooCommerce deprecates parts of its own API and keeps calling them
// internally (the analytics feature-flag shim, the POS check in the CSV
// exporter, ...). WP_UnitTestCase fails any test that triggers a deprecation
// it did not declare, so each WooCommerce release could break tests that never
// touched the deprecated code. When the nearest plugin frame below the
// deprecated call is WooCommerce itself, declare the notice as expected on the
// running test case; deprecated calls made from StoreSuite still fail.
add_action(
	'deprecated_function_run',
	function ( $function_name ) {
		$frames  = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
		$start   = 0;
		$origin  = '';
		$plugins = array(
			'woocommerce' => wp_normalize_path( WC_ABSPATH ),
			'storesuite'  => trailingslashit( wp_normalize_path( STORESUITE_DIR ) ),
		);

		foreach ( $frames as $i => $frame ) {
			if ( isset( $frame['function'] ) && in_array( $frame['function'], array( '_deprecated_function', 'wc_deprecated_function' ), true ) ) {
				$start = $i + 1;
			}
		}

		for ( $i = $start; $i < count( $frames ); $i++ ) {
			$file = isset( $frames[ $i ]['file'] ) ? wp_normalize_path( $frames[ $i ]['file'] ) : '';
			foreach ( $plugins as $slug => $dir ) {
				if ( '' !== $file && 0 === strpos( $file, $dir ) ) {
					$origin = $slug;
					break 2;
				}
			}
		}

		if ( 'woocommerce' !== $origin ) {
			return;
		}

		foreach ( $GLOBALS['wp_filter']['deprecated_function_run']->callbacks[10] ?? array() as $callback ) {
			if ( is_array( $callback['function'] ) && $callback['function'][0] instanceof WP_UnitTestCase_Base ) {
				$callback['function'][0]->setExpectedDeprecated( $function_name );
			}
		}
	},
	9
);
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
