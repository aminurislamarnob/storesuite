<?php
/**
 * Daily low-stock digest email (HTML).
 *
 * Override by copying to yourtheme/storesuite/emails/daily-stock-digest.php.
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

$storesuite_text_align = is_rtl() ? 'right' : 'left';
$storesuite_base_color = get_option( 'woocommerce_email_base_color', '#7f54b3' );
$storesuite_manage_url = function_exists( 'storesuite_get_navigation_url' )
	? storesuite_get_navigation_url( 'inventory' )
	: admin_url( 'edit.php?post_type=product' );

$storesuite_out_count = count(
	array_filter(
		$items,
		static function ( $item ) {
			return 'out' === ( $item['level'] ?? 'low' );
		}
	)
);
$storesuite_low_count = count( $items ) - $storesuite_out_count;

$storesuite_head_style = 'text-align: ' . $storesuite_text_align . '; padding: 10px 16px; border-bottom: 2px solid #e5e5e5; background: #f8f8f8; font-weight: 600; font-size: 13px;';
$storesuite_cell_style = 'text-align: ' . $storesuite_text_align . '; padding: 10px 16px; border-bottom: 1px solid #efefef; font-size: 13px; vertical-align: middle;';

do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p style="margin: 0 0 20px;">
	<?php
	printf(
		/* translators: 1: number of low-stock products, 2: number of out-of-stock products */
		esc_html__( 'Since the previous digest, %1$s went low on stock and %2$s ran out of stock.', 'storesuite' ),
		esc_html(
			sprintf(
				/* translators: %d: number of products */
				_n( '%d product', '%d products', $storesuite_low_count, 'storesuite' ),
				$storesuite_low_count
			)
		),
		esc_html(
			sprintf(
				/* translators: %d: number of products */
				_n( '%d product', '%d products', $storesuite_out_count, 'storesuite' ),
				$storesuite_out_count
			)
		)
	);
	?>
</p>

<table cellspacing="0" cellpadding="0" border="0" style="width: 100%; border: 1px solid #e5e5e5; border-collapse: collapse; margin: 0 0 24px;">
	<thead>
		<tr>
			<th scope="col" style="<?php echo esc_attr( $storesuite_head_style ); ?>"><?php esc_html_e( 'Product', 'storesuite' ); ?></th>
			<th scope="col" style="<?php echo esc_attr( $storesuite_head_style ); ?>"><?php esc_html_e( 'SKU', 'storesuite' ); ?></th>
			<th scope="col" style="<?php echo esc_attr( $storesuite_head_style ); ?>"><?php esc_html_e( 'Status', 'storesuite' ); ?></th>
			<th scope="col" style="<?php echo esc_attr( $storesuite_head_style ); ?> text-align: center;"><?php esc_html_e( 'Qty left', 'storesuite' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $items as $item ) : ?>
			<?php
			$storesuite_is_out     = ( 'out' === ( $item['level'] ?? 'low' ) );
			$storesuite_badge_bg   = $storesuite_is_out ? '#fbeaea' : '#fcf9e8';
			$storesuite_badge_text = $storesuite_is_out ? '#b32d2e' : '#8a6116';
			?>
			<tr>
				<td style="<?php echo esc_attr( $storesuite_cell_style ); ?>"><strong><?php echo esc_html( $item['name'] ?? '' ); ?></strong></td>
				<td style="<?php echo esc_attr( $storesuite_cell_style ); ?>"><?php echo esc_html( ( isset( $item['sku'] ) && '' !== $item['sku'] ) ? $item['sku'] : '—' ); ?></td>
				<td style="<?php echo esc_attr( $storesuite_cell_style ); ?>">
					<span style="display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; white-space: nowrap; background: <?php echo esc_attr( $storesuite_badge_bg ); ?>; color: <?php echo esc_attr( $storesuite_badge_text ); ?>;">
						<?php echo esc_html( $storesuite_is_out ? __( 'Out of stock', 'storesuite' ) : __( 'Low on stock', 'storesuite' ) ); ?>
					</span>
				</td>
				<td style="<?php echo esc_attr( $storesuite_cell_style ); ?> text-align: center;"><strong><?php echo esc_html( (string) (int) ( $item['qty'] ?? 0 ) ); ?></strong></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
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
