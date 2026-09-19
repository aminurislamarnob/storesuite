<?php
/**
 * Product List Filters - Off-Canvas
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get current filter values.
// phpcs:disable WordPress.Security.NonceVerification.Recommended --  Nonce not required: read-only filter parameters; no state change.
$current_category = isset( $_GET['product_cat'] ) ? absint( $_GET['product_cat'] ) : '';
$current_type     = isset( $_GET['product_type'] ) ? sanitize_text_field( wp_unslash( $_GET['product_type'] ) ) : '';
$current_stock    = isset( $_GET['stock_status'] ) ? sanitize_text_field( wp_unslash( $_GET['stock_status'] ) ) : '';
$current_brand    = isset( $_GET['product_brand'] ) ? absint( $_GET['product_brand'] ) : '';
$current_search   = isset( $_GET['search_by'] ) ? sanitize_text_field( wp_unslash( $_GET['search_by'] ) ) : '';
$current_status   = isset( $_GET['post_status'] ) ? sanitize_key( wp_unslash( $_GET['post_status'] ) ) : '';
$current_from     = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
$current_to       = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
$current_min      = isset( $_GET['price_min'] ) ? sanitize_text_field( wp_unslash( $_GET['price_min'] ) ) : '';
$current_max      = isset( $_GET['price_max'] ) ? sanitize_text_field( wp_unslash( $_GET['price_max'] ) ) : '';
$current_orderby  = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : '';
$current_order    = isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : '';
// phpcs:enable

$storesuite_filter_statuses = array_intersect_key(
	(array) storesuite_get_post_status(),
	array_flip( apply_filters( 'storesuite_product_listing_post_statuses', array( 'publish', 'draft', 'pending', 'future' ) ) )
);
?>

<div class="storesuite-filter-offcanvas-overlay" id="storesuite-filter-overlay"></div>
<div class="storesuite-filter-offcanvas" id="storesuite-filter-offcanvas">
	<div class="storesuite-filter-offcanvas-header">
		<h3><?php esc_html_e( 'Filters', 'storesuite' ); ?></h3>
		<button type="button" class="storesuite-filter-close" id="storesuite-filter-close">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">
				<path d="M18,6h0a1,1,0,0,0-1.414,0L12,10.586,7.414,6A1,1,0,0,0,6,6H6a1,1,0,0,0,0,1.414L10.586,12,6,16.586A1,1,0,0,0,6,18h0a1,1,0,0,0,1.414,0L12,13.414,16.586,18A1,1,0,0,0,18,18h0a1,1,0,0,0,0-1.414L13.414,12,18,7.414A1,1,0,0,0,18,6Z"/>
			</svg>
		</button>
	</div>

	<div class="storesuite-filter-offcanvas-body">
		<form method="get" class="storesuite-filters-form-offcanvas">
			<?php if ( ! empty( $current_search ) ) : ?>
				<input type="hidden" name="search_by" value="<?php echo esc_attr( $current_search ); ?>">
			<?php endif; ?>
			<?php if ( ! empty( $current_orderby ) ) : ?>
				<input type="hidden" name="orderby" value="<?php echo esc_attr( $current_orderby ); ?>">
				<input type="hidden" name="order" value="<?php echo esc_attr( $current_order ); ?>">
			<?php endif; ?>

			<!-- Filter by Category -->
			<div class="storesuite-form-group">
				<label><?php esc_html_e( 'Filter by category', 'storesuite' ); ?></label>
				<select name="product_cat" class="storesuite-form-control">
					<option value=""><?php esc_html_e( 'All Categories', 'storesuite' ); ?></option>
					<?php
					$categories = get_terms(
						array(
							'taxonomy'   => 'product_cat',
							'hide_empty' => true,
							'orderby'    => 'name',
							'order'      => 'ASC',
						)
					);
					if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
						foreach ( $categories as $category ) {
							printf(
								'<option value="%s" %s>%s (%d)</option>',
								esc_attr( $category->term_id ),
								selected( $current_category, $category->term_id, false ),
								esc_html( $category->name ),
								absint( $category->count )
							);
						}
					}
					?>
				</select>
			</div>

			<!-- Filter by Product Type -->
			<div class="storesuite-form-group">
				<label><?php esc_html_e( 'Filter by product type', 'storesuite' ); ?></label>
				<select name="product_type" class="storesuite-form-control">
					<option value=""><?php esc_html_e( 'All Types', 'storesuite' ); ?></option>
					<?php
					$product_types = wc_get_product_types();
					foreach ( $product_types as $type_key => $type_label ) {
						printf(
							'<option value="%s" %s>%s</option>',
							esc_attr( $type_key ),
							selected( $current_type, $type_key, false ),
							esc_html( $type_label )
						);
					}
					?>
				</select>
			</div>

			<!-- Filter by Stock Status -->
			<div class="storesuite-form-group">
				<label><?php esc_html_e( 'Filter by stock status', 'storesuite' ); ?></label>
				<select name="stock_status" class="storesuite-form-control">
					<option value=""><?php esc_html_e( 'All Stock Status', 'storesuite' ); ?></option>
					<?php
					$stock_statuses = wc_get_product_stock_status_options();
					foreach ( $stock_statuses as $status_key => $status_label ) {
						printf(
							'<option value="%s" %s>%s</option>',
							esc_attr( $status_key ),
							selected( $current_stock, $status_key, false ),
							esc_html( $status_label )
						);
					}
					?>
				</select>
			</div>

			<!-- Filter by Brand -->
			<div class="storesuite-form-group">
				<label><?php esc_html_e( 'Filter by brand', 'storesuite' ); ?></label>
				<select name="product_brand" class="storesuite-form-control">
					<option value=""><?php esc_html_e( 'All Brands', 'storesuite' ); ?></option>
					<?php
					$brands = get_terms(
						array(
							'taxonomy'   => 'product_brand',
							'hide_empty' => true,
							'orderby'    => 'name',
							'order'      => 'ASC',
						)
					);
					if ( ! empty( $brands ) && ! is_wp_error( $brands ) ) {
						foreach ( $brands as $brand ) {
							printf(
								'<option value="%s" %s>%s (%d)</option>',
								esc_attr( $brand->term_id ),
								selected( $current_brand, $brand->term_id, false ),
								esc_html( $brand->name ),
								absint( $brand->count )
							);
						}
					}
					?>
				</select>
			</div>

			<!-- Filter by Status -->
			<div class="storesuite-form-group">
				<label><?php esc_html_e( 'Filter by status', 'storesuite' ); ?></label>
				<select name="post_status" class="storesuite-form-control">
					<option value=""><?php esc_html_e( 'All Statuses', 'storesuite' ); ?></option>
					<?php
					foreach ( $storesuite_filter_statuses as $storesuite_status_key => $storesuite_status_label ) {
						printf(
							'<option value="%s" %s>%s</option>',
							esc_attr( $storesuite_status_key ),
							selected( $current_status, $storesuite_status_key, false ),
							esc_html( $storesuite_status_label )
						);
					}
					?>
				</select>
			</div>

			<!-- Filter by Created Date -->
			<div class="storesuite-form-group">
				<label for="storesuite-filter-date-from"><?php esc_html_e( 'Created from', 'storesuite' ); ?></label>
				<input type="date" name="date_from" id="storesuite-filter-date-from" class="storesuite-form-control" value="<?php echo esc_attr( $current_from ); ?>">
			</div>
			<div class="storesuite-form-group">
				<label for="storesuite-filter-date-to"><?php esc_html_e( 'Created to', 'storesuite' ); ?></label>
				<input type="date" name="date_to" id="storesuite-filter-date-to" class="storesuite-form-control" value="<?php echo esc_attr( $current_to ); ?>">
			</div>

			<!-- Filter by Price -->
			<div class="storesuite-form-group">
				<label><?php esc_html_e( 'Price range', 'storesuite' ); ?></label>
				<div class="storesuite-filter-price-range">
					<input type="number" name="price_min" class="storesuite-form-control" min="0" step="any" placeholder="<?php esc_attr_e( 'Min', 'storesuite' ); ?>" aria-label="<?php esc_attr_e( 'Minimum price', 'storesuite' ); ?>" value="<?php echo esc_attr( $current_min ); ?>">
					<span class="storesuite-filter-price-sep" aria-hidden="true">&ndash;</span>
					<input type="number" name="price_max" class="storesuite-form-control" min="0" step="any" placeholder="<?php esc_attr_e( 'Max', 'storesuite' ); ?>" aria-label="<?php esc_attr_e( 'Maximum price', 'storesuite' ); ?>" value="<?php echo esc_attr( $current_max ); ?>">
				</div>
			</div>

			<div class="storesuite-filter-offcanvas-footer">
				<button type="submit" class="my-storesuite-button"><?php esc_html_e( 'Filter Products', 'storesuite' ); ?></button>
				<a href="?" class="my-storesuite-button storesuite-button-neutral-panel"><?php esc_html_e( 'Reset', 'storesuite' ); ?></a>
			</div>
		</form>
	</div>
</div>
