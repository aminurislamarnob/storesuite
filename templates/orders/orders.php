<?php
/**
 * StoreSuite order List Page
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PluginizeLab\StoreSuite\Order\OrderManager;
$orders_obj = new OrderManager();

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
			// Bulk action result notice, set by OrderController::handle_order_bulk_actions().
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only feedback counters on a redirect.
			$storesuite_bulk_updated = isset( $_GET['updated'] ) ? absint( wp_unslash( $_GET['updated'] ) ) : 0;
			$storesuite_bulk_trashed = isset( $_GET['trashed'] ) ? absint( wp_unslash( $_GET['trashed'] ) ) : 0;
			$storesuite_bulk_skipped = isset( $_GET['skipped'] ) ? absint( wp_unslash( $_GET['skipped'] ) ) : 0;
			// phpcs:enable

			if ( $storesuite_bulk_updated || $storesuite_bulk_trashed || $storesuite_bulk_skipped ) :
				?>
				<div class="storesuite-bulk-edit-feedback storesuite-form-group" role="status">
					<?php if ( $storesuite_bulk_updated ) : ?>
						<p class="storesuite-bulk-edit-feedback-line storesuite-text-success">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d: number of orders updated. */
									_n( '%d order updated.', '%d orders updated.', $storesuite_bulk_updated, 'storesuite' ),
									$storesuite_bulk_updated
								)
							);
							?>
						</p>
					<?php endif; ?>
					<?php if ( $storesuite_bulk_trashed ) : ?>
						<p class="storesuite-bulk-edit-feedback-line storesuite-text-success">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d: number of orders moved to the trash. */
									_n( '%d order moved to the Trash.', '%d orders moved to the Trash.', $storesuite_bulk_trashed, 'storesuite' ),
									$storesuite_bulk_trashed
								)
							);
							?>
						</p>
					<?php endif; ?>
					<?php if ( $storesuite_bulk_skipped ) : ?>
						<p class="storesuite-bulk-edit-feedback-line">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d: number of orders skipped. */
									_n( '%d order could not be updated.', '%d orders could not be updated.', $storesuite_bulk_skipped, 'storesuite' ),
									$storesuite_bulk_skipped
								)
							);
							?>
						</p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<div class="storesuite-table-header-part">
				<div class="row g-2">
					<div class="col-md-auto storesuite-orders-toolbar-bulk">
						<div class="storesuite-form-group d-flex align-items-center storesuite-bulk-product-actions storesuite-bulk-order-actions mb-0">
							<select name="action" id="bulk-action-selector-top" class="storesuite-form-control" form="storesuite-order-bulk-actions">
								<option value="-1"><?php esc_html_e( 'Bulk actions', 'storesuite' ); ?></option>
								<option value="mark_processing"><?php esc_html_e( 'Change status to processing', 'storesuite' ); ?></option>
								<option value="mark_on-hold"><?php esc_html_e( 'Change status to on-hold', 'storesuite' ); ?></option>
								<option value="mark_completed"><?php esc_html_e( 'Change status to completed', 'storesuite' ); ?></option>
								<option value="mark_cancelled"><?php esc_html_e( 'Change status to cancelled', 'storesuite' ); ?></option>
								<option value="trash"><?php esc_html_e( 'Move to Trash', 'storesuite' ); ?></option>
							</select>
							<button type="submit" id="doaction" class="my-storesuite-button" form="storesuite-order-bulk-actions"><?php esc_html_e( 'Apply', 'storesuite' ); ?></button>
						</div>
					</div>
					<div class="col-md-auto storesuite-orders-toolbar-search">
						<form action="" method="get" class="storesuite-search-form storesuite-order-search-form">
							<div class="storesuite-table-search-input storesuite-form-group">
								<div class="storesuite-table-search-icon">
									<svg xmlns="http://www.w3.org/2000/svg" id="Outline" viewBox="0 0 24 24" width="24" height="24">
										<path d="M23.707,22.293l-5.969-5.969a10.016,10.016,0,1,0-1.414,1.414l5.969,5.969a1,1,0,0,0,1.414-1.414ZM10,18a8,8,0,1,1,8-8A8.009,8.009,0,0,1,10,18Z"/>
									</svg>
								</div>
								<input type="text" name="search_by" id="search_by" placeholder="<?php esc_attr_e( 'Search Order', 'storesuite' ); ?>" value="<?php echo esc_attr( isset( $_GET['search_by'] ) ? sanitize_text_field( wp_unslash( $_GET['search_by'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only search; no state change. ?>" />
							</div>
							<div class="storesuite-order-search-filters">
							<div class="storesuite-form-group">
								<?php
								$options = array(
									'order_id'       => __( 'Order ID', 'storesuite' ),
									'customer_email' => __( 'Customer Email', 'storesuite' ),
									'customers'      => __( 'Customers', 'storesuite' ),
									'products'       => __( 'Products', 'storesuite' ),
									'all'            => __( 'All', 'storesuite' ),
								);

								/**
								 * Filters the search filters available in the order search. Can be used to add new or remove existing filters.
								 * When adding new filters, `woocommerce_hpos_generate_where_for_search_filter` should also be used to generate the WHERE clause for the new filter
								 *
								 * @param $options array List of available filters.
								 */
								$options       = apply_filters( 'woocommerce_hpos_admin_search_filters', $options );
								$saved_setting = get_user_setting( 'wc-search-filter-hpos-admin', 'all' );
								$selected      = isset( $_REQUEST['search-filter'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['search-filter'] ) ) : $saved_setting; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only search filter; no state change.
								if ( $saved_setting !== $selected ) {
									set_user_setting( 'wc-search-filter-hpos-admin', $selected );
								}
								?>
								<select name="search-filter" id="order-search-filter" class="storesuite-form-control">
									<?php foreach ( $options as $value => $label ) { ?>
										<option value="<?php echo esc_attr( wp_unslash( sanitize_text_field( $value ) ) ); ?>" <?php selected( $value, sanitize_text_field( wp_unslash( $selected ) ) ); ?>><?php echo esc_html( $label ); ?></option>
									<?php } ?>
								</select>
							</div>
							<div class="storesuite-form-group">
								<button type="submit" class="my-storesuite-button">
									<div class="storesuite-table-search-icon">
										<svg xmlns="http://www.w3.org/2000/svg" id="Outline" viewBox="0 0 24 24" width="24" height="24">
											<path d="M23.707,22.293l-5.969-5.969a10.016,10.016,0,1,0-1.414,1.414l5.969,5.969a1,1,0,0,0,1.414-1.414ZM10,18a8,8,0,1,1,8-8A8.009,8.009,0,0,1,10,18Z"/>
										</svg>
									</div>
									<?php esc_html_e( 'Search', 'storesuite' ); ?>
								</button>
							</div>
							</div><!-- .storesuite-order-search-filters -->
						</form>
					</div>
					<div class="col-md text-right storesuite-toolbar-add">
						<div class="row justify-content-end">
							<div class="col-md-auto">
								<a href="<?php echo esc_url( storesuite_get_navigation_url( 'add-new-order' ) ); ?>" class="my-storesuite-button">
									<svg xmlns="http://www.w3.org/2000/svg" id="Outline" viewBox="0 0 24 24" width="24" height="24">
										<path d="M23,11H13V1a1,1,0,0,0-1-1h0a1,1,0,0,0-1,1V11H1a1,1,0,0,0-1,1H0a1,1,0,0,0,1,1H11V23a1,1,0,0,0,1,1h0a1,1,0,0,0,1-1V13H23a1,1,0,0,0,1-1h0A1,1,0,0,0,23,11Z"/>
									</svg>
									<?php esc_html_e( 'Add Order', 'storesuite' ); ?>
								</a>
							</div>
							<div class="col-md-auto">
								<button type="button" class="my-storesuite-button storesuite-filter-toggle" id="storesuite-order-filter-toggle">
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
			<!-- Off-canvas Order Filter -->
			<?php storesuite_get_template_part( 'orders/order-filters-offcanvas' ); ?>
			<form id="storesuite-order-bulk-actions" method="post">
				<?php wp_nonce_field( 'storesuite_order_bulk_action', 'storesuite_bulk_action_nonce' ); ?>
				<div class="storesuite-table-responsive">
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
										'id'     => 'cb-select-all-orders',
									)
								);
								?>
							</th>
							<th><?php echo esc_html__( 'Order', 'storesuite' ); ?></th>
							<th><?php echo esc_html__( 'Status', 'storesuite' ); ?></th>
							<th><?php echo esc_html__( 'Order Total', 'storesuite' ); ?></th>
							<th><?php echo esc_html__( 'Total Items', 'storesuite' ); ?></th>
							<th><?php echo esc_html__( 'Customer', 'storesuite' ); ?></th>
							<th><?php echo esc_html__( 'Billing Phone', 'storesuite' ); ?></th>
							<th><?php echo esc_html__( 'Date', 'storesuite' ); ?></th>
							<th class="text-right"><?php echo esc_html__( 'Actions', 'storesuite' ); ?></th>
						</tr>
					</thead>
					<tbody>
							<?php
							$current_page    = ( get_query_var( 'paged' ) ) ? absint( get_query_var( 'paged' ) ) : 1;
							$orders_per_page = apply_filters( 'storesuite_orders_per_page', 10 );

							// Build filters array. Nonce not required: read-only filter parameters; no state change.
							// phpcs:disable WordPress.Security.NonceVerification.Recommended
							$filters = array(
								'search_term'    => isset( $_GET['search_by'] ) ? sanitize_text_field( wp_unslash( $_GET['search_by'] ) ) : '',
								'search_filter'  => isset( $_GET['search-filter'] ) ? sanitize_text_field( wp_unslash( $_GET['search-filter'] ) ) : 'all',
								'order_status'   => isset( $_GET['order_status'] ) ? sanitize_text_field( wp_unslash( $_GET['order_status'] ) ) : '',
								'_customer_user' => isset( $_GET['_customer_user'] ) ? sanitize_text_field( wp_unslash( $_GET['_customer_user'] ) ) : '',
								'order_channel'  => isset( $_GET['order_channel'] ) ? sanitize_text_field( wp_unslash( $_GET['order_channel'] ) ) : '',
								'm'              => isset( $_GET['m'] ) ? sanitize_text_field( wp_unslash( $_GET['m'] ) ) : '',
							);
							// phpcs:enable
							$orders = $orders_obj->get_all_orders( $orders_per_page, $current_page, $filters );

							if ( empty( $orders->orders ) ) {
								echo '<tr id="order-row-not-found"><td colspan="9">';
								storesuite_get_template_part(
									'not-found',
									'',
									array(
										'title' => esc_html__( 'No order found!', 'storesuite' ),
										'desc'  => esc_html__( 'There is nothing to display at the moment.', 'storesuite' ),
									)
								);
								echo '</td></tr>';
							} else {
								foreach ( $orders->orders as $order ) { // phpcs:ignore
									?>
								<tr class="storesuite-list-row">
									<td class="check-column">
										<?php
										storesuite_get_template_part(
											'shared/list-bulk-checkbox',
											'',
											array(
												'value' => (string) $order->get_id(),
												'input_name' => 'bulk_order_ids[]',
											)
										);
										?>
									</td>
									<td data-title="<?php echo esc_attr__( 'Order', 'storesuite' ); ?>">
										<?php $orders_obj->get_order_number_column_value( $order ); ?>
									</td>
									<td data-title="<?php echo esc_attr__( 'Status', 'storesuite' ); ?>">
										<span class="storesuite-badge storesuite-badge-<?php echo esc_attr( storesuite_get_order_status_class( $order->get_status() ) ); ?>">
											<?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?>
										</span>
									</td>
									<td data-title="<?php echo esc_attr__( 'Total', 'storesuite' ); ?>">
										<?php echo wp_kses_post( $order->get_formatted_order_total() ); ?>
									</td>
									<td data-title="<?php echo esc_html__( 'Total Items', 'storesuite' ); ?>">
										<?php echo esc_html( $order->get_item_count() ); ?>
									</td>
									<td data-title="<?php echo esc_attr__( 'Customer', 'storesuite' ); ?>">
										<?php $orders_obj->get_order_customer_column_value( $order ); ?>
									</td>
									<td data-title="<?php echo esc_attr__( 'Billing Phone', 'storesuite' ); ?>">
										<?php
										if ( $order->get_billing_phone() ) {
											echo esc_html( $order->get_billing_phone() );
										} else {
											echo esc_html__( 'N/A', 'storesuite' );
										}
										?>
									</td>
									<td data-title="<?php echo esc_attr__( 'Date', 'storesuite' ); ?>">
										<?php echo wp_kses_post( $orders_obj->get_order_date_column_value( $order ) ); ?>
									</td>
									<td class="text-right" data-title="<?php esc_attr_e( 'Actions', 'storesuite' ); ?>">
										<div class="storesuite-dropdown">
											<span class="storesuite-dropdown-icon">
												<svg width="20" height="20" fill="currentColor" aria-hidden="true" focusable="false"><use href="#storesuite-icon-three-dots"></use></svg>
											</span>
											<ul class="storesuite-dropdown-menu">
												<li>
													<a href="<?php echo esc_url( sprintf( storesuite_get_navigation_url( 'order-details' ) . '%s', $order->get_id() ) ); ?>" class="dropdown-link"><svg width="16" height="16" fill="currentColor" aria-hidden="true" focusable="false"><use href="#storesuite-icon-view"></use></svg><?php esc_html_e( 'View', 'storesuite' ); ?></a>
												</li>
												<li>
													<a href="<?php echo esc_url( sprintf( storesuite_get_navigation_url( 'edit-order' ) . '%s', $order->get_id() ) ); ?>" class="dropdown-link"><svg width="16" height="16" fill="currentColor" aria-hidden="true" focusable="false"><use href="#storesuite-icon-edit"></use></svg><?php echo esc_html__( 'Edit', 'storesuite' ); ?></a>
												</li>
												<?php
												/**
												 * Fires inside an order row's action dropdown.
												 *
												 * Allows plugins to add extra <li> action items (e.g. PDF-invoice
												 * / packing-slip document links).
												 *
												 * @param WC_Order $order Current order.
												 */
												do_action( 'storesuite_order_list_row_actions', $order );
												?>
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
				if ( $orders->max_num_pages > 1 ) {
					storesuite_get_template_part(
						'pagination',
						'',
						array(
							'total_items'  => $orders->total,
							'total_pages'  => $orders->max_num_pages,
							'current_page' => $current_page,
							'per_page'     => $orders_per_page,
						)
					);
				}
				?>
				</div>
			</form>
		</main>
	</div>
</div>
<?php do_action( 'storesuite_dashboard_wrapper_end' ); ?>