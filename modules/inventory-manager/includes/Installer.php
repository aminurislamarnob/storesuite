<?php
/**
 * Inventory Manager — schema installer.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Modules\InventoryManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owns the `{prefix}storesuite_stock_log` table — an append-only movement log
 * for every stock change (dashboard edits, bulk updates, order reductions).
 */
class Installer {

	const SCHEMA_VERSION        = '1.0.0';
	const SCHEMA_VERSION_OPTION = 'storesuite_inventory_manager_db_version';

	/**
	 * @return string
	 */
	public static function stock_log_table() {
		global $wpdb;
		return $wpdb->prefix . 'storesuite_stock_log';
	}

	/**
	 * Create or upgrade the schema. Idempotent.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$table           = self::stock_log_table();

		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT(20) UNSIGNED NOT NULL,
			user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			qty_before INT NULL,
			qty_after INT NULL,
			change_type VARCHAR(32) NOT NULL DEFAULT 'manual',
			reference VARCHAR(128) NULL,
			note VARCHAR(255) NULL,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY product_id (product_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );

		update_option( self::SCHEMA_VERSION_OPTION, self::SCHEMA_VERSION );
	}

	/**
	 * @return void
	 */
	public static function maybe_upgrade() {
		$installed = get_option( self::SCHEMA_VERSION_OPTION );
		if ( version_compare( (string) $installed, self::SCHEMA_VERSION, '<' ) ) {
			self::install();
		}
	}

	/**
	 * Drop the table and forget the schema version. Uninstall only.
	 *
	 * @return void
	 */
	public static function uninstall() {
		global $wpdb;
		$table = self::stock_log_table();
		// Identifier from the trusted $wpdb prefix + hard-coded suffix.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		delete_option( self::SCHEMA_VERSION_OPTION );
	}
}
