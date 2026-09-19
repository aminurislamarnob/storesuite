<?php
/**
 * StoreSuite product form: ACF `textarea` field input.
 *
 * @var array  $field       ACF field array.
 * @var string $input_name  Input name attribute.
 * @var string $input_id    Input id attribute.
 * @var mixed  $value       Value to prefill, null when none.
 * @var bool   $is_required Whether the field is required.
 * @var \PluginizeLab\StoreSuite\Integration\Acf\FieldRenderer $renderer Field renderer.
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rows      = ! empty( $field['rows'] ) ? absint( $field['rows'] ) : 8;
$maxlength = ! empty( $field['maxlength'] ) ? absint( $field['maxlength'] ) : 0;
?>
<textarea
	class="storesuite-form-control"
	id="<?php echo esc_attr( $input_id ); ?>"
	name="<?php echo esc_attr( $input_name ); ?>"
	rows="<?php echo esc_attr( $rows ); ?>"
	<?php if ( ! empty( $field['placeholder'] ) ) : ?>
		placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"
	<?php endif; ?>
	<?php if ( $maxlength > 0 ) : ?>
		maxlength="<?php echo esc_attr( $maxlength ); ?>"
	<?php endif; ?>
	<?php echo $is_required ? 'required' : ''; ?>
><?php echo esc_textarea( is_scalar( $value ) ? (string) $value : '' ); ?></textarea>
