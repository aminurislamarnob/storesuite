<?php
/**
 * StoreSuite Inventory List Page
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PluginizeLab\StoreSuite\Inventory\InventoryManager;

do_action( 'storesuite_dashboard_wrapper_start' );
?>
<div class="my-storesuite-container">
	<aside id="storesuite-dashboard-sidebar" class="my-storesuite-sidebar" role="navigation" aria-label="<?php esc_attr_e( 'Store dashboard navigation', 'storesuite' ); ?>">
		<?php do_action( 'storesuite_dashboard_navigation' ); ?>
	</aside>
	<div class="my-storesuite-wrapper">
		<?php do_action( 'storesuite_dashboard_content_before' ); ?>
		<main class="my-storesuite-page-content">
			<?php do_action( 'storesuite_dashboard_before_main_content' ); ?>
			<div class="storesuite-table-header-part storesuite-inventory-toolbar">
				<div class="row align-items-center g-2">
					<div class="col-md-auto storesuite-toolbar-bulk">
						<div class="storesuite-form-group d-flex align-items-center storesuite-bulk-product-actions">
							<select name="action" id="bulk-action-selector-products" class="storesuite-form-control" form="storesuite-product-bulk-actions">
								<option value="-1"><?php esc_html_e( 'Bulk actions', 'storesuite' ); ?></option>
								<option value="edit"><?php esc_html_e( 'Edit', 'storesuite' ); ?></option>
								<option value="trash"><?php esc_html_e( 'Move to Trash', 'storesuite' ); ?></option>
								<option value="delete"><?php esc_html_e( 'Delete permanently', 'storesuite' ); ?></option>
							</select>
							<button type="submit" id="storesuite-product-doaction" class="my-storesuite-button" form="storesuite-product-bulk-actions"><?php esc_html_e( 'Apply', 'storesuite' ); ?></button>
						</div>
					</div>
					<div class="col-md storesuite-toolbar-search">
						<form action="" method="get" class="storesuite-search-form">
							<div class="storesuite-table-search-input">
								<div class="storesuite-table-search-icon">
									<svg xmlns="http://www.w3.org/2000/svg" id="Outline" viewBox="0 0 24 24" width="24" height="24">
										<path d="M23.707,22.293l-5.969-5.969a10.016,10.016,0,1,0-1.414,1.414l5.969,5.969a1,1,0,0,0,1.414-1.414ZM10,18a8,8,0,1,1,8-8A8.009,8.009,0,0,1,10,18Z"/>
									</svg>
								</div>
								<input type="text" name="search_by" id="search_by" placeholder="<?php esc_attr_e( 'Search by name or SKU', 'storesuite' ); ?>" value="<?php echo esc_attr( isset( $_GET['search_by'] ) ? sanitize_text_field( wp_unslash( $_GET['search_by'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only search; no state change. ?>" />
							</div>
						</form>
					</div>
					<div class="col-md-auto text-md-end storesuite-toolbar-actions">
						<div class="row justify-content-end g-2">
							<div class="col-auto storesuite-search-toggle-col">
								<button type="button" class="my-storesuite-button storesuite-search-toggle" id="storesuite-search-toggle" aria-label="<?php esc_attr_e( 'Search', 'storesuite' ); ?>" aria-expanded="false" aria-controls="search_by">
									<svg xmlns="http://www.w3.org/2000/svg" id="Outline" viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true" focusable="false">
										<path d="M23.707,22.293l-5.969-5.969a10.016,10.016,0,1,0-1.414,1.414l5.969,5.969a1,1,0,0,0,1.414-1.414ZM10,18a8,8,0,1,1,8-8A8.009,8.009,0,0,1,10,18Z"/>
									</svg>
								</button>
							</div>
							<div class="col-auto">
								<?php
								// Number of active filters, shown as a badge on the toggle.
								// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filter values.
								$storesuite_active_filter_keys  = array( 'product_cat', 'product_type', 'stock_status', 'low_stock' );
								$storesuite_active_filter_count = 0;
								foreach ( $storesuite_active_filter_keys as $storesuite_active_filter_key ) {
									if ( isset( $_GET[ $storesuite_active_filter_key ] ) && '' !== trim( sanitize_text_field( wp_unslash( $_GET[ $storesuite_active_filter_key ] ) ) ) ) {
										++$storesuite_active_filter_count;
									}
								}
								// phpcs:enable
								?>
								<button type="button" class="my-storesuite-button storesuite-filter-toggle" id="storesuite-filter-toggle">
									<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-funnel" viewBox="0 0 16 16">
										<path d="M1.5 1.5A.5.5 0 0 1 2 1h12a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-.128.334L10 8.692V13.5a.5.5 0 0 1-.342.474l-3 1A.5.5 0 0 1 6 14.5V8.692L1.628 3.834A.5.5 0 0 1 1.5 3.5zm1 .5v1.308l4.372 4.858A.5.5 0 0 1 7 8.5v5.306l2-.666V8.5a.5.5 0 0 1 .128-.334L13.5 3.308V2z"/>
									</svg>
									<span class="storesuite-button-label"><?php esc_html_e( 'Filter', 'storesuite' ); ?></span>
									<?php if ( $storesuite_active_filter_count > 0 ) : ?>
										<span class="storesuite-filter-count" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: number of active filters. */ _n( '%d active filter', '%d active filters', $storesuite_active_filter_count, 'storesuite' ), $storesuite_active_filter_count ) ); ?>"><?php echo esc_html( (string) $storesuite_active_filter_count ); ?></span>
									<?php endif; ?>
								</button>
							</div>
						</div>
					</div>
				</div>
			</div>
			<!-- Off-canvas Filter -->
			<?php storesuite_get_template_part( 'inventory/inventory-filters-offcanvas' ); ?>
			<form id="storesuite-product-bulk-actions" method="post">
				<div class="storesuite-table-responsive">
				<?php
				$current_page = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
				$search_term  = isset( $_GET['search_by'] ) ? sanitize_text_field( wp_unslash( $_GET['search_by'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only search; no state change.

				// Get filter parameters. Nonce not required: read-only filter values; no state change.
				// phpcs:disable WordPress.Security.NonceVerification.Recommended
				$filters = array(
					'category'     => isset( $_GET['product_cat'] ) ? absint( $_GET['product_cat'] ) : '',
					'product_type' => isset( $_GET['product_type'] ) ? sanitize_text_field( wp_unslash( $_GET['product_type'] ) ) : '',
					'stock_status' => isset( $_GET['stock_status'] ) ? sanitize_text_field( wp_unslash( $_GET['stock_status'] ) ) : '',
					'low_stock'    => isset( $_GET['low_stock'] ) ? absint( $_GET['low_stock'] ) : 0,
					'orderby'      => isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : '',
					'order'        => isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : '',
				);
				// phpcs:enable
				$inventory_obj  = new InventoryManager();
				$inventory_data = $inventory_obj->get_paginated_inventory( $current_page, $search_term, $filters );
				$product_query  = $inventory_data->products;

				if ( $product_query->found_posts > 0 ) {
					?>
				<table class="my-storesuite-tbl my-storesuite-product-list-table storesuite-list-table">
					<thead>
						<tr>
							<th class="check-column">
								<?php
								storesuite_get_template_part(
									'shared/list-bulk-checkbox',
									'',
									array(
										'is_all' => true,
										'id'     => 'cb-select-all-products',
									)
								);
								?>
							</th>
							<th><?php esc_html_e( 'Image', 'storesuite' ); ?></th>
							<th><?php esc_html_e( 'Name', 'storesuite' ); ?></th>
							<th><?php esc_html_e( 'SKU', 'storesuite' ); ?></th>
							<th><?php esc_html_e( 'Stock Qty', 'storesuite' ); ?></th>
							<th><?php esc_html_e( 'Stock Status', 'storesuite' ); ?></th>
							<th><?php esc_html_e( 'Backorders', 'storesuite' ); ?></th>
							<th><?php esc_html_e( 'Price', 'storesuite' ); ?></th>
							<th class="text-right"><?php esc_html_e( 'Actions', 'storesuite' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						while ( $product_query->have_posts() ) :
							$product_query->the_post();
							$product_id = get_the_ID();
							$product    = wc_get_product( $product_id );
							if ( ! $product ) {
								continue;
							}

							storesuite_get_template_part(
								'inventory/inventory-list-table-row',
								'',
								array(
									'product_id' => $product_id,
									'product'    => $product,
								)
							);
						endwhile;
						wp_reset_postdata();
						?>
					</tbody>
				</table>
					<?php
					$total_pages = $product_query->max_num_pages;

					if ( $total_pages > 1 ) {
						$current_page_num = max( 1, $current_page );
						storesuite_get_template_part(
                            'pagination',
                            '',
                            array(
								'total_items'  => $product_query->found_posts,
								'total_pages'  => $total_pages,
								'current_page' => $current_page_num,
								'per_page'     => $inventory_data->per_page,
                            )
						);
					}
				} else {
					storesuite_get_template_part(
						'not-found',
						'',
						array(
							'title' => esc_html__( 'No products found!', 'storesuite' ),
							'desc'  => esc_html__( 'No products match the current inventory filters.', 'storesuite' ),
						)
					);
				}
				?>
				</div>
			</form>
			<?php storesuite_get_template_part( 'products/product-bulk-edit-modal' ); ?>
		</main>
		<?php do_action( 'storesuite_dashboard_content_after' ); ?>
	</div>
</div>
<?php do_action( 'storesuite_dashboard_wrapper_end' ); ?>
