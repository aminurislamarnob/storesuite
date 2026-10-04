<?php
/**
 * StoreSuite product form: base wrapper shared by every ACF field type.
 *
 * Renders the grid column, label (with required marker), the type-specific
 * input template and the field instructions as help text. The label is a
 * `<label for>` when the type renders a single control; choice groups, the
 * image picker and fields without an input get a `<span>` instead, which
 * their template references through aria-labelledby.
 *
 * @var array                                                 $field        ACF field array.
 * @var string                                                $type         ACF field type.
 * @var bool                                                  $supported    Whether StoreSuite can edit and save this type.
 * @var bool                                                  $layout       Whether the type is layout-only (message, separator).
 * @var string                                                $input_name   Input name attribute (storesuite_acf[<key>]).
 * @var string                                                $input_id     Input id attribute.
 * @var mixed                                                 $value        Value to prefill, null when none.
 * @var mixed                                                 $stored_value Stored value of an unsupported field (for conditions), else null.
 * @var array                                                 $conditions   Conditional logic groups, empty when none.
 * @var int                                                   $columns      Grid column span (1–12).
 * @var int                                                   $product_id   Product ID, 0 on the add form.
 * @var bool                                                  $is_edit_mode Whether the edit form is rendered.
 * @var \PluginizeLab\StoreSuite\Integration\Acf\FieldRenderer $renderer     Field renderer.
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wrapper_classes = array( 'col-md-' . $columns, 'storesuite-acf-field', 'storesuite-acf-field-' . $type );

if ( ! empty( $field['wrapper']['class'] ) ) {
	$wrapper_classes[] = $field['wrapper']['class'];
}

$wrapper_id   = ! empty( $field['wrapper']['id'] ) ? (string) $field['wrapper']['id'] : '';
$data_attrs   = '';

if ( ! empty( $conditions ) ) {
	$data_attrs .= ' data-conditions="' . esc_attr( wp_json_encode( $conditions ) ) . '"';
}

if ( null !== $stored_value ) {
	$data_attrs .= ' data-value="' . esc_attr( wp_json_encode( $stored_value ) ) . '"';
}
$instructions = ! empty( $field['instructions'] ) ? (string) $field['instructions'] : '';
$is_required  = ! empty( $field['required'] );

// Checked on submit by product-acf.js, which shows the message under the
// field. A blank password on the edit form keeps the stored value.
if ( $is_required && $supported && ! ( 'password' === $type && $is_edit_mode ) ) {
	$data_attrs .= ' data-required-message="' . esc_attr( $renderer->get_required_message( $field ) ) . '"';
}
$label_for    = $renderer->has_labelable_control( $type, $supported ) ? $input_id : '';
$label_id     = '' === $label_for && ! empty( $field['label'] ) ? $input_id . '-label' : '';
$label_tag    = '' !== $label_for ? 'label' : 'span';
?>
<div class="<?php echo esc_attr( implode( ' ', $wrapper_classes ) ); ?>"<?php echo '' !== $wrapper_id ? ' id="' . esc_attr( $wrapper_id ) . '"' : ''; ?> data-key="<?php echo esc_attr( $field['key'] ); ?>" data-name="<?php echo esc_attr( isset( $field['name'] ) ? $field['name'] : '' ); ?>" data-type="<?php echo esc_attr( $type ); ?>"<?php echo $data_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>>
	<div class="storesuite-form-group">
		<?php if ( ! empty( $field['label'] ) ) : ?>
			<<?php echo tag_escape( $label_tag ); ?> class="storesuite-acf-label"<?php echo '' !== $label_for ? ' for="' . esc_attr( $label_for ) . '"' : ' id="' . esc_attr( $label_id ) . '"'; ?>>
				<?php echo esc_html( $field['label'] ); ?>
				<?php if ( $is_required && $supported ) : ?>
					<span class="req">*</span>
				<?php endif; ?>
			</<?php echo tag_escape( $label_tag ); ?>>
		<?php endif; ?>
		<?php
		$renderer->render_input(
			array(
				'field'        => $field,
				'type'         => $type,
				'supported'    => $supported,
				'layout'       => $layout,
				'input_name'   => $input_name,
				'input_id'     => $input_id,
				'label_id'     => $label_id,
				'value'        => $value,
				'is_required'  => $is_required,
				'product_id'   => $product_id,
				'is_edit_mode' => $is_edit_mode,
				'renderer'     => $renderer,
			)
		);
		?>
		<?php if ( '' !== $instructions ) : ?>
			<small class="storesuite-form-text"><?php echo wp_kses_post( $instructions ); ?></small>
		<?php endif; ?>
	</div>
</div>
