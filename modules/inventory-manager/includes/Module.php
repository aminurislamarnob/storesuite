<?php
/**
 * Inventory Manager module entry point.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Modules\InventoryManager;

use PluginizeLab\StoreSuite\Abstracts\Module as BaseModule;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Inventory management module: a stock list across products/variations with
 * inline + bulk quantity edits, a low-stock view and dashboard widget, a stock
 * movement log, and low-stock email alerts. Area: `inventory`
 * (cap `storesuite_manage_inventory`).
 */
class Module extends BaseModule {

	const ENDPOINT = 'inventory';
	const AREA     = 'manage_inventory';

	/**
	 * {@inheritDoc}
	 */
	public function get_slug() {
		return 'inventory-manager';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return __( 'Inventory Manager', 'storesuite' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description() {
		return __( 'A stock list across all product types with inline and bulk quantity updates, a low-stock view, a stock movement log, and low-stock email alerts.', 'storesuite' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_requires() {
		return array( 'woocommerce/woocommerce.php' );
	}

	/**
	 * One-shot setup: create the stock log table.
	 *
	 * @return void
	 */
	public function activate() {
		Installer::install();
		Emails\Manager::sync_digest_schedule();
	}

	/**
	 * Non-destructive teardown: clear scheduled crons.
	 *
	 * @return void
	 */
	public function deactivate() {
		StockLog::unschedule();
		Emails\Manager::unschedule();
	}

	/**
	 * Boot hooks for an active module.
	 *
	 * @return void
	 */
	public function boot() {
		Installer::maybe_upgrade();

		add_action( 'init', array( $this, 'register_endpoint' ) );
		add_filter( 'storesuite_query_var_filter', array( $this, 'register_query_var' ) );
		add_filter( 'storesuite_dashboard_menus', array( $this, 'register_menu' ), 20 );
		add_action( 'storesuite_load_custom_template', array( $this, 'load_template' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		add_filter( 'storesuite_endpoint_capability_map', array( $this, 'register_endpoint_area' ) );
		add_filter( 'storesuite_capability_registry', array( $this, 'register_capability' ) );
		add_filter( 'storesuite_endpoint_inventory_title', array( $this, 'endpoint_title' ) );

		( new StockLog() )->register();
		( new Emails\Manager() )->register();
		RestController::register_cache_busting();
	}

	/**
	 * @return void
	 */
	public function register_endpoint() {
		add_rewrite_endpoint( self::ENDPOINT, EP_PAGES );
	}

	/**
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function register_query_var( $vars ) {
		if ( is_array( $vars ) ) {
			$vars[ self::ENDPOINT ] = self::ENDPOINT;
		}
		return $vars;
	}

	/**
	 * @param array $map Endpoint => area.
	 * @return array
	 */
	public function register_endpoint_area( $map ) {
		if ( is_array( $map ) ) {
			$map[ self::ENDPOINT ] = self::AREA;
		}
		return $map;
	}

	/**
	 * @param array $areas Area => label.
	 * @return array
	 */
	public function register_capability( $areas ) {
		if ( is_array( $areas ) ) {
			$areas[ self::AREA ] = __( 'Manage inventory', 'storesuite' );
		}
		return $areas;
	}

	/**
	 * @return void
	 */
	public function register_rest_routes() {
		( new RestController() )->register_routes();
	}

	/**
	 * @param array $menus Menu definitions.
	 * @return array
	 */
	public function register_menu( $menus ) {
		if ( ! is_array( $menus ) ) {
			return $menus;
		}

		$list_url = storesuite_get_navigation_url( self::ENDPOINT );

		$menus['inventory'] = array(
			'title'      => __( 'Inventory', 'storesuite' ),
			'url'        => $list_url,
			'permission' => 'storesuite_' . self::AREA,
			'icon'       => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M21,4H3A3,3,0,0,0,0,7v10a3,3,0,0,0,3,3H21a3,3,0,0,0,3-3V7A3,3,0,0,0,21,4Zm1,13a1,1,0,0,1-1,1H3a1,1,0,0,1-1-1V7A1,1,0,0,1,3,6H21a1,1,0,0,1,1,1ZM6,9a1,1,0,0,0-1,1v4a1,1,0,0,0,2,0V10A1,1,0,0,0,6,9Zm5,0a1,1,0,0,0-1,1v4a1,1,0,0,0,2,0V10A1,1,0,0,0,11,9Zm5,0a1,1,0,0,0-1,1v4a1,1,0,0,0,2,0V10A1,1,0,0,0,16,9Z"/></svg>',
			'submenu'    => array(
				'stock-list'   => array(
					'title'      => __( 'Stock list', 'storesuite' ),
					'url'        => $list_url,
					'permission' => 'storesuite_' . self::AREA,
					'endpoint'   => self::ENDPOINT,
					'view'       => 'list',
					'default'    => true,
				),
				'movement-log' => array(
					'title'      => __( 'Movement log', 'storesuite' ),
					'url'        => add_query_arg( 'view', 'log', $list_url ),
					'permission' => 'storesuite_' . self::AREA,
					'endpoint'   => self::ENDPOINT,
					'view'       => 'log',
				),
			),
		);

		return $menus;
	}

	/**
	 * Supply the dashboard page title (and breadcrumb leaf) for the inventory
	 * endpoint. The endpoint hosts two views on one page, switched via `?view=`,
	 * so the title reflects the active view.
	 *
	 * @param string $title Incoming title (empty for this endpoint).
	 * @return string
	 */
	public function endpoint_title( $title ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view switch for the page title.
		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'list';

		return ( 'log' === $view )
			? __( 'Movement log', 'storesuite' )
			: __( 'Stock list', 'storesuite' );
	}

	/**
	 * Render the inventory list or the stock log.
	 *
	 * @param array $query_vars Query vars.
	 * @return void
	 */
	public function load_template( $query_vars ) {
		if ( ! is_array( $query_vars ) || ! isset( $query_vars[ self::ENDPOINT ] ) ) {
			return;
		}

		if ( ! storesuite_current_user_can( self::AREA ) ) {
			storesuite_get_template_part( 'global/no-permission' );
			return;
		}

		// The React app renders both the stock list and the movement log; the
		// active view is switched client-side via `?view=`.
		$template = $this->get_path() . '/templates/inventory.php';

		if ( file_exists( $template ) ) {
			include $template;
		}
	}

	/**
	 * Enqueue the React inventory app on the inventory endpoint.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! storesuite_is_endpoint_url( self::ENDPOINT ) ) {
			return;
		}

		$handle     = 'storesuite-inventory-manager';
		$build      = $this->get_path() . '/assets/build';
		$build_url  = $this->get_url() . '/assets/build';
		$asset_file = $build . '/script.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = include $asset_file;

		wp_enqueue_script(
			$handle,
			$build_url . '/script.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		if ( file_exists( $build . '/script.css' ) ) {
			wp_enqueue_style(
				$handle,
				$build_url . '/script.css',
				array(),
				$asset['version']
			);
		}

		wp_add_inline_script(
			$handle,
			'window.StoreSuiteInventory = ' . wp_json_encode(
				array(
					'root'       => esc_url_raw( rest_url() ),
					'nonce'      => wp_create_nonce( 'wp_rest' ),
					'perPage'    => (int) apply_filters( 'storesuite_inventory_per_page', 20 ),
					'logPerPage' => (int) apply_filters( 'storesuite_stock_log_per_page', 30 ),
				)
			) . ';',
			'before'
		);

		wp_set_script_translations( $handle, 'storesuite' );
	}

	/**
	 * This module has no wp-admin React surface, so suppress the base class's
	 * default admin bundle enqueue (it would otherwise load our frontend build,
	 * which expects a mount div and config that only exist on the dashboard).
	 *
	 * @return void
	 */
	public function enqueue_admin_assets() {}

	/**
	 * {@inheritDoc}
	 */
	public function has_settings() {
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_settings_schema() {
		return Settings::get_schema();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_settings() {
		return Settings::get();
	}

	/**
	 * {@inheritDoc}
	 */
	public function update_settings( array $data ) {
		return Settings::update( $data );
	}

	/**
	 * Permanent teardown.
	 *
	 * @return void
	 */
	public function uninstall() {
		Installer::uninstall();
		delete_option( Settings::OPTION_KEY );
		delete_option( Emails\Manager::QUEUE_OPTION );
		delete_option( Emails\Manager::ALERT_SETTINGS_OPTION );
		delete_option( Emails\Manager::DIGEST_SETTINGS_OPTION );
	}
}
