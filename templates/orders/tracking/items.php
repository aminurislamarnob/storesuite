<?php
/**
 * StoreSuite order shipment list (Advanced Shipment Tracking integration).
 *
 * Rendered inside the order details tracking card, and returned by the
 * add / delete tracking AJAX endpoints to refresh it in place.
 *
 * @var int   $order_id Order ID.
 * @var array $items    Formatted AST tracking items.
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
	$carrier = ! empty( $item['formatted_tracking_provider'] ) ? $item['formatted_tracking_provider'] : ( isset( $item['tracking_provider'] ) ? $item['tracking_provider'] : '' );
	$url     = ! empty( $item['ast_tracking_link'] ) ? $item['ast_tracking_link'] : ( isset( $item['formatted_tracking_link'] ) ? $item['formatted_tracking_link'] : '' );
	$shipped = ! empty( $item['date_shipped'] ) ? date_i18n( wc_date_format(), (int) $item['date_shipped'] ) : '';
	?>
	<li class="storesuite-tracking-item" data-tracking-id="<?php echo esc_attr( $item['tracking_id'] ); ?>">
		<?php if ( ! empty( $item['tracking_provider_image'] ) ) : ?>
			<img class="storesuite-tracking-logo" src="<?php echo esc_url( $item['tracking_provider_image'] ); ?>" alt="" width="36" height="36" loading="lazy" />
		<?php endif; ?>
		<div class="storesuite-tracking-details">
			<strong class="storesuite-tracking-carrier"><?php echo esc_html( $carrier ); ?></strong>
			<span class="storesuite-tracking-number"><?php echo esc_html( $item['tracking_number'] ); ?></span>
			<?php if ( $shipped ) : ?>
				<span class="storesuite-tracking-date">
					<?php
					/* translators: %s: date shipped. */
					echo esc_html( sprintf( __( 'Shipped on %s', 'storesuite' ), $shipped ) );
					?>
				</span>
			<?php endif; ?>
			<span class="storesuite-tracking-actions">
				<?php if ( $url ) : ?>
					<a href="<?php echo esc_url( $url ); ?>" class="storesuite-tracking-link" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Track', 'storesuite' ); ?></a>
				<?php endif; ?>
				<a href="#" class="storesuite-delete-tracking" role="button" data-order-id="<?php echo esc_attr( $order_id ); ?>" data-tracking-id="<?php echo esc_attr( $item['tracking_id'] ); ?>"><?php esc_html_e( 'Delete', 'storesuite' ); ?></a>
			</span>
		</div>
	</li>
	<?php
endforeach;
