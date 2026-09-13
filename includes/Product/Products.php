<?php
/**
 * Products handler
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Products class
 */
class Products {

	/**
	 * Get paginated products with search functionality.
	 *
	 * @param int    $page Current page number.
	 * @param string $search_term Search term to filter products.
	 * @param array  $filters Filters array (category, product_type, stock_status, brand,
	 *                        status, date_from, date_to, price_min, price_max, orderby, order).
	 * @return object
	 */
	public function get_paginated_products( $page = 1, $search_term = '', $filters = array() ) {
		// Get configuration from filters
		$statuses = apply_filters( 'storesuite_product_listing_post_statuses', array( 'publish', 'draft', 'pending', 'future' ) );
		$per_page = apply_filters( 'storesuite_products_per_page', 10 );

		$query = array(
			'posts_per_page' => $per_page,
			'post_type'      => 'product',
			'post_status'    => $statuses,
			'paged'          => $page,
		);

		// Add search functionality (searches in title, content, excerpt, and SKU)
		if ( ! empty( $search_term ) ) {
			$query['s'] = $search_term;

			// Add filter to extend search to SKU field
			add_filter(
				'posts_search',
				function ( $where ) use ( $search_term ) {
					return $this->extend_search_to_sku( $where, $search_term );
				},
				10,
				1
			);
		}

		// Apply filters (similar to WooCommerce admin)
		$query = $this->apply_product_filters( $query, $filters );

		// Apply column sorting.
		$query = $this->apply_product_sorting( $query, $filters );

		$sort_meta = isset( $query['storesuite_sort_meta'] ) ? $query['storesuite_sort_meta'] : null;
		unset( $query['storesuite_sort_meta'] );

		$sort_clauses_filter = null;
		if ( $sort_meta ) {
			$sort_clauses_filter = function ( $clauses ) use ( $sort_meta ) {
				return $this->sort_meta_clauses( $clauses, $sort_meta );
			};
			add_filter( 'posts_clauses', $sort_clauses_filter, 10, 1 );
		}

		$product_query = new \WP_Query( $query );

		if ( $sort_clauses_filter ) {
			remove_filter( 'posts_clauses', $sort_clauses_filter, 10 );
		}

		// Remove filter after query
		if ( ! empty( $search_term ) ) {
			remove_all_filters( 'posts_search' );
		}

		return (object) array(
			'products'      => $product_query,
			'found_posts'   => $product_query->found_posts,
			'max_num_pages' => $product_query->max_num_pages,
			'current_page'  => $page,
			'per_page'      => $per_page,
			'search_term'   => $search_term,
			'filters'       => $filters,
		);
	}

	/**
	 * Apply product filters to query (similar to WooCommerce admin).
	 *
	 * @param array $query WP_Query arguments.
	 * @param array $filters Filters array.
	 * @return array Modified query arguments.
	 */
	private function apply_product_filters( $query, $filters ) {
		// Filter by category
		if ( ! empty( $filters['category'] ) ) {
			$query['tax_query'][] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'term_id',
				'terms'    => absint( $filters['category'] ),
			);
		}

		// Filter by brand
		if ( ! empty( $filters['brand'] ) ) {
			$query['tax_query'][] = array(
				'taxonomy' => 'product_brand',
				'field'    => 'term_id',
				'terms'    => absint( $filters['brand'] ),
			);
		}

		// Filter by product type
		if ( ! empty( $filters['product_type'] ) ) {
			$query['tax_query'][] = array(
				'taxonomy' => 'product_type',
				'field'    => 'slug',
				'terms'    => sanitize_text_field( $filters['product_type'] ),
			);
		}

		// Filter by stock status
		if ( ! empty( $filters['stock_status'] ) ) {
			$query['meta_query'][] = array(
				'key'     => '_stock_status',
				'value'   => sanitize_text_field( $filters['stock_status'] ),
				'compare' => '=',
			);
		}

