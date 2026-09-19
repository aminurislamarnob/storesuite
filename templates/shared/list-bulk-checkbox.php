<?php
/**
 * Shared bulk-select checkbox markup for the StoreSuite list pages.
 *
 * @package StoreSuite
 *
 * @var bool   $is_all     Whether this is the header "select all" checkbox.
 * @var string $value      Row value (term/item ID) for per-row checkboxes.
 * @var string $input_name Optional input name for lists that POST a real form (e.g. bulk_product_ids[]).
 *                         Deliberately not called "name": storesuite_get_template_part() extracts args
 *                         into its own scope, and a "name" arg would clobber its $name parameter.
 * @var string $id         Optional input id (e.g. cb-select-all-products, cb-select-123).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$storesuite_cb_is_all = ! empty( $is_all );
$storesuite_cb_class  = $storesuite_cb_is_all ? 'storesuite-bulk-select-all' : 'storesuite-bulk-cb';
$storesuite_cb_value  = isset( $value ) ? (string) $value : '';
$storesuite_cb_name   = isset( $input_name ) ? (string) $input_name : '';
$storesuite_cb_id     = isset( $id ) ? (string) $id : '';
$storesuite_cb_label  = $storesuite_cb_is_all ? __( 'Select all', 'storesuite' ) : __( 'Select item', 'storesuite' );
?>
<label class="my-storesuite-checkbox">
	<input type="checkbox" class="my-storesuite-checkbox-input <?php echo esc_attr( $storesuite_cb_class ); ?>" aria-label="<?php echo esc_attr( $storesuite_cb_label ); ?>"<?php echo '' !== $storesuite_cb_name ? ' name="' . esc_attr( $storesuite_cb_name ) . '"' : ''; ?><?php echo '' !== $storesuite_cb_id ? ' id="' . esc_attr( $storesuite_cb_id ) . '"' : ''; ?><?php echo $storesuite_cb_is_all ? '' : ' value="' . esc_attr( $storesuite_cb_value ) . '"'; ?>>
	<span class="my-storesuite-checkbox-back"></span>
	<span class="my-storesuite-tick">
		<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-check" viewBox="0 0 16 16">
			<path d="M10.97 4.97a.75.75 0 0 1 1.07 1.05l-3.99 4.99a.75.75 0 0 1-1.08.02L4.324 8.384a.75.75 0 1 1 1.06-1.06l2.094 2.093 3.473-4.425z"/>
		</svg>
	</span>
</label>
