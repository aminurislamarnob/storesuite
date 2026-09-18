<?php
/**
 * Low/out-of-stock alert email (plain text).
 *
 * Override by copying to yourtheme/storesuite/emails/plain/low-stock-alert.php.
 *
 * @var \WC_Product $product            Product that triggered the alert.
 * @var string      $level              low|out.
 * @var string      $email_heading      Email heading.
 * @var string      $additional_content Additional content from settings.
 * @var \WC_Email   $email              Email instance.
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo '= ' . esc_html( wp_strip_all_tags( $email_heading ) ) . " =\n\n";

$storesuite_status = 'out' === $level ? __( 'is out of stock', 'storesuite' ) : __( 'is low on stock', 'storesuite' );

printf(
	/* translators: 1: product name, 2: status phrase, 3: quantity */
	esc_html__( '%1$s %2$s (current quantity: %3$s).', 'storesuite' ),
	esc_html( $product->get_name() ),
	esc_html( $storesuite_status ),
	esc_html( (string) (int) $product->get_stock_quantity() )
);
echo "\n";

if ( $product->get_sku() ) {
	printf(
		/* translators: %s: product SKU */
		esc_html__( 'SKU: %s', 'storesuite' ),
		esc_html( $product->get_sku() )
	);
	echo "\n";
}

echo "\n----------------------------------------\n\n";

if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
	echo "\n\n----------------------------------------\n\n";
}

echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