		// Restrict to an explicit ID set (e.g. the inventory low-stock filter).
		if ( ! empty( $filters['post__in'] ) && is_array( $filters['post__in'] ) ) {
			$ids               = array_values( array_filter( array_map( 'absint', $filters['post__in'] ) ) );
			$query['post__in'] = ! empty( $ids ) ? $ids : array( 0 );
		}

		// Filter by post status, validated against the listing allow-list.
		if ( ! empty( $filters['status'] ) ) {
			$allowed_statuses = apply_filters( 'storesuite_product_listing_post_statuses', array( 'publish', 'draft', 'pending', 'future' ) );
			$status           = sanitize_key( $filters['status'] );
			if ( in_array( $status, $allowed_statuses, true ) ) {
				$query['post_status'] = $status;
			}
		}

		// Filter by created date range (inclusive, Y-m-d from the date inputs).
		$date_query = array();
		if ( ! empty( $filters['date_from'] ) ) {
			$date_query['after'] = sanitize_text_field( $filters['date_from'] );
		}
		if ( ! empty( $filters['date_to'] ) ) {
			$date_query['before'] = sanitize_text_field( $filters['date_to'] ) . ' 23:59:59';
		}
		if ( ! empty( $date_query ) ) {
			$date_query['inclusive'] = true;
			$query['date_query']     = array( $date_query );
		}

		// Filter by price range on the synced _price meta.
		$price_min = isset( $filters['price_min'] ) && '' !== $filters['price_min'] ? wc_format_decimal( $filters['price_min'] ) : '';
		$price_max = isset( $filters['price_max'] ) && '' !== $filters['price_max'] ? wc_format_decimal( $filters['price_max'] ) : '';
		if ( is_numeric( $price_min ) && is_numeric( $price_max ) ) {
			$query['meta_query'][] = array(
				'key'     => '_price',
				'value'   => array( (float) $price_min, (float) $price_max ),
				'compare' => 'BETWEEN',
				'type'    => 'DECIMAL(19,4)',
			);
		} elseif ( is_numeric( $price_min ) ) {
			$query['meta_query'][] = array(
				'key'     => '_price',
				'value'   => (float) $price_min,
				'compare' => '>=',
				'type'    => 'DECIMAL(19,4)',
			);
		} elseif ( is_numeric( $price_max ) ) {
			$query['meta_query'][] = array(
				'key'     => '_price',
				'value'   => (float) $price_max,
				'compare' => '<=',
				'type'    => 'DECIMAL(19,4)',
			);
		}

		// Set tax_query relation if multiple taxonomies
		if ( isset( $query['tax_query'] ) && count( $query['tax_query'] ) > 1 ) {
			$query['tax_query']['relation'] = 'AND';
		}

