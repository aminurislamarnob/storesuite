<?php
/**
 * Module\Manager lifecycle tests: dependency gating, activate/deactivate
 * idempotency, boot behavior, and registry validation.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Tests\Module;

use PluginizeLab\StoreSuite\Module\Manager;
use PluginizeLab\StoreSuite\Tests\Fixtures\FixtureModule;
use WP_UnitTestCase;

/**
 * Tests for PluginizeLab\StoreSuite\Module\Manager.
 *
 * Each test builds its own Manager and injects fixture modules through the
 * storesuite_register_modules filter (disk scanning is pointed at a
 * nonexistent directory), so tests never depend on the bundled modules.
 * WP_UnitTestCase rolls back the DB and restores hooks between tests.
 */
class ManagerTest extends WP_UnitTestCase {

	const FAKE_DEP = 'fake-dependency/fake-dependency.php';

	/**
	 * Reset the options the Manager writes to.
	 */
	public function set_up() {
		parent::set_up();
		delete_option( Manager::ACTIVE_OPTION );
		delete_option( 'storesuite_flush_rewrite_rules' );
	}

	/**
	 * Build a Manager whose registry contains exactly $modules.
	 *
	 * @param FixtureModule[] $modules Fixture modules to register.
	 * @return Manager
	 */
	private function make_manager( array $modules ) {
		add_filter(
			'storesuite_modules_dir',
			function () {
				return sys_get_temp_dir() . '/storesuite-tests-no-such-dir';
			}
		);

		add_filter(
			'storesuite_register_modules',
			function ( $registered ) use ( $modules ) {
				foreach ( $modules as $module ) {
					$registered[ $module->get_slug() ] = $module;
				}
				return $registered;
			}
		);

		return new Manager();
	}

	/**
	 * Mark the fake dependency plugin as active (is_plugin_active() only
	 * consults the active_plugins option; the file need not exist).
	 */
	private function activate_fake_dependency() {
		update_option( 'active_plugins', array( self::FAKE_DEP ) );
	}

	/*
	|-----------------------------------------------------------------------
	| Dependency gating
	|-----------------------------------------------------------------------
	*/

	public function test_activate_refuses_when_required_plugin_is_inactive() {
		$module  = new FixtureModule( 'needs-dep', array( self::FAKE_DEP ) );
		$manager = $this->make_manager( array( $module ) );

		$this->assertFalse( $manager->activate( 'needs-dep' ) );
		$this->assertFalse( $manager->is_active( 'needs-dep' ) );
		$this->assertSame( 0, $module->activate_calls );
		$this->assertFalse( get_option( Manager::ACTIVE_OPTION ) );
	}

	public function test_get_missing_requirements_lists_only_inactive_plugins() {
		$module  = new FixtureModule( 'needs-dep', array( self::FAKE_DEP ) );
		$manager = $this->make_manager( array( $module ) );

		$this->assertSame( array( self::FAKE_DEP ), $manager->get_missing_requirements( 'needs-dep' ) );

		$this->activate_fake_dependency();
		$this->assertSame( array(), $manager->get_missing_requirements( 'needs-dep' ) );

		// Unknown slugs report no missing requirements rather than erroring.
		$this->assertSame( array(), $manager->get_missing_requirements( 'ghost' ) );
	}

	public function test_activate_succeeds_once_requirement_is_met() {
		$module  = new FixtureModule( 'needs-dep', array( self::FAKE_DEP ) );
		$manager = $this->make_manager( array( $module ) );

		$this->activate_fake_dependency();

		$this->assertTrue( $manager->activate( 'needs-dep' ) );
		$this->assertTrue( $manager->is_active( 'needs-dep' ) );
		$this->assertSame( 1, $module->activate_calls );
	}

	public function test_boot_active_skips_module_whose_requirement_disappeared() {
		$gated   = new FixtureModule( 'needs-dep', array( self::FAKE_DEP ) );
		$plain   = new FixtureModule( 'plain' );
		$manager = $this->make_manager( array( $gated, $plain ) );

		$this->activate_fake_dependency();
		$manager->activate( 'needs-dep' );
		$manager->activate( 'plain' );

		// Simulate the dependency plugin being deactivated afterwards.
		update_option( 'active_plugins', array() );

		$manager->boot_active();

		$this->assertSame( 0, $gated->boot_calls, 'Module with a missing requirement must not boot.' );
		$this->assertSame( 1, $plain->boot_calls );
		$this->assertTrue( $manager->is_active( 'needs-dep' ), 'Skipped module stays listed as active.' );
	}

