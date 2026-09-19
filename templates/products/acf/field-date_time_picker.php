<?php
/**
 * StoreSuite product form: ACF `date_time_picker` field input.
 *
 * Native datetime-local input; converted to ACF's Y-m-d H:i:s on save.
 *
 * @var array  $field        ACF field array.
 * @var string $input_name   Input name attribute.
 * @var string $input_id     Input id attribute.
 * @var mixed  $value        Stored value (ACF storage format), null when none.
 * @var bool   $is_required  Whether the field is required.
 * @var bool   $is_edit_mode Whether the edit form is rendered.
 *
 * @package StoreSuite
 */

use PluginizeLab\StoreSuite\Integration\Acf\DateFormats;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$input_value = DateFormats::to_input( 'date_time_picker', $value );

if ( '' === $input_value && ! $is_edit_mode && ! empty( $field['default_to_current_date'] ) ) {
	$input_value = DateFormats::now_for_input( 'date_time_picker' );
}
?>
<input
	type="datetime-local"
	class="storesuite-form-control"
	id="<?php echo esc_attr( $input_id ); ?>"
	name="<?php echo esc_attr( $input_name ); ?>"
	value="<?php echo esc_attr( $input_value ); ?>"
	<?php echo $is_required ? 'required' : ''; ?>
>
