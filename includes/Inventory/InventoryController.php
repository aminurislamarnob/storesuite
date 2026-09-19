<?php

namespace PluginizeLab\StoreSuite\Inventory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Inventory dashboard page controller.
 */
class InventoryController {

	/**
	 * The constructor.
	 */
	public function __construct() {
		add_action( 'storesuite_load_custom_template', array( $this, 'load_inventory_template' ) );
	}

	/**
	 * Render the inventory list page for the `inventory` endpoint.
	 *
	 * @param array $query_vars Current query vars.
	 * @return void
	 */
	public function load_inventory_template( $query_vars ) {
		if ( ! isset( $query_vars['inventory'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			storesuite_get_template_part( 'global/no-permission' );
			return;
		}

		storesuite_get_template_part(
			'inventory/inventory',
			'',
			array(
				'query_vars' => $query_vars,
			)
		);
	}
}
