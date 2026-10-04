<?php
/**
 * StoreSuite brands list page
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PluginizeLab\StoreSuite\ProductBrand\Brands;

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
			<div class="storesuite-table-header-part">
				<div class="row align-items-center g-2">
					<div class="col-md-auto">
						<?php storesuite_get_template_part( 'shared/list-bulk-actions', '', array( 'object_type' => 'brand' ) ); ?>
					</div>
					<div class="col-md">
					<form action="" method="get">
						<div class="storesuite-table-search-input">
							<div class="storesuite-table-search-icon">
								<svg xmlns="http://www.w3.org/2000/svg" id="Outline" viewBox="0 0 24 24" width="24" height="24">
									<path d="M23.707,22.293l-5.969-5.969a10.016,10.016,0,1,0-1.414,1.414l5.969,5.969a1,1,0,0,0,1.414-1.414ZM10,18a8,8,0,1,1,8-8A8.009,8.009,0,0,1,10,18Z"/>
								</svg>
							</div>
							<input type="text" name="search_by" id="search_by" placeholder="<?php esc_attr_e( 'Search Brand', 'storesuite' ); ?>" value="<?php echo esc_attr( isset( $_GET['search_by'] ) ? sanitize_text_field( wp_unslash( $_GET['search_by'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only search; no state change. ?>" />
							</div>
						</form>
					</div>
					<div class="col-md-auto storesuite-toolbar-columns">
						<?php storesuite_get_template_part( 'shared/column-manager', '', array( 'table' => 'brands' ) ); ?>
					</div>
					<div class="col-md-auto text-right storesuite-toolbar-add">
						<?php do_action( 'storesuite_brands_toolbar_add_button' ); ?>
					</div>
				</div>
			</div>
			<?php
			$current_page    = ( get_query_var( 'paged' ) ) ? absint( get_query_var( 'paged' ) ) : 1;
			$brands_per_page = apply_filters( 'storesuite_brands_per_page', 10 );
			$search_term     = isset( $_GET['search_by'] ) ? sanitize_text_field( wp_unslash( $_GET['search_by'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only search; no state change.
			$product_brands  = new Brands();
			$brands_data     = $product_brands->get_paginated_brands_with_children( $brands_per_page, $current_page, $search_term );
			?>
			<div class="storesuite-table-responsive">
				<table class="my-storesuite-tbl my-storesuite-product-list-table storesuite-list-table">
					<thead>
						<tr>
							<th class="check-column">
								<?php storesuite_get_template_part( 'shared/list-bulk-checkbox', '', array( 'is_all' => true ) ); ?>
							</th>
							<th<?php storesuite_list_column_attrs( 'brands', 'image' ); ?> width="60"><?php esc_html_e( 'Image', 'storesuite' ); ?></th>
							<th<?php storesuite_list_column_attrs( 'brands', 'name' ); ?>><?php esc_html_e( 'Name', 'storesuite' ); ?></th>
							<th<?php storesuite_list_column_attrs( 'brands', 'description' ); ?>><?php esc_html_e( 'Description', 'storesuite' ); ?></th>
							<th<?php storesuite_list_column_attrs( 'brands', 'parent' ); ?>><?php esc_html_e( 'Parent', 'storesuite' ); ?></th>
							<th<?php storesuite_list_column_attrs( 'brands', 'slug' ); ?>><?php esc_html_e( 'Slug', 'storesuite' ); ?></th>
							<th<?php storesuite_list_column_attrs( 'brands', 'count' ); ?>><?php esc_html_e( 'Count', 'storesuite' ); ?></th>
							<th class="text-right"><?php esc_html_e( 'Actions', 'storesuite' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						if ( empty( $brands_data->brands ) ) {
							echo '<tr id="brand-row-not-found"><td colspan="8">';
							storesuite_get_template_part(
								'not-found',
								'',
								array(
									'title' => esc_html__( 'No brand found!', 'storesuite' ),
									'desc'  => esc_html__( 'There is nothing to display at the moment. Please try adding a brand.', 'storesuite' ),
								)
							);
							echo '</td></tr>';
						} else {
							foreach ( $brands_data->brands as $brand_item ) {
								$product_brand = $brand_item['brand'];
								$depth         = $brand_item['depth'];
								$parent        = $brand_item['parent'];
								$dash_prefix   = str_repeat( '&mdash; ', $depth );

								$template_args = array(
									'brand'       => $product_brand,
									'dash_prefix' => $dash_prefix,
									'parent'      => $parent,
								);
								storesuite_get_template_part( 'brands/brand-list-table-row', '', $template_args );
							}
						}
						?>
					</tbody>
				</table>
				<?php
				if ( $brands_data->max_num_pages > 1 ) {
					storesuite_get_template_part(
						'pagination',
						'',
						array(
							'total_items'  => $brands_data->total,
							'total_pages'  => $brands_data->max_num_pages,
							'current_page' => $current_page,
							'per_page'     => $brands_per_page,
						)
					);
				}
				?>
			</div>
			<?php storesuite_get_template_part( 'shared/list-quick-edit-modal' ); ?>
		</main>
	</div>
</div>
<?php do_action( 'storesuite_dashboard_wrapper_end' ); ?>