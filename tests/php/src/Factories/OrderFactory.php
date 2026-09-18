<?php
/**
 * WooCommerce order factory for StoreSuite tests.
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test\Factories;

/**
 * Creates WooCommerce orders through the WC CRUD layer, so tests run against
 * whichever order storage (HPOS or posts) the WooCommerce install activated.
 */
class OrderFactory {

	/**
	 * Create and save an order.
	 *
	 * @param array $args {
	 *     Optional order arguments.
	 *
	 *     @type string           $status      Order status slug (unprefixed). Default 'processing'.
	 *     @type int              $customer_id Customer user ID. Default 0 (guest).
	 *     @type string           $created_via Sales channel, e.g. 'checkout' or 'pos'.
	 *     @type \WC_Product|null $product     Product to add as a line item.
	 *     @type int              $quantity    Line item quantity. Default 1.
	 * }
	 * @return \WC_Order Saved order.
	 */
	public function create( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'status'      => 'processing',
				'customer_id' => 0,
				'created_via' => '',
				'product'     => null,
				'quantity'    => 1,
			)
		);

		$order = wc_create_order(
			array(
				'status'      => $args['status'],
				'customer_id' => $args['customer_id'],
			)
		);

		if ( $args['product'] instanceof \WC_Product ) {
			$order->add_product( $args['product'], $args['quantity'] );
			$order->calculate_totals();
		}

		if ( '' !== $args['created_via'] ) {
			$order->set_created_via( $args['created_via'] );
		}

		$order->save();

		return $order;
	}
}
