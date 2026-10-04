<?php
/**
 * Customer CRM — frontend asset loader.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Modules\Customers;

use PluginizeLab\StoreSuite\Analytics\WCAdminBootstrap;
use PluginizeLab\StoreSuite\Analytics\Settings as AnalyticsSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ships the WooCommerce-admin React bundle for the CRM to the frontend
 * dashboard, mirroring includes/Analytics/Assets.php: register WC admin scripts
 * on the frontend via WCAdminBootstrap, enqueue the module bundle with the WC
 * deps, and inject the config + settings globals the app reads.
 */
class Assets {

	const ENDPOINT = 'customers';
	const HANDLE   = 'storesuite-customers';

	/**
	 * Register hooks. Called from Module::boot().
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueue the CRM bundle on the customers endpoint for permitted users.
	 *
	 * @return void
	 */
	public function enqueue() {
		if ( ! storesuite_is_endpoint_url( self::ENDPOINT ) ) {
			return;
		}

		if ( ! storesuite_current_user_can( 'manage_customers' ) ) {
			return;
		}

		WCAdminBootstrap::ensure();

		$build_dir  = STORESUITE_DIR . '/modules/customers/assets/build';
		$build_url  = STORESUITE_PLUGIN_URL . 'modules/customers/assets/build';
		$asset_file = $build_dir . '/script.asset.php';
		$asset      = file_exists( $asset_file ) ? include $asset_file : array();
		$deps       = $asset['dependencies'] ?? array();
		$version    = $asset['version'] ?? STORESUITE_PLUGIN_VERSION;

		$wc_deps = array( 'wc-components', 'wc-admin-layout', 'wc-experimental', 'wc-customer-effort-score', 'wp-components' );
		$deps    = array_unique( array_merge( $deps, $wc_deps ) );

		wp_register_script( self::HANDLE, $build_url . '/script.js', $deps, $version, true );
		wp_register_style( self::HANDLE, $build_url . '/style-script.css', array( 'wc-components', 'wp-components' ), $version );

		wp_enqueue_script( self::HANDLE );
		if ( file_exists( $build_dir . '/style-script.css' ) ) {
			wp_enqueue_style( self::HANDLE );
		}
		wp_set_script_translations( self::HANDLE, 'storesuite', STORESUITE_DIR . '/languages' );

		$customers_url = storesuite_get_navigation_url( self::ENDPOINT );

		wp_add_inline_script(
			self::HANDLE,
			'var storeSuiteCustomersConfig = ' . wp_json_encode(
				array(
					'assetsPath'      => $build_url . '/',
					'customersUrl'    => $customers_url,
					'customersPath'   => wp_parse_url( $customers_url, PHP_URL_PATH ),
					'orderDetailsPath' => wp_parse_url( storesuite_get_navigation_url( 'order-details' ), PHP_URL_PATH ),
					'restBase'        => esc_url_raw( rest_url( 'storesuite/v1/customers' ) ),
					'wcApiRoot'       => esc_url_raw( rest_url() ),
					'nonce'           => wp_create_nonce( 'wp_rest' ),
					'settings'        => Settings::get(),
				)
			),
			'before'
		);

		// Reuse the core analytics settings payload (currency, countries,
		// preload) with a minimal preload subset, exactly like the dashboard.
		if ( class_exists( AnalyticsSettings::class ) ) {
			$settings = ( new AnalyticsSettings() )->get_settings( array() );
			wp_add_inline_script(
				self::HANDLE,
				'var storeSuiteAnalyticsSettings = ' . wp_json_encode( $settings ),
				'before'
			);
		}
	}
}
