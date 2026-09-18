<?php
/**
 * StoreSuite product form: ACF `password` field input.
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

// The stored secret is never prefilled; leaving the field blank keeps it.
$renderer->open_input_group( $field );
?>
<input
	type="password"
	class="storesuite-form-control"
	id="<?php echo esc_attr( $input_id ); ?>"
	name="<?php echo esc_attr( $input_name ); ?>"
	value=""
	autocomplete="new-password"
	<?php if ( ! empty( $field['placeholder'] ) ) : ?>
		placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"
	<?php endif; ?>
>
<?php
$renderer->close_input_group( $field );
?>
<small class="storesuite-form-text storesuite-acf-password-hint"><?php esc_html_e( 'Leave blank to keep the current value.', 'storesuite' ); ?></small>
