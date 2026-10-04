<?php
/**
 * Custom Order Statuses module entry point.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Modules\OrderStatuses;

use PluginizeLab\StoreSuite\Abstracts\Module as BaseModule;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom Order Statuses module: unlimited custom WooCommerce order statuses with
 * colours, transition rules, paid/reporting behaviour and bulk apply. Managed
 * from a dashboard screen under the orders area (cap storesuite_manage_orders).
 */
class Module extends BaseModule {

	const ENDPOINT = 'order-statuses';
	const AREA     = 'orders';

	/**
	 * {@inheritDoc}
	 */
	public function get_slug() {
		return 'order-statuses';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return __( 'Custom Order Statuses', 'storesuite' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description() {
		return __( 'Create unlimited custom order statuses with colours, transition rules, paid/reporting behaviour and bulk apply.', 'storesuite' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_requires() {
		return array( 'woocommerce/woocommerce.php' );
	}

	/**
	 * Boot hooks for an active module.
	 *
	 * @return void
	 */
	public function boot() {
		( new Registrar() )->register();
		( new AjaxController() )->register();

		add_action( 'init', array( $this, 'register_endpoint' ) );
		add_filter( 'storesuite_query_var_filter', array( $this, 'register_query_var' ) );
		add_filter( 'storesuite_dashboard_menus', array( $this, 'register_menu' ), 20 );
		add_action( 'storesuite_load_custom_template', array( $this, 'load_template' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'storesuite_endpoint_capability_map', array( $this, 'register_endpoint_area' ) );
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
	 * Add an "Order Statuses" item under the orders area in the sidebar.
	 *
	 * @param array $menus Menu definitions.
	 * @return array
	 */
	public function register_menu( $menus ) {
		if ( ! is_array( $menus ) ) {
			return $menus;
		}

		// Prefer nesting under the existing Orders submenu when present.
		if ( isset( $menus['orders']['submenu'] ) && is_array( $menus['orders']['submenu'] ) ) {
			$menus['orders']['submenu']['order-statuses'] = array(
				'title'      => __( 'Order Statuses', 'storesuite' ),
				'url'        => storesuite_get_navigation_url( self::ENDPOINT ),
				'permission' => 'storesuite_' . self::AREA,
				'endpoint'   => self::ENDPOINT,
			);
			return $menus;
		}

		$menus['order-statuses'] = array(
			'title'      => __( 'Order Statuses', 'storesuite' ),
			'url'        => storesuite_get_navigation_url( self::ENDPOINT ),
			'permission' => 'storesuite_' . self::AREA,
			'icon'       => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M12,0A12,12,0,1,0,24,12,12,12,0,0,0,12,0Zm0,22A10,10,0,1,1,22,12,10,10,0,0,1,12,22Z"/><path d="M12,6a1,1,0,0,0-1,1v5a1,1,0,0,0,.29.71l3,3a1,1,0,0,0,1.42-1.42L13,13.59V7A1,1,0,0,0,12,6Z"/></svg>',
		);

		return $menus;
	}

	/**
	 * Render the manage-statuses screen.
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

		$template = $this->get_path() . '/templates/order-statuses.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
	}

	/**
	 * Enqueue the manage screen JS, and the transition-enforcement JS on the
	 * order edit page.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		$on_manage = storesuite_is_endpoint_url( self::ENDPOINT );
		$on_edit   = storesuite_is_endpoint_url( 'edit-order' ) || storesuite_is_endpoint_url( 'add-new-order' );

		if ( ! $on_manage && ! $on_edit ) {
			return;
		}

		$handle = 'storesuite-order-statuses';
		wp_enqueue_script(
			$handle,
			$this->get_url() . '/assets/order-statuses.js',
			array( 'jquery', 'storesuite_sweetalert2_script' ),
			$this->get_version(),
			true
		);

		wp_localize_script(
			$handle,
			'StoreSuiteOrderStatuses',
			array(
				'ajax_url'    => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( AjaxController::NONCE ),
				'transitions' => $this->transition_map(),
				'context'     => $on_manage ? 'manage' : 'edit',
				'i18n'        => array(
					'error'         => __( 'Something went wrong', 'storesuite' ),
					'confirmDelete' => __( 'Delete this status? Orders using it move to the fallback status.', 'storesuite' ),
				),
			)
		);
	}

	/**
	 * Build a slug => allowed-next-slugs map for client-side transition
	 * enforcement on the order edit dropdown.
	 *
	 * @return array<string,string[]>
	 */
	private function transition_map() {
		$map = array();
		foreach ( StatusRepository::all() as $status ) {
			if ( ! empty( $status['transitions'] ) ) {
				$map[ $status['slug'] ] = $status['transitions'];
			}
		}
		return $map;
	}

	/**
	 * Permanent teardown: reassign orders off custom statuses and drop the
	 * option.
	 *
	 * @return void
	 */
	public function uninstall() {
		foreach ( array_keys( StatusRepository::all() ) as $slug ) {
			StatusRepository::reassign_orders( $slug, 'on-hold' );
		}
		delete_option( StatusRepository::OPTION_KEY );
	}
}
