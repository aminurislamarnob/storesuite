<?php
/**
 * StoreSuite coupon List Page
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
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
						<div class="storesuite-form-group d-flex align-items-center storesuite-bulk-product-actions">
							<select name="action" id="bulk-action-selector-coupons" class="storesuite-form-control" form="storesuite-coupon-bulk-actions">
								<option value="-1"><?php esc_html_e( 'Bulk actions', 'storesuite' ); ?></option>
								<option value="edit"><?php esc_html_e( 'Edit', 'storesuite' ); ?></option>
								<option value="trash"><?php esc_html_e( 'Move to Trash', 'storesuite' ); ?></option>
							</select>
							<button type="submit" id="storesuite-coupon-doaction" class="my-storesuite-button" form="storesuite-coupon-bulk-actions"><?php esc_html_e( 'Apply', 'storesuite' ); ?></button>
						</div>
					</div>
					<div class="col-md">
						<form action="" method="get">
							<div class="storesuite-table-search-input">
								<div class="storesuite-table-search-icon">
									<svg xmlns="http://www.w3.org/2000/svg" id="Outline" viewBox="0 0 24 24" width="24" height="24">
										<path d="M23.707,22.293l-5.969-5.969a10.016,10.016,0,1,0-1.414,1.414l5.969,5.969a1,1,0,0,0,1.414-1.414ZM10,18a8,8,0,1,1,8-8A8.009,8.009,0,0,1,10,18Z"/>
									</svg>
								</div>
								<input type="text" name="search_by" id="search_by" placeholder="<?php esc_attr_e( 'Search Coupon', 'storesuite' ); ?>" value="<?php echo esc_attr( isset( $_GET['search_by'] ) ? sanitize_text_field( wp_unslash( $_GET['search_by'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only search; no state change. ?>" />
							</div>
						</form>
					</div>
					<div class="col-md-auto text-right storesuite-toolbar-add">
						<a href="<?php echo esc_url( storesuite_get_navigation_url( 'add-new-coupon' ) ); ?>" class="my-storesuite-button">
							<svg xmlns="http://www.w3.org/2000/svg" id="Outline" viewBox="0 0 24 24" width="24" height="24">
								<path d="M23,11H13V1a1,1,0,0,0-1-1h0a1,1,0,0,0-1,1V11H1a1,1,0,0,0-1,1H0a1,1,0,0,0,1,1H11V23a1,1,0,0,0,1,1h0a1,1,0,0,0,1-1V13H23a1,1,0,0,0,1-1h0A1,1,0,0,0,23,11Z"/>
							</svg>
							<?php esc_html_e( 'Add Coupon', 'storesuite' ); ?>
						</a>
					</div>
				</div>
			</div>
			<form id="storesuite-coupon-bulk-actions" method="post">
			<div class="storesuite-table-responsive">
				<?php
				$coupon_statuses = apply_filters( 'storesuite_coupon_listing_post_statuses', array( 'publish', 'draft', 'pending', 'private' ) );
				$posts_per_page = apply_filters( 'storesuite_coupons_per_page', 10 );
				$current_page   = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
				$search_by      = isset( $_GET['search_by'] ) ? sanitize_text_field( wp_unslash( $_GET['search_by'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only search; no state change.

				$query = array(
					'posts_per_page' => $posts_per_page,
					'post_type'      => 'shop_coupon',
					'post_status'    => $coupon_statuses,
					'paged'          => $current_page,
					'orderby'        => 'date',
					'order'          => 'DESC',
					's'              => $search_by,
				);

				$coupon_query = new WP_Query( $query );
				if ( $coupon_query->found_posts > 0 ) {
					?>
				<table class="my-storesuite-tbl my-storesuite-coupon-list-table storesuite-list-table">
					<thead>
						<tr>
							<th class="check-column">
								<?php
								storesuite_get_template_part(
									'shared/list-bulk-checkbox',
									'',
									array(
										'is_all' => true,
										'id'     => 'cb-select-all-coupons',
									)
								);
								?>
							</th>
							<th><?php esc_html_e( 'Code', 'storesuite' ); ?></th>
							<th><?php esc_html_e( 'Type', 'storesuite' ); ?></th>
							<th><?php esc_html_e( 'Amount', 'storesuite' ); ?></th>
							<th><?php esc_html_e( 'Description', 'storesuite' ); ?></th>
							<th><?php esc_html_e( 'Usage / Limit', 'storesuite' ); ?></th>
							<th><?php esc_html_e( 'Expiry Date', 'storesuite' ); ?></th>
							<th><?php esc_html_e( 'Status', 'storesuite' ); ?></th>
							<th class="text-right"><?php esc_html_e( 'Actions', 'storesuite' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						while ( $coupon_query->have_posts() ) :
							$coupon_query->the_post();
							$coupon_id = get_the_ID();
							$coupon    = new WC_Coupon( $coupon_id );
							?>
							<tr class="single-coupon-item storesuite-list-row">
								<td class="check-column">
									<?php
									storesuite_get_template_part(
										'shared/list-bulk-checkbox',
										'',
										array(
											'value' => (string) $coupon_id,
											'name'  => 'bulk_coupon_ids[]',
											'id'    => 'cb-select-' . $coupon_id,
										)
									);
									?>
								</td>
								<td class="tbl-coupon-code" data-title="<?php esc_attr_e( 'Code', 'storesuite' ); ?>">
									<a href="<?php echo esc_url( sprintf( storesuite_get_navigation_url( 'edit-coupon' ) . '%s', $coupon_id ) ); ?>"><?php echo esc_html( $coupon->get_code() ); ?></a>
								</td>
								<td data-title="<?php esc_attr_e( 'Type', 'storesuite' ); ?>">
									<?php echo esc_html( wc_get_coupon_type( $coupon->get_discount_type() ) ); ?>
								</td>
								<td data-title="<?php esc_attr_e( 'Amount', 'storesuite' ); ?>">
									<?php
									if ( 'percent' === $coupon->get_discount_type() ) {
										echo esc_html( $coupon->get_amount() ) . '%';
									} else {
										echo wp_kses_post( wc_price( $coupon->get_amount() ) );
									}
									?>
								</td>
								<td data-title="<?php esc_attr_e( 'Description', 'storesuite' ); ?>">
									<?php
									$description = $coupon->get_description();
									if ( $description ) {
										echo esc_html( wp_trim_words( $description, 10, '...' ) );
									} else {
										echo '<span class="no-description">&ndash;</span>';
									}
									?>
								</td>
								<td data-title="<?php esc_attr_e( 'Usage / Limit', 'storesuite' ); ?>">
									<?php
									$usage_count = $coupon->get_usage_count();
									$usage_limit = $coupon->get_usage_limit();

									printf(
										/* translators: 1: usage count 2: usage limit */
										esc_html__( '%1$s / %2$s', 'storesuite' ),
										esc_html( absint( $usage_count ) ),
										$usage_limit ? esc_html( absint( $usage_limit ) ) : '&infin;'
									);
									?>
								</td>
								<td data-title="<?php esc_attr_e( 'Expiry Date', 'storesuite' ); ?>">
									<?php
									$expiry_date = $coupon->get_date_expires();
									if ( $expiry_date ) {
										echo esc_html( $expiry_date->date_i18n( get_option( 'date_format' ) ) );
									} else {
										echo '<span class="no-expiry">&ndash;</span>';
									}
									?>
								</td>
								<td data-title="<?php esc_attr_e( 'Status', 'storesuite' ); ?>">
									<span class="storesuite-badge storesuite-badge-<?php echo esc_attr( storesuite_get_post_status_class( get_post_status( $coupon_id ) ) ); ?>">
										<?php echo esc_html( storesuite_get_post_status( get_post_status( $coupon_id ) ) ); ?>
									</span>
								</td>
								<td class="text-right" data-title="<?php esc_attr_e( 'Actions', 'storesuite' ); ?>">
									<div class="storesuite-dropdown">
										<span class="storesuite-dropdown-icon">
											<svg width="20" height="20" fill="currentColor" aria-hidden="true" focusable="false"><use href="#storesuite-icon-three-dots"></use></svg>
										</span>
										<ul class="storesuite-dropdown-menu">
											<li>
												<a href="<?php echo esc_url( sprintf( storesuite_get_navigation_url( 'edit-coupon' ) . '%s', $coupon_id ) ); ?>" class="dropdown-link"><?php esc_html_e( 'Edit', 'storesuite' ); ?></a>
											</li>
											<li>
												<button type="button" class="inline-button dropdown-link storesuite-delete-coupon" data-coupon-id="<?php echo esc_attr( $coupon_id ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( '_storesuite_delete_coupon_' ) ); ?>"><?php esc_html_e( 'Delete', 'storesuite' ); ?></button>
											</li>
										</ul>
									</div>
								</td>
							</tr>
							<?php
						endwhile;
						wp_reset_postdata();
						?>
					</tbody>
				</table>
					<?php
					$total_pages = $coupon_query->max_num_pages;

					if ( $total_pages > 1 ) {
						$current_page_num = max( 1, $current_page );
						$total_coupons    = $coupon_query->found_posts;
						storesuite_get_template_part(
							'pagination',
							'',
							array(
								'total_items'  => $total_coupons,
								'total_pages'  => $total_pages,
								'current_page' => $current_page_num,
								'per_page'     => $posts_per_page,
							)
						);
					}
				} else {
					storesuite_get_template_part(
						'not-found',
						'',
						array(
							'title' => esc_html__( 'No coupon found!', 'storesuite' ),
							'desc'  => esc_html__( 'There is nothing to display at the moment. Please try adding a coupon.', 'storesuite' ),
						)
					);
				}
				?>
			</div>
			</form>
			<?php storesuite_get_template_part( 'coupons/coupon-bulk-edit-modal' ); ?>
		</main>
		<?php do_action( 'storesuite_dashboard_content_after' ); ?>
	</div>
</div>
<?php do_action( 'storesuite_dashboard_wrapper_end' ); ?>
