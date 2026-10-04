<?php

namespace PluginizeLab\StoreSuite\EditHistory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Records before/after values for inline and bulk edits and reverts them.
 *
 * A *batch* is one user action (an inline cell edit, a bulk edit run, an
 * order bulk status change, or an undo). Each batch owns *items*: one row per
 * (object, field) that actually changed. Undo walks a batch's items and sets
 * every field back to its old value, skipping items whose current value no
 * longer matches what the batch wrote (someone changed it since). The undo is
 * itself recorded as a batch, so nothing is ever destructive.
 */
class EditHistoryManager {

	const ENABLED_OPTION = 'storesuite_edit_history_enabled';

	const RETENTION_OPTION = 'storesuite_edit_history_retention_days';

	const DEFAULT_RETENTION_DAYS = 90;

	/**
	 * Product fields tracked, in display order.
	 *
	 * @var string[]
	 */
	const PRODUCT_FIELDS = array(
		'status',
		'regular_price',
		'sale_price',
		'stock_quantity',
		'stock_status',
		'manage_stock',
		'sku',
		'product_cat',
		'product_tag',
	);

	/**
	 * Whether recording is switched on in settings (default on).
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$value = storesuite_get_option_by_key( self::ENABLED_OPTION );

		return apply_filters( 'storesuite_edit_history_enabled', 'no' !== $value );
	}

	/**
	 * Retention window in days.
	 *
	 * @return int
	 */
	public static function get_retention_days() {
		$days = absint( storesuite_get_option_by_key( self::RETENTION_OPTION ) );
		if ( ! $days ) {
			$days = self::DEFAULT_RETENTION_DAYS;
		}

		return (int) apply_filters( 'storesuite_edit_history_retention_days', $days );
	}

	/*
	|--------------------------------------------------------------------------
	| Snapshots
	|--------------------------------------------------------------------------
	*/

	/**
	 * Capture the tracked fields of a product.
	 *
	 * @param \WC_Product|int $product Product or ID.
	 * @return array<string, string> Field => scalar value (term lists are JSON of sorted IDs).
	 */
	public function snapshot_product( $product ) {
		$product = $product instanceof \WC_Product ? $product : wc_get_product( $product );
		if ( ! $product ) {
			return array();
		}

		$id = $product->get_id();

		return array(
			'status'         => (string) $product->get_status( 'edit' ),
			'regular_price'  => (string) $product->get_regular_price( 'edit' ),
			'sale_price'     => (string) $product->get_sale_price( 'edit' ),
			'stock_quantity' => null === $product->get_stock_quantity( 'edit' ) ? '' : (string) $product->get_stock_quantity( 'edit' ),
			'stock_status'   => (string) $product->get_stock_status( 'edit' ),
			'manage_stock'   => $product->get_manage_stock( 'edit' ) ? 'yes' : 'no',
			'sku'            => (string) $product->get_sku( 'edit' ),
			'product_cat'    => $this->snapshot_terms( $id, 'product_cat' ),
			'product_tag'    => $this->snapshot_terms( $id, 'product_tag' ),
		);
	}

	/**
	 * Capture the tracked fields of a fresh copy of a product (bypasses object caches).
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, string>
	 */
	public function snapshot_product_fresh( $product_id ) {
		clean_post_cache( $product_id );
		wp_cache_delete( 'product-' . $product_id, 'products' );

		return $this->snapshot_product( wc_get_product( $product_id ) );
	}

	/**
	 * Capture the tracked fields of an order.
	 *
	 * @param \WC_Order|int $order Order or ID.
	 * @return array<string, string>
	 */
	public function snapshot_order( $order ) {
		$order = $order instanceof \WC_Order ? $order : wc_get_order( $order );
		if ( ! $order ) {
			return array();
		}

		return array(
			'status' => (string) $order->get_status( 'edit' ),
		);
	}

	/**
	 * JSON list of sorted term IDs for a product taxonomy.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $taxonomy   Taxonomy.
	 * @return string
	 */
	private function snapshot_terms( $product_id, $taxonomy ) {
		$ids = wp_get_object_terms( $product_id, $taxonomy, array( 'fields' => 'ids' ) );
		$ids = is_wp_error( $ids ) ? array() : array_map( 'intval', $ids );
		sort( $ids );

		return wp_json_encode( $ids );
	}

