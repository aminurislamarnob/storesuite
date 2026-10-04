<?php
/**
 * Custom Order Statuses — management screen.
 *
 * @package StoreSuite
 */

use PluginizeLab\StoreSuite\Modules\OrderStatuses\StatusRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$storesuite_statuses = StatusRepository::all();
$storesuite_core     = wc_get_order_statuses(); // wc-prefixed => label, for the transitions picker.

$storesuite_presets = array( '#2d5bdb', '#16a34a', '#f59e0b', '#ef4444', '#8b5cf6', '#0ea5e9', '#64748b' );

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
			storesuite_get_template_part(
				'dashboard-title',
				null,
				array(
					'page_title'            => __( 'Order Statuses', 'storesuite' ),
					'parent_endpoint_title' => __( 'Orders', 'storesuite' ),
					'parent_endpoint_url'   => storesuite_get_navigation_url( 'orders' ),
				)
			);
			?>

			<div class="row">
				<div class="col-md-7">
					<div class="storesuite-table-responsive">
						<table class="my-storesuite-tbl storesuite-list-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Status', 'storesuite' ); ?></th>
									<th><?php esc_html_e( 'Slug', 'storesuite' ); ?></th>
									<th><?php esc_html_e( 'Paid', 'storesuite' ); ?></th>
									<th><?php esc_html_e( 'Reports', 'storesuite' ); ?></th>
									<th><?php esc_html_e( 'Actions', 'storesuite' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php if ( empty( $storesuite_statuses ) ) : ?>
									<tr><td colspan="5"><?php esc_html_e( 'No custom statuses yet. Create one on the right.', 'storesuite' ); ?></td></tr>
								<?php else : ?>
									<?php foreach ( $storesuite_statuses as $storesuite_status ) : ?>
										<tr class="single-product-item storesuite-list-row"
											data-slug="<?php echo esc_attr( $storesuite_status['slug'] ); ?>"
											data-label="<?php echo esc_attr( $storesuite_status['label'] ); ?>"
											data-color="<?php echo esc_attr( $storesuite_status['color'] ); ?>"
											data-paid="<?php echo esc_attr( $storesuite_status['is_paid'] ? '1' : '0' ); ?>"
											data-reports="<?php echo esc_attr( $storesuite_status['in_reports'] ? '1' : '0' ); ?>"
											data-transitions="<?php echo esc_attr( implode( ',', (array) $storesuite_status['transitions'] ) ); ?>">
											<td data-title="<?php esc_attr_e( 'Status', 'storesuite' ); ?>">
												<span class="storesuite-badge" style="background:<?php echo esc_attr( $storesuite_status['color'] ); ?>1a;color:<?php echo esc_attr( $storesuite_status['color'] ); ?>;"><?php echo esc_html( $storesuite_status['label'] ); ?></span>
											</td>
											<td data-title="<?php esc_attr_e( 'Slug', 'storesuite' ); ?>"><code><?php echo esc_html( $storesuite_status['slug'] ); ?></code></td>
											<td data-title="<?php esc_attr_e( 'Paid', 'storesuite' ); ?>"><?php echo $storesuite_status['is_paid'] ? '✓' : '—'; ?></td>
											<td data-title="<?php esc_attr_e( 'Reports', 'storesuite' ); ?>"><?php echo $storesuite_status['in_reports'] ? '✓' : '—'; ?></td>
											<td data-title="<?php esc_attr_e( 'Actions', 'storesuite' ); ?>">
												<div class="action-buttons">
													<button type="button" class="my-storesuite-button edit storesuite-edit-status"><?php esc_html_e( 'Edit', 'storesuite' ); ?></button>
													<button type="button" class="my-storesuite-button delete storesuite-delete-status"><?php esc_html_e( 'Delete', 'storesuite' ); ?></button>
												</div>
											</td>
										</tr>
									<?php endforeach; ?>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>

				<div class="col-md-5">
					<div class="storesuite-card storesuite-card-with-header">
						<h3 class="storesuite-card-title" id="storesuite-status-form-title"><?php esc_html_e( 'Add status', 'storesuite' ); ?></h3>
						<div class="storesuite-card-content">
							<form id="storesuite-status-form">
								<input type="hidden" name="slug" value="" />
								<div class="storesuite-form-group">
									<label for="storesuite-status-label"><?php esc_html_e( 'Name', 'storesuite' ); ?> <span class="req">*</span></label>
									<input type="text" class="storesuite-form-control" id="storesuite-status-label" name="label" required />
								</div>

								<div class="storesuite-form-group">
									<label for="storesuite-status-color"><?php esc_html_e( 'Colour', 'storesuite' ); ?></label>
									<div class="storesuite-color-presets">
										<?php foreach ( $storesuite_presets as $storesuite_preset ) : ?>
											<button type="button" class="storesuite-color-swatch" data-color="<?php echo esc_attr( $storesuite_preset ); ?>" style="background:<?php echo esc_attr( $storesuite_preset ); ?>;" aria-label="<?php echo esc_attr( $storesuite_preset ); ?>"></button>
										<?php endforeach; ?>
									</div>
									<input type="color" class="storesuite-form-control" id="storesuite-status-color" name="color" value="#2d5bdb" />
								</div>

								<div class="storesuite-form-switch storesuite-form-group">
									<input type="checkbox" class="storesuite-form-control" id="storesuite-status-paid" name="is_paid" value="1" />
									<label for="storesuite-status-paid"><?php esc_html_e( 'Counts as a paid status', 'storesuite' ); ?></label>
								</div>
								<div class="storesuite-form-switch storesuite-form-group">
									<input type="checkbox" class="storesuite-form-control" id="storesuite-status-reports" name="in_reports" value="1" />
									<label for="storesuite-status-reports"><?php esc_html_e( 'Include in analytics reports', 'storesuite' ); ?></label>
								</div>

								<div class="storesuite-form-group">
									<label><?php esc_html_e( 'Allowed transitions (leave empty for any)', 'storesuite' ); ?></label>
									<select multiple class="storesuite-form-control" name="transitions[]" id="storesuite-status-transitions" size="6">
										<?php foreach ( $storesuite_core as $storesuite_wc_slug => $storesuite_wc_label ) : ?>
											<option value="<?php echo esc_attr( preg_replace( '/^wc-/', '', $storesuite_wc_slug ) ); ?>"><?php echo esc_html( $storesuite_wc_label ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>

								<div class="storesuite-form-group text-md-end">
									<button type="button" class="my-storesuite-button my-storesuite-button-light" id="storesuite-status-reset"><?php esc_html_e( 'Clear', 'storesuite' ); ?></button>
									<button type="submit" class="my-storesuite-button"><?php esc_html_e( 'Save Status', 'storesuite' ); ?></button>
								</div>
							</form>
						</div>
					</div>
				</div>
			</div>
		</main>
	</div>
</div>
<?php do_action( 'storesuite_dashboard_wrapper_end' ); ?>
