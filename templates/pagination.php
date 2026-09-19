<?php
/**
 * StoreSuite pagination template
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( $total_pages > 1 ) {
	$start_item = ( $current_page - 1 ) * $per_page + 1;
	$end_item   = min( $total_items, $current_page * $per_page );
	$big_num    = 999999999;

	// Explicitly carry the list's search/filter/sort params on every page link,
	// so a filtered or sorted result set survives pagination regardless of how
	// get_pagenum_link() treats the current query string.
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only pass-through of list state.
	$storesuite_preserved_keys = apply_filters(
		'storesuite_pagination_preserved_args',
		array( 'search_by', 'search-filter', 'product_cat', 'product_type', 'stock_status', 'product_brand', 'post_status', 'date_from', 'date_to', 'price_min', 'price_max', 'orderby', 'order', 'order_status', '_customer_user', 'order_channel', 'm' )
	);
	$storesuite_add_args       = array();
	foreach ( $storesuite_preserved_keys as $storesuite_preserved_key ) {
		if ( isset( $_GET[ $storesuite_preserved_key ] ) && '' !== $_GET[ $storesuite_preserved_key ] ) {
			$storesuite_add_args[ $storesuite_preserved_key ] = rawurlencode( sanitize_text_field( wp_unslash( $_GET[ $storesuite_preserved_key ] ) ) );
		}
	}
	// phpcs:enable

	$page_links = paginate_links(
		array(
			// get_pagenum_link()'s $escape argument must be false here: escaped,
			// it encodes '&' as '&#038;', and paginate_links() then feeds the base
			// through add_query_arg(), which reads the '#' as the start of a
			// fragment — truncating the query string and leaving a junk
			// '#038;...' fragment on every page link. paginate_links() escapes
			// the final href itself.
			'base'      => str_replace( $big_num, '%#%', get_pagenum_link( $big_num, false ) ),
			'format'    => '?page=%#%',
			'add_args'  => ! empty( $storesuite_add_args ) ? $storesuite_add_args : false,
			'current'   => $current_page,
			'total'     => $total_pages,
			'type'      => 'array', // list.
			'prev_text' => '&larr;',
			'next_text' => '&rarr;',
			'end_size'  => 3,
			'mid_size'  => 3,
		)
	);

	echo '<div class="storesuite-pagination-wrap">';

	echo '<div class="storesuite-result-text">';
	/* translators: %1$s: Start Item, %2$s: End Item, %3$s: Total Items */
	printf( esc_html__( 'Showing %1$s to %2$s of %3$s', 'storesuite' ), esc_html( $start_item ), esc_html( $end_item ), esc_html( $total_items ) );
	echo '</div>';

	if ( ! empty( $page_links ) ) {
		echo '<ul class="storesuite-pagination"><li>';
		echo wp_kses_post( join( '</li><li>', $page_links ) );
		echo '</li></ul>';
	}
	echo '</div>';
}
