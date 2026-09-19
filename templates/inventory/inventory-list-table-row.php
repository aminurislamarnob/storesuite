<?php
/**
 * Single product row for the StoreSuite inventory list (used on first paint and after inline edit save).
 *
 * @package StoreSuite
 *
 * @var int         $product_id Product post ID.
 * @var \WC_Product $product    Product object.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$storesuite_inv_thumb = get_the_post_thumbnail_url( $product_id, 'thumbnail' );
if ( empty( $storesuite_inv_thumb ) ) {
	$storesuite_inv_thumb = wc_placeholder_img_src( 'thumbnail' );
}

$storesuite_can_inline_edit = current_user_can( 'edit_post', $product_id );
$storesuite_manages_stock   = $product->managing_stock() && ! $product->is_type( 'variable' );
$storesuite_inline_sku      = $storesuite_can_inline_edit;
$storesuite_inline_stock    = $storesuite_can_inline_edit && $storesuite_manages_stock;
$storesuite_inline_sstatus  = $storesuite_can_inline_edit && ! $product->managing_stock();
$storesuite_inline_price    = $storesuite_can_inline_edit && $product->is_type( \PluginizeLab\StoreSuite\Product\ProductInlineEdit::get_price_editable_types() );
$storesuite_inline_title    = __( 'Click to edit', 'storesuite' );

// Low-stock badge color for the quantity, against the resolved threshold.
$storesuite_stock_qty   = $product->get_stock_quantity();
$storesuite_threshold   = function_exists( 'wc_get_low_stock_amount' ) ? absint( wc_get_low_stock_amount( $product ) ) : absint( get_option( 'woocommerce_notify_low_stock_amount', 2 ) );
$storesuite_qty_variant = 'success';
if ( null !== $storesuite_stock_qty && $storesuite_stock_qty <= 0 ) {
	$storesuite_qty_variant = 'danger';
} elseif ( null !== $storesuite_stock_qty && $storesuite_stock_qty <= $storesuite_threshold ) {
	$storesuite_qty_variant = 'warning';
}
?>
<tr class="single-inventory-item storesuite-list-row" id="product-row-<?php echo esc_attr( (string) $product_id ); ?>">
	<td class="check-column">
		<?php
		storesuite_get_template_part(
			'shared/list-bulk-checkbox',
			'',
			array(
				'value' => (string) $product_id,
				'input_name' => 'bulk_product_ids[]',
				'id'    => 'cb-select-' . $product_id,
			)
		);
		?>
	</td>
	<td data-title="<?php esc_attr_e( 'Image', 'storesuite' ); ?>">
		<img src="<?php echo esc_url( $storesuite_inv_thumb ); ?>" class="my-storesuite-thumb" alt="<?php echo esc_attr( get_the_title( $product_id ) ); ?>">
	</td>
	<td class="tbl-product-name" data-title="<?php esc_attr_e( 'Name', 'storesuite' ); ?>">
		<a href="<?php echo esc_url( sprintf( storesuite_get_navigation_url( 'edit-product' ) . '%s', $product_id ) ); ?>"><?php echo esc_html( get_the_title( $product_id ) ); ?></a>
	</td>
	<td data-title="<?php esc_attr_e( 'SKU', 'storesuite' ); ?>"
		<?php if ( $storesuite_inline_sku ) : ?>
		class="storesuite-inline-cell" data-inline-field="sku" data-inline-value="<?php echo esc_attr( $product->get_sku() ); ?>" tabindex="0" title="<?php echo esc_attr( $storesuite_inline_title ); ?>"
		<?php endif; ?>
	>
		<?php
		if ( $product->get_sku() ) {
			echo esc_html( $product->get_sku() );
		} else {
			echo '<span class="no-sku">&ndash;</span>';
		}
		?>
	</td>
	<td data-title="<?php esc_attr_e( 'Stock Qty', 'storesuite' ); ?>"
		<?php if ( $storesuite_inline_stock ) : ?>
		class="storesuite-inline-cell" data-inline-field="stock_quantity" data-inline-value="<?php echo esc_attr( (string) $storesuite_stock_qty ); ?>" tabindex="0" title="<?php echo esc_attr( $storesuite_inline_title ); ?>"
		<?php endif; ?>
	>
		<?php if ( $storesuite_manages_stock && null !== $storesuite_stock_qty ) : ?>
			<span class="storesuite-badge storesuite-badge-<?php echo esc_attr( $storesuite_qty_variant ); ?>"><?php echo esc_html( (string) $storesuite_stock_qty ); ?></span>
		<?php else : ?>
			<span class="no-sku">&ndash;</span>
		<?php endif; ?>
	</td>
	<td data-title="<?php esc_attr_e( 'Stock Status', 'storesuite' ); ?>"
		<?php if ( $storesuite_inline_sstatus ) : ?>
		class="storesuite-inline-cell" data-inline-field="stock_status" data-inline-value="<?php echo esc_attr( $product->get_stock_status() ); ?>" tabindex="0" title="<?php echo esc_attr( $storesuite_inline_title ); ?>"
		<?php endif; ?>
	>
		<?php
		if ( $product->is_on_backorder() ) {
			echo '<span class="storesuite-badge storesuite-badge-warning">' . esc_html__( 'On backorder', 'storesuite' ) . '</span>';
		} elseif ( $product->is_in_stock() ) {
			echo '<span class="storesuite-badge storesuite-badge-success">' . esc_html__( 'In stock', 'storesuite' ) . '</span>';
		} else {
			echo '<span class="storesuite-badge storesuite-badge-danger">' . esc_html__( 'Out of stock', 'storesuite' ) . '</span>';
		}
		?>
	</td>
	<td data-title="<?php esc_attr_e( 'Backorders', 'storesuite' ); ?>">
		<?php
		$storesuite_backorder_options = wc_get_product_backorder_options();
		$storesuite_backorders        = $product->get_backorders();
		echo esc_html( isset( $storesuite_backorder_options[ $storesuite_backorders ] ) ? $storesuite_backorder_options[ $storesuite_backorders ] : $storesuite_backorders );
		?>
	</td>
	<td data-title="<?php esc_attr_e( 'Price', 'storesuite' ); ?>"
		<?php if ( $storesuite_inline_price ) : ?>
		class="storesuite-inline-cell" data-inline-field="price" data-regular-price="<?php echo esc_attr( $product->get_regular_price() ); ?>" data-sale-price="<?php echo esc_attr( $product->get_sale_price() ); ?>" tabindex="0" title="<?php echo esc_attr( $storesuite_inline_title ); ?>"
		<?php endif; ?>
	>
		<?php echo wp_kses_post( $product->get_price_html() ); ?>
	</td>
	<td class="text-right" data-title="<?php esc_attr_e( 'Actions', 'storesuite' ); ?>">
		<div class="storesuite-dropdown">
			<span class="storesuite-dropdown-icon">
				<svg width="20" height="20" fill="currentColor" aria-hidden="true" focusable="false"><use href="#storesuite-icon-three-dots"></use></svg>
			</span>
			<ul class="storesuite-dropdown-menu">
				<li>
					<a href="<?php echo esc_url( get_permalink( $product_id ) ); ?>" target="_blank" class="dropdown-link"><?php esc_html_e( 'View', 'storesuite' ); ?></a>
				</li>
				<li>
					<a href="<?php echo esc_url( sprintf( storesuite_get_navigation_url( 'edit-product' ) . '%s', $product_id ) ); ?>" class="dropdown-link"><?php esc_html_e( 'Edit', 'storesuite' ); ?></a>
				</li>
			</ul>
		</div>
	</td>
</tr>
