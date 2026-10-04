<?php
/**
 * Customer CRM — settings helper.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Modules\Customers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owns the `storesuite_customers_settings` option. Schema-driven for the
 * generic Modules → Configure form.
 */
class Settings {

	const OPTION_KEY = 'storesuite_customers_settings';

	/**
	 * @return array
	 */
	public static function get_schema() {
		return array(
			'default_sort'         => array(
				'type'        => 'select',
				'label'       => __( 'Default sort', 'storesuite' ),
				'description' => __( 'How the customer list is ordered by default.', 'storesuite' ),
				'default'     => 'last_active',
				'options'     => array(
					'last_active' => __( 'Last active', 'storesuite' ),
					'total_spend' => __( 'Total spent', 'storesuite' ),
					'orders'      => __( 'Order count', 'storesuite' ),
				),
			),
			'per_page'             => array(
				'type'        => 'number',
				'label'       => __( 'Customers per page', 'storesuite' ),
				'description' => __( 'How many customers to show per page in the list.', 'storesuite' ),
				'default'     => 25,
				'min'         => 5,
				'max'         => 100,
			),
			'show_guest_customers' => array(
				'type'        => 'toggle',
				'label'       => __( 'Show guest customers', 'storesuite' ),
				'description' => __( 'Include customers who checked out without an account.', 'storesuite' ),
				'default'     => true,
			),
		);
	}

	/**
	 * @return array
	 */
	public static function get_defaults() {
		$defaults = array();
		foreach ( self::get_schema() as $key => $field ) {
			$defaults[ $key ] = $field['default'] ?? '';
		}
		return $defaults;
	}

	/**
	 * @return array
	 */
	public static function get() {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array_merge( self::get_defaults(), $stored );
	}

	/**
	 * @param array $data Raw input.
	 * @return array
	 */
	public static function update( array $data ) {
		$schema = self::get_schema();
		$clean  = self::get();

		foreach ( $schema as $key => $field ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}
			$clean[ $key ] = self::sanitize_field( $data[ $key ], $field );
		}

		update_option( self::OPTION_KEY, $clean );
		return $clean;
	}

	/**
	 * @param mixed $value Raw value.
	 * @param array $field Schema entry.
	 * @return mixed
	 */
	private static function sanitize_field( $value, array $field ) {
		$type = $field['type'] ?? 'text';

		switch ( $type ) {
			case 'toggle':
				return (bool) $value;
			case 'number':
				$value = (int) $value;
				if ( isset( $field['min'] ) ) {
					$value = max( (int) $field['min'], $value );
				}
				if ( isset( $field['max'] ) ) {
					$value = min( (int) $field['max'], $value );
				}
				return $value;
			case 'select':
				$options = (array) ( $field['options'] ?? array() );
				$value   = (string) $value;
				return array_key_exists( $value, $options ) ? $value : ( $field['default'] ?? '' );
			case 'text':
			default:
				return sanitize_text_field( (string) $value );
		}
	}
}
