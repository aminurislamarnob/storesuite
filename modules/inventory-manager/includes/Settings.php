<?php
/**
 * Inventory Manager — settings helper.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Modules\InventoryManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owns the `storesuite_inventory_manager_settings` option.
 */
class Settings {

	const OPTION_KEY = 'storesuite_inventory_manager_settings';

	/**
	 * Virtual fields mirroring the enabled flag of the module's WooCommerce
	 * emails. They render as toggles on the module settings screen but are
	 * stored in the emails' own `woocommerce_{email_id}_settings` options
	 * (the source of truth), never in OPTION_KEY.
	 *
	 * @return array key => [option, default]
	 */
	private static function email_toggle_fields() {
		return array(
			'enable_low_stock_alert_email'   => array(
				'option'  => Emails\Manager::ALERT_SETTINGS_OPTION,
				'default' => true,
			),
			'enable_daily_stock_digest_email' => array(
				'option'  => Emails\Manager::DIGEST_SETTINGS_OPTION,
				'default' => false,
			),
		);
	}

	/**
	 * @return array
	 */
	public static function get_schema() {
		return array(
			// The two email toggles mirror (and write to) each email's
			// enabled flag under WooCommerce → Settings → Emails; recipients,
			// subjects and templates are managed there.
			'enable_low_stock_alert_email' => array(
				'type'        => 'toggle',
				'label'       => __( 'Immediate low-stock alert email', 'storesuite' ),
				'description' => __( 'Email a notification the moment a product drops to or below its low-stock threshold or runs out of stock.', 'storesuite' ),
				'default'     => true,
				'link'        => array(
					'url'   => admin_url( Emails\Manager::ALERT_SETTINGS_URL ),
					'label' => __( 'Configure this email in WooCommerce →', 'storesuite' ),
				),
			),
			'enable_daily_stock_digest_email' => array(
				'type'        => 'toggle',
				'label'       => __( 'Daily stock digest email', 'storesuite' ),
				'description' => __( 'Send one daily email listing every product that went low on stock or out of stock since the previous digest.', 'storesuite' ),
				'default'     => false,
				'link'        => array(
					'url'   => admin_url( Emails\Manager::DIGEST_SETTINGS_URL ),
					'label' => __( 'Configure this email in WooCommerce →', 'storesuite' ),
				),
			),
			'enable_stock_log'            => array(
				'type'        => 'toggle',
				'label'       => __( 'Record stock movement log', 'storesuite' ),
				'description' => __( 'Log every stock change with quantity, reason and who made it.', 'storesuite' ),
				'default'     => true,
			),
			'log_retention_days'          => array(
				'type'        => 'number',
				'label'       => __( 'Stock log retention (days)', 'storesuite' ),
				'description' => __( 'Older movement entries are purged daily. Use 0 to keep forever.', 'storesuite' ),
				'default'     => 180,
				'min'         => 0,
				'max'         => 3650,
			),
			'low_stock_default_threshold' => array(
				'type'        => 'number',
				'label'       => __( 'Default low-stock threshold', 'storesuite' ),
				'description' => __( 'Used when a product has no per-product low-stock amount set.', 'storesuite' ),
				'default'     => 2,
				'min'         => 0,
				'max'         => 10000,
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
		$values = array_merge( self::get_defaults(), $stored );

		// The email toggles always reflect the WooCommerce email settings.
		foreach ( self::email_toggle_fields() as $key => $email ) {
			$values[ $key ] = Emails\Manager::is_email_enabled( $email['option'], $email['default'] );
		}

		return $values;
	}

	/**
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function value( $key ) {
		$all = self::get();
		return $all[ $key ] ?? null;
	}

	/**
	 * @param array $data Raw input.
	 * @return array
	 */
	public static function update( array $data ) {
		$schema = self::get_schema();
		$clean  = self::get();

		$email_toggles = self::email_toggle_fields();

		foreach ( $schema as $key => $field ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}

			// Email toggles write through to the WooCommerce email settings
			// (the source of truth) instead of this module's option.
			if ( isset( $email_toggles[ $key ] ) ) {
				Emails\Manager::set_email_enabled( $email_toggles[ $key ]['option'], (bool) $data[ $key ] );
				continue;
			}

			$clean[ $key ] = self::sanitize_field( $data[ $key ], $field );
		}

		foreach ( array_keys( $email_toggles ) as $key ) {
			unset( $clean[ $key ] );
		}

		update_option( self::OPTION_KEY, $clean );

		// Keep the scheduled actions in step with the new settings.
		Emails\Manager::sync_digest_schedule();
		StockLog::sync_schedule();
		return self::get();
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
