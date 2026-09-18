<?php
/**
 * Plugin Name: StoreSuite
 * Plugin URI:  https://wordpress.org/plugins/storesuite/
 * Description: AI-assisted frontend dashboard to manage your WooCommerce store — products, orders, coupons, categories, and analytics in one place.
 * Version: 1.3.1
 * Author: Aminur Islam Arnob
 * Author URI: https://github.com/aminurislamarnob/
 * Text Domain: storesuite
 * WC requires at least: 10.4.3
 * Requires Plugins: woocommerce
 * License: GPL2
 */

use PluginizeLab\StoreSuite\StoreSuite;

// don't call the file directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
if ( ! defined( 'STORESUITE_FILE' ) ) {
	define( 'STORESUITE_FILE', __FILE__ );
}
if ( ! defined( 'STORESUITE_PLUGIN_FILE' ) ) {
	define( 'STORESUITE_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'STORESUITE_PLUGIN_DIR' ) ) {
	define( 'STORESUITE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'STORESUITE_PLUGIN_URL' ) ) {
	define( 'STORESUITE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Load Store_Suite Plugin when all plugins loaded
 *
 * @return \PluginizeLab\StoreSuite\StoreSuite
 */
function pluginizelab_storesuite() {
	return StoreSuite::init();
}

// Lets Go....
pluginizelab_storesuite();

/**
 * Initialize the plugin tracker
 *
 * @return void
 */
function storesuite_init_appsero_tracker() {
	if ( ! class_exists( 'Appsero\Client' ) ) {
		return;
	}

	$client = new Appsero\Client(
		'f7ef1e53-4c57-466c-b668-cb5c2d34a3e7',
		'StoreSuite – Frontend Shop Manager for WooCommerce with AI – Product, Order, Coupon Management & Analytics Dashboard',
		__FILE__
	);

	// Active insights.
	$client->insights()->init();
}

storesuite_init_appsero_tracker();
