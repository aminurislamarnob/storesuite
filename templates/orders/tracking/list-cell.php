<?php
/**
 * StoreSuite orders list tracking cell (Advanced Shipment Tracking integration).
 *
 * One block per shipment with the same details as the plugin's own wp-admin
 * orders column: carrier, tracking number, date shipped, who added it and
 * from where.
 *
 * @var array $items Formatted AST tracking items, each with `display_carrier`,
 *                   `display_url` and `display_meta`.
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

$items      = array_values( $items );
$more_count = count( $items ) - 1;
/* translators: %d: number of further shipments on the order. */
$more_label = sprintf( _n( 'Show %d more shipment', 'Show %d more shipments', $more_count, 'storesuite' ), $more_count );

foreach ( $items as $index => $item ) :
	// Only the first shipment shows up front; the rest sit in a collapsed group.
	if ( 1 === $index ) {
		// Inline display so the script can slide the group open and closed.
		echo '<div class="storesuite-tracking-cell-more" style="display:none">';
	}
	?>
	<div class="storesuite-tracking-cell-item">
		<strong class="storesuite-tracking-cell-carrier"><?php echo esc_html( $item['display_carrier'] ); ?></strong>
		<?php if ( $item['display_url'] ) : ?>
			<a href="<?php echo esc_url( $item['display_url'] ); ?>" class="storesuite-tracking-cell-number" target="_blank" rel="noopener noreferrer" title="<?php esc_attr_e( 'Track Shipment', 'storesuite' ); ?>"><?php echo esc_html( $item['tracking_number'] ); ?></a>
		<?php else : ?>
			<span class="storesuite-tracking-cell-number"><?php echo esc_html( $item['tracking_number'] ); ?></span>
		<?php endif; ?>
		<?php if ( $item['display_meta'] ) : ?>
			<span class="storesuite-tracking-cell-meta"><?php echo esc_html( $item['display_meta'] ); ?></span>
		<?php endif; ?>
	</div>
	<?php
endforeach;

if ( $more_count > 0 ) :
	?>
	</div>
	<button type="button" class="storesuite-tracking-cell-toggle" aria-expanded="false" data-more-label="<?php echo esc_attr( $more_label ); ?>" data-less-label="<?php esc_attr_e( 'Show less', 'storesuite' ); ?>"><?php echo esc_html( $more_label ); ?></button>
	<?php
endif;
