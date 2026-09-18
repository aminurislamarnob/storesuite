<?php
/**
 * Inventory Manager — stock movement log.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Modules\InventoryManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writes and reads the stock movement log. Captures every stock change via the
 * WooCommerce stock hooks (order reductions included) as well as explicit
 * dashboard/bulk edits.
 */
class StockLog {

	const CRON_HOOK = 'storesuite_inventory_manager_purge_log';

	/**
	 * Guards against double-logging when our own setter also triggers the WC
	 * stock hook.
	 *
	 * @var array<int,bool>
	 */
	private static $suppress = array();

	/**
	 * Log row IDs inserted by the generic WC-hook capture in this request,
	 * keyed by product ID. Lets the order-item hooks re-attribute the row
	 * (WC fires `woocommerce_product_set_stock` per item *before*
	 * `woocommerce_reduce_order_item_stock`) instead of inserting a duplicate.
	 *
	 * @var array<int,int>
	 */
	private static $hook_rows = array();

	/**
	 * Register capture hooks + purge cron. Called from Module::boot().
	 *
	 * @return void
	 */
	public function register() {
		if ( (bool) Settings::value( 'enable_stock_log' ) ) {
			add_action( 'woocommerce_product_set_stock', array( $this, 'on_stock_set' ), 10, 1 );
			add_action( 'woocommerce_variation_set_stock', array( $this, 'on_stock_set' ), 10, 1 );
			add_action( 'woocommerce_reduce_order_item_stock', array( $this, 'on_order_item_reduce' ), 10, 3 );
			add_action( 'woocommerce_restore_order_item_stock', array( $this, 'on_order_item_restore' ), 10, 4 );

			add_action( self::CRON_HOOK, array( $this, 'purge' ) );
			// Action Scheduler can't accept new actions before `init`.
			add_action( 'init', array( __CLASS__, 'sync_schedule' ) );
		}
	}

	/**
	 * Reconcile the recurring purge action with the current settings. Runs on
	 * `init` while the stock log is enabled and directly after every settings
	 * save, so toggling the log never leaves an orphan action.
	 *
	 * @return void
	 */
	public static function sync_schedule() {
		if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}

		// Clean up the legacy WP-Cron event from pre-Action Scheduler versions.
		$legacy_ts = wp_next_scheduled( self::CRON_HOOK );
		if ( $legacy_ts ) {
			wp_unschedule_event( $legacy_ts, self::CRON_HOOK );
			\storesuite_log( '[inventory-manager] Migrated stock-log purge schedule from WP-Cron to Action Scheduler.', 'info' );
		}

		if ( (bool) Settings::value( 'enable_stock_log' ) ) {
			if ( false === as_next_scheduled_action( self::CRON_HOOK, array(), Emails\Manager::AS_GROUP ) ) {
				as_schedule_recurring_action( time() + HOUR_IN_SECONDS, DAY_IN_SECONDS, self::CRON_HOOK, array(), Emails\Manager::AS_GROUP, true );
				\storesuite_log( '[inventory-manager] Scheduled the recurring stock-log purge action.', 'info' );
			}
			return;
		}

