<?php

namespace PluginizeLab\StoreSuite\Integration\Acf;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use DateTime;
use DateTimeZone;

/**
 * Converts between the ISO-style values the product form uses and ACF's
 * storage formats for the date / time field types.
 *
 * ACF stores `date_picker` as `Ymd`, `date_time_picker` as `Y-m-d H:i:s`
 * and `time_picker` as `H:i:s`; its update_value() does no normalisation,
 * so anything else would corrupt wp-admin's display. The form shows and
 * submits `Y-m-d`, `Y-m-d\TH:i` (datetime-local) and `H:i` (time) instead,
 * ignoring ACF's display_format / return_format.
 */
class DateFormats {

	/**
	 * Storage formats keyed by field type.
	 *
	 * @var array<string, string>
	 */
	const STORAGE = array(
		'date_picker'      => 'Ymd',
		'date_time_picker' => 'Y-m-d H:i:s',
		'time_picker'      => 'H:i:s',
	);

	/**
	 * Input (form) formats keyed by field type.
	 *
	 * @var array<string, string>
	 */
	const INPUT = array(
		'date_picker'      => 'Y-m-d',
		'date_time_picker' => 'Y-m-d\TH:i',
		'time_picker'      => 'H:i',
	);

	/**
	 * Formats accepted from the form, per type, most specific first.
	 *
	 * @var array<string, string[]>
	 */
	const ACCEPTED_INPUT = array(
		'date_picker'      => array( 'Y-m-d' ),
		'date_time_picker' => array( 'Y-m-d\TH:i:s', 'Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i' ),
		'time_picker'      => array( 'H:i:s', 'H:i' ),
	);

	/**
	 * Formats accepted from storage, per type, most specific first.
	 *
	 * The date picker also accepts `Y-m-d`, which ACF < 5 wrote.
	 *
	 * @var array<string, string[]>
	 */
	const ACCEPTED_STORAGE = array(
		'date_picker'      => array( 'Ymd', 'Y-m-d' ),
		'date_time_picker' => array( 'Y-m-d H:i:s', 'Y-m-d H:i' ),
		'time_picker'      => array( 'H:i:s', 'H:i' ),
	);

	/**
	 * Whether a field type is one of the date / time pickers.
	 *
	 * @param string $type ACF field type.
	 *
	 * @return bool
	 */
	public static function is_date_type( string $type ): bool {
		return isset( self::STORAGE[ $type ] );
	}

	/**
	 * Convert a submitted value to ACF's storage format.
	 *
	 * @param string $type  ACF field type.
	 * @param mixed  $input Submitted value.
	 *
	 * @return string Storage value, or '' when the input is empty or malformed.
	 */
	public static function to_storage( string $type, $input ): string {
		return self::convert( $input, self::ACCEPTED_INPUT[ $type ] ?? array(), self::STORAGE[ $type ] ?? '' );
	}

	/**
	 * Convert a stored value to the form's input format.
	 *
	 * @param string $type   ACF field type.
	 * @param mixed  $stored Stored value.
	 *
	 * @return string Input value, or '' when the stored value is empty or unreadable.
	 */
	public static function to_input( string $type, $stored ): string {
		return self::convert( $stored, self::ACCEPTED_STORAGE[ $type ] ?? array(), self::INPUT[ $type ] ?? '' );
	}

	/**
	 * The current site-local date / time in the form's input format.
	 *
	 * @param string $type ACF field type.
	 *
	 * @return string
	 */
	public static function now_for_input( string $type ): string {
		if ( ! isset( self::INPUT[ $type ] ) ) {
			return '';
		}

		return wp_date( self::INPUT[ $type ] );
	}

	/**
	 * Parse a value against a list of formats and re-format it.
	 *
	 * Parsing is strict: the value must round-trip through the format it
	 * matched, so "2024-02-30" is rejected rather than rolled into March.
	 *
	 * @param mixed    $value   Value to convert.
	 * @param string[] $formats Formats to try, most specific first.
	 * @param string   $output  Output format.
	 *
	 * @return string
	 */
	protected static function convert( $value, array $formats, string $output ): string {
		if ( '' === $output || ! is_scalar( $value ) ) {
			return '';
		}

		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		// Formats are evaluated in UTC: values carry no offset and we only reformat them.
		$timezone = new DateTimeZone( 'UTC' );

		foreach ( $formats as $format ) {
			$date = DateTime::createFromFormat( '!' . $format, $value, $timezone );

			if ( $date && $date->format( $format ) === $value ) {
				return $date->format( $output );
			}
		}

		return '';
	}
}
