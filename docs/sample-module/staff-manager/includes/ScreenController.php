<?php
/**
 * Staff Manager — REST controller for the dedicated Staff admin screen.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Modules\StaffManager;

use WP_REST_Controller;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Demonstrates that a module can register its own REST routes alongside the
 * core StoreSuite endpoints. Owns the `storesuite_staff_manager_screen` option
 * and exposes it at `storesuite/v1/staff-manager/screen`.
 *
 * This is distinct from the per-module Settings flow surfaced under
 * `Modules > Configure` — those settings configure the module itself; these
 * power the top-nav Staff landing screen.
 */
class ScreenController extends WP_REST_Controller {

	const OPTION_KEY = 'storesuite_staff_manager_screen';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = 'storesuite/v1';
		$this->rest_base = 'staff-manager/screen';
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_screen' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_screen' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);
	}

	/**
	 * Default values for every field on the Staff screen.
	 *
	 * @return array
	 */
	public static function get_defaults() {
		return array(
			'landing_heading'         => __( 'Welcome to your team workspace', 'storesuite' ),
			'enable_new_staff_signup' => false,
			'staff_dashboard_redirect' => 'dashboard',
			'notify_admin_on_signup'  => true,
		);
	}

	/**
	 * Current values merged with defaults.
	 *
	 * @return array
	 */
	public static function get() {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array_merge( self::get_defaults(), $stored );
	}

	/**
	 * GET handler.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_screen( $request ) {
		return rest_ensure_response( self::get() );
	}

	/**
	 * POST handler — sanitize against the field types and persist.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function update_screen( $request ) {
		$current = self::get();
		$clean   = $current;

		if ( $request->has_param( 'landing_heading' ) ) {
			$clean['landing_heading'] = sanitize_text_field(
				(string) $request->get_param( 'landing_heading' )
			);
		}

		if ( $request->has_param( 'enable_new_staff_signup' ) ) {
			$clean['enable_new_staff_signup'] = (bool) $request->get_param( 'enable_new_staff_signup' );
		}

		if ( $request->has_param( 'staff_dashboard_redirect' ) ) {
			$redirect = (string) $request->get_param( 'staff_dashboard_redirect' );
			$allowed  = array( 'dashboard', 'products', 'orders' );
			$clean['staff_dashboard_redirect'] = in_array( $redirect, $allowed, true )
				? $redirect
				: $current['staff_dashboard_redirect'];
		}

		if ( $request->has_param( 'notify_admin_on_signup' ) ) {
			$clean['notify_admin_on_signup'] = (bool) $request->get_param( 'notify_admin_on_signup' );
		}

		update_option( self::OPTION_KEY, $clean );

		return rest_ensure_response( $clean );
	}

	/**
	 * Permission check — admins only, same gate as the rest of the settings app.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool
	 */
	public function permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}
}
