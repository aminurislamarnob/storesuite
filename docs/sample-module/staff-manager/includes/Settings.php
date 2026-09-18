<?php
/**
 * Staff Manager — settings helper.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Modules\StaffManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owns the `storesuite_staff_manager_settings` option.
 *
 * The schema is the single source of truth: it drives the React form, the
 * default values, and the sanitization in `update()`. Add a field to the
 * schema and it shows up in the admin automatically — no UI code changes.
 */
class Settings {

	const OPTION_KEY = 'storesuite_staff_manager_settings';

	/**
	 * Describe every configurable field.
	 *
	 * @return array
	 */
	public static function get_schema() {
		return array(
			'default_role'             => array(
				'type'        => 'select',
				'label'       => __( 'Default staff role', 'storesuite' ),
				'description' => __( 'Role assigned to new staff accounts when no role is specified.', 'storesuite' ),
				'default'     => 'shop_manager',
				'options'     => array(
					'shop_manager' => __( 'Shop Manager', 'storesuite' ),
					'editor'       => __( 'Editor', 'storesuite' ),
					'author'       => __( 'Author', 'storesuite' ),
				),
			),
			'allow_product_management' => array(
				'type'        => 'toggle',
				'label'       => __( 'Allow product management', 'storesuite' ),
				'description' => __( 'Staff accounts can create and edit products from the StoreSuite dashboard.', 'storesuite' ),
				'default'     => true,
			),
			'allow_order_management'   => array(
				'type'        => 'toggle',
				'label'       => __( 'Allow order management', 'storesuite' ),
				'description' => __( 'Staff accounts can view and update customer orders.', 'storesuite' ),
				'default'     => true,
			),
			'max_staff_accounts'       => array(
				'type'        => 'number',
				'label'       => __( 'Maximum staff accounts', 'storesuite' ),
				'description' => __( 'Hard cap on how many staff users can be created. Use 0 for unlimited.', 'storesuite' ),
				'default'     => 10,
				'min'         => 0,
				'max'         => 1000,
			),
			'welcome_message'          => array(
				'type'        => 'text',
				'label'       => __( 'Welcome message', 'storesuite' ),
				'description' => __( 'Shown on the staff landing page in the dashboard.', 'storesuite' ),
				'default'     => '',
			),
		);
	}

	/**
	 * Defaults pulled from the schema, keyed by field name.
	 *
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
	 * Current saved settings merged with defaults so callers always see the
	 * full set of fields.
	 *
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
	 * Sanitize input against the schema and persist. Unknown keys are
	 * silently dropped; type mismatches fall back to the default.
	 *
	 * @param array $data Raw input.
	 * @return array Canonical post-save values.
	 */
	public static function update( array $data ) {
		$schema  = self::get_schema();
		$current = self::get();
		$clean   = $current;

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
	 * Coerce a single value to the type declared in the schema.
	 *
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
