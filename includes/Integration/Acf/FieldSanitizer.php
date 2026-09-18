<?php

namespace PluginizeLab\StoreSuite\Integration\Acf;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Per-type sanitisation of posted ACF values.
 *
 * Returns the value ACF should store, or null when the field type is not
 * supported so the caller drops it from the save set.
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
		}

		return null;
	}

	/**
	 * Sanitise a `text` field value.
	 *
	 * @param mixed $raw Raw posted value.
	 *
	 * @return string
	 */
	protected function sanitize_text( $raw ): string {
		if ( is_array( $raw ) || is_object( $raw ) ) {
			return '';
		}

		return sanitize_text_field( (string) $raw );
	}
}
