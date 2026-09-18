<?php
/**
 * Router for running WordPress under PHP's built-in server with pretty
 * permalinks (php -S 127.0.0.1:9999 router.php from the WordPress root).
 *
 * @package StoreSuite\Tests
 */

$storesuite_router_path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- WordPress is not loaded yet.
$storesuite_router_file = __DIR__ . $storesuite_router_path;

if ( '/' !== $storesuite_router_path && file_exists( $storesuite_router_file ) && ! is_dir( $storesuite_router_file ) ) {
	return false; // Serve the static file as-is.
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/index.php';
