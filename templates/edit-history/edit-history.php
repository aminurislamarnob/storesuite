<?php
/**
 * StoreSuite Edit History page.
 *
 * Args from EditHistoryController: $batches, $manager, $enabled, $total_items,
 * $total_pages, $current_page, $per_page, $nonce, $query_vars.
 *
 * @package StoreSuite
 */

use PluginizeLab\StoreSuite\EditHistory\EditHistoryManager;

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
		<main class="my-storesuite-page-content storesuite-edit-history-page" data-nonce="<?php echo esc_attr( $nonce ); ?>">
			<?php do_action( 'storesuite_dashboard_before_main_content' ); ?>
			<?php if ( ! $enabled ) : ?>
				<div class="storesuite-bulk-edit-feedback storesuite-form-group" role="status">
					<p class="storesuite-bulk-edit-feedback-line"><?php esc_html_e( 'Edit history is switched off in StoreSuite settings. Existing entries are shown but new edits are not recorded.', 'storesuite' ); ?></p>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $batches ) ) : ?>
				<div class="storesuite-card storesuite-card-with-header storesuite-mb-24">
					<h3 class="storesuite-card-title"><?php esc_html_e( 'Edit History', 'storesuite' ); ?></h3>
					<div class="storesuite-card-content storesuite-table-wrapper">
						<table class="my-storesuite-tbl storesuite-list-table storesuite-edit-history-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Change', 'storesuite' ); ?></th>
									<th><?php esc_html_e( 'By', 'storesuite' ); ?></th>
									<th><?php esc_html_e( 'When', 'storesuite' ); ?></th>
									<th class="text-right"><?php esc_html_e( 'Actions', 'storesuite' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $batches as $storesuite_batch ) : ?>
									<?php
									$storesuite_user   = get_userdata( (int) $storesuite_batch->user_id );
									$storesuite_items  = $manager->get_items( (int) $storesuite_batch->id );
									$storesuite_undone = ! empty( $storesuite_batch->undone_at );
									$storesuite_is_undo = 'undo' === $storesuite_batch->source;
									?>
									<tr class="storesuite-list-row storesuite-history-row<?php echo $storesuite_undone ? ' is-undone' : ''; ?>" data-batch-id="<?php echo esc_attr( (string) $storesuite_batch->id ); ?>">
										<td data-title="<?php esc_attr_e( 'Change', 'storesuite' ); ?>">
											<div class="storesuite-history-summary">
												<span class="storesuite-badge storesuite-badge-<?php echo esc_attr( $storesuite_is_undo ? 'warning' : 'info' ); ?>"><?php echo esc_html( EditHistoryManager::source_label( $storesuite_batch->source ) ); ?></span>
												<strong><?php echo esc_html( $storesuite_batch->summary ); ?></strong>
												<?php if ( $storesuite_undone ) : ?>
													<span class="storesuite-history-undone-flag"><?php esc_html_e( '(undone)', 'storesuite' ); ?></span>
												<?php endif; ?>
											</div>
											<?php if ( ! empty( $storesuite_items ) ) : ?>
												<details class="storesuite-history-details">
													<summary>
														<?php
														echo esc_html(
															sprintf(
																/* translators: %d: number of field changes. */
																_n( '%d field change', '%d field changes', count( $storesuite_items ), 'storesuite' ),
																count( $storesuite_items )
															)
														);
														?>
													</summary>
													<ul class="storesuite-history-items">
														<?php foreach ( $storesuite_items as $storesuite_item ) : ?>
															<li>
																<?php
																$storesuite_object_label = 'order' === $storesuite_item->object_type
																	/* translators: %d: order ID. */
																	? sprintf( __( 'Order #%d', 'storesuite' ), (int) $storesuite_item->object_id )
																	: get_the_title( (int) $storesuite_item->object_id );
																$storesuite_object_url = 'order' === $storesuite_item->object_type
																	? storesuite_get_navigation_url( 'order-details' ) . (int) $storesuite_item->object_id
																	: storesuite_get_navigation_url( 'edit-product' ) . (int) $storesuite_item->object_id;
																?>
																<a href="<?php echo esc_url( $storesuite_object_url ); ?>"><?php echo esc_html( $storesuite_object_label ? $storesuite_object_label : '#' . (int) $storesuite_item->object_id ); ?></a>
																<span class="storesuite-history-field"><?php echo esc_html( EditHistoryManager::field_label( $storesuite_item->field ) ); ?>:</span>
																<span class="storesuite-history-old"><?php echo esc_html( EditHistoryManager::format_value( $storesuite_item->object_type, $storesuite_item->field, $storesuite_item->old_value ) ); ?></span>
																<span class="storesuite-history-arrow" aria-hidden="true">→</span>
																<span class="storesuite-history-new"><?php echo esc_html( EditHistoryManager::format_value( $storesuite_item->object_type, $storesuite_item->field, $storesuite_item->new_value ) ); ?></span>
															</li>
														<?php endforeach; ?>
													</ul>
												</details>
											<?php endif; ?>
										</td>
										<td data-title="<?php esc_attr_e( 'By', 'storesuite' ); ?>"><?php echo esc_html( $storesuite_user ? $storesuite_user->display_name : __( 'Unknown', 'storesuite' ) ); ?></td>
										<td data-title="<?php esc_attr_e( 'When', 'storesuite' ); ?>">
											<time datetime="<?php echo esc_attr( $storesuite_batch->created_at ); ?>" title="<?php echo esc_attr( get_date_from_gmt( $storesuite_batch->created_at, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?>">
												<?php
												/* translators: %s: human time diff. */
												echo esc_html( sprintf( __( '%s ago', 'storesuite' ), human_time_diff( strtotime( $storesuite_batch->created_at . ' UTC' ) ) ) );
												?>
											</time>
										</td>
										<td class="text-right" data-title="<?php esc_attr_e( 'Actions', 'storesuite' ); ?>">
											<?php if ( ! $storesuite_undone ) : ?>
												<button type="button" class="my-storesuite-button my-storesuite-button-light storesuite-history-undo" data-batch-id="<?php echo esc_attr( (string) $storesuite_batch->id ); ?>"><?php esc_html_e( 'Undo', 'storesuite' ); ?></button>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
				<?php
				if ( $total_pages > 1 ) {
					storesuite_get_template_part(
						'pagination',
						'',
						array(
							'total_items'  => $total_items,
							'total_pages'  => $total_pages,
							'current_page' => $current_page,
							'per_page'     => $per_page,
						)
					);
				}
				?>
			<?php else : ?>
				<?php
				storesuite_get_template_part(
					'not-found',
					'',
					array(
						'title' => esc_html__( 'No edits recorded yet', 'storesuite' ),
						'desc'  => esc_html__( 'Inline edits and bulk edits on products and orders will show up here, and can be undone.', 'storesuite' ),
					)
				);
				?>
			<?php endif; ?>
		</main>
		<?php do_action( 'storesuite_dashboard_content_after' ); ?>
	</div>
</div>
<?php do_action( 'storesuite_dashboard_wrapper_end' ); ?>
