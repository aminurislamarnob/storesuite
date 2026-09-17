<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
$thumbnail    = $_product ? apply_filters( 'woocommerce_admin_order_item_thumbnail', $_product->get_image( 'thumbnail', array( 'title' => '' ), false ), $item_id, $item ) : '';
$is_visible   = $_product && $_product->is_visible();

/**
 * Filter the order item name.
 * @param string $item_name The order item's name.
 * @param WC_Order_Item $item The order item object.
 * @param bool $is_visible Item's product visibility in the catalog.
 */
$item_name = apply_filters( 'woocommerce_order_item_name', $item->get_name(), $item, $is_visible );
?>
<tr class="item <?php echo ! empty( $class ) ? esc_attr( $class ) : ''; ?>" data-order_item_id="<?php echo esc_attr( $item_id ); ?>">
    <td class="thumb" style="width: 10%">
        <?php echo '<div class="wc-order-item-thumbnail">' . wp_kses_post( $thumbnail ) . '</div>'; ?>
    </td>

    <td class="name" style="width: 65%">
        <?php if ( $_product ) : ?>
            <a target="_blank" href="<?php echo esc_url( get_permalink( $_product->get_id() ) ); ?>">
                <?php echo esc_html( $item_name ); ?>
            </a>
        <?php else : ?>
            <?php echo esc_html( $item_name ); ?>
        <?php endif; ?>

        <?php
        if ( $_product && $_product->get_sku() ) {
			echo '<div class="wc-order-item-sku"><strong>' . esc_html__( 'SKU:', 'storesuite' ) . '</strong> ' . esc_html( $_product->get_sku() ) . '</div>';
		}

		if ( $item->get_variation_id() ) {
			echo '<div class="wc-order-item-variation"><strong>' . esc_html__( 'Variation ID:', 'storesuite' ) . '</strong> ';
			if ( 'product_variation' === get_post_type( $item->get_variation_id() ) ) {
				echo esc_html( $item->get_variation_id() );
			} else {
				/* translators: %s: variation id */
				printf( esc_html__( '%s (No longer exists)', 'storesuite' ), esc_html( $item->get_variation_id() ) );
			}
			echo '</div>';
		}
        ?>
        <?php
        do_action( 'woocommerce_before_order_itemmeta', $item_id, $item, $_product );

        storesuite_get_template_part(
            'orders/html-order-item-meta', '', array(
                'item'     => $item,
            )
        );

        do_action( 'woocommerce_after_order_itemmeta', $item_id, $item, $_product );
        ?>
    </td>

    <?php do_action( 'woocommerce_admin_order_item_values', $_product, $item, absint( $item_id ) ); ?>

    <td style="width: 1%">
        <?php
        if ( isset( $item['qty'] ) ) {
            echo esc_html( $item['qty'] );
        }
        ?>
    </td>

    <td class="line_cost" style="width: 1%">
        <?php
        if ( isset( $item['line_total'] ) ) {
            if ( isset( $item['line_subtotal'] ) && $item['line_subtotal'] !== $item['line_total'] ) {
                echo wp_kses_post( '<del>' . wc_price( $item['line_subtotal'] ) . '</del> ' );
            }

            echo wp_kses_post( wc_price( $item['line_total'], [ 'currency' => $order->get_currency() ] ) );
        }
        ?>
    </td>
</tr>