		return $query;
	}

	/**
	 * Sortable columns and the meta key / cast each one orders by.
	 *
	 * @return array<string, array{key:string, type:string}>
	 */
	public static function get_sortable_meta_columns() {
		return array(
			'price' => array(
				'key'  => '_price',
				'type' => 'DECIMAL(19,4)',
			),
			'sku'   => array(
				'key'  => '_sku',
				'type' => 'CHAR',
			),
			'stock' => array(
				'key'  => '_stock',
				'type' => 'SIGNED',
			),
		);
	}

	/**
	 * Apply column sorting to the query (allow-listed orderby only).
	 *
	 * Meta sorts use an OR (EXISTS / NOT EXISTS) clause so products without
	 * the meta key (e.g. grouped products without _price) stay in the list
	 * instead of being dropped by the implicit INNER JOIN.
	 *
	 * @param array $query   WP_Query arguments.
	 * @param array $filters Filters array (orderby, order).
	 * @return array Modified query arguments.
	 */
	private function apply_product_sorting( $query, $filters ) {
		$orderby = isset( $filters['orderby'] ) ? sanitize_key( $filters['orderby'] ) : '';
		if ( '' === $orderby ) {
			return $query;
		}

		$order = isset( $filters['order'] ) && 'desc' === strtolower( (string) $filters['order'] ) ? 'DESC' : 'ASC';

		if ( 'title' === $orderby || 'date' === $orderby ) {
			$query['orderby'] = $orderby;
			$query['order']   = $order;
			return $query;
		}

		$meta_columns = self::get_sortable_meta_columns();
		if ( ! isset( $meta_columns[ $orderby ] ) ) {
			return $query;
		}

		// Sorting is applied through a keyed LEFT JOIN in sort_meta_clauses()
		// rather than a meta_query. A meta_query OR (EXISTS / NOT EXISTS) keeps
		// products without the meta in the list, but WP builds the EXISTS join
		// without a meta_key condition, so those products end up ordered by an
		// arbitrary meta row of theirs instead of sorting last.
		$query['storesuite_sort_meta'] = array(
			'key'   => $meta_columns[ $orderby ]['key'],
			'type'  => $meta_columns[ $orderby ]['type'],
			'order' => $order,
		);

		return $query;
	}

	/**
	 * Order the products query by one meta key via a keyed LEFT JOIN.
	 *
	 * Products that have no value for the key (unmanaged stock, grouped
	 * products without _price, blank SKUs) are kept and always listed last,
	 * whatever the direction; ties fall back to the title.
	 *
	 * @param array<string, string>                        $clauses   WP_Query SQL clauses.
	 * @param array{key:string, type:string, order:string} $sort_meta Meta key, SQL cast type and direction.
	 * @return array<string, string> Modified clauses.
	 */
	private function sort_meta_clauses( $clauses, $sort_meta ) {
		global $wpdb;

		$alias = 'storesuite_sort_meta';
		$order = 'DESC' === strtoupper( $sort_meta['order'] ) ? 'DESC' : 'ASC';

		$allowed_types = array_column( self::get_sortable_meta_columns(), 'type', 'type' );
		$type          = isset( $allowed_types[ $sort_meta['type'] ] ) ? $sort_meta['type'] : 'CHAR';

		$clauses['join'] .= $wpdb->prepare(
			" LEFT JOIN {$wpdb->postmeta} AS {$alias} ON ( {$wpdb->posts}.ID = {$alias}.post_id AND {$alias}.meta_key = %s )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- core table names and a fixed, non-user alias.
			$sort_meta['key']
		);

		$clauses['orderby'] = sprintf(
			"( %1\$s.meta_value IS NULL OR %1\$s.meta_value = '' ) ASC, CAST( %1\$s.meta_value AS %2\$s ) %3\$s, %4\$s.post_title ASC",
			$alias,
			$type,
			$order,
			$wpdb->posts
		);

		return $clauses;
	}

	/**
	 * Extend product search to include SKU field.
	 *
	 * @param string $where SQL WHERE clause.
	 * @param string $search_term Search term.
	 * @return string Modified WHERE clause.
	 */
	private function extend_search_to_sku( $where, $search_term ) {
		global $wpdb;

		if ( empty( $where ) || empty( $search_term ) ) {
			return $where;
		}

		// Search for products by SKU
		$search_ids = array();
		$wild       = '%';
		$find       = $search_term;
		$like       = $wild . $wpdb->esc_like( $find ) . $wild;

		// Get product IDs that match the SKU
		$sku_to_id = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_sku' AND meta_value LIKE %s", $like ) );

		if ( $sku_to_id && count( $sku_to_id ) > 0 ) {
			$search_ids = array_filter( array_map( 'absint', $sku_to_id ) );
		}

		// Add product IDs to the WHERE clause with OR logic
		if ( count( $search_ids ) > 0 ) {
			$placeholders = implode( ',', array_fill( 0, count( $search_ids ), '%d' ) );
			$in_clause    = $wpdb->prepare( $placeholders, $search_ids ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- %d placeholder list built from the IDs being prepared.
			$where        = str_replace( ')))', ") OR ({$wpdb->posts}.ID IN ({$in_clause}))))", $where );
		}

		return $where;
	}
}
