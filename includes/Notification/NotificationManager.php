<?php

namespace PluginizeLab\StoreSuite\Notification;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PluginizeLab\StoreSuite\Cache;

/**
 * Notification data layer.
 *
 * Rows are fanned out on write: one row per recipient (every user with
 * `manage_woocommerce`), so seen-state is a plain column and all reads are
 * simple indexed queries. Rows store only `type` + `object_id`; display text
 * and URLs are built at render time so they stay translatable and never go
 * stale.
 */
class NotificationManager {

	/**
	 * Option holding the id of the most recently inserted notification.
	 *
	 * Autoload is intentionally off: the value changes on every insert, and
	 * poll requests read it through the object cache with a single indexed
	 * option query as the uncached-host fallback.
	 *
	 * @var string
	 */
	const CURSOR_OPTION = 'storesuite_notifications_last_id';

	/**
	 * Object-cache key (Cache class adds the `storesuite_` prefix).
	 *
	 * @var string
	 */
	const CURSOR_CACHE_KEY = 'notifications_last_id';

	/**
	 * Transient key caching recipient user IDs (Cache class adds the prefix).
	 *
	 * @var string
	 */
	const RECIPIENTS_CACHE_KEY = 'notification_recipient_ids';

	/**
	 * Get the notifications table name.
	 *
	 * @return string
	 */
	protected function get_table() {
		return NotificationInstaller::get_table_name();
	}

	/**
	 * Insert a notification for every eligible recipient.
	 *
	 * Skips entirely when the event type is disabled in settings, and when a
	 * row for the same type + object already exists (guards against an event
	 * hook firing twice for the same object).
	 *
	 * @param string $type      Event type: new_order, new_customer, or product_review.
	 * @param int    $object_id Related object ID (order, user, or comment ID).
	 * @return int Number of rows inserted.
	 */
	public function insert( $type, $object_id ) {
		global $wpdb;

		$object_id = absint( $object_id );

		if ( ! $object_id || ! storesuite_is_notification_enabled( $type ) ) {
			return 0;
		}

		$table = $this->get_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, name is plugin-controlled.
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE type = %s AND object_id = %d LIMIT 1", $type, $object_id ) );
		if ( $exists ) {
			return 0;
		}

		$recipient_ids = $this->get_recipient_ids();
		if ( empty( $recipient_ids ) ) {
			return 0;
		}

		$created_at   = current_time( 'mysql', true );
		$values       = array();
		$placeholders = array();

		foreach ( $recipient_ids as $user_id ) {
			$placeholders[] = '(%d, %s, %d, %s)';
			array_push( $values, $user_id, $type, $object_id, $created_at );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Table name is plugin-controlled; placeholders built above, values passed through prepare().
		$inserted = $wpdb->query( $wpdb->prepare( "INSERT INTO {$table} (user_id, type, object_id, created_at) VALUES " . implode( ', ', $placeholders ), $values ) );

		if ( $inserted ) {
			$this->bump_cursor();
		}

		return (int) $inserted;
	}

	/**
	 * Get IDs of users who receive notifications (anyone with manage_woocommerce).
	 *
	 * Cached in a short-lived transient: inserts only fire on actual store
	 * events, so the recompute cost is negligible on hosts with or without a
	 * persistent object cache.
	 *
	 * @return int[]
	 */
	public function get_recipient_ids() {
		$ids = Cache::get( self::RECIPIENTS_CACHE_KEY );

		if ( false === $ids ) {
			$ids = get_users(
				array(
					'capability' => 'manage_woocommerce',
					'fields'     => 'ID',
				)
			);
			$ids = array_map( 'absint', $ids );
			Cache::set( self::RECIPIENTS_CACHE_KEY, $ids, 5 * MINUTE_IN_SECONDS );
		}

		return $ids;
	}

	/**
	 * Get the id of the most recently inserted notification.
	 *
	 * Reads through the object cache; on a miss (or a host without a
	 * persistent object cache) this falls back to a single indexed
	 * wp_options query and re-primes the cache.
	 *
	 * @return int
	 */
	public function get_cursor() {
		$cursor = Cache::get( self::CURSOR_CACHE_KEY, false );

		if ( false === $cursor ) {
			$cursor = (int) get_option( self::CURSOR_OPTION, 0 );
			Cache::set( self::CURSOR_CACHE_KEY, $cursor, 0, false );
		}

		return (int) $cursor;
	}

	/**
	 * Refresh the cursor from the table after an insert.
	 *
	 * @return void
	 */
	protected function bump_cursor() {
		global $wpdb;

		$table = $this->get_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, name is plugin-controlled.
		$last_id = (int) $wpdb->get_var( "SELECT MAX(id) FROM {$table}" );

		update_option( self::CURSOR_OPTION, $last_id, false );
		Cache::set( self::CURSOR_CACHE_KEY, $last_id, 0, false );
	}

	/**
	 * Get the most recent notifications for a user.
	 *
	 * @param int $user_id User ID.
	 * @param int $limit   Max rows.
	 * @return array[] Formatted notifications.
	 */
	public function get_recent( $user_id, $limit = 5 ) {
		global $wpdb;

		$table = $this->get_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, name is plugin-controlled.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY id DESC LIMIT %d", $user_id, $limit ) );

		return array_map( array( $this, 'format' ), (array) $rows );
	}

	/**
	 * Get a page of notifications for a user.
	 *
	 * @param int $user_id  User ID.
	 * @param int $page     Page number (1-based).
	 * @param int $per_page Rows per page.
	 * @return array[] Formatted notifications.
	 */
	public function get_for_user( $user_id, $page = 1, $per_page = 15 ) {
		global $wpdb;

		$table  = $this->get_table();
		$offset = ( max( 1, $page ) - 1 ) * $per_page;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, name is plugin-controlled.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY id DESC LIMIT %d OFFSET %d", $user_id, $per_page, $offset ) );

		return array_map( array( $this, 'format' ), (array) $rows );
	}

	/**
	 * Count all notifications for a user.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public function count_for_user( $user_id ) {
		global $wpdb;

		$table = $this->get_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, name is plugin-controlled.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d", $user_id ) );
	}

	/**
	 * Count unseen notifications for a user.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public function count_unseen( $user_id ) {
		global $wpdb;

		$table = $this->get_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, name is plugin-controlled.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND seen_at IS NULL", $user_id ) );
	}

	/**
	 * Mark specific notifications as seen for a user.
	 *
	 * @param int   $user_id User ID.
	 * @param int[] $ids     Notification IDs.
	 * @return int Rows updated.
	 */
	public function mark_seen( $user_id, array $ids ) {
		global $wpdb;

		$ids = array_filter( array_map( 'absint', $ids ) );
		if ( empty( $ids ) ) {
			return 0;
		}

		$table        = $this->get_table();
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		$values       = array_merge( array( current_time( 'mysql', true ), $user_id ), $ids );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Table name is plugin-controlled; placeholders built above, values passed through prepare().
		return (int) $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET seen_at = %s WHERE user_id = %d AND seen_at IS NULL AND id IN ( {$placeholders} )", $values ) );
	}

	/**
	 * Mark all notifications as seen for a user.
	 *
	 * @param int $user_id User ID.
	 * @return int Rows updated.
	 */
	public function mark_all_seen( $user_id ) {
		global $wpdb;

		$table = $this->get_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, name is plugin-controlled.
		return (int) $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET seen_at = %s WHERE user_id = %d AND seen_at IS NULL", current_time( 'mysql', true ), $user_id ) );
	}

	/**
	 * Delete all notifications for a user.
	 *
	 * @param int $user_id User ID.
	 * @return int Rows deleted.
	 */
	public function delete_all_for_user( $user_id ) {
		global $wpdb;

		$table = $this->get_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, name is plugin-controlled.
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE user_id = %d", $user_id ) );
	}

	/**
	 * Delete notifications older than the retention window.
	 *
	 * @param int|null $days Retention days; defaults to the filtered value.
	 * @return int Rows deleted.
	 */
	public function delete_older_than( $days = null ) {
		global $wpdb;

		if ( null === $days ) {
			/**
			 * Filters how many days notifications are kept before the daily
			 * cleanup cron removes them.
			 *
			 * @param int $days Retention window in days.
			 */
			$days = (int) apply_filters( 'storesuite_notification_retention_days', 90 );
		}

		$table  = $this->get_table();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( absint( $days ) * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, name is plugin-controlled.
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff ) );
	}