	/*
	|-----------------------------------------------------------------------
	| Activate / deactivate lifecycle & idempotency
	|-----------------------------------------------------------------------
	*/

	public function test_activate_unknown_slug_returns_false_and_persists_nothing() {
		$manager = $this->make_manager( array() );

		$this->assertFalse( $manager->activate( 'ghost' ) );
		$this->assertFalse( get_option( Manager::ACTIVE_OPTION ) );
	}

	public function test_activate_persists_slug_runs_hook_and_fires_action() {
		$module  = new FixtureModule( 'plain' );
		$manager = $this->make_manager( array( $module ) );

		$fired = array();
		add_action(
			'storesuite_module_activated',
			function ( $slug, $instance ) use ( &$fired ) {
				$fired[] = array( $slug, $instance );
			},
			10,
			2
		);

		$this->assertTrue( $manager->activate( 'plain' ) );
		$this->assertSame( array( 'plain' ), get_option( Manager::ACTIVE_OPTION ) );
		$this->assertSame( 1, $module->activate_calls );
		$this->assertCount( 1, $fired );
		$this->assertSame( 'plain', $fired[0][0] );
		$this->assertSame( $module, $fired[0][1] );
	}

	public function test_second_activate_is_idempotent() {
		$module  = new FixtureModule( 'plain' );
		$manager = $this->make_manager( array( $module ) );

		$this->assertTrue( $manager->activate( 'plain' ) );
		$this->assertTrue( $manager->activate( 'plain' ), 'Re-activating an active module still reports success.' );

		$this->assertSame( 1, $module->activate_calls, 'activate() hook must not re-run.' );
		$this->assertSame( array( 'plain' ), get_option( Manager::ACTIVE_OPTION ), 'Slug must not be duplicated.' );
	}

	public function test_activate_flags_rewrite_flush() {
		$manager = $this->make_manager( array( new FixtureModule( 'plain' ) ) );

		$manager->activate( 'plain' );

		$this->assertEquals( 1, get_option( 'storesuite_flush_rewrite_rules' ) );
	}

	public function test_deactivate_unknown_slug_returns_false() {
		$manager = $this->make_manager( array() );

		$this->assertFalse( $manager->deactivate( 'ghost' ) );
	}

	public function test_deactivate_removes_slug_runs_hook_and_fires_action() {
		$module  = new FixtureModule( 'plain' );
		$manager = $this->make_manager( array( $module ) );
		$manager->activate( 'plain' );

		$fired = array();
		add_action(
			'storesuite_module_deactivated',
			function ( $slug, $instance ) use ( &$fired ) {
				$fired[] = array( $slug, $instance );
			},
			10,
			2
		);

		$this->assertTrue( $manager->deactivate( 'plain' ) );
		$this->assertFalse( $manager->is_active( 'plain' ) );
		$this->assertSame( 1, $module->deactivate_calls );
		$this->assertCount( 1, $fired );
		$this->assertSame( 'plain', $fired[0][0] );
		$this->assertSame( $module, $fired[0][1] );
	}

	public function test_deactivate_flags_rewrite_flush() {
		$manager = $this->make_manager( array( new FixtureModule( 'plain' ) ) );
		$manager->activate( 'plain' );

		// Clear the flag set by activate() so the assertion below can only be
		// satisfied by deactivate() itself.
		delete_option( 'storesuite_flush_rewrite_rules' );

		$manager->deactivate( 'plain' );

		$this->assertEquals( 1, get_option( 'storesuite_flush_rewrite_rules' ) );
	}

	public function test_deactivate_of_inactive_module_is_a_noop() {
		$module  = new FixtureModule( 'plain' );
		$manager = $this->make_manager( array( $module ) );

		$this->assertTrue( $manager->deactivate( 'plain' ) );
		$this->assertSame( 0, $module->deactivate_calls, 'deactivate() hook must not run for an inactive module.' );
	}

	/*
	|-----------------------------------------------------------------------
	| Boot ordering & lifecycle actions
	|-----------------------------------------------------------------------
	*/

