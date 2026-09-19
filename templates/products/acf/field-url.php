<?php
/**
 * StoreSuite product form: ACF `url` field input.
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
?>
<input
	type="url"
	class="storesuite-form-control"
	id="<?php echo esc_attr( $input_id ); ?>"
	name="<?php echo esc_attr( $input_name ); ?>"
	value="<?php echo esc_attr( is_scalar( $value ) ? (string) $value : '' ); ?>"
	<?php if ( ! empty( $field['placeholder'] ) ) : ?>
		placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"
	<?php endif; ?>
	<?php echo $is_required ? 'required' : ''; ?>
>
