<?php
/**
 * Idempotent seed data for the E2E suite, run via `wp eval-file`.
 *
 * Creates (once) a published simple product and a processing order that the
 * orders/products list screens can rely on. Everything is tagged "E2E Seed"
 * so it is recognizable in the dev site's data.
 *
 * @package StoreSuite
 */

$e2e_product_name = 'E2E Seed Product';
$e2e_product_id   = 0;

$e2e_existing = get_page_by_title( $e2e_product_name, OBJECT, 'product' );
if ( $e2e_existing instanceof WP_Post && 'publish' === $e2e_existing->post_status ) {
	$e2e_product_id = $e2e_existing->ID;
} else {
	$e2e_product = new WC_Product_Simple();
	$e2e_product->set_name( $e2e_product_name );
	$e2e_product->set_regular_price( '25' );
	$e2e_product->set_status( 'publish' );
	$e2e_product->set_stock_status( 'instock' );
	$e2e_product_id = $e2e_product->save();
	echo "Created seed product {$e2e_product_id}\n";
}

// The Inventory Manager stock list only shows stock-managed products.
$e2e_product = wc_get_product( $e2e_product_id );
if ( ! $e2e_product->get_manage_stock() ) {
	$e2e_product->set_manage_stock( true );
	$e2e_product->set_stock_quantity( 40 );
	$e2e_product->save();
	echo "Enabled stock management on seed product\n";
}

// One processing order for the seed product, owned by the E2E customer.
$e2e_orders = wc_get_orders(
	array(
		'limit'    => 1,
		'meta_key' => '_e2e_seed_order', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	)
);

if ( empty( $e2e_orders ) ) {
	$e2e_customer = get_user_by( 'login', 'e2e-customer' );

	$e2e_order = wc_create_order(
		array( 'customer_id' => $e2e_customer ? $e2e_customer->ID : 0 )
	);
	$e2e_order->add_product( wc_get_product( $e2e_product_id ), 2 );
	$e2e_order->set_billing_first_name( 'E2E' );
	$e2e_order->set_billing_last_name( 'Customer' );
	$e2e_order->set_billing_email( 'e2e-customer@example.test' );
	$e2e_order->calculate_totals();
	$e2e_order->set_status( 'processing' );
	$e2e_order->update_meta_data( '_e2e_seed_order', '1' );
	$e2e_order->save();
	echo 'Created seed order ' . $e2e_order->get_id() . "\n";
}
