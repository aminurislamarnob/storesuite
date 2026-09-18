<?php
/**
 * StoreSuite product form: base wrapper shared by every ACF field type.
 *
 * Renders the grid column, label (with required marker), the type-specific
 * input template and the field instructions as help text.
 *
 * @var array                                                 $field        ACF field array.
 * @var string                                                $type         ACF field type.
 * @var bool                                                  $supported    Whether StoreSuite can edit this type.
 * @var string                                                $input_name   Input name attribute (storesuite_acf[<key>]).
 * @var string                                                $input_id     Input id attribute.
 * @var mixed                                                 $value        Value to prefill, null when none.
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
$instructions = ! empty( $field['instructions'] ) ? (string) $field['instructions'] : '';
$is_required  = ! empty( $field['required'] );
?>
<div class="<?php echo esc_attr( implode( ' ', $wrapper_classes ) ); ?>"<?php echo '' !== $wrapper_id ? ' id="' . esc_attr( $wrapper_id ) . '"' : ''; ?> data-key="<?php echo esc_attr( $field['key'] ); ?>" data-name="<?php echo esc_attr( isset( $field['name'] ) ? $field['name'] : '' ); ?>" data-type="<?php echo esc_attr( $type ); ?>">
	<div class="storesuite-form-group">
		<?php if ( ! empty( $field['label'] ) ) : ?>
			<label for="<?php echo esc_attr( $input_id ); ?>">
				<?php echo esc_html( $field['label'] ); ?>
				<?php if ( $is_required && $supported ) : ?>
					<span class="req">*</span>
				<?php endif; ?>
			</label>
		<?php endif; ?>
		<?php
		$renderer->render_input(
			array(
				'field'        => $field,
				'type'         => $type,
				'supported'    => $supported,
				'input_name'   => $input_name,
				'input_id'     => $input_id,
				'value'        => $value,
				'is_required'  => $is_required,
				'product_id'   => $product_id,
				'is_edit_mode' => $is_edit_mode,
			)
		);
		?>
		<?php if ( '' !== $instructions ) : ?>
			<small class="storesuite-form-text"><?php echo wp_kses_post( $instructions ); ?></small>
		<?php endif; ?>
	</div>
</div>
