<?php
/**
 * WordPress test-suite configuration for the StoreSuite PHPUnit tests.
 *
 * All values are environment-overridable so the same file serves local runs
 * and the GitHub Actions workflow (.github/workflows/phpunit.yml).
 *
 * @package StoreSuite\Tests
 */

$storesuite_wp_core_dir = getenv( 'WP_CORE_DIR' );
if ( ! $storesuite_wp_core_dir ) {
	$storesuite_wp_core_dir = '/tmp/wordpress';
}

define( 'ABSPATH', rtrim( $storesuite_wp_core_dir, '/' ) . '/' );

define( 'DB_NAME', getenv( 'WP_DB_NAME' ) ? getenv( 'WP_DB_NAME' ) : 'wordpress_test' );
define( 'DB_USER', getenv( 'WP_DB_USER' ) ? getenv( 'WP_DB_USER' ) : 'root' );
define( 'DB_PASSWORD', false !== getenv( 'WP_DB_PASS' ) ? getenv( 'WP_DB_PASS' ) : 'root' );
define( 'DB_HOST', getenv( 'WP_DB_HOST' ) ? getenv( 'WP_DB_HOST' ) : '127.0.0.1' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

$table_prefix = 'wptests_'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Required by the WP test suite.

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'StoreSuite Test Site' );

define( 'WP_PHP_BINARY', 'php' );
define( 'WP_DEBUG', true );
