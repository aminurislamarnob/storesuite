<?php
/**
 * Low/out-of-stock alert email (HTML).
 *
 * Override by copying to yourtheme/storesuite/emails/low-stock-alert.php.
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

$storesuite_text_align = is_rtl() ? 'right' : 'left';
$storesuite_is_out     = ( 'out' === $level );
$storesuite_badge_bg   = $storesuite_is_out ? '#fbeaea' : '#fcf9e8';
$storesuite_badge_text = $storesuite_is_out ? '#b32d2e' : '#8a6116';
$storesuite_status     = $storesuite_is_out ? __( 'Out of stock', 'storesuite' ) : __( 'Low on stock', 'storesuite' );
$storesuite_base_color = get_option( 'woocommerce_email_base_color', '#7f54b3' );
$storesuite_manage_url = function_exists( 'storesuite_get_navigation_url' )
	? storesuite_get_navigation_url( 'inventory' )
	: admin_url( 'edit.php?post_type=product' );

$storesuite_th_style = 'text-align: ' . $storesuite_text_align . '; width: 38%; padding: 12px 16px; border-bottom: 1px solid #e5e5e5; background: #f8f8f8; font-weight: 600; vertical-align: top;';
$storesuite_td_style = 'text-align: ' . $storesuite_text_align . '; padding: 12px 16px; border-bottom: 1px solid #e5e5e5; vertical-align: top;';

do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p style="margin: 0 0 20px;">
	<?php
	echo esc_html(
		$storesuite_is_out
			? __( 'A product in your store just ran out of stock and can no longer be purchased.', 'storesuite' )
			: __( 'A product in your store just dropped to or below its low-stock threshold.', 'storesuite' )
	);
	?>
</p>

<table cellspacing="0" cellpadding="0" border="0" style="width: 100%; border: 1px solid #e5e5e5; border-collapse: collapse; margin: 0 0 24px;">
	<tr>
		<th scope="row" style="<?php echo esc_attr( $storesuite_th_style ); ?>"><?php esc_html_e( 'Product', 'storesuite' ); ?></th>
		<td style="<?php echo esc_attr( $storesuite_td_style ); ?>"><strong><?php echo esc_html( $product->get_name() ); ?></strong></td>
	</tr>
	<?php if ( $product->get_sku() ) : ?>
		<tr>
			<th scope="row" style="<?php echo esc_attr( $storesuite_th_style ); ?>"><?php esc_html_e( 'SKU', 'storesuite' ); ?></th>
			<td style="<?php echo esc_attr( $storesuite_td_style ); ?>"><?php echo esc_html( $product->get_sku() ); ?></td>
		</tr>
	<?php endif; ?>
	<tr>
		<th scope="row" style="<?php echo esc_attr( $storesuite_th_style ); ?>"><?php esc_html_e( 'Status', 'storesuite' ); ?></th>
		<td style="<?php echo esc_attr( $storesuite_td_style ); ?>">
			<span style="display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; background: <?php echo esc_attr( $storesuite_badge_bg ); ?>; color: <?php echo esc_attr( $storesuite_badge_text ); ?>;">
				<?php echo esc_html( $storesuite_status ); ?>
			</span>
		</td>
	</tr>
	<tr>
		<th scope="row" style="<?php echo esc_attr( $storesuite_th_style ); ?> border-bottom: none;"><?php esc_html_e( 'Quantity left', 'storesuite' ); ?></th>
		<td style="<?php echo esc_attr( $storesuite_td_style ); ?> border-bottom: none;"><strong><?php echo esc_html( (string) (int) $product->get_stock_quantity() ); ?></strong></td>
	</tr>
</table>

<table cellspacing="0" cellpadding="0" border="0" style="margin: 0 0 24px;">
	<tr>
		<td style="padding: 0; background: transparent;">
			<a href="<?php echo esc_url( $storesuite_manage_url ); ?>" style="display: inline-block; padding: 7px 14px; font-size: 13px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 4px; background: <?php echo esc_attr( $storesuite_base_color ); ?>;">
				<?php esc_html_e( 'Manage inventory', 'storesuite' ); ?>
			</a>
		</td>
	</tr>
</table>

<?php
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
