<?php
/**
 * StoreSuite tag List Page
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PluginizeLab\StoreSuite\ProductTag\Tags;

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
						<?php storesuite_get_template_part( 'shared/list-bulk-actions', '', array( 'object_type' => 'tag' ) ); ?>
					</div>
					<div class="col-md">
					<form action="" method="get">
						<div class="storesuite-table-search-input">
							<div class="storesuite-table-search-icon">
								<svg xmlns="http://www.w3.org/2000/svg" id="Outline" viewBox="0 0 24 24" width="24" height="24">
									<path d="M23.707,22.293l-5.969-5.969a10.016,10.016,0,1,0-1.414,1.414l5.969,5.969a1,1,0,0,0,1.414-1.414ZM10,18a8,8,0,1,1,8-8A8.009,8.009,0,0,1,10,18Z"/>
								</svg>
							</div>
							<input type="text" name="search_by" id="search_by" placeholder="<?php esc_attr_e( 'Search Tag', 'storesuite' ); ?>" value="<?php echo esc_attr( isset( $_GET['search_by'] ) ? sanitize_text_field( wp_unslash( $_GET['search_by'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only search; no state change. ?>" />
							</div>
						</form>
					</div>
					<div class="col-md-auto storesuite-toolbar-columns">
						<?php storesuite_get_template_part( 'shared/column-manager', '', array( 'table' => 'tags' ) ); ?>
					</div>
					<div class="col-md-auto text-right storesuite-toolbar-add">
						<?php do_action( 'storesuite_tags_toolbar_add_button' ); ?>
					</div>
				</div>
			</div>
			<?php
			$current_page  = ( get_query_var( 'paged' ) ) ? absint( get_query_var( 'paged' ) ) : 1;
			$tags_per_page = apply_filters( 'storesuite_tags_per_page', 10 );
			$search_term   = isset( $_GET['search_by'] ) ? sanitize_text_field( wp_unslash( $_GET['search_by'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only search; no state change.
			$product_tags  = new Tags();
			$tags_data     = $product_tags->get_paginated_tags( $tags_per_page, $current_page, $search_term );
			?>
			<div class="storesuite-table-responsive">
				<table class="my-storesuite-tbl my-storesuite-product-list-table my-storesuite-tags-table storesuite-list-table">
					<thead>
						<tr>
							<th class="check-column">
								<?php storesuite_get_template_part( 'shared/list-bulk-checkbox', '', array( 'is_all' => true ) ); ?>
							</th>
							<th<?php storesuite_list_column_attrs( 'tags', 'name' ); ?> width="210"><?php echo esc_html__( 'Name', 'storesuite' ); ?></th>
							<th<?php storesuite_list_column_attrs( 'tags', 'description' ); ?>><?php echo esc_html__( 'Description', 'storesuite' ); ?></th>
							<th<?php storesuite_list_column_attrs( 'tags', 'slug' ); ?> width="210"><?php echo esc_html__( 'Slug', 'storesuite' ); ?></th>
							<th<?php storesuite_list_column_attrs( 'tags', 'count' ); ?> width="70"><?php echo esc_html__( 'Count', 'storesuite' ); ?></th>
							<th class="text-right"><?php echo esc_html__( 'Actions', 'storesuite' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						if ( empty( $tags_data->tags ) ) {
							echo '<tr id="tag-row-not-found"><td colspan="6">';
							storesuite_get_template_part(
								'not-found',
								'',
								array(
									'title' => esc_html__( 'No tag found!', 'storesuite' ),
									'desc'  => esc_html__( 'There is nothing to display at the moment. Please try adding a tag.', 'storesuite' ),
								)
							);
							echo '</td></tr>';
						} else {
							foreach ( $tags_data->tags as $product_tag ) {
								?>
						<tr class="storesuite-list-row" id="tag-row-<?php echo esc_attr( $product_tag->term_id ); ?>">
							<td class="check-column">
								<?php storesuite_get_template_part( 'shared/list-bulk-checkbox', '', array( 'value' => $product_tag->term_id ) ); ?>
							</td>
							<td<?php storesuite_list_column_attrs( 'tags', 'name' ); ?> data-title="<?php esc_attr_e( 'Name', 'storesuite' ); ?>"><a href="<?php echo esc_url( sprintf( storesuite_get_navigation_url( 'edit-tag' ) . '%s', $product_tag->term_id ) ); ?>"><?php echo esc_html( $product_tag->name ); ?></a></td>
							<td<?php storesuite_list_column_attrs( 'tags', 'description' ); ?> data-title="<?php esc_attr_e( 'Description', 'storesuite' ); ?>"><?php echo esc_html( wp_trim_words( $product_tag->description, '9', '...' ) ); ?></td>
							<td<?php storesuite_list_column_attrs( 'tags', 'slug' ); ?> data-title="<?php esc_attr_e( 'Slug', 'storesuite' ); ?>"><?php echo esc_html( $product_tag->slug ); ?></td>
							<td<?php storesuite_list_column_attrs( 'tags', 'count' ); ?> data-title="<?php esc_attr_e( 'Count', 'storesuite' ); ?>"><?php echo esc_html( $product_tag->count ); ?></td>
							<td class="text-right" data-title="<?php esc_attr_e( 'Actions', 'storesuite' ); ?>">
								<div class="storesuite-dropdown">
									<span class="storesuite-dropdown-icon">
										<svg width="20" height="20" fill="currentColor" aria-hidden="true" focusable="false"><use href="#storesuite-icon-three-dots"></use></svg>
									</span>
									<ul class="storesuite-dropdown-menu">
										<li>
											<a href="<?php echo esc_url( get_category_link( $product_tag->term_id ) ); ?>" class="dropdown-link">
												<?php echo esc_html__( 'View', 'storesuite' ); ?>
											</a>
										</li>
										<li>
											<a href="<?php echo esc_url( sprintf( storesuite_get_navigation_url( 'edit-tag' ) . '%s', $product_tag->term_id ) ); ?>" class="dropdown-link"><?php echo esc_html__( 'Edit', 'storesuite' ); ?></a>
										</li>
										<li>
											<button type="button" class="inline-button dropdown-link storesuite-item-quick-edit" data-object-type="tag" data-id="<?php echo esc_attr( $product_tag->term_id ); ?>"><?php echo esc_html__( 'Quick edit', 'storesuite' ); ?></button>
										</li>
										<li>
											<button type="button" class="inline-button dropdown-link storesuite-delete-tag" data-tag-id="<?php echo esc_attr( $product_tag->term_id ); ?>">
												<?php echo esc_html__( 'Delete', 'storesuite' ); ?>
											</button>
										</li>
									</ul>
								</div>
							</td>
						</tr>
								<?php
							}
						}
						?>
					</tbody>
				</table>
				<?php
				if ( $tags_data->max_num_pages > 1 ) {
					storesuite_get_template_part(
						'pagination',
						'',
						array(
							'total_items'  => $tags_data->total,
							'total_pages'  => $tags_data->max_num_pages,
							'current_page' => $current_page,
							'per_page'     => $tags_per_page,
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