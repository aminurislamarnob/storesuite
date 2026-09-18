<?php
/**
 * Base class for StoreSuite modules.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Abstracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every StoreSuite module extends this class.
 *
 * A module is a self-contained feature (staff management, customer management,
 * etc.) that lives under the plugin's `modules/` directory and can be activated
 * or deactivated independently via the admin Modules screen.
 *
 * Subclasses must define the static metadata methods. The `boot()` method is
 * called by the ModuleManager when the module is active and StoreSuite has
 * finished loading. Activation and deactivation hooks let modules run one-shot
 * setup or teardown work (creating tables, scheduling cron, flushing rewrites).
 */
abstract class Module {

	/**
	 * Absolute path to the module's bootstrap file.
	 *
	 * @var string
	 */
	protected $file = '';

	/**
	 * Constructor.
	 *
	 * @param string $file Absolute path to the module's bootstrap file
	 *                     (e.g. `modules/staff-manager/module.php`). Stored so
	 *                     the module can resolve its own directory/URL.
	 */
	public function __construct( $file = '' ) {
		$this->file = $file;
	}

	/**
	 * Unique slug for the module (kebab-case).
	 *
	 * Used as the directory name under `modules/` and as the identifier stored
	 * in the active-modules option.
	 *
	 * @return string
	 */
	abstract public function get_slug();

	/**
	 * Human-readable module name.
	 *
	 * @return string
	 */
	abstract public function get_name();

	/**
	 * Short description shown on the Modules admin screen.
	 *
	 * @return string
	 */
	public function get_description() {
		return '';
	}

	/**
	 * Module version.
	 *
	 * @return string
	 */
	public function get_version() {
		return '1.0.0';
	}

	/**
	 * Optional list of plugin slugs the module depends on
	 * (e.g. `array( 'woocommerce/woocommerce.php' )`).
	 *
	 * @return array
	 */
	public function get_requires() {
		return array();
	}

	/**
	 * Module directory path (no trailing slash).
	 *
	 * @return string
	 */
	public function get_path() {
		return untrailingslashit( dirname( $this->file ) );
	}

	/**
	 * Module directory URL (no trailing slash).
	 *
	 * @return string
	 */
	public function get_url() {
		return untrailingslashit( plugins_url( '', $this->file ) );
	}

	/**
	 * Register hooks and services. Called by ModuleManager when the module is
	 * active. Runs on the `storesuite_loaded` action so WordPress, WooCommerce
	 * and the StoreSuite container are all ready.
	 *
	 * @return void
	 */
	abstract public function boot();

	/**
	 * One-shot setup work (create tables, seed options, schedule cron).
	 * Called when the user activates the module from the admin screen.
	 *
	 * @return void
	 */
	public function activate() {}

	/**
	 * One-shot teardown work. Called when the user deactivates the module.
	 *
	 * Deactivation is intentionally non-destructive — a module that owns a DB
	 * table or options should leave that data intact here so it survives a
	 * toggle off/on. Destructive cleanup belongs in `uninstall()`.
	 *
	 * @return void
	 */
	public function deactivate() {}

	/**
	 * Permanent teardown. Called from the plugin's root `uninstall.php` (via
	 * `Module\Manager::uninstall_all()`) when StoreSuite itself is deleted.
	 * This is where a module drops its tables and deletes its options — the
	 * one place data removal is expected.
	 *
	 * Only BUNDLED modules (under the plugin's own `modules/` directory) are
	 * uninstalled this way. Modules shipped by third-party plugins via the
	 * `storesuite_register_modules` filter are skipped — those plugins remain
	 * installed after StoreSuite is deleted and must run their own
	 * `uninstall.php` teardown.
	 *
	 * @return void
	 */
	public function uninstall() {}

	/**
	 * Does this module expose configurable settings on the admin Modules
	 * screen? Modules with settings get a "Configure" action and their own
	 * route at `#/modules/{slug}` in the React app.
	 *
	 * @return bool
	 */
	public function has_settings() {
		return false;
	}

	/**
	 * Schema describing the module's settings fields. Used by the React app
	 * to render a form generically. Subclasses override this — see
	 * `Modules\StaffManager\Settings::get_schema()` for an example.
	 *
	 * Each field is keyed by setting name with the shape:
	 *   array(
	 *       'type'        => 'toggle' | 'text' | 'number' | 'select',
	 *       'label'       => 'Human label',
	 *       'description' => 'Optional help text',
	 *       'default'     => mixed,
	 *       'options'     => array( value => label ),   // select only
	 *       'min'         => int,                       // number only
	 *       'max'         => int,                       // number only
	 *   )
	 *
	 * @return array
	 */
	public function get_settings_schema() {
		return array();
	}

	/**
	 * Current setting values, merged with defaults from the schema.
	 *
	 * @return array
	 */
	public function get_settings() {
		return array();
	}

	/**
	 * Persist settings. Implementations should sanitize against the schema
	 * and return the canonical post-save values.
	 *
	 * @param array $data Raw input.
	 * @return array Saved values.
	 */
	public function update_settings( array $data ) {
		unset( $data );
		return $this->get_settings();
	}

	/**
	 * Top-level navigation tabs the module wants to inject into the StoreSuite
	 * admin app while it is active. Each entry has the shape:
	 *
	 * Array shape:
	 *   array(
	 *       'to'    => '/staff-manager',          // React Router path
	 *       'label' => __( 'Staff', 'storesuite' ),
	 *   )
	 *
	 * The React app fetches the modules list, picks up active modules' tabs,
	 * and renders them after the built-in tabs. The matching React route must
	 * be registered in `admin.js`.
	 *
	 * @return array
	 */
	public function get_admin_tabs() {
		return array();
	}

	/**
	 * Enqueue the module's admin React bundle on the StoreSuite settings page.
	 *
	 * Default behaviour: if `modules/<slug>/assets/build/script.js` exists,
	 * enqueue it declaring `storesuite-admin-page` as a dependency so the
	 * `storesuite_admin_routes` hooks filter is wired before the module bundle
	 * runs `addFilter`. The build artifact lives inside the module's own
	 * directory (not under the top-level `assets/build/`) so a module is
	 * portable as a standalone unit — matches Dokan Pro's distribution model.
	 *
	 * Subclasses can override to add CSS, change handles, or skip when the
	 * module has no admin React surface. The Assets class invokes this on
	 * `admin_enqueue_scripts` only when the StoreSuite settings page is the
	 * current screen AND the module is active.
	 *
	 * @return void
	 */
	public function enqueue_admin_assets() {
		$relative   = 'modules/' . $this->get_slug() . '/assets/build/script.js';
		$file       = STORESUITE_DIR . '/' . $relative;
		$asset_file = STORESUITE_DIR . '/modules/' . $this->get_slug() . '/assets/build/script.asset.php';

		if ( ! file_exists( $file ) ) {
			return;
		}

		$asset = file_exists( $asset_file )
			? include $asset_file
			: array(
				'dependencies' => array(),
				'version'      => filemtime( $file ),
			);

		$deps = array_values(
			array_unique(
				array_merge(
					array( 'storesuite-admin-page', 'wp-hooks' ),
					(array) ( $asset['dependencies'] ?? array() )
				)
			)
		);

		wp_enqueue_script(
			'storesuite-module-' . $this->get_slug(),
			STORESUITE_PLUGIN_URL . $relative,
			$deps,
			$asset['version'] ?? null,
			true
		);
	}
}
