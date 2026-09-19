<?php
/**
 * Inventory List Filters - Off-Canvas
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get current filter values.
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce not required: read-only filter parameters; no state change.
$current_category  = isset( $_GET['product_cat'] ) ? absint( $_GET['product_cat'] ) : '';
$current_type      = isset( $_GET['product_type'] ) ? sanitize_text_field( wp_unslash( $_GET['product_type'] ) ) : '';
$current_stock     = isset( $_GET['stock_status'] ) ? sanitize_text_field( wp_unslash( $_GET['stock_status'] ) ) : '';
$current_low_stock = isset( $_GET['low_stock'] ) ? absint( $_GET['low_stock'] ) : 0;
$current_search    = isset( $_GET['search_by'] ) ? sanitize_text_field( wp_unslash( $_GET['search_by'] ) ) : '';
// phpcs:enable
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

			<!-- Low stock only -->
			<div class="storesuite-form-group">
				<label class="storesuite-inventory-low-stock-toggle">
					<input type="checkbox" name="low_stock" value="1" <?php checked( $current_low_stock, 1 ); ?>>
					<span><?php esc_html_e( 'Low stock only', 'storesuite' ); ?></span>
				</label>
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

			<div class="storesuite-filter-offcanvas-footer">
				<button type="submit" class="my-storesuite-button"><?php esc_html_e( 'Filter Inventory', 'storesuite' ); ?></button>
				<a href="?" class="my-storesuite-button storesuite-button-neutral-panel"><?php esc_html_e( 'Reset', 'storesuite' ); ?></a>
			</div>
		</form>
	</div>
</div>
