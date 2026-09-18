<?php
/**
 * Module discovery and lifecycle manager.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Module;

use PluginizeLab\StoreSuite\Abstracts\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Discovers modules under `modules/<slug>/module.php`, tracks which ones the
 * site administrator has activated, and boots them after StoreSuite is loaded.
 *
 * Active module slugs are persisted in the `storesuite_active_modules` option
 * (a plain array of slugs). Other code can query state via `is_active()` and
 * `get_active()`, or react to the per-module `storesuite_module_{slug}_loaded`
 * action.
 */
class Manager {

	const ACTIVE_OPTION = 'storesuite_active_modules';

	/**
	 * Map of slug => Module instance for every module discovered on disk.
	 *
	 * @var Module[]
	 */
	private $modules = array();

	/**
	 * Whether discovery has run yet.
	 *
	 * @var bool
	 */
	private $discovered = false;

	/**
	 * Wire the manager into the StoreSuite lifecycle.
	 */
	public function __construct() {
		// Boot active modules after the rest of StoreSuite has registered its
		// services and helpers — modules can then safely call into them.
		// Discovery itself is lazy (see `get_all()`).
		add_action( 'storesuite_loaded', array( $this, 'boot_active' ) );
	}

	/**
	 * Root directory holding all bundled modules.
	 *
	 * @return string
	 */
	public function get_modules_dir() {
		/**
		 * Filter the modules directory. Useful for tests or for sites that want
		 * to load modules from a custom location.
		 *
		 * @param string $dir Absolute path, no trailing slash.
		 */
		return untrailingslashit( apply_filters( 'storesuite_modules_dir', STORESUITE_DIR . '/modules' ) );
	}

	/**
	 * Scan the modules directory and build the registry.
	 *
	 * Each subdirectory containing a `module.php` is loaded; the bootstrap is
	 * expected to `return` an instance of `Abstracts\Module`. Third-party code
	 * can register additional modules via the `storesuite_register_modules`
	 * filter (receives the slug => instance map).
	 *
	 * @return void
	 */
	public function discover() {
		if ( $this->discovered ) {
			return;
		}
		$this->discovered = true;

		$dir = $this->get_modules_dir();

		// Only scan the bundled directory when it exists — but always run the
		// registration filter below so third-party modules can register even
		// when the bundled `modules/` directory is absent (custom builds,
		// filtered directory).
		if ( is_dir( $dir ) ) {
			foreach ( (array) glob( $dir . '/*/module.php' ) as $bootstrap ) {
				// `include_once` returns the file's return value only the first
				// time it is included; a second include yields `true`. Discovery
				// runs once per request (guarded by `$this->discovered`), so each
				// bootstrap is included exactly once here and returns its Module.
				$module = include_once $bootstrap;

				$this->register_module( $module );
			}
		}

		/**
		 * Register additional modules from outside the bundled directory.
		 *
		 * @param Module[] $modules Map of slug => Module instance.
		 */
		$registered = apply_filters( 'storesuite_register_modules', $this->modules );

		// Re-validate after the filter: third-party callers can return anything,
		// and an invalid entry would fatal later when `boot_active()` calls
		// `->boot()` on it. Rebuild the registry from scratch so only valid
		// Module instances survive.
		if ( is_array( $registered ) && $registered !== $this->modules ) {
			$this->modules = array();
			foreach ( $registered as $module ) {
				$this->register_module( $module );
			}
		}
	}

	/**
	 * Validate a discovered/registered value and add it to the registry.
	 *
	 * Silently ignores anything that isn't a `Module` with a non-empty slug,
	 * and never lets a later registration clobber an already-registered slug.
	 *
	 * @param mixed $module Candidate module instance.
	 * @return void
	 */
	private function register_module( $module ) {
		if ( ! $module instanceof Module ) {
			return;
		}

		$slug = $module->get_slug();
		if ( empty( $slug ) || isset( $this->modules[ $slug ] ) ) {
			return;
		}

		$this->modules[ $slug ] = $module;
	}

	/**
	 * Boot every active module. Fires a per-module action so other code can
	 * hang behavior on a specific module being loaded.
	 *
	 * @return void
	 */
	public function boot_active() {
		foreach ( $this->get_active() as $module ) {
			// A required plugin may have been deactivated after this module was
			// activated. Skip booting rather than fataling on a missing
			// dependency; the Modules screen still shows it as active.
			if ( $this->get_missing_requirements( $module->get_slug() ) ) {
				continue;
			}

			$module->boot();

			/**
			 * Fired after a single module finishes booting.
			 *
			 * @param Module $module The module instance.
			 */
			do_action( 'storesuite_module_' . $module->get_slug() . '_loaded', $module );
		}

		/**
		 * Fired after all active modules are loaded.
		 *
		 * @param Manager $manager The module manager.
		 */
		do_action( 'storesuite_modules_loaded', $this );
	}