	public function test_boot_active_boots_only_active_modules_and_fires_actions() {
		$active   = new FixtureModule( 'active-mod' );
		$inactive = new FixtureModule( 'inactive-mod' );
		$manager  = $this->make_manager( array( $active, $inactive ) );
		$manager->activate( 'active-mod' );

		$per_module = 0;
		$all_loaded = 0;
		add_action(
			'storesuite_module_active-mod_loaded',
			function () use ( &$per_module ) {
				++$per_module;
			}
		);
		add_action(
			'storesuite_modules_loaded',
			function () use ( &$all_loaded ) {
				++$all_loaded;
			}
		);

		$manager->boot_active();

		$this->assertSame( 1, $active->boot_calls );
		$this->assertSame( 0, $inactive->boot_calls );
		$this->assertSame( 1, $per_module );
		$this->assertSame( 1, $all_loaded );
	}

	/*
	|-----------------------------------------------------------------------
	| Registry / active-option hygiene
	|-----------------------------------------------------------------------
	*/

	public function test_active_slugs_exclude_modules_missing_from_disk_but_keep_the_option_intact() {
		update_option( Manager::ACTIVE_OPTION, array( 'plain', 'ghost' ) );
		$manager = $this->make_manager( array( new FixtureModule( 'plain' ) ) );

		$this->assertSame( array( 'plain' ), $manager->get_active_slugs() );
		$this->assertSame(
			array( 'plain', 'ghost' ),
			get_option( Manager::ACTIVE_OPTION ),
			'The stored option keeps unknown slugs so a module that reappears comes back active.'
		);
	}

	public function test_register_modules_filter_entries_are_validated() {
		add_filter(
			'storesuite_modules_dir',
			function () {
				return sys_get_temp_dir() . '/storesuite-tests-no-such-dir';
			}
		);
		add_filter(
			'storesuite_register_modules',
			function () {
				return array(
					'bad-string' => 'not-a-module',
					'bad-null'   => null,
					'empty-slug' => new FixtureModule( '' ),
					'good'       => new FixtureModule( 'good' ),
				);
			}
		);

		$manager = new Manager();

		$this->assertSame( array( 'good' ), array_keys( $manager->get_all() ) );
	}

	public function test_first_registration_of_a_slug_wins_over_later_duplicates() {
		$first  = new FixtureModule( 'dup' );
		$second = new FixtureModule( 'dup' );

		add_filter(
			'storesuite_modules_dir',
			function () {
				return sys_get_temp_dir() . '/storesuite-tests-no-such-dir';
			}
		);
		add_filter(
			'storesuite_register_modules',
			function () use ( $first, $second ) {
				// Array keys are ignored by registration — the Manager keys the
				// registry by each instance's own get_slug().
				return array( $first, $second );
			}
		);

		$manager = new Manager();
		$modules = $manager->get_all();

		$this->assertCount( 1, $modules );
		$this->assertSame( $first, $modules['dup'], 'A later registration must not clobber an already-registered slug.' );
	}

	public function test_register_modules_filter_merges_with_disk_discovered_modules() {
		$extra = new FixtureModule( 'filter-extra' );

		add_filter(
			'storesuite_modules_dir',
			function () {
				return dirname( __DIR__ ) . '/fixtures/modules-merge';
			}
		);
		add_filter(
			'storesuite_register_modules',
			function ( $registered ) use ( $extra ) {
				$registered[ $extra->get_slug() ] = $extra;
				return $registered;
			}
		);

		$manager = new Manager();
		$modules = $manager->get_all();

		$this->assertArrayHasKey( 'beta', $modules, 'Disk-discovered module must survive the filter.' );
		$this->assertArrayHasKey( 'filter-extra', $modules, 'Filter-added module must be registered alongside disk modules.' );
		$this->assertSame( $extra, $modules['filter-extra'] );
	}

	public function test_uninstall_all_runs_on_every_discovered_module_even_inactive_ones() {
		$active   = new FixtureModule( 'active-mod' );
		$inactive = new FixtureModule( 'inactive-mod' );
		$manager  = $this->make_manager( array( $active, $inactive ) );
		$manager->activate( 'active-mod' );

		$manager->uninstall_all();

		$this->assertSame( 1, $active->uninstall_calls );
		$this->assertSame( 1, $inactive->uninstall_calls );
	}

	public function test_discovery_finds_module_bootstraps_on_disk() {
		add_filter(
			'storesuite_modules_dir',
			function () {
				return dirname( __DIR__ ) . '/fixtures/modules';
			}
		);

		$manager = new Manager();
		$modules = $manager->get_all();

		$this->assertArrayHasKey( 'alpha', $modules );
		$this->assertInstanceOf( FixtureModule::class, $modules['alpha'] );
	}
}
