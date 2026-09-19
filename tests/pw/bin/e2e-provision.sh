#!/usr/bin/env bash
#
# Provision a WordPress site for the StoreSuite Playwright suite.
#
# Requirements: a running WordPress install reachable by wp-cli, with the
# WooCommerce and StoreSuite plugin directories present under wp-content.
# Point WP_CLI at a wrapper for remote/wp-env use, e.g.:
#   WP_CLI="npx wp-env run cli wp" bash tests/pw/bin/e2e-provision.sh
set -euo pipefail

WP_CLI="${WP_CLI:-wp}"

$WP_CLI plugin activate woocommerce
$WP_CLI plugin activate storesuite

$WP_CLI rewrite structure '/%postname%/'
$WP_CLI rewrite flush

$WP_CLI user create manager manager@example.com --role=shop_manager --user_pass=password || true
$WP_CLI user create customer customer@example.com --role=customer --user_pass=password || true

$WP_CLI option update woocommerce_notify_low_stock_amount 3

# Wide per-page settings give the list specs headroom: every run adds an
# order, a coupon and a category, and specs that seed their own products
# add more still — the seeded rows must stay on page 1 of a persistent
# site for many runs.
$WP_CLI eval '
$settings = get_option( "storesuite_settings", array() );
foreach ( array( "order", "coupon", "category", "product", "inventory" ) as $list ) {
	$settings[ "storesuite_{$list}_per_page" ] = "50";
}
update_option( "storesuite_settings", $settings );
echo "Per-page settings widened.\n";
'

# Application passwords for the REST API specs. They only work over plain
# HTTP when the environment type is "local". Re-runnable: an existing
# e2e-api password is deleted first (its secret is only shown at creation).
$WP_CLI config set WP_ENVIRONMENT_TYPE local || true
for wp_user in admin manager; do
	uuid=$( $WP_CLI user application-password list "$wp_user" --fields=uuid,name --format=csv 2>/dev/null | awk -F, '$2 == "e2e-api" { print $1 }' )
	if [ -n "$uuid" ]; then
		$WP_CLI user application-password delete "$wp_user" "$uuid" || true
	fi
done
echo "ADMIN_APP_PASSWORD=$( $WP_CLI user application-password create admin e2e-api --porcelain )"
echo "MANAGER_APP_PASSWORD=$( $WP_CLI user application-password create manager e2e-api --porcelain )"
echo "Copy the two lines above into tests/pw/.env."

# Seed the products and coupon that utils/testData.ts describes.
$WP_CLI eval '
$specs = [
	[ "Blue Hoodie", "45", "HOOD-1", 20 ],
	[ "Red Cap", "15", "CAP-1", 2 ],
	[ "Green Scarf", "25", "SCARF-1", null ],
	[ "Black Mug", "12", "MUG-1", 8 ],
	[ "Sticker Pack", "5", "STICK-1", 1 ],
];
foreach ( $specs as $s ) {
	if ( wc_get_product_id_by_sku( $s[2] ) ) {
		continue;
	}
	$p = new WC_Product_Simple();
	$p->set_name( $s[0] );
	$p->set_regular_price( $s[1] );
	$p->set_sku( $s[2] );
	if ( null !== $s[3] ) {
		$p->set_manage_stock( true );
		$p->set_stock_quantity( $s[3] );
	}
	$p->save();
}
if ( ! wc_get_coupon_id_by_code( "welcome10" ) ) {
	$c = new WC_Coupon();
	$c->set_code( "welcome10" );
	$c->set_amount( 10 );
	$c->set_discount_type( "percent" );
	$c->save();
}
$orders = [
	[ "processing", "checkout", "Alice" ],
	[ "completed",  "checkout", "Bob" ],
	[ "on-hold",    "pos",      "Carol" ],
];
$existing = wc_get_orders( [ "billing_last_name" => "Tester", "limit" => 1, "return" => "ids" ] );
if ( empty( $existing ) ) {
	$product = wc_get_product( wc_get_product_id_by_sku( "HOOD-1" ) );
	foreach ( $orders as $o ) {
		$order = wc_create_order( [ "status" => $o[0], "customer_id" => 0, "created_via" => $o[1] ] );
		$order->add_product( $product, 1 );
		$order->set_billing_first_name( $o[2] );
		$order->set_billing_last_name( "Tester" );
		$order->calculate_totals();
		$order->save();
	}
}
echo "Seeded.\n";
'

echo "Provisioning complete."
