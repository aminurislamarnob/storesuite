<?php
/**
 * StoreSuite product form: ACF `button_group` field input.
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

$choices  = $renderer->get_choices( $field );
$selected = $renderer->get_selected_values( $value );
$index    = 0;
?>
<?php // Sentinel so "nothing selected" still submits the key (clears when allow_null is on). ?>
<input type="hidden" name="<?php echo esc_attr( $input_name ); ?>" value="">
<div class="storesuite-acf-button-group" id="<?php echo esc_attr( $input_id ); ?>" role="radiogroup">
	<?php foreach ( $choices as $choice_value => $choice_label ) : ?>
		<?php $choice_id = $input_id . '-' . ( $index++ ); ?>
		<input
			type="radio"
			class="storesuite-acf-button-group-input"
			id="<?php echo esc_attr( $choice_id ); ?>"
			name="<?php echo esc_attr( $input_name ); ?>"
			value="<?php echo esc_attr( $choice_value ); ?>"
			<?php checked( in_array( $choice_value, $selected, true ) ); ?>
			<?php echo $is_required ? 'required' : ''; ?>
		>
		<label class="storesuite-acf-button-group-option" for="<?php echo esc_attr( $choice_id ); ?>"><?php echo esc_html( $choice_label ); ?></label>
	<?php endforeach; ?>
</div>
