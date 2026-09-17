<?php

namespace PluginizeLab\StoreSuite\EditHistory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates the edit history tables.
 *
 * Two tables: one row per user action (a "batch") and one row per changed
 * field on one object inside that action. Mirrors NotificationInstaller.
 */
class EditHistoryInstaller {

	const DB_VERSION = '1';

	const DB_VERSION_OPTION = 'storesuite_edit_history_db_version';

	/**
	 * Batches table name.
	 *
	 * @return string
	 */
	public static function get_batches_table() {
		global $wpdb;
		return $wpdb->prefix . 'storesuite_edit_batches';
	}

	/**
	 * Items table name.
	 *
	 * @return string
	 */
	public static function get_items_table() {
		global $wpdb;
		return $wpdb->prefix . 'storesuite_edit_items';
	}

	/**
	 * Create or update both tables (idempotent via dbDelta).
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;

		$batches         = self::get_batches_table();
		$items           = self::get_items_table();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$batches} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			source varchar(32) NOT NULL,
			object_type varchar(32) NOT NULL,
			summary varchar(255) NOT NULL DEFAULT '',
			item_count int(10) unsigned NOT NULL DEFAULT 0,
			undo_of bigint(20) unsigned NULL DEFAULT NULL,
			undone_at datetime NULL DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY user_id (user_id)
		) {$charset_collate};
		CREATE TABLE {$items} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			batch_id bigint(20) unsigned NOT NULL,
			object_type varchar(32) NOT NULL,
			object_id bigint(20) unsigned NOT NULL,
			field varchar(64) NOT NULL,
			old_value longtext NULL,
			new_value longtext NULL,
			PRIMARY KEY  (id),
			KEY batch_id (batch_id),
			KEY object (object_type,object_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create the tables and schedule the cleanup cron once per DB version.
	 *
	 * Safe to call on every request: it is a single autoloaded option read
	 * until the install has happened, so sites that update without
	 * re-activating still get the tables.
	 *
	 * @return void
	 */
	public static function maybe_install() {
		if ( self::DB_VERSION === get_option( self::DB_VERSION_OPTION ) ) {
			return;
		}

		self::install();
	}

	/**
	 * Create the tables, schedule the cleanup cron and record the DB version.
	 *
	 * @return void
	 */
	public static function install() {
		self::create_tables();

		if ( ! wp_next_scheduled( EditHistoryHooks::CLEANUP_HOOK ) ) {
			wp_schedule_event( time(), 'daily', EditHistoryHooks::CLEANUP_HOOK );
		}

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}
}
