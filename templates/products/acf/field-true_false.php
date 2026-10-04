<?php
/**
 * StoreSuite product form: ACF `true_false` field input.
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

$checked = ! empty( $value ) && '0' !== (string) $value;
$message = ! empty( $field['message'] ) ? (string) $field['message'] : '';
?>
<?php // Sentinel: an unchecked switch submits '0' so the stored value is cleared. ?>
<input type="hidden" name="<?php echo esc_attr( $input_name ); ?>" value="0">
<div class="storesuite-form-group storesuite-form-switch storesuite-acf-switch">
	<input type="checkbox" class="storesuite-form-control" id="<?php echo esc_attr( $input_id ); ?>" name="<?php echo esc_attr( $input_name ); ?>" value="1" <?php checked( $checked ); ?>>
	<label for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html( $message ); ?></label>
</div>
