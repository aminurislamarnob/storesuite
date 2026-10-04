<?php
/**
 * Helpers for tests that exercise the Advanced Shipment Tracking integration.
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test\Integration;

use PluginizeLab\StoreSuite\Integration\ShipmentTrackingIntegration;

/**
 * Skips tests when Advanced Shipment Tracking (AST) is not loaded and seeds
 * its carrier table, which the plugin normally fills from a remote sync.
 *
 * AST is loaded by tests/php/bootstrap.php when AST_DIR points at a checkout.
 */
trait ShipmentTrackingTestHelpers {

	/**
	 * Skip the test unless AST is loaded, then create its carrier table.
	 *
	 * @return void
	 */
	protected function require_ast() {
		if ( ! ShipmentTrackingIntegration::is_ast_active() ) {
			$this->markTestSkipped( 'Advanced Shipment Tracking is not installed (set AST_DIR).' );
		}

		\WC_Advanced_Shipment_Tracking_Install::get_instance()->create_shippment_tracking_table();
	}

	/**
	 * Insert a carrier row into AST's carrier table.
	 *
	 * @param string $slug    Carrier slug.
	 * @param string $name    Carrier name.
	 * @param bool   $enabled Whether the carrier is enabled in AST settings.
	 * @return void
	 */
	protected function insert_carrier( $slug, $name, $enabled = true ) {
		global $wpdb;

		$wpdb->insert(
			\WC_Advanced_Shipment_Tracking_Actions::get_instance()->table,
			array(
				'provider_name'         => $name,
				'ts_slug'               => $slug,
				'provider_url'          => 'https://carrier.test/track?n=%number%',
				'shipping_country'      => 'US',
				'shipping_country_name' => 'United States (US)',
				'shipping_default'      => 1,
				'display_in_order'      => $enabled ? 1 : 0,
			)
		);
	}

	/**
	 * Write one key into AST's general settings.
	 *
	 * @param string $key   Setting key, e.g. 'wc_ast_status_partial_shipped'.
	 * @param mixed  $value Setting value.
	 * @return void
	 */
	protected function set_ast_setting( $key, $value ) {
		update_ast_settings( 'ast_general_settings', $key, $value );
	}

	/**
	 * Dispatch the add-tracking endpoint.
	 *
	 * @param array $fields Request fields.
	 * @return array Decoded response.
	 */
	protected function add_tracking_ajax( array $fields ) {
		return $this->do_ajax( ShipmentTrackingIntegration::ADD_ACTION, $fields );
	}

	/**
	 * Dispatch the delete-tracking endpoint.
	 *
	 * @param array $fields Request fields.
	 * @return array Decoded response.
	 */
	protected function delete_tracking_ajax( array $fields ) {
		return $this->do_ajax( ShipmentTrackingIntegration::DELETE_ACTION, $fields );
	}
}
