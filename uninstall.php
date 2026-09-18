<?php
/**
 * StoreSuite uninstall routine.
 *
 * Runs when the plugin is deleted from wp-admin. The plugin itself is NOT
 * bootstrapped here, so we load just enough to let each module clean up after
 * itself and then remove the module-system's own options.
 *
 * @package StoreSuite
 */

// Exit if accessed directly or not during an uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$storesuite_autoload = __DIR__ . '/vendor/autoload.php';
if ( ! file_exists( $storesuite_autoload ) ) {
	// Without the autoloader we can't resolve the module classes; still remove
	// the core option so we don't leave obvious orphans behind.
	delete_option( 'storesuite_active_modules' );
	delete_option( 'storesuite_flush_rewrite_rules' );
	return;
}

require_once $storesuite_autoload;

// The module Manager resolves the bundled modules directory from STORESUITE_DIR.
if ( ! defined( 'STORESUITE_DIR' ) ) {
	define( 'STORESUITE_DIR', __DIR__ );
}

// Discover every bundled module and let each run its permanent teardown
// (dropping tables, deleting its own options).
//
// Deliberately limit this to BUNDLED modules: other active plugins are loaded
// during an uninstall request, so an add-on that hooked
// `storesuite_register_modules` (or redirected `storesuite_modules_dir`)
// would otherwise have its own data dropped here — while the add-on plugin
// itself remains installed. Third-party plugins own their uninstall routines;
// strip their filters before discovery so only our modules/ directory is
// torn down.
if ( class_exists( \PluginizeLab\StoreSuite\Module\Manager::class ) ) {
	remove_all_filters( 'storesuite_register_modules' );
	remove_all_filters( 'storesuite_modules_dir' );

	$storesuite_manager = new \PluginizeLab\StoreSuite\Module\Manager();
	$storesuite_manager->uninstall_all();
}

// Remove the module-system's own bookkeeping options.
delete_option( 'storesuite_active_modules' );
delete_option( 'storesuite_flush_rewrite_rules' );
