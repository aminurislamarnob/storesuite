<?php
/**
 * Customer CRM — schema installer.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Modules\Customers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owns the CRM tables:
 *   {prefix}storesuite_customer_notes    — internal notes per customer.
 *   {prefix}storesuite_customer_tags     — tag definitions.
 *   {prefix}storesuite_customer_tag_rel  — customer <-> tag relations.
 *
 * Rows key on the WooCommerce Analytics customer id (wc_customer_lookup.customer_id),
 * which exists for BOTH registered users and guests (guest orders are grouped by
 * billing email natively), so notes and tags work for guest customers too.
 */
class Installer {

	const SCHEMA_VERSION        = '1.0.0';
	const SCHEMA_VERSION_OPTION = 'storesuite_customers_db_version';

	/**
	 * @return string
	 */
	public static function notes_table() {
		global $wpdb;
		return $wpdb->prefix . 'storesuite_customer_notes';
	}

	/**
	 * @return string
	 */
	public static function tags_table() {
		global $wpdb;
		return $wpdb->prefix . 'storesuite_customer_tags';
	}

	/**
	 * @return string
	 */
	public static function tag_rel_table() {
		global $wpdb;
		return $wpdb->prefix . 'storesuite_customer_tag_rel';
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
		$notes           = self::notes_table();
		$tags            = self::tags_table();
		$rel             = self::tag_rel_table();

		$notes_sql = "CREATE TABLE {$notes} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			customer_id BIGINT(20) UNSIGNED NOT NULL,
			author_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			note TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY customer_id (customer_id)
		) {$charset_collate};";

		$tags_sql = "CREATE TABLE {$tags} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(100) NOT NULL DEFAULT '',
			slug VARCHAR(100) NOT NULL DEFAULT '',
			color VARCHAR(7) NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug)
		) {$charset_collate};";

		$rel_sql = "CREATE TABLE {$rel} (
			customer_id BIGINT(20) UNSIGNED NOT NULL,
			tag_id BIGINT(20) UNSIGNED NOT NULL,
			PRIMARY KEY  (customer_id,tag_id),
			KEY tag_id (tag_id)
		) {$charset_collate};";

		dbDelta( $notes_sql );
		dbDelta( $tags_sql );
		dbDelta( $rel_sql );

		update_option( self::SCHEMA_VERSION_OPTION, self::SCHEMA_VERSION );
	}

	/**
	 * Run dbDelta only when the stored schema version is behind.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$installed = get_option( self::SCHEMA_VERSION_OPTION );
		if ( version_compare( (string) $installed, self::SCHEMA_VERSION, '<' ) ) {
			self::install();
		}
	}

	/**
	 * Drop all CRM tables and forget the schema version. Uninstall only.
	 *
	 * @return void
	 */
	public static function uninstall() {
		global $wpdb;

		foreach ( array( self::notes_table(), self::tags_table(), self::tag_rel_table() ) as $table ) {
			// Identifiers come from the trusted $wpdb prefix + hard-coded suffixes.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}

		delete_option( self::SCHEMA_VERSION_OPTION );
	}
}
