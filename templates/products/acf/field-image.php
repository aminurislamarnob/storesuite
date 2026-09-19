<?php
/**
 * StoreSuite product form: ACF `image` field input.
 *
 * Same drop-area markup as the featured image, driven by the generic
 * media picker in product-acf.js (data attributes, no fixed ids). The
 * hidden input always carries the key so removing the image saves as empty.
 *
 * @var array  $field       ACF field array.
 * @var string $input_name  Input name attribute.
 * @var string $input_id    Input id attribute.
 * @var mixed  $value       Stored attachment ID, null when none.
 * @var bool   $is_required Whether the field is required.
 * @var \PluginizeLab\StoreSuite\Integration\Acf\FieldRenderer $renderer Field renderer.
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$attachment_id = is_scalar( $value ) ? absint( $value ) : 0;
$preview_size  = ! empty( $field['preview_size'] ) ? (string) $field['preview_size'] : 'medium';
$preview_url   = $attachment_id && wp_attachment_is_image( $attachment_id ) ? wp_get_attachment_image_url( $attachment_id, $preview_size ) : '';
$mime_types    = $renderer->get_mime_types( ! empty( $field['mime_types'] ) ? (string) $field['mime_types'] : '' );

if ( ! $preview_url ) {
	$attachment_id = 0;
}
?>
<div class="storesuite-acf-image" data-storesuite-media-picker data-target="#<?php echo esc_attr( $input_id ); ?>" data-preview-size="<?php echo esc_attr( $preview_size ); ?>" data-mime-types="<?php echo esc_attr( implode( ',', $mime_types ) ); ?>" data-title="<?php echo esc_attr( sprintf( /* translators: %s: field label */ __( 'Select %s', 'storesuite' ), $field['label'] ) ); ?>">
	<input type="hidden" id="<?php echo esc_attr( $input_id ); ?>" name="<?php echo esc_attr( $input_name ); ?>" value="<?php echo esc_attr( $attachment_id ? $attachment_id : '' ); ?>">
	<div class="image-drop-container storesuite-media-picker-drop<?php echo $attachment_id ? ' image-drop-bg' : ''; ?>" role="button" tabindex="0">
		<div class="preview-image storesuite-media-picker-preview">
			<?php if ( $preview_url ) : ?>
				<img src="<?php echo esc_url( $preview_url ); ?>" alt="<?php echo esc_attr( $field['label'] ); ?>">
			<?php endif; ?>
		</div>
		<div class="image-drop-text">
			<i class="las la-image"></i>
			<span><?php $attachment_id ? esc_html_e( 'Remove Image', 'storesuite' ) : esc_html_e( 'Upload Image', 'storesuite' ); ?></span>
		</div>
	</div>
</div>
