<?php
/**
 * StoreSuite product form: ACF `number` field input.
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

// Without a step the browser would reject decimals; ACF stores any numeric value.
$step = isset( $field['step'] ) && '' !== (string) $field['step'] ? (string) $field['step'] : 'any';

$renderer->open_input_group( $field );
?>
<input
	type="number"
	class="storesuite-form-control"
	id="<?php echo esc_attr( $input_id ); ?>"
	name="<?php echo esc_attr( $input_name ); ?>"
	value="<?php echo esc_attr( is_scalar( $value ) ? (string) $value : '' ); ?>"
	step="<?php echo esc_attr( $step ); ?>"
	<?php if ( isset( $field['min'] ) && '' !== (string) $field['min'] ) : ?>
		min="<?php echo esc_attr( $field['min'] ); ?>"
	<?php endif; ?>
	<?php if ( isset( $field['max'] ) && '' !== (string) $field['max'] ) : ?>
		max="<?php echo esc_attr( $field['max'] ); ?>"
	<?php endif; ?>
	<?php if ( ! empty( $field['placeholder'] ) ) : ?>
		placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"
	<?php endif; ?>
	<?php echo $is_required ? 'required' : ''; ?>
>
<?php
$renderer->close_input_group( $field );