	/**
	 * Build the display representation of a notification row.
	 *
	 * Text and URLs come from the live object so they follow the current
	 * locale and never go stale; deleted objects degrade to a generic
	 * message with no link.
	 *
	 * @param object $row Notification row.
	 * @return array
	 */
	public function format( $row ) {
		$title   = '';
		$message = '';
		$url     = '';

		switch ( $row->type ) {
			case 'new_order':
				$title = __( 'New order', 'storesuite' );
				$order = wc_get_order( $row->object_id );
				if ( $order ) {
					$customer = $order->get_formatted_billing_full_name();
					$customer = $customer ? $customer : __( 'Guest', 'storesuite' );
					$total    = html_entity_decode( wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ), ENT_QUOTES, get_bloginfo( 'charset' ) );
					/* translators: 1: order number, 2: order total, 3: customer name */
					$message = sprintf( __( 'Order #%1$s (%2$s) placed by %3$s.', 'storesuite' ), $order->get_order_number(), $total, $customer );
					$url     = storesuite_get_navigation_url( 'order-details' ) . $row->object_id;
				} else {
					/* translators: %d: order ID */
					$message = sprintf( __( 'Order #%d is no longer available.', 'storesuite' ), $row->object_id );
				}
				break;

			case 'new_customer':
				$title = __( 'New customer', 'storesuite' );
				$user  = get_userdata( $row->object_id );
				if ( $user ) {
					/* translators: %s: customer display name */
					$message = sprintf( __( '%s registered as a new customer.', 'storesuite' ), $user->display_name );
				} else {
					$message = __( 'A new customer registered (account no longer exists).', 'storesuite' );
				}
				break;

			case 'product_review':
				$title   = __( 'New review', 'storesuite' );
				$comment = get_comment( $row->object_id );
				if ( $comment ) {
					$product_title = get_the_title( $comment->comment_post_ID );
					/* translators: 1: reviewer name, 2: product title */
					$message = sprintf( __( '%1$s left a review on "%2$s".', 'storesuite' ), $comment->comment_author, $product_title );
					$url     = get_comment_link( $comment );
				} else {
					$message = __( 'A product review was submitted (review no longer exists).', 'storesuite' );
				}
				break;

			default:
				$title   = __( 'Notification', 'storesuite' );
				$message = '';
				break;
		}

		return array(
			'id'       => (int) $row->id,
			'type'     => $row->type,
			'title'    => $title,
			'message'  => $message,
			'url'      => $url,
			/* translators: %s: human-readable time difference, e.g. "5 mins" */
			'time_ago' => sprintf( __( '%s ago', 'storesuite' ), human_time_diff( strtotime( $row->created_at . ' UTC' ), time() ) ),
			'is_seen'  => null !== $row->seen_at,
		);
	}
}
