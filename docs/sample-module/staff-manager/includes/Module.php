<?php
/**
 * Staff Manager module entry point.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Modules\StaffManager;

use PluginizeLab\StoreSuite\Abstracts\Module as BaseModule;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Example module demonstrating the full StoreSuite module contract:
 * a custom DB table created on activation, a rewrite endpoint that needs a
 * permalink flush, dashboard sidebar integration, and configurable settings
 * surfaced through the generic Modules screen in the React admin.
 */
class Module extends BaseModule {

	const ENDPOINT = 'staff';

	/**
	 * {@inheritDoc}
	 */
	public function get_slug() {
		return 'staff-manager';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return __( 'Staff Manager', 'storesuite' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description() {
		return __( 'Add staff accounts with scoped capabilities so team members can manage the store without full WordPress admin access.', 'storesuite' );
	}

	/**
	 * Create the staff table on first activation. The Manager already flags
	 * a rewrite flush after this returns, so the `/staff` endpoint registered
	 * in `boot()` will be routable on the next request.
	 *
	 * @return void
	 */
	public function activate() {
		Installer::install();
	}

	/**
	 * Permanent teardown when StoreSuite is deleted. Drops the staff table and
	 * removes every option this module owns. Runs from the plugin's root
	 * `uninstall.php` via `Module\Manager::uninstall_all()`.
	 *
	 * @return void
	 */
	public function uninstall() {
		Installer::uninstall();
		delete_option( Settings::OPTION_KEY );
		delete_option( ScreenController::OPTION_KEY );
	}

	/**
	 * Boot hooks for an active module. Runs on `storesuite_loaded`.
	 *
	 * @return void
	 */
	public function boot() {
		// Pick up any pending schema upgrade shipped with a plugin update.
		Installer::maybe_upgrade();

		add_action( 'init', array( $this, 'register_endpoint' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_filter( 'storesuite_query_var_filter', array( $this, 'register_query_var' ) );
		add_filter( 'storesuite_dashboard_menus', array( $this, 'register_menu' ), 20 );
		add_action( 'storesuite_load_custom_template', array( $this, 'load_template' ) );
	}

	/**
	 * Render the Staff landing page when the `/storesuite-dashboard/staff/`
	 * endpoint is requested.
	 *
	 * The core dashboard shortcode fires `storesuite_load_custom_template` for
	 * any request that doesn't match a built-in query var, passing the current
	 * query vars. We claim the request when our endpoint var is present. This
	 * mirrors how the Analytics feature renders its own screen.
	 *
	 * @param array $query_vars Current WP query vars.
	 * @return void
	 */
	public function load_template( $query_vars ) {
		if ( ! is_array( $query_vars ) || ! isset( $query_vars[ self::ENDPOINT ] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			storesuite_get_template_part( 'global/no-permission' );
			return;
		}

		$template = $this->get_path() . '/templates/staff.php';
		if ( ! file_exists( $template ) ) {
			return;
		}

		// Exposed to the template scope.
		$screen   = ScreenController::get();
		$settings = Settings::get();

		include $template;
	}

	/**
	 * Register the module's own REST routes. Demonstrates that a module can
	 * extend the REST surface beyond what the core ModulesController exposes.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		( new ScreenController() )->register_routes();
	}

	/**
	 * Register the `/storesuite-dashboard/staff/` rewrite endpoint.
	 *
	 * @return void
	 */
	public function register_endpoint() {
		add_rewrite_endpoint( self::ENDPOINT, EP_PAGES );
	}

	/**
	 * Expose the endpoint to the existing StoreSuite Rewrites helper so the
	 * sidebar active-state and template resolver can detect it.
	 *
	 * @param array $vars Existing query vars.
	 * @return array
	 */
	public function register_query_var( $vars ) {
		if ( is_array( $vars ) ) {
			$vars[ self::ENDPOINT ] = self::ENDPOINT;
		}
		return $vars;
	}

	/**
	 * Add a Staff item to the dashboard sidebar.
	 *
	 * @param array $menus Existing menu definitions.
	 * @return array
	 */
	public function register_menu( $menus ) {
		if ( ! is_array( $menus ) ) {
			return $menus;
		}

		$menus['staff'] = array(
			'title'      => __( 'Staff', 'storesuite' ),
			// Resolve against the configured dashboard page permalink (same as
			// core menu items) rather than assuming a fixed `/storesuite-dashboard/`
			// path, which breaks if the page is renamed or the permalink differs.
			'url'        => storesuite_get_navigation_url( self::ENDPOINT ),
			'permission' => 'manage_woocommerce',
			'icon'       => '<svg xmlns="http://www.w3.org/2000/svg" id="Layer_1" data-name="Layer 1" viewBox="0 0 24 24" width="24" height="24"><path d="M15,6c0-3.309-2.691-6-6-6S3,2.691,3,6s2.691,6,6,6,6-2.691,6-6Zm-6,4c-2.206,0-4-1.794-4-4s1.794-4,4-4,4,1.794,4,4-1.794,4-4,4Zm-.008,4.938c.068,.548-.32,1.047-.869,1.116-3.491,.436-6.124,3.421-6.124,6.946,0,.552-.448,1-1,1s-1-.448-1-1c0-4.531,3.386-8.37,7.876-8.93,.542-.069,1.047,.32,1.116,.869Zm13.704,4.195l-.974-.562c.166-.497,.278-1.019,.278-1.572s-.111-1.075-.278-1.572l.974-.562c.478-.276,.642-.888,.366-1.366-.277-.479-.887-.644-1.366-.366l-.973,.562c-.705-.794-1.644-1.375-2.723-1.594v-1.101c0-.552-.448-1-1-1s-1,.448-1,1v1.101c-1.079,.22-2.018,.801-2.723,1.594l-.973-.562c-.48-.277-1.09-.113-1.366,.366-.276,.479-.112,1.09,.366,1.366l.974,.562c-.166,.497-.278,1.019-.278,1.572s.111,1.075,.278,1.572l-.974,.562c-.478,.276-.642,.888-.366,1.366,.186,.321,.521,.5,.867,.5,.169,0,.341-.043,.499-.134l.973-.562c.705,.794,1.644,1.375,2.723,1.594v1.101c0,.552,.448,1,1,1s1-.448,1-1v-1.101c1.079-.22,2.018-.801,2.723-1.594l.973,.562c.158,.091,.33,.134,.499,.134,.346,0,.682-.179,.867-.5,.276-.479,.112-1.09-.366-1.366Zm-5.696,.866c-1.654,0-3-1.346-3-3s1.346-3,3-3,3,1.346,3,3-1.346,3-3,3Z"/></svg>',
		);

		return $menus;
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
	 * Inject a top-level "Staff" tab into the StoreSuite admin navigation
	 * while this module is active. The matching React route lives in
	 * `src/admin.js` and renders `StaffManagerAdmin`.
	 *
	 * @return array
	 */
	public function get_admin_tabs() {
		return array(
			array(
				'to'    => '/staff-manager',
				'label' => __( 'Staff', 'storesuite' ),
			),
		);
	}
}
