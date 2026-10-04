<?php
/**
 * StoreSuite order shipment list (Advanced Shipment Tracking integration).
 *
 * Rendered inside the order tracking card, and returned by the add / delete
 * tracking AJAX endpoints to refresh it in place. Shows the same details as
 * the plugin's own wp-admin order box: carrier, tracking number, date shipped,
 * who added it and from where.
 *
 * @var int   $order_id Order ID.
 * @var array $items    Formatted AST tracking items, each with `display_carrier`,
 *                      `display_url` and `display_meta`.
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $items ) ) {
	?>
	<li class="storesuite-tracking-item storesuite-tracking-empty"><?php esc_html_e( 'No tracking added yet.', 'storesuite' ); ?></li>
	<?php
	return;
}

foreach ( $items as $item ) :
	?>
	<li class="storesuite-tracking-item" data-tracking-id="<?php echo esc_attr( $item['tracking_id'] ); ?>">
		<div class="storesuite-tracking-content">
			<strong class="storesuite-tracking-carrier"><?php echo esc_html( $item['display_carrier'] ); ?></strong>
			-
			<?php if ( $item['display_url'] ) : ?>
				<a href="<?php echo esc_url( $item['display_url'] ); ?>" class="storesuite-tracking-link" target="_blank" rel="noopener noreferrer" title="<?php esc_attr_e( 'Track Shipment', 'storesuite' ); ?>"><?php echo esc_html( $item['tracking_number'] ); ?></a>
			<?php else : ?>
				<span class="storesuite-tracking-number"><?php echo esc_html( $item['tracking_number'] ); ?></span>
			<?php endif; ?>
		</div>
		<p class="storesuite-tracking-meta">
			<span><?php echo esc_html( $item['display_meta'] ); ?></span>
			<a href="#" class="storesuite-delete-tracking" role="button" data-order-id="<?php echo esc_attr( $order_id ); ?>" data-tracking-id="<?php echo esc_attr( $item['tracking_id'] ); ?>"><?php esc_html_e( 'Delete', 'storesuite' ); ?></a>
		</p>
	</li>
	<?php
endforeach;
