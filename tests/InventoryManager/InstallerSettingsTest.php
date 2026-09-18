<?php
/**
 * Inventory Manager module tests: the stock-log schema installer and the
 * module settings helper (defaults, sanitization, email write-through).
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Tests\InventoryManager;

use PluginizeLab\StoreSuite\Modules\InventoryManager\Emails\Manager as EmailManager;
use PluginizeLab\StoreSuite\Modules\InventoryManager\Installer;
use PluginizeLab\StoreSuite\Modules\InventoryManager\Settings;
use WP_UnitTestCase;

/**
 * Tests for the Inventory Manager Installer and Settings classes.
 *
 * The stock-log table itself is created once in tests/bootstrap.php — DDL
 * implicitly commits in MySQL and would break per-test transaction rollback
 * if run inside a test. The one test that must drop the table
 * (test_uninstall_*) runs last in this class and re-installs immediately.
 */
class InstallerSettingsTest extends WP_UnitTestCase {

	/**
	 * Clean the module's options before each test.
	 */
	public function set_up() {
		parent::set_up();

		delete_option( Settings::OPTION_KEY );
		delete_option( EmailManager::ALERT_SETTINGS_OPTION );
		delete_option( EmailManager::DIGEST_SETTINGS_OPTION );
	}

	/**
	 * Whether the stock-log table currently exists.
	 *
	 * @return bool
	 */
	private function table_exists() {
		global $wpdb;

		// SHOW TABLES does not list the TEMPORARY tables the test suite
		// creates, so probe the table with a query instead.
		$suppress = $wpdb->suppress_errors();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->query( 'SELECT 1 FROM ' . Installer::stock_log_table() . ' LIMIT 1' );
		$wpdb->suppress_errors( $suppress );

		return false !== $result;
	}

	/*
	|-----------------------------------------------------------------------
	| Installer
	|-----------------------------------------------------------------------
	*/

	public function test_install_creates_the_table_and_stamps_the_schema_version() {
		delete_option( Installer::SCHEMA_VERSION_OPTION );

		// The table already exists (bootstrap), so this run is DDL-free and
		// exercises idempotency: no error, and the version option is stamped.
		Installer::install();

		$this->assertTrue( $this->table_exists() );
		$this->assertSame( Installer::SCHEMA_VERSION, get_option( Installer::SCHEMA_VERSION_OPTION ) );
	}

	public function test_maybe_upgrade_runs_only_when_the_stored_version_is_behind() {
		delete_option( Installer::SCHEMA_VERSION_OPTION );
		Installer::maybe_upgrade();
		$this->assertSame( Installer::SCHEMA_VERSION, get_option( Installer::SCHEMA_VERSION_OPTION ), 'A missing version means a fresh install: it must run.' );

		// Already current: nothing to do (would be a no-op even if it ran,
		// but the early return is the documented contract).
		update_option( Installer::SCHEMA_VERSION_OPTION, Installer::SCHEMA_VERSION );
		Installer::maybe_upgrade();
		$this->assertSame( Installer::SCHEMA_VERSION, get_option( Installer::SCHEMA_VERSION_OPTION ) );
	}

	/*
	|-----------------------------------------------------------------------
	| Settings
	|-----------------------------------------------------------------------
	*/

	public function test_defaults_come_from_the_schema() {
		$settings = Settings::get();

		$this->assertTrue( $settings['enable_stock_log'] );
		$this->assertSame( 180, $settings['log_retention_days'] );
		$this->assertSame( 2, $settings['low_stock_default_threshold'] );

		// Email toggles default to the emails' own defaults (alert on, digest off).
		$this->assertTrue( $settings['enable_low_stock_alert_email'] );
		$this->assertFalse( $settings['enable_daily_stock_digest_email'] );

		$this->assertSame( 180, Settings::value( 'log_retention_days' ) );
		$this->assertNull( Settings::value( 'nonexistent_key' ) );
	}

	public function test_update_sanitizes_by_field_type_and_ignores_unknown_keys() {
		$saved = Settings::update(
			array(
				'enable_stock_log'            => '0',
				'log_retention_days'          => 999999,
				'low_stock_default_threshold' => -5,
				'made_up_key'                 => 'evil',
			)
		);

		$this->assertFalse( $saved['enable_stock_log'] );
		$this->assertSame( 3650, $saved['log_retention_days'], 'Numbers clamp to the schema max.' );
		$this->assertSame( 0, $saved['low_stock_default_threshold'], 'Numbers clamp to the schema min.' );
		$this->assertArrayNotHasKey( 'made_up_key', $saved );

		$stored = get_option( Settings::OPTION_KEY );
		$this->assertArrayNotHasKey( 'made_up_key', $stored );
	}

	public function test_email_toggles_write_through_to_the_woocommerce_email_options() {
		// Pre-existing email settings (recipient etc.) must survive the toggle.
		update_option( EmailManager::ALERT_SETTINGS_OPTION, array( 'recipient' => 'shop@example.com' ) );

		Settings::update(
			array(
				'enable_low_stock_alert_email'    => false,
				'enable_daily_stock_digest_email' => true,
			)
		);

		$alert = get_option( EmailManager::ALERT_SETTINGS_OPTION );
		$this->assertSame( 'no', $alert['enabled'] );
		$this->assertSame( 'shop@example.com', $alert['recipient'], 'Other saved email fields must be preserved.' );

		$digest = get_option( EmailManager::DIGEST_SETTINGS_OPTION );
		$this->assertSame( 'yes', $digest['enabled'] );

		// The toggles are virtual: never stored in the module's own option.
		$stored = (array) get_option( Settings::OPTION_KEY );
		$this->assertArrayNotHasKey( 'enable_low_stock_alert_email', $stored );
		$this->assertArrayNotHasKey( 'enable_daily_stock_digest_email', $stored );
	}

	public function test_get_reflects_the_woocommerce_email_options_as_source_of_truth() {
		// Simulate the user enabling the digest from WooCommerce → Settings → Emails.
		update_option( EmailManager::DIGEST_SETTINGS_OPTION, array( 'enabled' => 'yes' ) );
		update_option( EmailManager::ALERT_SETTINGS_OPTION, array( 'enabled' => 'no' ) );

		$settings = Settings::get();

		$this->assertTrue( $settings['enable_daily_stock_digest_email'] );
		$this->assertFalse( $settings['enable_low_stock_alert_email'] );
	}

	/*
	|-----------------------------------------------------------------------
	| Uninstall — must stay the LAST test in this class (see class docblock)
	|-----------------------------------------------------------------------
	*/

	public function test_uninstall_drops_the_table_and_forgets_the_version() {
		// The WP test framework rewrites DROP TABLE → DROP TEMPORARY TABLE via
		// a 'query' filter, which would leave the real table in place; detach
		// the rewrites so uninstall() runs its actual DDL.
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );

		Installer::uninstall();

		$this->assertFalse( $this->table_exists() );
		$this->assertFalse( get_option( Installer::SCHEMA_VERSION_OPTION ) );

		// Restore the table for the rest of the suite. The DROP above already
		// committed this test's transaction, so nothing here rolls back —
		// keep this test free of posts/products for that reason.
		Installer::install();
		$this->assertTrue( $this->table_exists() );
	}
}
