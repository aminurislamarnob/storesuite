<?php
/**
 * Single product row for the StoreSuite products list (used on first paint and after quick edit save).
 *
 * @package StoreSuite
 *
 * @var int         $product_id Product post ID.
 * @var \WC_Product $product    Product object.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$storesuite_wfm_thumb = get_the_post_thumbnail_url( $product_id, 'thumbnail' );
if ( empty( $storesuite_wfm_thumb ) ) {
	$storesuite_wfm_thumb = wc_placeholder_img_src( 'thumbnail' );
}

$storesuite_can_inline_edit = current_user_can( 'edit_post', $product_id );
$storesuite_inline_status   = $storesuite_can_inline_edit && in_array( get_post_status( $product_id ), \PluginizeLab\StoreSuite\Product\ProductInlineEdit::EDITABLE_STATUSES, true );
$storesuite_inline_sku      = $storesuite_can_inline_edit;
$storesuite_inline_stock    = $storesuite_can_inline_edit && $product->managing_stock() && ! $product->is_type( 'variable' );
$storesuite_inline_price    = $storesuite_can_inline_edit && $product->is_type( \PluginizeLab\StoreSuite\Product\ProductInlineEdit::get_price_editable_types() );
$storesuite_inline_title    = __( 'Click to edit', 'storesuite' );
?>
<tr class="single-product-item storesuite-list-row" id="product-row-<?php echo esc_attr( (string) $product_id ); ?>">
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
	<td<?php storesuite_list_column_attrs( 'products', 'image' ); ?> data-title="<?php esc_attr_e( 'Image', 'storesuite' ); ?>">
		<img src="<?php echo esc_url( $storesuite_wfm_thumb ); ?>" class="my-storesuite-thumb" alt="<?php echo esc_attr( get_the_title( $product_id ) ); ?>">
	</td>
	<td<?php storesuite_list_column_attrs( 'products', 'name' ); ?> class="tbl-product-name" data-title="<?php esc_attr_e( 'Name', 'storesuite' ); ?>">
		<a href="<?php echo esc_url( sprintf( storesuite_get_navigation_url( 'edit-product' ) . '%s', $product_id ) ); ?>"><?php echo esc_html( get_the_title( $product_id ) ); ?></a>
	</td>
	<td<?php storesuite_list_column_attrs( 'products', 'category' ); ?> data-title="<?php esc_attr_e( 'Category', 'storesuite' ); ?>">
		<?php echo wp_kses_post( wc_get_product_category_list( $product_id, ', ', '', '' ) ); ?>
	</td>
	<td<?php storesuite_list_column_attrs( 'products', 'status' ); ?> data-title="<?php esc_attr_e( 'Status', 'storesuite' ); ?>"
		<?php if ( $storesuite_inline_status ) : ?>
		class="storesuite-inline-cell" data-inline-field="status" data-inline-value="<?php echo esc_attr( get_post_status( $product_id ) ); ?>" tabindex="0" title="<?php echo esc_attr( $storesuite_inline_title ); ?>"
		<?php endif; ?>
	>
		<span class="storesuite-badge storesuite-badge-<?php echo esc_attr( storesuite_get_post_status_class( get_post_status( $product_id ) ) ); ?>">
			<?php echo esc_html( storesuite_get_post_status( get_post_status( $product_id ) ) ); ?>
		</span>
	</td>
	<td<?php storesuite_list_column_attrs( 'products', 'sku' ); ?> data-title="<?php esc_attr_e( 'SKU', 'storesuite' ); ?>"
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
	<td<?php storesuite_list_column_attrs( 'products', 'stock' ); ?> data-title="<?php esc_attr_e( 'Stock', 'storesuite' ); ?>"
		<?php if ( $storesuite_inline_stock ) : ?>
		class="storesuite-inline-cell" data-inline-field="stock_quantity" data-inline-value="<?php echo esc_attr( (string) $product->get_stock_quantity() ); ?>" tabindex="0" title="<?php echo esc_attr( $storesuite_inline_title ); ?>"
		<?php endif; ?>
	>
		<?php
		$stock_count = '';
		if ( $product->managing_stock() ) {
			$stock_count = '(' . $product->get_stock_quantity() . ')';
		}

		if ( $product->is_on_backorder() ) {
			echo '<span class="storesuite-badge storesuite-badge-warning">' . esc_html__( 'On backorder', 'storesuite' ) . '</span>';
		} elseif ( $product->is_in_stock() ) {
			echo '<span class="storesuite-badge storesuite-badge-success">' . esc_html__( 'In stock', 'storesuite' ) . esc_html( $stock_count ) . '</span>';
		} else {
			echo '<span class="storesuite-badge storesuite-badge-danger">' . esc_html__( 'Out of stock', 'storesuite' ) . '</span>';
		}
		?>
	</td>
	<td<?php storesuite_list_column_attrs( 'products', 'price' ); ?> data-title="<?php esc_attr_e( 'Price', 'storesuite' ); ?>"
		<?php if ( $storesuite_inline_price ) : ?>
		class="storesuite-inline-cell" data-inline-field="price" data-regular-price="<?php echo esc_attr( $product->get_regular_price() ); ?>" data-sale-price="<?php echo esc_attr( $product->get_sale_price() ); ?>" tabindex="0" title="<?php echo esc_attr( $storesuite_inline_title ); ?>"
		<?php endif; ?>
	>
		<?php echo wp_kses_post( $product->get_price_html() ); ?>
	</td>
	<td<?php storesuite_list_column_attrs( 'products', 'type' ); ?> data-title="<?php esc_attr_e( 'Type', 'storesuite' ); ?>">
		<?php storesuite_get_product_type( $product ); ?>
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
				<?php if ( current_user_can( 'edit_post', $product_id ) ) : ?>
				<li>
					<button type="button" class="inline-button dropdown-link storesuite-item-inline-edit" data-product-id="<?php echo esc_attr( (string) $product_id ); ?>"><?php esc_html_e( 'Quick edit', 'storesuite' ); ?></button>
				</li>
				<?php endif; ?>
				<li>
					<button type="button" class="inline-button dropdown-link storesuite-duplicate-item" data-object="product" data-id="<?php echo esc_attr( (string) $product_id ); ?>"><?php esc_html_e( 'Duplicate', 'storesuite' ); ?></button>
				</li>
				<li>
					<button type="button" class="inline-button dropdown-link storesuite-delete-product" data-product-id="<?php echo esc_attr( (string) $product_id ); ?>"><?php esc_html_e( 'Delete', 'storesuite' ); ?></button>
				</li>
			</ul>
		</div>
	</td>
</tr>
