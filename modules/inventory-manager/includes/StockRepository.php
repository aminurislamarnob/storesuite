<?php
/**
 * Inventory Manager — stock query + mutation.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Modules\InventoryManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes product stock across simple products and variations. The
 * list query targets stock-managed items (`_manage_stock = yes`), which is where
 * an inventory manager actually works.
 */
class StockRepository {

	/**
	 * Paginated list of stock-managed products/variations.
	 *
	 * @param array $args {
	 *     @type string $search       Title/SKU search.
	 *     @type string $stock_status Filter by stock status.
	 *     @type bool   $low_only     Only items at/below their threshold.
	 *     @type int    $paged        Page (1-based).
	 *     @type int    $per_page     Rows per page.
	 * }
	 * @return array{items:array,total:int,total_pages:int}
	 */
	public static function get_paginated( array $args = array() ) {
		global $wpdb;

		$per_page  = isset( $args['per_page'] ) ? max( 1, (int) $args['per_page'] ) : 20;
		$paged     = isset( $args['paged'] ) ? max( 1, (int) $args['paged'] ) : 1;
		$offset    = ( $paged - 1 ) * $per_page;
		$search    = isset( $args['search'] ) ? trim( (string) $args['search'] ) : '';
		$status    = isset( $args['stock_status'] ) ? sanitize_text_field( $args['stock_status'] ) : '';
		$low_only  = ! empty( $args['low_only'] );
		$threshold = (int) Settings::value( 'low_stock_default_threshold' );

		$posts    = $wpdb->posts;
		$meta     = $wpdb->postmeta;
		$joins    = array();
		$where    = array( "p.post_type IN ('product','product_variation')", "p.post_status = 'publish'" );
		$params   = array();

		$joins[] = "INNER JOIN {$meta} mms ON mms.post_id = p.ID AND mms.meta_key = '_manage_stock' AND mms.meta_value = 'yes'";
		$joins[] = "LEFT JOIN {$meta} ms ON ms.post_id = p.ID AND ms.meta_key = '_stock'";
		$joins[] = "LEFT JOIN {$meta} mls ON mls.post_id = p.ID AND mls.meta_key = '_low_stock_amount'";
		$joins[] = "LEFT JOIN {$meta} mss ON mss.post_id = p.ID AND mss.meta_key = '_stock_status'";
		$joins[] = "LEFT JOIN {$meta} msku ON msku.post_id = p.ID AND msku.meta_key = '_sku'";

		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '( p.post_title LIKE %s OR msku.meta_value LIKE %s )';
			$params[] = $like;
			$params[] = $like;
		}

		if ( '' !== $status ) {
			$where[]  = 'mss.meta_value = %s';
			$params[] = $status;
		}

		if ( $low_only ) {
			$where[]  = 'CAST(ms.meta_value AS SIGNED) <= COALESCE(NULLIF(mls.meta_value, ""), %d)';
			$params[] = $threshold;
		}

		$join_sql  = implode( "\n", $joins );
		$where_sql = implode( ' AND ', $where );

		// $join_sql/$where_sql are built only from the fixed fragments above;
		// user input flows through $params.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count_sql   = "SELECT COUNT(*) FROM {$posts} p {$join_sql} WHERE {$where_sql}";
		$total       = (int) $wpdb->get_var( $params ? $wpdb->prepare( $count_sql, $params ) : $count_sql );

		$list_sql    = "SELECT p.ID FROM {$posts} p {$join_sql} WHERE {$where_sql} ORDER BY p.post_title ASC LIMIT %d OFFSET %d";
		$list_params = array_merge( $params, array( $per_page, $offset ) );
		$ids         = $wpdb->get_col( $wpdb->prepare( $list_sql, $list_params ) );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$items = array();
		foreach ( (array) $ids as $id ) {
			$row = self::describe( (int) $id );
			if ( $row ) {
				$items[] = $row;
			}
		}

		return array(
			'items'       => $items,
			'total'       => $total,
			'total_pages' => (int) ceil( $total / $per_page ),
		);
	}

	/**
	 * Describe a product/variation for the list, using the WC product object.
	 *
	 * @param int $id Product or variation ID.
	 * @return array|null
	 */
	public static function describe( $id ) {
		$product = wc_get_product( $id );
		if ( ! $product ) {
			return null;
		}

		$low = $product->get_low_stock_amount();
		if ( '' === $low || null === $low ) {
			$low = (int) Settings::value( 'low_stock_default_threshold' );
		}

		$qty = $product->get_stock_quantity();

		return array(
			'id'           => $product->get_id(),
			'name'         => $product->get_name(),
			'sku'          => $product->get_sku(),
			'type'         => $product->get_type(),
			'stock_status' => $product->get_stock_status(),
			'stock_qty'    => null === $qty ? '' : (int) $qty,
			'low_stock'    => (int) $low,
			'backorders'   => $product->get_backorders(),
			'is_low'       => ( null !== $qty && (int) $qty <= (int) $low ),
			'edit_url'     => storesuite_get_navigation_url( 'edit-product' ) . ( $product->get_parent_id() ? $product->get_parent_id() : $product->get_id() ),
		);
	}

	/**
	 * Set an item's stock quantity, logging the movement.
	 *
	 * @param int    $id     Product/variation ID.
	 * @param int    $qty    New quantity.
	 * @param string $reason Change type for the log.
	 * @return int|\WP_Error New quantity, or error.
	 */
	public static function set_quantity( $id, $qty, $reason = 'manual' ) {
		$product = wc_get_product( $id );
		if ( ! $product ) {
			return new \WP_Error( 'storesuite_no_product', __( 'Product not found.', 'storesuite' ) );
		}

		$before = $product->get_stock_quantity();
		$qty    = wc_stock_amount( $qty );

		if ( ! $product->get_manage_stock() ) {
			$product->set_manage_stock( true );
		}
		$product->set_stock_quantity( $qty );

		// We log this change explicitly below; suppress the generic WC-hook
		// capture the save fires so it isn't logged twice. Unsuppress after in
		// case the save didn't fire the hook (e.g. unchanged quantity).
		StockLog::suppress( $product->get_id() );
		$product->save();
		StockLog::unsuppress( $product->get_id() );

		StockLog::record( $product->get_id(), $before, $qty, $reason );

		return (int) $qty;
	}
}
