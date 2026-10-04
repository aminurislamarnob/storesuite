<?php
/**
 * StoreSuite product form: placeholder for ACF field types StoreSuite cannot edit.
 *
 * No input is rendered, so the stored value is never touched by a frontend save.
 *
 * @var array  $field ACF field array.
 * @var string $type  ACF field type.
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<p class="storesuite-acf-field-unsupported-note">
	<?php
	printf(
		/* translators: %s: ACF field type, e.g. "repeater". */
		esc_html__( 'The "%s" field type can\'t be edited here. Edit this field in the WordPress admin.', 'storesuite' ),
		esc_html( $type )
	);
	?>
</p>
