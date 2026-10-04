<?php
/**
 * Customer CRM module entry point.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Modules\Customers;

use PluginizeLab\StoreSuite\Abstracts\Module as BaseModule;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Customer CRM module: a WooCommerce-admin React list + profile view shipped to
 * the frontend dashboard (like Analytics), backed by module-owned notes and
 * tags. Registered area: `customers` (cap `storesuite_manage_customers`).
 */
class Module extends BaseModule {

	const ENDPOINT = 'customers';
	const AREA     = 'manage_customers';

	/**
	 * {@inheritDoc}
	 */
	public function get_slug() {
		return 'customers';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return __( 'Customer CRM', 'storesuite' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description() {
		return __( 'A searchable customer list with a per-customer profile: purchase history, lifetime value, internal notes and tags.', 'storesuite' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_requires() {
		return array( 'woocommerce/woocommerce.php' );
	}

	/**
	 * One-shot setup: create the CRM tables.
	 *
	 * @return void
	 */
	public function activate() {
		Installer::install();
	}

	/**
	 * Boot hooks for an active module. Runs on `storesuite_loaded`.
	 *
	 * @return void
	 */
	public function boot() {
		Installer::maybe_upgrade();

		add_action( 'init', array( $this, 'register_endpoint' ) );
		add_filter( 'storesuite_query_var_filter', array( $this, 'register_query_var' ) );
		add_filter( 'storesuite_dashboard_menus', array( $this, 'register_menu' ), 20 );
		add_action( 'storesuite_load_custom_template', array( $this, 'load_template' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Register the permission area so the shortcode gate and the employee
		// role editor both know about it.
		add_filter( 'storesuite_endpoint_capability_map', array( $this, 'register_endpoint_area' ) );
		add_filter( 'storesuite_capability_registry', array( $this, 'register_capability' ) );

		( new Assets() )->register();
	}

	/**
	 * Register the `/customers/` rewrite endpoint.
	 *
	 * @return void
	 */
	public function register_endpoint() {
		add_rewrite_endpoint( self::ENDPOINT, EP_PAGES );
	}

	/**
	 * Expose the endpoint to the Rewrites helper.
	 *
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
	 * Map the customers endpoint to its permission area for the core shortcode
	 * gate.
	 *
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
	 * Register the CRM area in the capability registry (for the role editor).
	 *
	 * @param array $areas Area => label.
	 * @return array
	 */
	public function register_capability( $areas ) {
		if ( is_array( $areas ) ) {
			$areas[ self::AREA ] = __( 'Manage customers (CRM)', 'storesuite' );
		}
		return $areas;
	}

	/**
	 * Register the module REST routes.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		( new RestController() )->register_routes();
	}

	/**
	 * Add the "Customers" sidebar item.
	 *
	 * @param array $menus Menu definitions.
	 * @return array
	 */
	public function register_menu( $menus ) {
		if ( ! is_array( $menus ) ) {
			return $menus;
		}

		$menus['customers'] = array(
			'title'      => __( 'Customers', 'storesuite' ),
			'url'        => storesuite_get_navigation_url( self::ENDPOINT ),
			'permission' => 'storesuite_' . self::AREA,
			'icon'       => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M12,12A6,6,0,1,0,6,6,6,6,0,0,0,12,12ZM12,2A4,4,0,1,1,8,6,4,4,0,0,1,12,2Z"/><path d="M12,14a9,9,0,0,0-9,9,1,1,0,0,0,2,0,7,7,0,0,1,14,0,1,1,0,0,0,2,0A9,9,0,0,0,12,14Z"/></svg>',
		);

		return $menus;
	}

	/**
	 * Render the CRM mount point on the customers endpoint.
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

		$template = $this->get_path() . '/templates/customers.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
	}

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
	}
}
