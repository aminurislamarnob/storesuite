<?php
/**
 * StoreSuite product form: ACF `date_picker` field input.
 *
 * A jQuery UI datepicker (initialised in product-acf.js) showing and submitting Y-m-d.
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

$input_value = DateFormats::to_input( 'date_picker', $value );

if ( '' === $input_value && ! $is_edit_mode && ! empty( $field['default_to_current_date'] ) ) {
	$input_value = DateFormats::now_for_input( 'date_picker' );
}
?>
<input
	type="text"
	class="storesuite-form-control storesuite-acf-datepicker"
	id="<?php echo esc_attr( $input_id ); ?>"
	name="<?php echo esc_attr( $input_name ); ?>"
	value="<?php echo esc_attr( $input_value ); ?>"
	placeholder="YYYY-MM-DD"
	pattern="\d{4}-\d{2}-\d{2}"
	autocomplete="off"
	<?php echo $is_required ? 'required' : ''; ?>
>
