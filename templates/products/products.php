<?php
/**
 * StoreSuite product List Page
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PluginizeLab\StoreSuite\Product\Products;

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
			<?php
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin notices from redirect query args.
			$storesuite_bulk_updated = isset( $_GET['updated'] ) ? absint( $_GET['updated'] ) : 0;
			$storesuite_bulk_skipped = isset( $_GET['skipped'] ) ? absint( $_GET['skipped'] ) : 0;
			$storesuite_bulk_locked       = isset( $_GET['locked'] ) ? absint( $_GET['locked'] ) : 0;
			$storesuite_bulk_trashed      = isset( $_GET['trashed'] ) ? absint( $_GET['trashed'] ) : 0;
			$storesuite_bulk_trash_locked = isset( $_GET['trash_locked'] ) ? absint( $_GET['trash_locked'] ) : 0;
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			if ( $storesuite_bulk_updated || $storesuite_bulk_skipped || $storesuite_bulk_locked || $storesuite_bulk_trashed || $storesuite_bulk_trash_locked ) :
				?>
			<div class="storesuite-bulk-edit-feedback storesuite-form-group" role="status">
				<?php if ( $storesuite_bulk_updated > 0 ) : ?>
					<p class="storesuite-bulk-edit-feedback-line storesuite-text-success">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: number of products updated */
								_n( '%d product updated.', '%d products updated.', $storesuite_bulk_updated, 'storesuite' ),
								$storesuite_bulk_updated
							)
						);
						?>
					</p>
				<?php endif; ?>
				<?php if ( $storesuite_bulk_skipped > 0 ) : ?>
					<p class="storesuite-bulk-edit-feedback-line">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: number of products skipped */
								_n( '%d product was not updated (permission or invalid data).', '%d products were not updated (permission or invalid data).', $storesuite_bulk_skipped, 'storesuite' ),
								$storesuite_bulk_skipped
							)
						);
						?>
					</p>
				<?php endif; ?>
				<?php if ( $storesuite_bulk_locked > 0 ) : ?>
					<p class="storesuite-bulk-edit-feedback-line">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: number of products locked by another user */
								_n( '%d product not updated, currently being edited by another user.', '%d products not updated, currently being edited by another user.', $storesuite_bulk_locked, 'storesuite' ),
								$storesuite_bulk_locked
							)
						);
						?>
					</p>
				<?php endif; ?>
				<?php if ( $storesuite_bulk_trashed > 0 ) : ?>
					<p class="storesuite-bulk-edit-feedback-line storesuite-text-success">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: number of products moved to trash */
								_n( '%d product moved to trash.', '%d products moved to trash.', $storesuite_bulk_trashed, 'storesuite' ),
								$storesuite_bulk_trashed
							)
						);
						?>
					</p>
				<?php endif; ?>
				<?php if ( $storesuite_bulk_trash_locked > 0 ) : ?>
					<p class="storesuite-bulk-edit-feedback-line">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: number of products not trashed because another user holds the edit lock */
								_n( '%d product was not moved to trash (another user is editing it).', '%d products were not moved to trash (another user is editing them).', $storesuite_bulk_trash_locked, 'storesuite' ),
								$storesuite_bulk_trash_locked
							)
						);
						?>
					</p>
				<?php endif; ?>
			</div>
				<?php
			endif;
			?>
			<div class="storesuite-table-header-part storesuite-products-toolbar">
				<div class="row align-items-center g-2">
					<div class="col-md-auto storesuite-toolbar-bulk">
						<div class="storesuite-form-group d-flex align-items-center storesuite-bulk-product-actions">
							<select name="action" id="bulk-action-selector-products" class="storesuite-form-control" form="storesuite-product-bulk-actions">
								<option value="-1"><?php esc_html_e( 'Bulk actions', 'storesuite' ); ?></option>
								<option value="edit"><?php esc_html_e( 'Edit', 'storesuite' ); ?></option>
								<option value="trash"><?php esc_html_e( 'Move to Trash', 'storesuite' ); ?></option>
								<option value="export"><?php esc_html_e( 'Export', 'storesuite' ); ?></option>
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
								<input type="text" name="search_by" id="search_by" placeholder="<?php esc_attr_e( 'Search Product', 'storesuite' ); ?>" value="<?php echo esc_attr( isset( $_GET['search_by'] ) ? sanitize_text_field( wp_unslash( $_GET['search_by'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only search; no state change. ?>" />
							</div>
						</form>
					</div>
					<div class="col-md-auto text-md-end storesuite-toolbar-actions">
						<div class="row justify-content-end g-2">
							<div class="col-auto">
								<a href="<?php echo esc_url( storesuite_get_navigation_url( 'add-new-product' ) ); ?>" class="my-storesuite-button">
									<svg xmlns="http://www.w3.org/2000/svg" id="Outline" viewBox="0 0 24 24" width="24" height="24">
										<path d="M23,11H13V1a1,1,0,0,0-1-1h0a1,1,0,0,0-1,1V11H1a1,1,0,0,0-1,1H0a1,1,0,0,0,1,1H11V23a1,1,0,0,0,1,1h0a1,1,0,0,0,1-1V13H23a1,1,0,0,0,1-1h0A1,1,0,0,0,23,11Z"/>
									</svg>
									<?php esc_html_e( 'Add', 'storesuite' ); ?>
									<span class="storesuite-add-product-text"><?php esc_html_e( 'Product', 'storesuite' ); ?></span>
								</a>
							</div>
							<div class="col-auto">
								<a href="<?php echo esc_url( storesuite_get_navigation_url( 'import-products' ) ); ?>" class="my-storesuite-button storesuite-import-link">
									<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="currentColor" aria-hidden="true" focusable="false">
										<path d="m19.95,5.536l-3.485-3.485c-1.322-1.322-3.08-2.05-4.95-2.05h-4.515C4.243,0,2,2.243,2,5v14c0,2.757,2.243,5,5,5h10c2.757,0,5-2.243,5-5v-8.515c0-1.87-.728-3.627-2.05-4.95Zm-1.414,1.414c.318.318.587.671.805,1.05h-4.341c-.551,0-1-.449-1-1V2.659c.379.218.733.487,1.05.805l3.485,3.485Zm1.464,12.05c0,1.654-1.346,3-3,3H7c-1.654,0-3-1.346-3-3V5c0-1.654,1.346-3,3-3h4.515c.163,0,.325.008.485.023v4.977c0,1.654,1.346,3,3,3h4.977c.015.16.023.322.023.485v8.515Zm-4.293-4.519c.391.391.391,1.023,0,1.414-.195.195-.451.293-.707.293s-.512-.098-.707-.293l-1.293-1.293v4.398c0,.552-.448,1-1,1s-1-.448-1-1v-4.398l-1.293,1.293c-.391.391-1.023.391-1.414,0s-.391-1.023,0-1.414l1.614-1.614c1.154-1.154,3.032-1.154,4.187,0l1.614,1.614Z"/>
									</svg>
									<?php esc_html_e( 'Import', 'storesuite' ); ?>
								</a>
							</div>
							<div class="col-auto storesuite-search-toggle-col">
								<button type="button" class="my-storesuite-button storesuite-search-toggle" id="storesuite-search-toggle" aria-label="<?php esc_attr_e( 'Search', 'storesuite' ); ?>" aria-expanded="false" aria-controls="search_by">
									<svg xmlns="http://www.w3.org/2000/svg" id="Outline" viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true" focusable="false">
										<path d="M23.707,22.293l-5.969-5.969a10.016,10.016,0,1,0-1.414,1.414l5.969,5.969a1,1,0,0,0,1.414-1.414ZM10,18a8,8,0,1,1,8-8A8.009,8.009,0,0,1,10,18Z"/>
									</svg>
								</button>
							</div>
							<div class="col-auto">
								<button type="button" class="my-storesuite-button storesuite-export-toggle" id="storesuite-export-toggle">
									<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="currentColor" aria-hidden="true" focusable="false">
										<path d="m19.949,5.536l-3.484-3.486c-1.323-1.322-3.081-2.05-4.95-2.05h-4.515C4.243,0,2,2.243,2,5v14c0,2.757,2.243,5,5,5h10c2.757,0,5-2.243,5-5v-8.515c0-1.871-.729-3.628-2.051-4.95Zm-1.414,1.415c.318.317.587.67.805,1.05h-4.341c-.552,0-1-.449-1-1V2.659c.38.218.733.487,1.051.805l3.484,3.486Zm1.465,12.05c0,1.654-1.346,3-3,3H7c-1.654,0-3-1.346-3-3V5c0-1.654,1.346-3,3-3h4.515c.163,0,.325.008.485.023v4.977c0,1.654,1.346,3,3,3h4.977c.015.16.023.322.023.485v8.515Zm-4.293-2.895c.391.39.391,1.023,0,1.414l-1.613,1.614c-.577.577-1.336.866-2.094.866s-1.517-.289-2.094-.866l-1.613-1.614c-.391-.391-.391-1.024,0-1.414.391-.391,1.023-.391,1.414,0l1.293,1.293v-4.398c0-.552.447-1,1-1s1,.448,1,1v4.398l1.293-1.293c.391-.391,1.023-.391,1.414,0Z"/>
									</svg>
									<span class="storesuite-button-label"><?php esc_html_e( 'Export', 'storesuite' ); ?></span>
								</button>
							</div>
							<div class="col-auto">
								<button type="button" class="my-storesuite-button storesuite-filter-toggle" id="storesuite-filter-toggle">
									<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-funnel" viewBox="0 0 16 16">
										<path d="M1.5 1.5A.5.5 0 0 1 2 1h12a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-.128.334L10 8.692V13.5a.5.5 0 0 1-.342.474l-3 1A.5.5 0 0 1 6 14.5V8.692L1.628 3.834A.5.5 0 0 1 1.5 3.5zm1 .5v1.308l4.372 4.858A.5.5 0 0 1 7 8.5v5.306l2-.666V8.5a.5.5 0 0 1 .128-.334L13.5 3.308V2z"/>
									</svg>
									<span class="storesuite-button-label"><?php esc_html_e( 'Filter', 'storesuite' ); ?></span>
								</button>
							</div>
						</div>
					</div>
				</div>
			</div>
			<!-- Off-canvas Filter -->
			<?php storesuite_get_template_part( 'products/product-filters-offcanvas' ); ?>
			<form id="storesuite-product-bulk-actions" method="post">
				<?php wp_nonce_field( 'storesuite_product_bulk', 'storesuite_product_bulk_nonce' ); ?>
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
					'brand'        => isset( $_GET['product_brand'] ) ? absint( $_GET['product_brand'] ) : '',
				);
				// phpcs:enable
				$products_obj  = new Products();
				$products_data = $products_obj->get_paginated_products( $current_page, $search_term, $filters );
				$product_query = $products_data->products;

				if ( $product_query->found_posts > 0 ) {
					?>
				<table class="my-storesuite-tbl my-storesuite-product-list-table storesuite-list-table">
					<thead>
						<tr>
							<th class="check-column">
								<label class="my-storesuite-checkbox">
									<input type="checkbox" id="cb-select-all-products" class="my-storesuite-checkbox-input" aria-label="<?php esc_attr_e( 'Select all', 'storesuite' ); ?>">
									<span class="my-storesuite-checkbox-back"></span>
									<span class="my-storesuite-tick">
										<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-check" viewBox="0 0 16 16">
											<path d="M10.97 4.97a.75.75 0 0 1 1.07 1.05l-3.99 4.99a.75.75 0 0 1-1.08.02L4.324 8.384a.75.75 0 1 1 1.06-1.06l2.094 2.093 3.473-4.425z"/>
										</svg>
									</span>
								</label>
							</th>
							<th><?php esc_html_e( 'Image', 'storesuite' ); ?></th>
							<th><?php esc_html_e( 'Name', 'storesuite' ); ?></th>
							<th><?php esc_html_e( 'Category', 'storesuite' ); ?></th>
							<th><?php esc_html_e( 'Status', 'storesuite' ); ?></th>
							<th><?php esc_html_e( 'SKU', 'storesuite' ); ?></th>
							<th><?php esc_html_e( 'Stock', 'storesuite' ); ?></th>
							<th><?php esc_html_e( 'Price', 'storesuite' ); ?></th>
							<th><?php esc_html_e( 'Type', 'storesuite' ); ?></th>
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
								'products/product-list-table-row',
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
						$total_products   = $product_query->found_posts;
						storesuite_get_template_part(
							'pagination',
							'',
							array(
								'total_items'  => $total_products,
								'total_pages'  => $total_pages,
								'current_page' => $current_page_num,
								'per_page'     => $products_data->per_page,
							)
						);
					}
				} else {
					storesuite_get_template_part(
						'not-found',
						'',
						array(
							'title' => esc_html__( 'No product found!', 'storesuite' ),
							'desc'  => esc_html__( 'There is nothing to display at the moment. Please try adding a product.', 'storesuite' ),
						)
					);
				}
				?>
				</div>
			</form>
			<?php storesuite_get_template_part( 'products/product-bulk-edit-modal' ); ?>
			<?php storesuite_get_template_part( 'products/product-quick-edit-modal' ); ?>
			<?php storesuite_get_template_part( 'products/product-export-modal' ); ?>
		</main>
		<?php do_action( 'storesuite_dashboard_content_after' ); ?>
	</div>
</div>
<?php do_action( 'storesuite_dashboard_wrapper_end' ); ?>