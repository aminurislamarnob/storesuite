<?php
/**
 * Configurable throwaway module for exercising Module\Manager in tests.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Tests\Fixtures;

use PluginizeLab\StoreSuite\Abstracts\Module;

/**
 * A Module whose slug and plugin requirements are set per instance, and which
 * counts every lifecycle call so tests can assert on hook idempotency.
 */
class FixtureModule extends Module {

	/**
	 * Module slug.
	 *
	 * @var string
	 */
	private $slug;

	/**
	 * Required plugin basenames.
	 *
	 * @var string[]
	 */
	private $requires;

	/**
	 * Number of times activate() ran.
	 *
	 * @var int
	 */
	public $activate_calls = 0;

	/**
	 * Number of times deactivate() ran.
	 *
	 * @var int
	 */
	public $deactivate_calls = 0;

	/**
	 * Number of times boot() ran.
	 *
	 * @var int
	 */
	public $boot_calls = 0;

	/**
	 * Number of times uninstall() ran.
	 *
	 * @var int
	 */
	public $uninstall_calls = 0;

	/**
	 * Admin tabs returned by get_admin_tabs(). Tests assign raw (possibly
	 * malformed) shapes here to exercise REST-boundary sanitization.
	 *
	 * @var array
	 */
	public $admin_tabs = array();

	/**
	 * Whether the module reports configurable settings.
	 *
	 * @var bool
	 */
	public $has_settings = false;

	/**
	 * Settings schema returned by get_settings_schema().
	 *
	 * @var array
	 */
	public $settings_schema = array();

	/**
	 * Current setting values; update_settings() writes back here so tests can
	 * assert on what was persisted.
	 *
	 * @var array
	 */
	public $settings_values = array();

	/**
	 * Constructor.
	 *
	 * @param string   $slug     Module slug.
	 * @param string[] $requires Required plugin basenames.
	 */
	public function __construct( $slug, array $requires = array() ) {
		parent::__construct( __FILE__ );
		$this->slug     = $slug;
		$this->requires = $requires;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_slug() {
		return $this->slug;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return 'Fixture: ' . $this->slug;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_requires() {
		return $this->requires;
	}

	/**
	 * {@inheritDoc}
	 */
	public function boot() {
		++$this->boot_calls;
	}

	/**
	 * {@inheritDoc}
	 */
	public function activate() {
		++$this->activate_calls;
	}

	/**
	 * {@inheritDoc}
	 */
	public function deactivate() {
		++$this->deactivate_calls;
	}

	/**
	 * {@inheritDoc}
	 */
	public function uninstall() {
		++$this->uninstall_calls;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_admin_tabs() {
		return $this->admin_tabs;
	}

	/**
	 * {@inheritDoc}
	 */
	public function has_settings() {
		return $this->has_settings;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_settings_schema() {
		return $this->settings_schema;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_settings() {
		return $this->settings_values;
	}

	/**
	 * {@inheritDoc}
	 */
	public function update_settings( array $data ) {
		$this->settings_values = array_intersect_key( $data, $this->settings_schema );

		return $this->settings_values;
	}
}
