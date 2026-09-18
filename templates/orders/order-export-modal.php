<?php
/**
 * Order export: modal form.
 *
 * Drives the batched order CSV exporter (PluginizeLab\StoreSuite\Order\OrderExporter)
 * through the StoreSuite dashboard. Field selectors are consumed by assets/frontend/order-export.js.
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_woocommerce' ) ) {
	return;
}

$storesuite_exporter       = new \PluginizeLab\StoreSuite\Order\OrderExporter();
$storesuite_export_columns = $storesuite_exporter->get_default_column_names();
$storesuite_order_statuses = wc_get_order_statuses();
?>
<div id="storesuite-order-export-modal" class="storesuite-product-bulk-modal-overlay" hidden aria-hidden="true">
	<div
		class="storesuite-product-bulk-modal-dialog"
		role="dialog"
		aria-modal="true"
		aria-labelledby="storesuite-order-export-title"
		tabindex="-1"
	>
		<div class="storesuite-product-bulk-modal-header">
			<h2 id="storesuite-order-export-title" class="storesuite-product-bulk-modal-title">
				<?php esc_html_e( 'Export orders to a CSV file', 'storesuite' ); ?>
			</h2>
			<button type="button" class="storesuite-product-bulk-modal-close my-storesuite-button storesuite-button-ghost" aria-label="<?php esc_attr_e( 'Close', 'storesuite' ); ?>">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">
					<path d="M18,6h0a1,1,0,0,0-1.414,0L12,10.586,7.414,6A1,1,0,0,0,6,6H6a1,1,0,0,0,0,1.414L10.586,12,6,16.586A1,1,0,0,0,6,18h0a1,1,0,0,0,1.414,0L12,13.414,16.586,18A1,1,0,0,0,18,18h0a1,1,0,0,0,0-1.414L13.414,12,18,7.414A1,1,0,0,0,18,6Z"/>
				</svg>
			</button>
		</div>
		<form id="storesuite-order-export-form" class="storesuite-product-bulk-edit-form">
			<input type="hidden" name="order_ids" id="storesuite-export-order-ids" value="" />

			<div class="storesuite-product-bulk-edit-scroll">
				<div class="storesuite-export-bulk-notice" hidden></div>

				<p class="storesuite-product-export-intro">
					<?php esc_html_e( 'This tool generates a CSV file with one row per order. Line items are listed in the Items column.', 'storesuite' ); ?>
				</p>

				<div class="storesuite-form-group">
					<label for="storesuite-order-export-columns" class="storesuite-form-label"><?php esc_html_e( 'Which columns should be exported?', 'storesuite' ); ?></label>
					<select name="export_columns" id="storesuite-order-export-columns" class="storesuite-form-control storesuite-select2 storesuite-export-columns" multiple data-placeholder="<?php esc_attr_e( 'Export all columns', 'storesuite' ); ?>">
						<?php foreach ( $storesuite_export_columns as $storesuite_column_id => $storesuite_column_name ) : ?>
							<option value="<?php echo esc_attr( $storesuite_column_id ); ?>"><?php echo esc_html( $storesuite_column_name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="storesuite-form-group storesuite-export-statuses-row">
					<label for="storesuite-order-export-statuses" class="storesuite-form-label"><?php esc_html_e( 'Which order statuses should be exported?', 'storesuite' ); ?></label>
					<select name="export_statuses" id="storesuite-order-export-statuses" class="storesuite-form-control storesuite-select2 storesuite-export-statuses" multiple data-placeholder="<?php esc_attr_e( 'Export all statuses', 'storesuite' ); ?>">
						<?php foreach ( $storesuite_order_statuses as $storesuite_status_key => $storesuite_status_label ) : ?>
							<option value="<?php echo esc_attr( preg_replace( '/^wc-/', '', $storesuite_status_key ) ); ?>"><?php echo esc_html( $storesuite_status_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="storesuite-form-group storesuite-export-dates-row">
					<label class="storesuite-form-label"><?php esc_html_e( 'Created between', 'storesuite' ); ?></label>
					<div class="storesuite-form-range">
						<input type="date" name="date_from" id="storesuite-order-export-date-from" class="storesuite-form-control" aria-label="<?php esc_attr_e( 'From date', 'storesuite' ); ?>" />
						<span class="storesuite-form-range-sep" aria-hidden="true">–</span>
						<input type="date" name="date_to" id="storesuite-order-export-date-to" class="storesuite-form-control" aria-label="<?php esc_attr_e( 'To date', 'storesuite' ); ?>" />
					</div>
				</div>

				<progress class="storesuite-export-progress" max="100" value="0"></progress>
			</div>

			<div class="storesuite-product-bulk-modal-footer">
				<button type="button" class="my-storesuite-button storesuite-button-neutral-panel storesuite-product-bulk-modal-cancel">
					<?php esc_html_e( 'Cancel', 'storesuite' ); ?>
				</button>
				<input type="submit" class="my-storesuite-button storesuite-export-submit" value="<?php esc_attr_e( 'Generate CSV', 'storesuite' ); ?>" />
			</div>
		</form>
	</div>
</div>
