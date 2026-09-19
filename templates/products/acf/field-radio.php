<?php
/**
 * StoreSuite product form: ACF `radio` field input.
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
$layout   = isset( $field['layout'] ) && 'horizontal' === $field['layout'] ? 'horizontal' : 'vertical';
$index    = 0;
?>
<?php // Sentinel so "nothing selected" still submits the key (clears when allow_null is on). ?>
<input type="hidden" name="<?php echo esc_attr( $input_name ); ?>" value="">
<div class="storesuite-acf-choices storesuite-acf-choices-<?php echo esc_attr( $layout ); ?>" id="<?php echo esc_attr( $input_id ); ?>">
	<?php foreach ( $choices as $choice_value => $choice_label ) : ?>
		<?php $choice_id = $input_id . '-' . ( $index++ ); ?>
		<label class="storesuite-acf-choice" for="<?php echo esc_attr( $choice_id ); ?>">
			<input
				type="radio"
				id="<?php echo esc_attr( $choice_id ); ?>"
				name="<?php echo esc_attr( $input_name ); ?>"
				value="<?php echo esc_attr( $choice_value ); ?>"
				<?php checked( in_array( $choice_value, $selected, true ) ); ?>
				<?php echo $is_required ? 'required' : ''; ?>
			>
			<span><?php echo esc_html( $choice_label ); ?></span>
		</label>
	<?php endforeach; ?>
</div>
