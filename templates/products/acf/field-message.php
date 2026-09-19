<?php
/**
 * StoreSuite product form: ACF `message` layout field.
 *
 * Mirrors ACF's own rendering (texturize, optional HTML escaping, new-line
 * handling) and passes the result through post-safe KSES. Nothing is submitted.
 *
 * @var array $field ACF field array.
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$message = isset( $field['message'] ) ? wptexturize( (string) $field['message'] ) : '';

if ( ! empty( $field['esc_html'] ) ) {
	$message = esc_html( $message );
}

$new_lines = isset( $field['new_lines'] ) ? $field['new_lines'] : 'wpautop';

if ( 'wpautop' === $new_lines ) {
	$message = wpautop( $message );
} elseif ( 'br' === $new_lines ) {
	$message = nl2br( $message );
}
?>
<div class="storesuite-acf-message"><?php echo wp_kses_post( $message ); ?></div>
