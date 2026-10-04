<?php
/**
 * StoreSuite product form: ACF `password` field input.
 *
 * @var array  $field        ACF field array.
 * @var string $input_name   Input name attribute.
 * @var string $input_id     Input id attribute.
 * @var mixed  $value        Value to prefill, null when none.
 * @var bool   $is_required  Whether the field is required.
 * @var bool   $is_edit_mode Whether the edit form is rendered.
 * @var \PluginizeLab\StoreSuite\Integration\Acf\FieldRenderer $renderer Field renderer.
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The stored secret is never prefilled. On the edit form a blank submit keeps
// it, so the field is only marked required where there is nothing to keep.
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
	<?php echo $is_required && ! $is_edit_mode ? 'aria-required="true"' : ''; ?>
>
<?php
$renderer->close_input_group( $field );
?>
<?php if ( $is_edit_mode ) : ?>
	<small class="storesuite-form-text storesuite-acf-password-hint"><?php esc_html_e( 'Leave blank to keep the current value.', 'storesuite' ); ?></small>
<?php endif; ?>
