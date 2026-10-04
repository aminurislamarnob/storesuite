<?php
/**
 * StoreSuite add-tracking modal (Advanced Shipment Tracking integration).
 *
 * Shared by the orders list and the order details page; the opening button
 * supplies the order through its data attributes.
 *
 * @var array  $carriers Enabled carriers: country label => ( slug => name ).
 * @var array  $mark_as  "Mark order as" choices: value => ( label, checked ).
 * @var string $today    Today's date, Y-m-d.
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="storesuite-order-tracking-modal" class="storesuite-product-bulk-modal-overlay storesuite-product-quick-edit-modal storesuite-order-tracking-modal" hidden aria-hidden="true">
	<div
		class="storesuite-product-bulk-modal-dialog storesuite-product-quick-edit-modal-dialog"
		role="dialog"
		aria-modal="true"
		aria-labelledby="storesuite-order-tracking-title"
		tabindex="-1"
	>
		<div class="storesuite-product-bulk-modal-header">
			<h2 id="storesuite-order-tracking-title" class="storesuite-product-bulk-modal-title">
				<?php esc_html_e( 'Add Tracking', 'storesuite' ); ?>
			</h2>
			<button type="button" class="storesuite-product-bulk-modal-close storesuite-order-tracking-modal-close my-storesuite-button storesuite-button-ghost" aria-label="<?php esc_attr_e( 'Close', 'storesuite' ); ?>">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">
					<path d="M18,6h0a1,1,0,0,0-1.414,0L12,10.586,7.414,6A1,1,0,0,0,6,6H6a1,1,0,0,0,0,1.414L10.586,12,6,16.586A1,1,0,0,0,6,18h0a1,1,0,0,0,1.414,0L12,13.414,16.586,18A1,1,0,0,0,18,18h0a1,1,0,0,0,0-1.414L13.414,12,18,7.414A1,1,0,0,0,18,6Z"/>
				</svg>
			</button>
		</div>

		<form id="storesuite-order-tracking-form" class="storesuite-product-bulk-edit-scroll storesuite-product-quick-edit-modal-scroll" novalidate>
			<input type="hidden" name="order_id" id="storesuite-tracking-order-id" value="" />

			<div class="storesuite-form-group storesuite-mb-20">
				<label for="storesuite-tracking-number">
					<?php esc_html_e( 'Tracking number', 'storesuite' ); ?>
					<span class="req">*</span>
				</label>
				<input type="text" class="storesuite-form-control" id="storesuite-tracking-number" name="tracking_number" autocomplete="off" />
			</div>

			<div class="storesuite-form-group storesuite-mb-20">
				<label for="storesuite-tracking-provider">
					<?php esc_html_e( 'Carrier', 'storesuite' ); ?>
					<span class="req">*</span>
				</label>
				<select class="storesuite-form-control" id="storesuite-tracking-provider" name="tracking_provider">
					<option value=""><?php esc_html_e( 'Select a carrier', 'storesuite' ); ?></option>
					<?php foreach ( $carriers as $country => $country_carriers ) : ?>
						<optgroup label="<?php echo esc_attr( $country ); ?>">
							<?php foreach ( $country_carriers as $slug => $name ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option>
							<?php endforeach; ?>
						</optgroup>
					<?php endforeach; ?>
				</select>
				<?php if ( empty( $carriers ) ) : ?>
					<small class="storesuite-form-text"><?php esc_html_e( 'No carriers are enabled. An administrator can enable them in the Shipment Tracking settings.', 'storesuite' ); ?></small>
				<?php endif; ?>
			</div>

			<div class="storesuite-form-group storesuite-mb-20">
				<label for="storesuite-tracking-date"><?php esc_html_e( 'Date shipped', 'storesuite' ); ?></label>
				<input type="text" class="date-picker storesuite-form-control date-input" id="storesuite-tracking-date" name="date_shipped" maxlength="10" autocomplete="off" placeholder="<?php esc_attr_e( 'YYYY-MM-DD', 'storesuite' ); ?>" value="<?php echo esc_attr( $today ); ?>" data-default="<?php echo esc_attr( $today ); ?>" />
			</div>

			<fieldset class="storesuite-tracking-mark-as">
				<legend><?php esc_html_e( 'Mark order as', 'storesuite' ); ?></legend>
				<?php foreach ( $mark_as as $value => $option ) : ?>
					<div class="storesuite-form-group storesuite-form-switch">
						<input type="checkbox" class="storesuite-form-control" id="storesuite-tracking-mark-<?php echo esc_attr( $value ); ?>" name="mark_order_as" value="<?php echo esc_attr( $value ); ?>" <?php checked( $option['checked'] ); ?> data-default="<?php echo $option['checked'] ? '1' : '0'; ?>" />
						<label for="storesuite-tracking-mark-<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $option['label'] ); ?></label>
					</div>
				<?php endforeach; ?>
			</fieldset>

			<span class="storesuite-field-error storesuite-order-tracking-error" role="alert" hidden></span>
		</form>

		<div class="storesuite-product-bulk-modal-footer">
			<button type="button" class="my-storesuite-button storesuite-button-neutral-panel storesuite-order-tracking-modal-cancel">
				<?php esc_html_e( 'Cancel', 'storesuite' ); ?>
			</button>
			<div class="storesuite-product-quick-edit-update-wrap">
				<button type="submit" form="storesuite-order-tracking-form" class="my-storesuite-button storesuite-order-tracking-submit">
					<?php esc_html_e( 'Add Tracking', 'storesuite' ); ?>
				</button>
			</div>
		</div>
	</div>
</div>
