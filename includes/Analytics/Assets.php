<?php

namespace PluginizeLab\StoreSuite\Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Assets {
	public function register_hooks(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	public function enqueue_scripts(): void {
		if ( ! storesuite_is_endpoint_url( 'analytics' ) ) {
			return;
		}

		// Capability gate: never ship the analytics bundle or preload globals
		// to users who can't manage WooCommerce.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		WCAdminBootstrap::ensure();

		$asset_file = STORESUITE_DIR . '/assets/build/analytics/index.asset.php';
		$asset      = file_exists( $asset_file ) ? include $asset_file : [];
		$deps       = $asset['dependencies'] ?? [];
		$version    = $asset['version'] ?? STORESUITE_PLUGIN_VERSION;

		$wc_deps = [
			'wc-components',
			'wc-admin-layout',
			'wc-experimental',
			'wc-customer-effort-score',
			'wp-components',
		];
		$deps    = array_unique( array_merge( $deps, $wc_deps ) );

		wp_register_script(
			'storesuite-analytics',
			STORESUITE_PLUGIN_ASSET . '/build/analytics/index.js',
			$deps,
			$version,
			true
		);

		wp_register_style(
			'storesuite-analytics',
			STORESUITE_PLUGIN_ASSET . '/build/analytics/index.css',
			[ 'wc-components', 'wp-components' ],
			$version
		);

		wp_enqueue_script( 'storesuite-analytics' );
		wp_enqueue_style( 'storesuite-analytics' );
		wp_set_script_translations( 'storesuite-analytics', 'storesuite', STORESUITE_DIR . '/languages' );

		// Force the StoreSuite shell stylesheet (dark-mode overrides) to print
		// after this build's index.css and the WooCommerce admin component
		// styles it depends on; they target the same selectors at equal
		// specificity and would otherwise win on source order.
		$this->load_shell_after( 'storesuite-analytics' );

		$analytics_url = storesuite_get_navigation_url( 'analytics' );
		$dashboard_url = storesuite_get_navigation_url();

		wp_add_inline_script(
			'storesuite-analytics',
			'var storeSuiteAnalyticsConfig = ' . wp_json_encode(
				[
					'assetsPath'       => STORESUITE_PLUGIN_ASSET . '/build/',
					'analyticsUrl'     => $analytics_url,
					'dashboardPath'    => wp_parse_url( $dashboard_url, PHP_URL_PATH ),
					'reportsPath'      => wp_parse_url( $analytics_url, PHP_URL_PATH ),
					'orderDetailsPath' => wp_parse_url( storesuite_get_navigation_url( 'order-details' ), PHP_URL_PATH ),
				]
			),
			'before'
		);

		$settings = ( new Settings() )->get_settings();
		wp_add_inline_script(
			'storesuite-analytics',
			'var storeSuiteAnalyticsSettings = ' . wp_json_encode( $settings ),
			'before'
		);
	}

	/**
	 * Force the StoreSuite shell stylesheet to be printed after the given
	 * (already enqueued) handle by appending it as a dependency. Dependencies
	 * are resolved at print time, so this works regardless of enqueue order and
	 * only takes effect on pages where $handle is registered.
	 *
	 * @param string $handle Style handle the shell must load after.
	 * @return void
	 */
	private function load_shell_after( string $handle ): void {
		$styles = wp_styles();
		if ( isset( $styles->registered['storesuite_style'] )
			&& ! in_array( $handle, $styles->registered['storesuite_style']->deps, true ) ) {
			$styles->registered['storesuite_style']->deps[] = $handle;
		}
	}
}