	/**
	 * Every discovered module, keyed by slug.
	 *
	 * @return Module[]
	 */
	public function get_all() {
		$this->discover();
		return $this->modules;
	}

	/**
	 * Active modules only, keyed by slug.
	 *
	 * @return Module[]
	 */
	public function get_active() {
		$active = $this->get_active_slugs();
		return array_intersect_key( $this->get_all(), array_flip( $active ) );
	}

	/**
	 * Slugs persisted in the active-modules option, filtered to ones that
	 * actually exist on disk.
	 *
	 * @return string[]
	 */
	public function get_active_slugs() {
		$stored = (array) get_option( self::ACTIVE_OPTION, array() );
		return array_values( array_intersect( $stored, array_keys( $this->get_all() ) ) );
	}

	/**
	 * Is the module active?
	 *
	 * @param string $slug Module slug.
	 * @return bool
	 */
	public function is_active( $slug ) {
		return in_array( $slug, $this->get_active_slugs(), true );
	}

	/**
	 * Activate a module: persist the slug and run its `activate()` hook.
	 *
	 * @param string $slug Module slug.
	 * @return bool True if the module is now active; false if the slug is
	 *              unknown or a required plugin (see `Module::get_requires()`)
	 *              is not active.
	 */
	public function activate( $slug ) {
		$modules = $this->get_all();
		if ( ! isset( $modules[ $slug ] ) ) {
			return false;
		}

		// Refuse activation when a declared dependency plugin is missing.
		if ( $this->get_missing_requirements( $slug ) ) {
			return false;
		}

		if ( ! $this->is_active( $slug ) ) {
			$active   = $this->get_active_slugs();
			$active[] = $slug;
			update_option( self::ACTIVE_OPTION, array_values( array_unique( $active ) ) );

			$modules[ $slug ]->activate();
			$this->flag_rewrite_flush();

			do_action( 'storesuite_module_activated', $slug, $modules[ $slug ] );
		}

		return true;
	}

	/**
	 * Plugin dependencies a module declares (via `get_requires()`) that are not
	 * currently active. Empty array means every requirement is satisfied.
	 *
	 * @param string $slug Module slug.
	 * @return string[] Plugin basenames (e.g. `woocommerce/woocommerce.php`) that are missing.
	 */
	public function get_missing_requirements( $slug ) {
		$modules = $this->get_all();
		if ( ! isset( $modules[ $slug ] ) ) {
			return array();
		}

		$missing = array();
		foreach ( (array) $modules[ $slug ]->get_requires() as $plugin ) {
			if ( $plugin && ! $this->is_plugin_active( (string) $plugin ) ) {
				$missing[] = (string) $plugin;
			}
		}

		return $missing;
	}

	/**
	 * Thin wrapper over core `is_plugin_active()`, loading the admin plugin
	 * helpers on the front end where they aren't included by default.
	 *
	 * @param string $plugin Plugin basename.
	 * @return bool
	 */
	private function is_plugin_active( $plugin ) {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( $plugin );
	}

	/**
	 * Run every discovered module's `uninstall()` teardown. Intended to be
	 * called from the plugin's root `uninstall.php` when StoreSuite is deleted,
	 * giving each module a chance to drop its tables and options.
	 *
	 * @return void
	 */
	public function uninstall_all() {
		foreach ( $this->get_all() as $module ) {
			$module->uninstall();
		}
	}

	/**
	 * Deactivate a module: remove the slug and run its `deactivate()` hook.
	 *
	 * @param string $slug Module slug.
	 * @return bool True on success, false if the slug is unknown.
	 */
	public function deactivate( $slug ) {
		$modules = $this->get_all();
		if ( ! isset( $modules[ $slug ] ) ) {
			return false;
		}

		if ( $this->is_active( $slug ) ) {
			$active = array_diff( $this->get_active_slugs(), array( $slug ) );
			update_option( self::ACTIVE_OPTION, array_values( $active ) );

			$modules[ $slug ]->deactivate();
			$this->flag_rewrite_flush();

			do_action( 'storesuite_module_deactivated', $slug, $modules[ $slug ] );
		}

		return true;
	}

	/**
	 * Ask StoreSuite to flush rewrite rules on the next request.
	 *
	 * Modules can register their own rewrite endpoints during `boot()`. Those
	 * rules only become routable after `flush_rewrite_rules()` runs, so any
	 * activation/deactivation toggles need to trigger one. We reuse the
	 * existing `storesuite_flush_rewrite_rules` option that
	 * `StoreSuite::maybe_flush_rewrite_rules()` handles on `init`.
	 */
	private function flag_rewrite_flush() {
		update_option( 'storesuite_flush_rewrite_rules', 1 );
	}
}