	/**
	 * Fields whose value differs between two snapshots.
	 *
	 * @param array<string, string> $before Before snapshot.
	 * @param array<string, string> $after  After snapshot.
	 * @return array<string, array{0:string,1:string}> Field => [old, new].
	 */
	public function diff( array $before, array $after ) {
		$changes = array();

		foreach ( $after as $field => $new ) {
			$old = isset( $before[ $field ] ) ? $before[ $field ] : '';
			if ( (string) $old !== (string) $new ) {
				$changes[ $field ] = array( (string) $old, (string) $new );
			}
		}

		return $changes;
	}

	/*
	|--------------------------------------------------------------------------
	| Recording
	|--------------------------------------------------------------------------
	*/

	/**
	 * Record a batch from per-object before/after snapshots.
	 *
	 * @param string                                $source      inline|bulk|order_bulk|undo.
	 * @param string                                $object_type product|order.
	 * @param string                                $summary     Human summary of the action.
	 * @param array<int, array<string, string>>     $before      Object ID => snapshot before.
	 * @param array<int, array<string, string>>     $after       Object ID => snapshot after.
	 * @param int|null                              $undo_of     Batch ID this batch reverts, if any.
	 * @return int Batch ID, or 0 when nothing changed or recording is off.
	 */
	public function record_from_snapshots( $source, $object_type, $summary, array $before, array $after, $undo_of = null ) {
		$items = array();

		foreach ( $after as $object_id => $snapshot ) {
			$changes = $this->diff( isset( $before[ $object_id ] ) ? $before[ $object_id ] : array(), $snapshot );
			foreach ( $changes as $field => list( $old, $new ) ) {
				$items[] = array(
					'object_id' => (int) $object_id,
					'field'     => $field,
					'old_value' => $old,
					'new_value' => $new,
				);
			}
		}

		return $this->record( $source, $object_type, $summary, $items, $undo_of );
	}

	/**
	 * Insert a batch and its items.
	 *
	 * @param string $source      inline|bulk|order_bulk|undo.
	 * @param string $object_type product|order.
	 * @param string $summary     Human summary of the action.
	 * @param array  $items       Rows with object_id, field, old_value, new_value.
	 * @param int    $undo_of     Batch ID this batch reverts, if any.
	 * @return int Batch ID, or 0.
	 */
	public function record( $source, $object_type, $summary, array $items, $undo_of = null ) {
		global $wpdb;

		if ( empty( $items ) || ( ! self::is_enabled() && 'undo' !== $source ) ) {
			return 0;
		}

		$batches = EditHistoryInstaller::get_batches_table();
		$table   = EditHistoryInstaller::get_items_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$wpdb->insert(
			$batches,
			array(
				'user_id'     => get_current_user_id(),
				'source'      => sanitize_key( $source ),
				'object_type' => sanitize_key( $object_type ),
				'summary'     => mb_substr( wp_strip_all_tags( $summary ), 0, 255 ),
				'item_count'  => count( $items ),
				'undo_of'     => $undo_of ? absint( $undo_of ) : null,
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%d', '%s' )
		);

		$batch_id = (int) $wpdb->insert_id;
		if ( ! $batch_id ) {
			return 0;
		}

		$values       = array();
		$placeholders = array();
		foreach ( $items as $item ) {
			$placeholders[] = '(%d, %s, %d, %s, %s, %s)';
			array_push(
				$values,
				$batch_id,
				sanitize_key( $object_type ),
				absint( $item['object_id'] ),
				sanitize_key( $item['field'] ),
				(string) $item['old_value'],
				(string) $item['new_value']
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Table is plugin-controlled; placeholders built above, values go through prepare().
		$wpdb->query( $wpdb->prepare( "INSERT INTO {$table} (batch_id, object_type, object_id, field, old_value, new_value) VALUES " . implode( ', ', $placeholders ), $values ) );

		/**
		 * After an edit batch has been recorded.
		 *
		 * @param int    $batch_id    Batch ID.
		 * @param string $source      Batch source.
		 * @param string $object_type Object type.
		 * @param array  $items       Recorded items.
		 */
		do_action( 'storesuite_edit_history_recorded', $batch_id, $source, $object_type, $items );

		return $batch_id;
	}

	/*
	|--------------------------------------------------------------------------
	| Queries
	|--------------------------------------------------------------------------
	*/

	/**
	 * One batch row.
	 *
	 * @param int $batch_id Batch ID.
	 * @return object|null
	 */
	public function get_batch( $batch_id ) {
		global $wpdb;

		$batches = EditHistoryInstaller::get_batches_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table.
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$batches} WHERE id = %d", absint( $batch_id ) ) );
	}

	/**
	 * Items of a batch.
	 *
	 * @param int $batch_id Batch ID.
	 * @return object[]
	 */
	public function get_items( $batch_id ) {
		global $wpdb;

		$table = EditHistoryInstaller::get_items_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table.
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE batch_id = %d ORDER BY id ASC", absint( $batch_id ) ) );
	}

	/**
	 * Paginated batches, newest first.
	 *
	 * @param int $page     Page number.
	 * @param int $per_page Rows per page.
	 * @return object[]
	 */
	public function get_batches( $page = 1, $per_page = 20 ) {
		global $wpdb;

		$batches = EditHistoryInstaller::get_batches_table();
		$offset  = ( max( 1, (int) $page ) - 1 ) * (int) $per_page;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table.
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$batches} ORDER BY id DESC LIMIT %d OFFSET %d", (int) $per_page, $offset ) );
	}

	/**
	 * Total number of batches.
	 *
	 * @return int
	 */
	public function count_batches() {
		global $wpdb;

		$batches = EditHistoryInstaller::get_batches_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table.
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$batches}" );
	}

