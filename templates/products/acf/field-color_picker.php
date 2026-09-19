<?php
/**
 * StoreSuite product form: ACF `color_picker` field input.
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

// Only a 6-digit hex value can drive the native picker; rgba() values (opacity
// is unsupported) or 3-digit shorthands are shown in the text box only.
$hex   = is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '';
$swatch = preg_match( '/^#[0-9a-f]{6}$/', $hex ) ? $hex : '';
?>
<div class="storesuite-acf-color">
	<input
		type="color"
		class="storesuite-acf-color-swatch"
		value="<?php echo esc_attr( '' !== $swatch ? $swatch : '#000000' ); ?>"
		aria-label="<?php echo esc_attr( sprintf( /* translators: %s: field label */ __( 'Pick a colour for %s', 'storesuite' ), $field['label'] ) ); ?>"
		data-target="#<?php echo esc_attr( $input_id ); ?>"
	>
	<input
		type="text"
		class="storesuite-form-control storesuite-acf-color-text"
		id="<?php echo esc_attr( $input_id ); ?>"
		name="<?php echo esc_attr( $input_name ); ?>"
		value="<?php echo esc_attr( $hex ); ?>"
		placeholder="#000000"
		pattern="^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$"
		autocomplete="off"
		spellcheck="false"
		<?php echo $is_required ? 'required' : ''; ?>
	>
</div>
