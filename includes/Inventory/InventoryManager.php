<?php

namespace PluginizeLab\StoreSuite\Inventory;

use PluginizeLab\StoreSuite\Product\Products;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Inventory manager: stock-focused product queries.
 */
class InventoryManager {

	/**
	 * Get paginated products for the inventory list.
	 *
	 * Wraps the shared Products query; when the low-stock filter is on, the
	 * result set is restricted to the low-stock ID set resolved in SQL.
	 *
	 * @param int    $page        Current page number.
	 * @param string $search_term Search term (title/SKU).
	 * @param array  $filters     Filters array (category, product_type, stock_status, low_stock, orderby, order).
	 * @return object See Products::get_paginated_products().
	 */
	public function get_paginated_inventory( $page = 1, $search_term = '', $filters = array() ) {
		if ( ! empty( $filters['low_stock'] ) ) {
			$filters['post__in'] = $this->get_low_stock_product_ids();
		}

		add_filter( 'storesuite_products_per_page', array( $this, 'set_per_page' ), 20 );
		$result = ( new Products() )->get_paginated_products( $page, $search_term, $filters );
		remove_filter( 'storesuite_products_per_page', array( $this, 'set_per_page' ), 20 );

		return $result;
	}

	/**
	 * Per-page override so the inventory list uses its own setting.
	 *
	 * @return int
	 */
	public function set_per_page() {
		return (int) apply_filters( 'storesuite_inventory_per_page', 10 );
	}

	/**
	 * IDs of stock-managed products at or below their low-stock threshold.
	 *
	 * The per-product _low_stock_amount wins; blank/missing values fall back
	 * to the store-wide woocommerce_notify_low_stock_amount option.
	 *
	 * @return int[] Product IDs (array(0) when none match, so post__in stays restrictive).
	 */
	public function get_low_stock_product_ids() {
		global $wpdb;

		$default_threshold = absint( get_option( 'woocommerce_notify_low_stock_amount', 2 ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Meta-to-meta comparison with per-product fallback is not expressible in WP_Query; result feeds post__in for one request.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT stock.post_id
				FROM {$wpdb->postmeta} AS stock
				INNER JOIN {$wpdb->posts} AS p ON p.ID = stock.post_id AND p.post_type = 'product'
				INNER JOIN {$wpdb->postmeta} AS manage ON manage.post_id = stock.post_id AND manage.meta_key = '_manage_stock' AND manage.meta_value = 'yes'
				LEFT JOIN {$wpdb->postmeta} AS low ON low.post_id = stock.post_id AND low.meta_key = '_low_stock_amount'
				WHERE stock.meta_key = '_stock'
					AND stock.meta_value != ''
					AND CAST( stock.meta_value AS SIGNED ) <= CAST( COALESCE( NULLIF( low.meta_value, '' ), %d ) AS SIGNED )",
				$default_threshold
			)
		);

		$ids = array_values( array_filter( array_map( 'absint', (array) $ids ) ) );

		/**
		 * Filter the low-stock product ID set used by the inventory list.
		 *
		 * @param int[] $ids               Matched product IDs.
		 * @param int   $default_threshold Store-wide low stock threshold.
		 */
		$ids = apply_filters( 'storesuite_low_stock_product_ids', $ids, $default_threshold );

		return ! empty( $ids ) ? $ids : array( 0 );
	}
}
