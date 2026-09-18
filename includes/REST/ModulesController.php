<?php
/**
 * Modules REST API controller.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\REST;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes the StoreSuite module registry to the admin React app.
 *
 * Routes:
 *   GET  /storesuite/v1/modules
 *   POST /storesuite/v1/modules/{slug}/activate
 *   POST /storesuite/v1/modules/{slug}/deactivate
 */
class ModulesController extends WP_REST_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = 'storesuite/v1';
		$this->rest_base = 'modules';
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
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<slug>[A-Za-z0-9_\-]+)/activate',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'activate_item' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'slug' => array(
							'type'     => 'string',
							'required' => true,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<slug>[A-Za-z0-9_\-]+)/deactivate',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'deactivate_item' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'slug' => array(
							'type'     => 'string',
							'required' => true,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<slug>[A-Za-z0-9_\-]+)/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);
	}

	/**
	 * GET handler — list every discovered module with metadata + active state.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_items( $request ) {
		$manager = $this->get_manager();
		$items   = array();

		foreach ( $manager->get_all() as $slug => $module ) {
			$items[] = $this->prepare_module( $slug, $module, $manager );
		}

		return rest_ensure_response( $items );
	}

	/**
	 * Build the public representation of a module.
	 *
	 * @param string                                       $slug    Module slug.
	 * @param \PluginizeLab\StoreSuite\Abstracts\Module    $module  Module instance.
	 * @param \PluginizeLab\StoreSuite\Module\Manager      $manager Module manager.
	 * @return array
	 */
	private function prepare_module( $slug, $module, $manager ) {
		return array(
			'slug'         => $slug,
			'name'         => $module->get_name(),
			'description'  => $module->get_description(),
			'version'      => $module->get_version(),
			'active'       => $manager->is_active( $slug ),
			'has_settings' => (bool) $module->has_settings(),
			'admin_tabs'   => $this->sanitize_admin_tabs( $module->get_admin_tabs() ),
		);
	}

	/**
	 * Whitelist module-provided admin tab entries before they cross the
	 * REST boundary into the React app.
	 *
	 * Third-party modules can return arbitrary shapes from `get_admin_tabs()`.
	 * Anything that isn't a `{ to, label }` pair with safe values is dropped
	 * silently so the nav can never render junk or be tricked into routing to
	 * an external URL.
	 *
	 * Rules per entry:
	 *  - Must be an array with non-empty string `to` and `label`.
	 *  - `to` must start with a single `/` (React Router relative path); paths
	 *    starting with `//` are rejected to prevent protocol-relative escapes.
	 *  - `to` must not contain HTML-sensitive characters (`<`, `>`, `"`, `'`).
	 *  - Only the `to` and `label` keys survive — any extras are dropped.
	 *
	 * @param mixed $tabs Raw value returned by `Module::get_admin_tabs()`.
	 * @return array Sanitized list of `{ to, label }` entries.
	 */
	private function sanitize_admin_tabs( $tabs ) {
		if ( ! is_array( $tabs ) ) {
			return array();
		}

		$clean = array();

		foreach ( $tabs as $tab ) {
			if ( ! is_array( $tab ) ) {
				continue;
			}

			$to    = isset( $tab['to'] ) ? trim( (string) $tab['to'] ) : '';
			$label = isset( $tab['label'] ) ? trim( (string) $tab['label'] ) : '';

			if ( '' === $to || '' === $label ) {
				continue;
			}

			// React Router paths must be relative and rooted. Reject
			// protocol-relative (`//evil.example`), absolute URLs, fragments,
			// and queries — the SPA owns the hash, not the path.
			if ( '/' !== $to[0] || 0 === strpos( $to, '//' ) ) {
				continue;
			}

			// Defense in depth — labels are React-escaped on render, but a
			// `to` value embedded in markup elsewhere shouldn't carry HTML.
			if ( preg_match( '#[<>"\']#', $to ) ) {
				continue;
			}

			$clean[] = array(
				'to'    => $to,
				'label' => $label,
			);
		}

		return array_values( $clean );
	}

	/**
	 * POST handler — activate a module.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function activate_item( $request ) {
		$slug    = (string) $request->get_param( 'slug' );
		$manager = $this->get_manager();

		if ( ! isset( $manager->get_all()[ $slug ] ) ) {
			return new WP_Error(
				'storesuite_module_not_found',
				__( 'Module not found.', 'storesuite' ),
				array( 'status' => 404 )
			);
		}

		// Distinguish an unmet-dependency failure from a missing module so the
		// UI can tell the user which plugin to install.
		$missing = $manager->get_missing_requirements( $slug );
		if ( $missing ) {
			return new WP_Error(
				'storesuite_module_requirements_unmet',
				sprintf(
					/* translators: %s: comma-separated list of required plugin identifiers. */
					__( 'This module requires the following plugin(s) to be active: %s', 'storesuite' ),
					implode( ', ', $missing )
				),
				array(
					'status'  => 400,
					'missing' => $missing,
				)
			);
		}

		if ( ! $manager->activate( $slug ) ) {
			return new WP_Error(
				'storesuite_module_activation_failed',
				__( 'The module could not be activated.', 'storesuite' ),
				array( 'status' => 400 )
			);
		}

		return $this->item_response( $slug );
	}

	/**
	 * POST handler — deactivate a module.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function deactivate_item( $request ) {
		$slug = (string) $request->get_param( 'slug' );

		if ( ! $this->get_manager()->deactivate( $slug ) ) {
			return new WP_Error(
				'storesuite_module_not_found',
				__( 'Module not found.', 'storesuite' ),
				array( 'status' => 404 )
			);
		}

		return $this->item_response( $slug );
	}

	/**
	 * Build a single-item response after a state change.
	 *
	 * @param string $slug Module slug.
	 * @return \WP_REST_Response
	 */
	private function item_response( $slug ) {
		$manager = $this->get_manager();
		$modules = $manager->get_all();

		return rest_ensure_response( $this->prepare_module( $slug, $modules[ $slug ], $manager ) );
	}

	/**
	 * GET handler — return the module's settings schema and current values.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function get_settings( $request ) {
		$module = $this->resolve_settings_module( (string) $request->get_param( 'slug' ) );
		if ( is_wp_error( $module ) ) {
			return $module;
		}

		return rest_ensure_response(
			array(
				'name'   => $module->get_name(),
				'schema' => $module->get_settings_schema(),
				'values' => $module->get_settings(),
			)
		);
	}

	/**
	 * POST handler — persist settings for a module that supports them.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function update_settings( $request ) {
		$module = $this->resolve_settings_module( (string) $request->get_param( 'slug' ) );
		if ( is_wp_error( $module ) ) {
			return $module;
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$values = is_array( $params['values'] ?? null ) ? $params['values'] : array();

		return rest_ensure_response(
			array(
				'name'   => $module->get_name(),
				'schema' => $module->get_settings_schema(),
				'values' => $module->update_settings( $values ),
			)
		);
	}

	/**
	 * Look up a module by slug and ensure it exposes settings.
	 *
	 * @param string $slug Module slug.
	 * @return \PluginizeLab\StoreSuite\Abstracts\Module|WP_Error
	 */
	private function resolve_settings_module( $slug ) {
		$modules = $this->get_manager()->get_all();

		if ( ! isset( $modules[ $slug ] ) ) {
			return new WP_Error(
				'storesuite_module_not_found',
				__( 'Module not found.', 'storesuite' ),
				array( 'status' => 404 )
			);
		}

		$module = $modules[ $slug ];

		if ( ! $module->has_settings() ) {
			return new WP_Error(
				'storesuite_module_no_settings',
				__( 'This module does not expose configurable settings.', 'storesuite' ),
				array( 'status' => 400 )
			);
		}

		return $module;
	}

	/**
	 * Permission check — same gate as the rest of the admin app.
	 *
	 * @return bool
	 */
	public function permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Resolve the module manager from the StoreSuite container.
	 *
	 * @return \PluginizeLab\StoreSuite\Module\Manager
	 */
	private function get_manager() {
		return pluginizelab_storesuite()->modules;
	}
}
