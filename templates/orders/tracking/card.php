<?php
/**
 * StoreSuite order details shipment tracking card (Advanced Shipment Tracking integration).
 *
 * @var WC_Order $order      Current order.
 * @var string   $items_html Rendered shipment list (orders/tracking/items.php).
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="storesuite-card storesuite-order-tracking">
	<div class="card-title-with-link">
		<h3 class="storesuite-card-title"><?php esc_html_e( 'Shipment Tracking', 'storesuite' ); ?></h3>
	</div>
	<div class="storesuite-card-content">
		<ul class="storesuite-tracking-items" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
			<?php echo $items_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in orders/tracking/items.php. ?>
		</ul>
		<button type="button" class="my-storesuite-button my-storesuite-button-light storesuite-add-tracking" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>" data-order-number="<?php echo esc_attr( $order->get_order_number() ); ?>">
			<svg width="16" height="16" fill="currentColor" aria-hidden="true" focusable="false"><use href="#storesuite-icon-truck"></use></svg>
			<?php esc_html_e( 'Add Tracking', 'storesuite' ); ?>
		</button>
	</div>
</div>
