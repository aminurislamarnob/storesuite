<?php

namespace PluginizeLab\StoreSuite\Notification;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates the notifications table.
 */
class NotificationInstaller {

	/**
	 * Get the notifications table name.
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'storesuite_notifications';
	}

	/**
	 * Create the notifications table via dbDelta (idempotent).
	 *
	 * Called from plugin activation (fresh installs) and from the Upgrader
	 * migration (existing installs updating to the version that introduced it).
	 *
	 * @return void
	 */
	public static function create_table() {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			type varchar(32) NOT NULL,
			object_id bigint(20) unsigned NOT NULL,
			seen_at datetime NULL DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_seen (user_id,seen_at),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
