<?php
/**
 * StoreSuite orders list tracking cell (Advanced Shipment Tracking integration).
 *
 * @var array $items Formatted AST tracking items.
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $items ) ) {
	echo '&ndash;';
	return;
}

foreach ( $items as $item ) :
	$carrier = ! empty( $item['formatted_tracking_provider'] ) ? $item['formatted_tracking_provider'] : ( isset( $item['tracking_provider'] ) ? $item['tracking_provider'] : '' );
	$url     = ! empty( $item['ast_tracking_link'] ) ? $item['ast_tracking_link'] : ( isset( $item['formatted_tracking_link'] ) ? $item['formatted_tracking_link'] : '' );
	?>
	<span class="storesuite-tracking-cell-item">
		<span class="storesuite-tracking-cell-carrier"><?php echo esc_html( $carrier ); ?></span>
		<?php if ( $url ) : ?>
			<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $item['tracking_number'] ); ?></a>
		<?php else : ?>
			<span><?php echo esc_html( $item['tracking_number'] ); ?></span>
		<?php endif; ?>
	</span>
	<?php
endforeach;
