<?php
/**
 * WordPress test-suite configuration.
 *
 * Connection settings for the throwaway test database. Every value can be
 * overridden via `WP_TESTS_*` environment variables — locally these are set
 * in the gitignored `phpunit.xml`. The `WP_DB_*` / `WP_CORE_DIR` names used
 * by .github/workflows/phpunit.yml are honoured as a fallback.
 *
 * @package StoreSuite
 */

/**
 * First non-empty environment variable among $names, or $default_value.
 *
 * @param string[] $names         Environment variable names, in priority order.
 * @param string   $default_value Fallback when none is set.
 * @return string
 */
function storesuite_tests_env( array $names, $default_value ) {
	foreach ( $names as $name ) {
		$value = getenv( $name );
		if ( false !== $value && '' !== $value ) {
			return $value;
		}
	}
	return $default_value;
}

define( 'DB_NAME', storesuite_tests_env( array( 'WP_TESTS_DB_NAME', 'WP_DB_NAME' ), 'storesuite_tests' ) );
define( 'DB_USER', storesuite_tests_env( array( 'WP_TESTS_DB_USER', 'WP_DB_USER' ), 'root' ) );
define( 'DB_PASSWORD', false !== getenv( 'WP_TESTS_DB_PASSWORD' ) ? getenv( 'WP_TESTS_DB_PASSWORD' ) : ( false !== getenv( 'WP_DB_PASS' ) ? getenv( 'WP_DB_PASS' ) : '' ) );
define( 'DB_HOST', storesuite_tests_env( array( 'WP_TESTS_DB_HOST', 'WP_DB_HOST' ), '127.0.0.1' ) );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

$table_prefix = 'wptests_'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'StoreSuite Tests' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WP_DEBUG', true );

if ( ! defined( 'ABSPATH' ) ) {
	// Default: the WordPress install this plugin lives in (plugin dir is wp-content/plugins/storesuite).
	$storesuite_abspath = storesuite_tests_env( array( 'WP_TESTS_ABSPATH', 'WP_CORE_DIR' ), dirname( __DIR__, 4 ) );
	define( 'ABSPATH', rtrim( $storesuite_abspath, '/' ) . '/' );
}
