<?php
/**
 * StoreSuite order shipment tracking card (Advanced Shipment Tracking integration).
 *
 * Shown in the sidebar of the order details page and of the add / edit order form.
 *
 * @var WC_Order $order         Current order.
 * @var string   $items_html    Rendered shipment list (orders/tracking/items.php).
 * @var bool     $status_change Whether the add-tracking modal may offer "Mark order as".
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="storesuite-card storesuite-order-tracking">
	<h3 class="storesuite-card-title"><?php esc_html_e( 'Shipment Tracking', 'storesuite' ); ?></h3>
	<div class="storesuite-card-content">
		<ul class="storesuite-tracking-items" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
			<?php echo $items_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in orders/tracking/items.php. ?>
		</ul>
		<button type="button" class="my-storesuite-button my-storesuite-button-light storesuite-add-tracking" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>" data-order-number="<?php echo esc_attr( $order->get_order_number() ); ?>" data-status-change="<?php echo empty( $status_change ) ? '0' : '1'; ?>">
			<svg width="16" height="16" fill="currentColor" aria-hidden="true" focusable="false"><use href="#storesuite-icon-truck"></use></svg>
			<?php esc_html_e( 'Add Tracking Info', 'storesuite' ); ?>
		</button>
	</div>
</div>
