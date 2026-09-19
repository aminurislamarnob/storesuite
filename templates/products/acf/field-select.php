<?php
/**
 * StoreSuite product form: ACF `select` field input.
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

$choices    = $renderer->get_choices( $field );
$multiple   = ! empty( $field['multiple'] );
$allow_null = ! empty( $field['allow_null'] );
$selected   = $renderer->get_selected_values( $value );

if ( $multiple ) :
	// Sentinel so clearing every option still submits the key.
	?>
	<input type="hidden" name="<?php echo esc_attr( $input_name ); ?>" value="">
	<select
		class="storesuite-form-control storesuite-select2"
		id="<?php echo esc_attr( $input_id ); ?>"
		name="<?php echo esc_attr( $input_name ); ?>[]"
		multiple
		data-placeholder="<?php echo esc_attr( ! empty( $field['placeholder'] ) ? $field['placeholder'] : __( 'Select', 'storesuite' ) ); ?>"
		data-allow_clear="true"
		<?php echo $is_required ? 'required' : ''; ?>
	>
		<?php foreach ( $choices as $choice_value => $choice_label ) : ?>
			<option value="<?php echo esc_attr( $choice_value ); ?>" <?php selected( in_array( $choice_value, $selected, true ) ); ?>><?php echo esc_html( $choice_label ); ?></option>
		<?php endforeach; ?>
	</select>
	<?php
else :
	?>
	<select
		class="storesuite-form-control"
		id="<?php echo esc_attr( $input_id ); ?>"
		name="<?php echo esc_attr( $input_name ); ?>"
		<?php echo $is_required ? 'required' : ''; ?>
	>
		<?php if ( $allow_null ) : ?>
			<option value=""><?php echo esc_html( ! empty( $field['placeholder'] ) ? $field['placeholder'] : __( '— Select —', 'storesuite' ) ); ?></option>
		<?php endif; ?>
		<?php foreach ( $choices as $choice_value => $choice_label ) : ?>
			<option value="<?php echo esc_attr( $choice_value ); ?>" <?php selected( in_array( $choice_value, $selected, true ) ); ?>><?php echo esc_html( $choice_label ); ?></option>
		<?php endforeach; ?>
	</select>
	<?php
endif;