		if ( false !== as_next_scheduled_action( self::CRON_HOOK, array(), Emails\Manager::AS_GROUP ) ) {
			as_unschedule_all_actions( self::CRON_HOOK, array(), Emails\Manager::AS_GROUP );
			\storesuite_log( '[inventory-manager] Unscheduled the stock-log purge action (stock log is off).', 'info' );
		}
	}

	/**
	 * Insert a movement row.
	 *
	 * @param int      $product_id Product/variation ID.
	 * @param int|null $before     Quantity before.
	 * @param int|null $after      Quantity after.
	 * @param string   $type       Change type (manual/bulk/order_reduce/...).
	 * @param string   $reference  Optional reference (e.g. order #).
	 * @param string   $note       Optional note.
	 * @return int Inserted row ID, or 0 when logging is disabled/failed.
	 */
	public static function record( $product_id, $before, $after, $type = 'manual', $reference = '', $note = '' ) {
		if ( ! (bool) Settings::value( 'enable_stock_log' ) ) {
			return 0;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->insert(
			Installer::stock_log_table(),
			array(
				'product_id'  => (int) $product_id,
				'user_id'     => get_current_user_id(),
				'qty_before'  => null === $before ? null : (int) $before,
				'qty_after'   => null === $after ? null : (int) $after,
				'change_type' => substr( (string) $type, 0, 32 ),
				'reference'   => $reference ? substr( (string) $reference, 0, 128 ) : null,
				'note'        => $note ? substr( (string) $note, 0, 255 ) : null,
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Suppress the next WC-hook capture for a product (used when we log the
	 * change explicitly ourselves). Call before the write that fires the WC
	 * stock hook, and pair with unsuppress() afterwards so a save that ends up
	 * not firing the hook (e.g. unchanged quantity) can't leave a stale flag.
	 *
	 * @param int $product_id Product ID.
	 * @return void
	 */
	public static function suppress( $product_id ) {
		self::$suppress[ (int) $product_id ] = true;
	}

	/**
	 * Clear a suppression flag.
	 *
	 * @param int $product_id Product ID.
	 * @return void
	 */
	public static function unsuppress( $product_id ) {
		unset( self::$suppress[ (int) $product_id ] );
	}

	/**
	 * Capture a generic stock set from WooCommerce.
	 *
	 * @param \WC_Product $product Product whose stock changed.
	 * @return void
	 */
	public function on_stock_set( $product ) {
		if ( ! $product instanceof \WC_Product ) {
			return;
		}
		$id = $product->get_id();
		if ( ! empty( self::$suppress[ $id ] ) ) {
			unset( self::$suppress[ $id ] );
			return;
		}
		// We don't know the previous value here; record the resulting quantity.
		// Remember the row so an order-item hook firing later in this request
		// can re-attribute it instead of adding a duplicate.
		$row_id = self::record( $id, null, $product->get_stock_quantity(), 'adjustment' );
		if ( $row_id ) {
			self::$hook_rows[ $id ] = $row_id;
		}
	}

	/**
	 * Attribute an order-driven reduction to the order. WC has already fired
	 * the generic set-stock hook for this item (writing an `adjustment` row),
	 * so update that row in place with the order reference and the exact
	 * before/after quantities.
	 *
	 * @param \WC_Order_Item_Product $item   Order item.
	 * @param array                  $change Change details (product, from, to).
	 * @param \WC_Order              $order  Order being reduced.
	 * @return void
	 */
	public function on_order_item_reduce( $item, $change, $order ) {
		unset( $item );
		if ( ! $order instanceof \WC_Order || empty( $change['product'] ) || ! $change['product'] instanceof \WC_Product ) {
			return;
		}
		$this->attribute_to_order(
			$change['product']->get_id(),
			isset( $change['from'] ) ? (int) $change['from'] : null,
			isset( $change['to'] ) ? (int) $change['to'] : null,
			'order_reduce',
			'#' . $order->get_order_number()
		);
	}

	/**
	 * Attribute an order-driven restock (cancellation/refund) to the order.
	 *
	 * @param \WC_Order_Item_Product $item      Order item.
	 * @param int|float              $new_stock New quantity.
	 * @param int|float              $old_stock Previous quantity.
	 * @param \WC_Order              $order     Order being restored.
	 * @return void
	 */
	public function on_order_item_restore( $item, $new_stock, $old_stock, $order ) {
		if ( ! $order instanceof \WC_Order || ! $item instanceof \WC_Order_Item_Product ) {
			return;
		}
		$product = $item->get_product();
		if ( ! $product ) {
			return;
		}
		$this->attribute_to_order( $product->get_id(), (int) $old_stock, (int) $new_stock, 'order_restore', '#' . $order->get_order_number() );
	}

	/**
	 * Re-attribute the hook-captured row for a product to an order, or insert
	 * a fresh row when no hook capture happened in this request.
	 *
	 * @param int      $product_id Product/variation ID.
	 * @param int|null $before     Quantity before.
	 * @param int|null $after      Quantity after.
	 * @param string   $type       order_reduce|order_restore.
	 * @param string   $reference  Order reference.
	 * @return void
	 */
	private function attribute_to_order( $product_id, $before, $after, $type, $reference ) {
		if ( ! (bool) Settings::value( 'enable_stock_log' ) ) {
			return;
		}

		$row_id = isset( self::$hook_rows[ $product_id ] ) ? self::$hook_rows[ $product_id ] : 0;
		unset( self::$hook_rows[ $product_id ] );

		if ( ! $row_id ) {
			self::record( $product_id, $before, $after, $type, $reference );
			return;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			Installer::stock_log_table(),
			array(
				'qty_before'  => null === $before ? null : (int) $before,
				'qty_after'   => null === $after ? null : (int) $after,
				'change_type' => substr( (string) $type, 0, 32 ),
				'reference'   => substr( (string) $reference, 0, 128 ),
			),
			array( 'id' => $row_id ),
			array( '%d', '%d', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Query the log with optional product filter.
	 *
	 * @param array $args {
	 *     @type int $product_id Filter by product.
	 *     @type int $paged      Page (1-based).
	 *     @type int $per_page   Rows per page.
	 * }
	 * @return array{items:array,total:int,total_pages:int}
	 */
	public static function query( array $args = array() ) {
		global $wpdb;

		$table    = Installer::stock_log_table();
		$per_page = isset( $args['per_page'] ) ? max( 1, (int) $args['per_page'] ) : 30;
		$paged    = isset( $args['paged'] ) ? max( 1, (int) $args['paged'] ) : 1;
		$offset   = ( $paged - 1 ) * $per_page;

		$where  = array( '1=1' );
		$params = array();
		if ( ! empty( $args['product_id'] ) ) {
			$where[]  = 'product_id = %d';
			$params[] = (int) $args['product_id'];
		}
		$where_sql = implode( ' AND ', $where );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total     = (int) $wpdb->get_var( $params ? $wpdb->prepare( $count_sql, $params ) : $count_sql );

		$list_sql    = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$list_params = array_merge( $params, array( $per_page, $offset ) );
		$rows        = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ), ARRAY_A );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return array(
			'items'       => is_array( $rows ) ? $rows : array(),
			'total'       => $total,
			'total_pages' => (int) ceil( $total / $per_page ),
		);
	}

	/**
	 * Purge entries past the retention window.
	 *
	 * @return void
	 */
	public function purge() {
		$days = (int) Settings::value( 'log_retention_days' );
		if ( $days <= 0 ) {
			return;
		}
		global $wpdb;
		$table  = Installer::stock_log_table();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff ) );
	}

	/**
	 * Clear the purge cron. Called on deactivate.
	 *
	 * @return void
	 */
	public static function unschedule() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::CRON_HOOK, array(), Emails\Manager::AS_GROUP );
		}

		// Also clear the legacy WP-Cron event from pre-Action Scheduler versions.
		$ts = wp_next_scheduled( self::CRON_HOOK );
		if ( $ts ) {
			wp_unschedule_event( $ts, self::CRON_HOOK );
		}
	}
}
