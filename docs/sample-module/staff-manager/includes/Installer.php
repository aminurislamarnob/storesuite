<?php
/**
 * Staff Manager — schema installer.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Modules\StaffManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owns the `{$wpdb->prefix}storesuite_staff` table.
 *
 * Schema version is stored in its own option so the installer can re-run
 * `dbDelta()` whenever the bundled schema bumps past the installed version.
 * dbDelta is additive — it adds new columns and indexes but never drops them,
 * which matches WordPress conventions and means deactivating the module is
 * non-destructive (data survives until the plugin is uninstalled).
 */
class Installer {

	const SCHEMA_VERSION        = '1.0.0';
	const SCHEMA_VERSION_OPTION = 'storesuite_staff_manager_db_version';

	/**
	 * Table name (with the `$wpdb` prefix already applied).
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'storesuite_staff';
	}

	/**
	 * Create or upgrade the schema. Safe to call repeatedly — dbDelta is
	 * idempotent.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		// dbDelta is strict about formatting: two spaces after PRIMARY KEY,
		// no trailing commas, lowercase types. Don't reformat.
		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) UNSIGNED NOT NULL,
			role VARCHAR(50) NOT NULL DEFAULT 'shop_manager',
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			capabilities LONGTEXT NULL,
			notes TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY user_id (user_id),
			KEY status (status)
		) {$charset_collate};";

		dbDelta( $sql );

		update_option( self::SCHEMA_VERSION_OPTION, self::SCHEMA_VERSION );
	}

	/**
	 * Drop the staff table and forget the schema version. Called only from the
	 * module's `uninstall()` — never on deactivation — so staff data survives a
	 * module toggle and is removed only when the plugin is deleted.
	 *
	 * @return void
	 */
	public static function uninstall() {
		global $wpdb;

		$table_name = self::table_name();

		// Table identifier can't be parameterised; it's built from the trusted
		// $wpdb prefix and a hard-coded suffix, so interpolation is safe here.
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching

		delete_option( self::SCHEMA_VERSION_OPTION );
	}

	/**
	 * Run dbDelta only when the stored schema version is behind. Called on
	 * every boot so a plugin update can ship a schema change without forcing
	 * the user to toggle the module off and on again.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$installed = get_option( self::SCHEMA_VERSION_OPTION );

		if ( version_compare( (string) $installed, self::SCHEMA_VERSION, '<' ) ) {
			self::install();
		}
	}
}
