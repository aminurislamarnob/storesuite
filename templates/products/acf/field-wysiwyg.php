<?php
/**
 * StoreSuite product form: ACF `wysiwyg` field input.
 *
 * Renders wp_editor() with the product description editor's base settings.
 * ACF's toolbar (full/basic), tabs (all/visual/text) and media_upload
 * settings are mapped onto the editor. The editor id is derived from the
 * field key so several editors in one group stay independent.
 *
 * @var array  $field       ACF field array.
 * @var string $input_name  Input name attribute.
 * @var string $input_id    Input id attribute.
 * @var mixed  $value       Value to prefill, null when none.
 * @var bool   $is_required Whether the field is required.
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$toolbar     = isset( $field['toolbar'] ) ? (string) $field['toolbar'] : 'full';
$editor_tabs = isset( $field['tabs'] ) ? (string) $field['tabs'] : 'all';
$content     = is_scalar( $value ) ? (string) $value : '';

// wp_editor() ids must be lowercase alphanumeric / underscore.
$editor_id = strtolower( preg_replace( '/[^a-z0-9_]/i', '_', $input_id ) );

$settings = array(
	'wpautop'       => true,
	'media_buttons' => ! empty( $field['media_upload'] ),
	'textarea_name' => $input_name,
	'textarea_rows' => get_option( 'default_post_edit_rows', 10 ),
	'tabindex'      => '',
	'editor_css'    => '',
	'editor_class'  => 'storesuite-acf-wysiwyg-textarea',
	'teeny'         => 'basic' === $toolbar,
	'dfw'           => true,
	'tinymce'       => 'text' !== $editor_tabs,
	'quicktags'     => 'visual' !== $editor_tabs,
);
?>
<div class="storesuite-acf-wysiwyg" data-editor-id="<?php echo esc_attr( $editor_id ); ?>">
	<?php wp_editor( htmlspecialchars_decode( wp_kses_post( $content ), ENT_NOQUOTES ), $editor_id, $settings ); ?>
</div>
