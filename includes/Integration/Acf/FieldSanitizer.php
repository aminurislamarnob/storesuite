<?php

namespace PluginizeLab\StoreSuite\Integration\Acf;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Per-type sanitisation of posted ACF values.
 *
 * Returns the value ACF should store, or null when nothing should be written
 * for the field (unsupported type, invalid input that must not clobber the
 * stored value, or a password left blank).
 */
class FieldSanitizer {

	/**
	 * Sanitise a posted value for a field.
	 *
	 * @param array $field ACF field array.
	 * @param mixed $raw   Raw (unslashed) posted value.
	 *
	 * @return mixed|null Sanitised value, or null to skip the field.
	 */
	public function sanitize( array $field, $raw ) {
		$type = isset( $field['type'] ) ? (string) $field['type'] : '';

		switch ( $type ) {
			case 'text':
				return $this->sanitize_text( $raw );
			case 'textarea':
				return $this->sanitize_textarea( $raw );
			case 'number':
			case 'range':
				return $this->sanitize_number( $raw );
			case 'email':
				return $this->sanitize_email( $raw );
			case 'url':
				return $this->sanitize_url( $raw );
			case 'password':
				return $this->sanitize_password( $raw );
			case 'color_picker':
				return $this->sanitize_color( $raw );
		}

		return null;
	}

	/**
	 * Coerce a posted value to a string; arrays and objects become ''.
	 *
	 * @param mixed $raw Raw posted value.
	 *
	 * @return string
	 */
	protected function to_string( $raw ): string {
		if ( is_array( $raw ) || is_object( $raw ) ) {
			return '';
		}

		return (string) $raw;
	}

	/**
	 * Sanitise a `text` field value.
	 *
	 * @param mixed $raw Raw posted value.
	 *
	 * @return string
	 */
	protected function sanitize_text( $raw ): string {
		return sanitize_text_field( $this->to_string( $raw ) );
	}

	/**
	 * Sanitise a `textarea` field value, keeping line breaks.
	 *
	 * @param mixed $raw Raw posted value.
	 *
	 * @return string
	 */
	protected function sanitize_textarea( $raw ): string {
		return sanitize_textarea_field( $this->to_string( $raw ) );
	}

	/**
	 * Sanitise a `number` / `range` field value.
	 *
	 * Mirrors ACF: thousands separators are stripped and blank is allowed.
	 * Anything non-numeric is rejected (null) so it never overwrites a value.
	 *
	 * @param mixed $raw Raw posted value.
	 *
	 * @return string|null
	 */
	protected function sanitize_number( $raw ) {
		$value = str_replace( ',', '', trim( $this->to_string( $raw ) ) );

		if ( '' === $value ) {
			return '';
		}

		return is_numeric( $value ) ? $value : null;
	}

	/**
	 * Sanitise an `email` field value.
	 *
	 * @param mixed $raw Raw posted value.
	 *
	 * @return string
	 */
	protected function sanitize_email( $raw ): string {
		return sanitize_email( $this->to_string( $raw ) );
	}

	/**
	 * Sanitise a `url` field value.
	 *
	 * @param mixed $raw Raw posted value.
	 *
	 * @return string
	 */
	protected function sanitize_url( $raw ): string {
		return esc_url_raw( trim( $this->to_string( $raw ) ) );
	}

	/**
	 * Sanitise a `password` field value.
	 *
	 * The form never prefills the stored secret, so an empty submit means
	 * "keep what is there" (null). A non-empty value is stored verbatim apart
	 * from invalid UTF-8 and line breaks: stripping tags or entities would
	 * silently change a secret.
	 *
	 * @param mixed $raw Raw posted value.
	 *
	 * @return string|null
	 */
	protected function sanitize_password( $raw ) {
		$value = $this->to_string( $raw );

		if ( '' === $value ) {
			return null;
		}

		$value = wp_check_invalid_utf8( $value );

		return str_replace( array( "\r", "\n" ), '', $value );
	}

	/**
	 * Sanitise a `color_picker` field value as a hex colour.
	 *
	 * Opacity is not supported, so rgba() strings are rejected (null) rather
	 * than stored.
	 *
	 * @param mixed $raw Raw posted value.
	 *
	 * @return string|null
	 */
	protected function sanitize_color( $raw ) {
		$value = trim( $this->to_string( $raw ) );

		if ( '' === $value ) {
			return '';
		}

		$hex = sanitize_hex_color( $value );

		return is_string( $hex ) && '' !== $hex ? strtolower( $hex ) : null;
	}
}
