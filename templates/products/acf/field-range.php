<?php
/**
 * StoreSuite product form: ACF `range` field input.
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

// Same fallbacks as ACF's own range field.
$min  = isset( $field['min'] ) && '' !== (string) $field['min'] ? (string) $field['min'] : '0';
$max  = isset( $field['max'] ) && '' !== (string) $field['max'] ? (string) $field['max'] : '100';
$step = isset( $field['step'] ) && '' !== (string) $field['step'] ? (string) $field['step'] : '1';

$value = is_scalar( $value ) && '' !== (string) $value ? (string) $value : $min;

$renderer->open_input_group( $field );
?>
<div class="storesuite-acf-range">
	<input
		type="range"
		class="storesuite-acf-range-input"
		id="<?php echo esc_attr( $input_id ); ?>"
		name="<?php echo esc_attr( $input_name ); ?>"
		value="<?php echo esc_attr( $value ); ?>"
		min="<?php echo esc_attr( $min ); ?>"
		max="<?php echo esc_attr( $max ); ?>"
		step="<?php echo esc_attr( $step ); ?>"
		<?php echo $is_required ? 'required' : ''; ?>
	>
	<output class="storesuite-acf-range-output" for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html( $value ); ?></output>
</div>
<?php
$renderer->close_input_group( $field );
