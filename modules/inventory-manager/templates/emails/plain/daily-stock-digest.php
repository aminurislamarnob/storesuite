<?php
/**
 * Daily low-stock digest email (plain text).
 *
 * Override by copying to yourtheme/storesuite/emails/plain/daily-stock-digest.php.
 *
 * @var array     $items              Queued items: name, sku, qty, level (keyed by product id).
 * @var string    $email_heading      Email heading.
 * @var string    $additional_content Additional content from settings.
 * @var \WC_Email $email              Email instance.
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo '= ' . esc_html( wp_strip_all_tags( $email_heading ) ) . " =\n\n";

esc_html_e( 'The following products went low on stock or out of stock since the previous digest:', 'storesuite' );
echo "\n\n";

foreach ( $items as $item ) {
	$storesuite_status = ( 'out' === ( $item['level'] ?? 'low' ) )
		? __( 'out of stock', 'storesuite' )
		: __( 'low on stock', 'storesuite' );

	printf(
		/* translators: 1: product name, 2: product SKU, 3: status phrase, 4: quantity */
		esc_html__( '- %1$s (%2$s) — %3$s, %4$s left', 'storesuite' ),
		esc_html( $item['name'] ?? '' ),
		esc_html( ( isset( $item['sku'] ) && '' !== $item['sku'] ) ? $item['sku'] : '—' ),
		esc_html( $storesuite_status ),
		esc_html( (string) (int) ( $item['qty'] ?? 0 ) )
	);
	echo "\n";
}

echo "\n----------------------------------------\n\n";

if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
	echo "\n\n----------------------------------------\n\n";
}

echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
