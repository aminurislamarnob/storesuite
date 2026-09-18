<?php
/**
 * Core Installer + Upgrader + Helper tests: dashboard page creation and
 * reuse, page-state labelling, page-ID lookup, and version migrations.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Tests;

use PluginizeLab\StoreSuite\Helper;
use PluginizeLab\StoreSuite\Installer;
use PluginizeLab\StoreSuite\Upgrader;
use WP_UnitTestCase;

/**
 * Tests for PluginizeLab\StoreSuite\Installer, Upgrader, and Helper.
 */
class InstallerUpgraderTest extends WP_UnitTestCase {

	/**
	 * Clean the options the installer/upgrader write.
	 */
	public function set_up() {
		parent::set_up();

		delete_option( 'storesuite_myshopdashboard_page_id' );
		delete_option( 'storesuite_settings' );
		delete_option( Upgrader::DB_VERSION_OPTION );
		delete_option( 'storesuite_flush_rewrite_rules' );
	}

	/*
	|-----------------------------------------------------------------------
	| Installer::create_plugin_page()
	|-----------------------------------------------------------------------
	*/

	public function test_create_plugin_page_creates_the_dashboard_page_with_the_shortcode() {
		Installer::create_plugin_page();

		$page_id = (int) get_option( 'storesuite_myshopdashboard_page_id' );
		$this->assertGreaterThan( 0, $page_id );

		$page = get_post( $page_id );
		$this->assertSame( 'page', $page->post_type );
		$this->assertSame( 'publish', $page->post_status );
		$this->assertStringContainsString( '[storesuite_dashboard]', $page->post_content );

		// The page ID is mirrored into the settings option (read by
		// storesuite_get_navigation_url / storesuite_is_dashboard_page).
		$this->assertSame( $page_id, (int) storesuite_get_option_by_key( 'storesuite_dashboard_page_id' ) );
	}

	public function test_create_plugin_page_reuses_a_valid_existing_page() {
		Installer::create_plugin_page();
		$first_id = (int) get_option( 'storesuite_myshopdashboard_page_id' );

		Installer::create_plugin_page();

		$this->assertSame( $first_id, (int) get_option( 'storesuite_myshopdashboard_page_id' ), 'Reinstalling must not create a duplicate page.' );
	}

	public function test_create_plugin_page_adopts_an_unlinked_page_holding_the_shortcode() {
		// The option is gone but a page with the shortcode exists (e.g. after
		// a botched migration): it must be adopted, not duplicated.
		$existing = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'My Custom Dashboard',
				'post_content' => '<!-- wp:shortcode -->[storesuite_dashboard]<!-- /wp:shortcode -->',
			)
		);

		Installer::create_plugin_page();

		$this->assertSame( $existing, (int) get_option( 'storesuite_myshopdashboard_page_id' ) );
	}

	public function test_create_plugin_page_restores_a_trashed_dashboard_page() {
		Installer::create_plugin_page();
		$page_id = (int) get_option( 'storesuite_myshopdashboard_page_id' );

		wp_trash_post( $page_id );

		Installer::create_plugin_page();

		$this->assertSame( $page_id, (int) get_option( 'storesuite_myshopdashboard_page_id' ) );
		$this->assertSame( 'publish', get_post_status( $page_id ), 'The trashed page is restored instead of recreated.' );
	}

	public function test_display_post_states_labels_the_dashboard_page() {
		Installer::create_plugin_page();
		$page_id = (int) get_option( 'storesuite_myshopdashboard_page_id' );

		$installer = new Installer();

		$states = $installer->add_display_post_states( array(), get_post( $page_id ) );
		$this->assertArrayHasKey( 'storesuite_page_for_dashboard', $states );

		$other  = self::factory()->post->create_and_get( array( 'post_type' => 'page' ) );
		$states = $installer->add_display_post_states( array(), $other );
		$this->assertArrayNotHasKey( 'storesuite_page_for_dashboard', $states );
	}

	/*
	|-----------------------------------------------------------------------
	| Helper::storesuite_get_page_id()
	|-----------------------------------------------------------------------
	*/

	public function test_helper_page_id_lookup_is_filterable_and_defaults_to_minus_one() {
		$this->assertSame( -1, Helper::storesuite_get_page_id( 'myshopdashboard' ) );

		update_option( 'storesuite_myshopdashboard_page_id', '42' );
		$this->assertSame( 42, Helper::storesuite_get_page_id( 'myshopdashboard' ) );

		add_filter(
			'storesuite_get_myshopdashboard_page_id',
			function () {
				return 7;
			}
		);
		$this->assertSame( 7, Helper::storesuite_get_page_id( 'myshopdashboard' ) );
	}

	/*
	|-----------------------------------------------------------------------
	| Upgrader::maybe_upgrade()
	|-----------------------------------------------------------------------
	*/

	public function test_upgrade_from_scratch_flags_a_rewrite_flush_and_stamps_the_version() {
		( new Upgrader() )->maybe_upgrade();

		$this->assertSame( STORESUITE_PLUGIN_VERSION, get_option( Upgrader::DB_VERSION_OPTION ) );
		$this->assertNotFalse( get_option( 'storesuite_flush_rewrite_rules' ), 'The 1.1.0 migration schedules a rewrite flush.' );
	}

	public function test_upgrade_is_skipped_when_already_current() {
		update_option( Upgrader::DB_VERSION_OPTION, STORESUITE_PLUGIN_VERSION );

		( new Upgrader() )->maybe_upgrade();

		$this->assertFalse( get_option( 'storesuite_flush_rewrite_rules' ), 'No migration may run when the DB version is current.' );
	}

	public function test_upgrade_from_a_mid_version_runs_only_the_outstanding_migrations() {
		// 1.1.2 sits between the two known migrations: only 1.1.4 should run.
		update_option( Upgrader::DB_VERSION_OPTION, '1.1.2' );

		( new Upgrader() )->maybe_upgrade();

		$this->assertNotFalse( get_option( 'storesuite_flush_rewrite_rules' ) );
		$this->assertSame( STORESUITE_PLUGIN_VERSION, get_option( Upgrader::DB_VERSION_OPTION ) );
	}
}
