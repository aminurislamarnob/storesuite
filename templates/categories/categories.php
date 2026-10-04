<?php
/**
 * StoreSuite category List Page
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PluginizeLab\StoreSuite\ProductCategory\Categories;

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
						<?php storesuite_get_template_part( 'shared/list-bulk-actions', '', array( 'object_type' => 'category' ) ); ?>
					</div>
					<div class="col-md">
						<form action="" method="get">
							<div class="storesuite-table-search-input">
								<div class="storesuite-table-search-icon">
									<svg xmlns="http://www.w3.org/2000/svg" id="Outline" viewBox="0 0 24 24" width="24" height="24">
										<path d="M23.707,22.293l-5.969-5.969a10.016,10.016,0,1,0-1.414,1.414l5.969,5.969a1,1,0,0,0,1.414-1.414ZM10,18a8,8,0,1,1,8-8A8.009,8.009,0,0,1,10,18Z"/>
									</svg>
								</div>
								<input type="text" name="search_by" id="search" placeholder="<?php esc_attr_e( 'Search Category', 'storesuite' ); ?>" value="<?php echo esc_attr( isset( $_GET['search_by'] ) ? sanitize_text_field( wp_unslash( $_GET['search_by'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only search; no state change. ?>" />
							</div>
						</form>
					</div>
					<div class="col-md-auto storesuite-toolbar-columns">
						<?php storesuite_get_template_part( 'shared/column-manager', '', array( 'table' => 'categories' ) ); ?>
					</div>
					<div class="col-md-auto text-right storesuite-toolbar-add">
						<?php do_action( 'storesuite_categories_toolbar_add_button' ); ?>
					</div>
				</div>
			</div>
			<?php
			$current_page        = ( get_query_var( 'paged' ) ) ? absint( get_query_var( 'paged' ) ) : 1;
			$categories_per_page = apply_filters( 'storesuite_categories_per_page', 15 );
			$search_term         = isset( $_GET['search_by'] ) ? sanitize_text_field( wp_unslash( $_GET['search_by'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only search; no state change.
			$product_categories  = new Categories();
			$categories_data     = $product_categories->get_paginated_categories_with_children( $categories_per_page, $current_page, $search_term );
			?>
			<div class="storesuite-table-responsive">
				<table class="my-storesuite-tbl my-storesuite-product-list-table storesuite-list-table">
					<thead>
						<tr>
							<th class="check-column">
								<?php storesuite_get_template_part( 'shared/list-bulk-checkbox', '', array( 'is_all' => true ) ); ?>
							</th>
							<th<?php storesuite_list_column_attrs( 'categories', 'image' ); ?> width="60"><?php echo esc_html__( 'Image', 'storesuite' ); ?></th>
							<th<?php storesuite_list_column_attrs( 'categories', 'name' ); ?> width="210"><?php echo esc_html__( 'Name', 'storesuite' ); ?></th>
							<th<?php storesuite_list_column_attrs( 'categories', 'description' ); ?>><?php echo esc_html__( 'Description', 'storesuite' ); ?></th>
							<th<?php storesuite_list_column_attrs( 'categories', 'parent' ); ?>><?php echo esc_html__( 'Parent', 'storesuite' ); ?></th>
							<th<?php storesuite_list_column_attrs( 'categories', 'slug' ); ?> width="210"><?php echo esc_html__( 'Slug', 'storesuite' ); ?></th>
							<th<?php storesuite_list_column_attrs( 'categories', 'count' ); ?> width="70"><?php echo esc_html__( 'Count', 'storesuite' ); ?></th>
							<th class="text-right"><?php echo esc_html__( 'Actions', 'storesuite' ); ?></th>
						</tr>
					</thead>
					<tbody>
							<?php
							if ( empty( $categories_data->categories ) ) {
								echo '<tr id="tag-category-not-found"><td colspan="8">';
								storesuite_get_template_part(
									'not-found',
									'',
									array(
										'title' => esc_html__( 'No category found!', 'storesuite' ),
										'desc'  => esc_html__( 'There is nothing to display at the moment. Please try adding a category.', 'storesuite' ),
									)
								);
								echo '</td></tr>';
							} else {
								foreach ( $categories_data->categories as $category_item ) {
									$product_category = $category_item['category'];
									$depth            = $category_item['depth'];
									$parent           = $category_item['parent'];
									$dash_prefix      = str_repeat( '&mdash; ', $depth );

									$template_args = array(
										'category'    => $product_category,
										'dash_prefix' => $dash_prefix,
										'parent'      => $parent,
									);
									storesuite_get_template_part( 'categories/category-list-table-row', '', $template_args );
								}
							}
							?>
					</tbody>
				</table>
				<?php
				if ( $categories_data->max_num_pages > 1 ) {
					storesuite_get_template_part(
						'pagination',
						'',
						array(
							'total_items'  => $categories_data->total,
							'total_pages'  => $categories_data->max_num_pages,
							'current_page' => $current_page,
							'per_page'     => $categories_per_page,
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