	/**
	 * Items recorded against one object, newest first, with their batch info.
	 *
	 * @param string $object_type product|order.
	 * @param int    $object_id   Object ID.
	 * @param int    $limit       Max rows.
	 * @return object[]
	 */
	public function get_for_object( $object_type, $object_id, $limit = 50 ) {
		global $wpdb;

		$batches = EditHistoryInstaller::get_batches_table();
		$table   = EditHistoryInstaller::get_items_table();

		$sql = "SELECT i.*, b.user_id, b.source, b.summary, b.created_at, b.undone_at
			FROM {$table} i INNER JOIN {$batches} b ON b.id = i.batch_id
			WHERE i.object_type = %s AND i.object_id = %d
			ORDER BY i.id DESC LIMIT %d";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Custom tables; table names are plugin-controlled, values go through prepare().
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, sanitize_key( $object_type ), absint( $object_id ), (int) $limit ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Undo
	|--------------------------------------------------------------------------
	*/

	/**
	 * Revert a batch.
	 *
	 * Every item whose current value still equals what the batch wrote is set
	 * back to its old value. Items changed since are skipped and reported. The
	 * revert is recorded as a new batch of source "undo".
	 *
	 * @param int $batch_id Batch to revert.
	 * @return array{reverted:int,skipped:int,batch_id:int,object_ids:int[]}|\WP_Error
	 */
	public function undo( $batch_id ) {
		$batch = $this->get_batch( $batch_id );

		if ( ! $batch ) {
			return new \WP_Error( 'storesuite_history_missing', __( 'This change could not be found.', 'storesuite' ) );
		}

		if ( ! empty( $batch->undone_at ) ) {
			return new \WP_Error( 'storesuite_history_already_undone', __( 'This change has already been undone.', 'storesuite' ) );
		}

		$items = $this->get_items( $batch_id );
		if ( empty( $items ) ) {
			return new \WP_Error( 'storesuite_history_empty', __( 'There is nothing to undo.', 'storesuite' ) );
		}

		$reverted   = 0;
		$skipped    = 0;
		$undo_items = array();
		$touched    = array();

		foreach ( $items as $item ) {
			$current = $this->read_value( $item->object_type, (int) $item->object_id, $item->field );

			if ( null === $current || (string) $current !== (string) $item->new_value ) {
				++$skipped;
				continue;
			}

			$applied = $this->write_value( $item->object_type, (int) $item->object_id, $item->field, (string) $item->old_value );
			if ( ! $applied ) {
				++$skipped;
				continue;
			}

			++$reverted;
			$touched[]    = (int) $item->object_id;
			$undo_items[] = array(
				'object_id' => (int) $item->object_id,
				'field'     => $item->field,
				'old_value' => (string) $item->new_value,
				'new_value' => (string) $item->old_value,
			);
		}

		$undo_batch_id = 0;
		if ( $reverted > 0 ) {
			$undo_batch_id = $this->record(
				'undo',
				$batch->object_type,
				$batch->summary,
				$undo_items,
				$batch_id
			);

			$this->mark_undone( $batch_id );
		}

		return array(
			'reverted'   => $reverted,
			'skipped'    => $skipped,
			'batch_id'   => $undo_batch_id,
			'object_ids' => array_values( array_unique( $touched ) ),
		);
	}

	/**
	 * Flag a batch as undone.
	 *
	 * @param int $batch_id Batch ID.
	 * @return void
	 */
	private function mark_undone( $batch_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$wpdb->update(
			EditHistoryInstaller::get_batches_table(),
			array( 'undone_at' => current_time( 'mysql', true ) ),
			array( 'id' => absint( $batch_id ) ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Current value of a tracked field.
	 *
	 * @param string $object_type product|order.
	 * @param int    $object_id   Object ID.
	 * @param string $field       Field key.
	 * @return string|null Null when the object is gone.
	 */
	public function read_value( $object_type, $object_id, $field ) {
		$snapshot = 'order' === $object_type ? $this->snapshot_order( $object_id ) : $this->snapshot_product_fresh( $object_id );

		if ( empty( $snapshot ) || ! array_key_exists( $field, $snapshot ) ) {
			return null;
		}

		return $snapshot[ $field ];
	}

	/**
	 * Set a tracked field to a value.
	 *
	 * @param string $object_type product|order.
	 * @param int    $object_id   Object ID.
	 * @param string $field       Field key.
	 * @param string $value       Value as stored in the log.
	 * @return bool
	 */
	public function write_value( $object_type, $object_id, $field, $value ) {
		if ( 'order' === $object_type ) {
			return $this->write_order_value( $object_id, $field, $value );
		}

		return $this->write_product_value( $object_id, $field, $value );
	}

	/**
	 * Set a product field.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $field      Field key.
	 * @param string $value      Value.
	 * @return bool
	 */
	private function write_product_value( $product_id, $field, $value ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return false;
		}

		try {
			switch ( $field ) {
				case 'product_cat':
				case 'product_tag':
					$ids = json_decode( $value, true );
					$ids = is_array( $ids ) ? array_map( 'intval', $ids ) : array();
					$set = wp_set_object_terms( $product_id, $ids, $field, false );
					if ( is_wp_error( $set ) ) {
						return false;
					}
					wc_delete_product_transients( $product_id );
					return true;

				case 'status':
					$product->set_status( $value );
					break;

				case 'regular_price':
					$product->set_regular_price( $value );
					break;

				case 'sale_price':
					$product->set_sale_price( $value );
					break;

				case 'stock_quantity':
					$product->set_stock_quantity( '' === $value ? null : wc_stock_amount( $value ) );
					break;

				case 'stock_status':
					$product->set_stock_status( $value );
					break;

				case 'manage_stock':
					$product->set_manage_stock( 'yes' === $value );
					break;

				case 'sku':
					$product->set_sku( $value );
					break;

				default:
					return false;
			}

			$product->save();
		} catch ( \Exception $e ) {
			return false;
		}

		return true;
	}

	/**
	 * Set an order field.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $field    Field key.
	 * @param string $value    Value.
	 * @return bool
	 */
	private function write_order_value( $order_id, $field, $value ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || 'status' !== $field ) {
			return false;
		}

		return (bool) $order->update_status( $value, __( 'Status reverted from StoreSuite edit history.', 'storesuite' ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Retention
	|--------------------------------------------------------------------------
	*/

	/**
	 * Delete batches older than the retention window, with their items.
	 *
	 * @param int|null $days Override the configured window.
	 * @return int Batches deleted.
	 */
	public function delete_older_than( $days = null ) {
		global $wpdb;

		$days    = null === $days ? self::get_retention_days() : absint( $days );
		$batches = EditHistoryInstaller::get_batches_table();
		$table   = EditHistoryInstaller::get_items_table();
		$cutoff  = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table.
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$batches} WHERE created_at < %s", $cutoff ) );
		if ( empty( $ids ) ) {
			return 0;
		}

		$ids = array_map( 'absint', $ids );
		$in  = implode( ',', $ids );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- IDs cast to int above.
		$wpdb->query( "DELETE FROM {$table} WHERE batch_id IN ({$in})" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- IDs cast to int above.
		$wpdb->query( "DELETE FROM {$batches} WHERE id IN ({$in})" );

		return count( $ids );
	}

	/*
	|--------------------------------------------------------------------------
	| Display helpers
	|--------------------------------------------------------------------------
	*/

	/**
	 * Human label for a tracked field.
	 *
	 * @param string $field Field key.
	 * @return string
	 */
	public static function field_label( $field ) {
		$labels = array(
			'status'         => __( 'Status', 'storesuite' ),
			'regular_price'  => __( 'Regular price', 'storesuite' ),
			'sale_price'     => __( 'Sale price', 'storesuite' ),
			'stock_quantity' => __( 'Stock quantity', 'storesuite' ),
			'stock_status'   => __( 'Stock status', 'storesuite' ),
			'manage_stock'   => __( 'Manage stock', 'storesuite' ),
			'sku'            => __( 'SKU', 'storesuite' ),
			'product_cat'    => __( 'Categories', 'storesuite' ),
			'product_tag'    => __( 'Tags', 'storesuite' ),
		);

		return isset( $labels[ $field ] ) ? $labels[ $field ] : $field;
	}

	/**
	 * Human rendering of a stored value.
	 *
	 * @param string $object_type product|order.
	 * @param string $field       Field key.
	 * @param string $value       Stored value.
	 * @return string
	 */
	public static function format_value( $object_type, $field, $value ) {
		$value = (string) $value;

		if ( 'product_cat' === $field || 'product_tag' === $field ) {
			$ids = json_decode( $value, true );
			if ( ! is_array( $ids ) || empty( $ids ) ) {
				return '—';
			}
			$names = array();
			foreach ( $ids as $id ) {
				$term    = get_term( (int) $id, $field );
				$names[] = $term && ! is_wp_error( $term ) ? $term->name : '#' . (int) $id;
			}
			return implode( ', ', $names );
		}

		if ( '' === $value ) {
			return '—';
		}

		switch ( $field ) {
			case 'status':
				if ( 'order' === $object_type ) {
					return wc_get_order_status_name( $value );
				}
				$statuses = get_post_statuses();
				return isset( $statuses[ $value ] ) ? $statuses[ $value ] : $value;

			case 'stock_status':
				$options = wc_get_product_stock_status_options();
				return isset( $options[ $value ] ) ? $options[ $value ] : $value;

			case 'manage_stock':
				return 'yes' === $value ? __( 'Yes', 'storesuite' ) : __( 'No', 'storesuite' );

			case 'regular_price':
			case 'sale_price':
				return wp_strip_all_tags( wc_price( $value ) );

			default:
				return $value;
		}
	}

	/**
	 * Human label for a batch source.
	 *
	 * @param string $source Source key.
	 * @return string
	 */
	public static function source_label( $source ) {
		$labels = array(
			'inline'     => __( 'Inline edit', 'storesuite' ),
			'bulk'       => __( 'Bulk edit', 'storesuite' ),
			'order_bulk' => __( 'Order bulk action', 'storesuite' ),
			'undo'       => __( 'Undo', 'storesuite' ),
		);

		return isset( $labels[ $source ] ) ? $labels[ $source ] : $source;
	}
}
