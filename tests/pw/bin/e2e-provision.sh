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
# Optional: the ACF integration spec skips itself when the plugin is absent.
$WP_CLI plugin activate advanced-custom-fields || echo "ACF not installed; skipping."

$WP_CLI rewrite structure '/%postname%/'
$WP_CLI rewrite flush

$WP_CLI user create manager manager@example.com --role=shop_manager --user_pass=password || true
$WP_CLI user create customer customer@example.com --role=customer --user_pass=password || true

$WP_CLI option update woocommerce_notify_low_stock_amount 3

# Wide per-page settings give the list specs headroom: every run adds an
# order, a coupon and a category, and the seeded rows must stay on page 1
# of a persistent site for many runs.
$WP_CLI eval '
$settings = get_option( "storesuite_settings", array() );
foreach ( array( "order", "coupon", "category" ) as $list ) {
	$settings[ "storesuite_{$list}_per_page" ] = "50";
}
update_option( "storesuite_settings", $settings );
echo "Per-page settings widened.\n";
'

# Application passwords for the REST API specs. They only work over plain
# HTTP when the environment type is "local". Re-runnable: an existing
# e2e-api password is deleted first (its secret is only shown at creation).
if ! $WP_CLI config has WP_ENVIRONMENT_TYPE; then
	$WP_CLI config set WP_ENVIRONMENT_TYPE local || true
fi
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

# ACF field group covering every supported type, one conditional rule and one
# unsupported type, plus a media-library image for the image picker. Stored in
# the database (not a local group) so it survives without a must-use plugin.
if $WP_CLI plugin is-active advanced-custom-fields 2>/dev/null; then
	$WP_CLI eval '
$choices = [ "red" => "Red", "green" => "Green", "blue" => "Blue" ];
$fields  = [
	[ "key" => "field_e2e_text",      "label" => "E2E Text",      "name" => "e2e_text",      "type" => "text", "required" => 1, "instructions" => "Required text." ],
	[ "key" => "field_e2e_textarea",  "label" => "E2E Textarea",  "name" => "e2e_textarea",  "type" => "textarea", "rows" => 3 ],
	[ "key" => "field_e2e_number",    "label" => "E2E Number",    "name" => "e2e_number",    "type" => "number", "min" => 0, "max" => 100, "prepend" => "#" ],
	[ "key" => "field_e2e_range",     "label" => "E2E Range",     "name" => "e2e_range",     "type" => "range", "min" => 0, "max" => 10 ],
	[ "key" => "field_e2e_email",     "label" => "E2E Email",     "name" => "e2e_email",     "type" => "email" ],
	[ "key" => "field_e2e_url",       "label" => "E2E URL",       "name" => "e2e_url",       "type" => "url" ],
	[ "key" => "field_e2e_password",  "label" => "E2E Password",  "name" => "e2e_password",  "type" => "password" ],
	[ "key" => "field_e2e_color",     "label" => "E2E Colour",    "name" => "e2e_color",     "type" => "color_picker" ],
	[ "key" => "field_e2e_select",    "label" => "E2E Select",    "name" => "e2e_select",    "type" => "select", "choices" => $choices, "allow_null" => 1 ],
	[ "key" => "field_e2e_multi",     "label" => "E2E Multi",     "name" => "e2e_multi",     "type" => "select", "choices" => $choices, "multiple" => 1 ],
	[ "key" => "field_e2e_checkbox",  "label" => "E2E Checkbox",  "name" => "e2e_checkbox",  "type" => "checkbox", "choices" => $choices, "layout" => "horizontal" ],
	[ "key" => "field_e2e_radio",     "label" => "E2E Radio",     "name" => "e2e_radio",     "type" => "radio", "choices" => $choices, "layout" => "horizontal" ],
	[ "key" => "field_e2e_buttons",   "label" => "E2E Buttons",   "name" => "e2e_buttons",   "type" => "button_group", "choices" => $choices ],
	[ "key" => "field_e2e_switch",    "label" => "E2E Switch",    "name" => "e2e_switch",    "type" => "true_false", "message" => "Enable the extra field" ],
	[ "key" => "field_e2e_dependent", "label" => "E2E Dependent", "name" => "e2e_dependent", "type" => "text",
		"conditional_logic" => [ [ [ "field" => "field_e2e_switch", "operator" => "==", "value" => "1" ] ] ] ],
	[ "key" => "field_e2e_date",      "label" => "E2E Date",      "name" => "e2e_date",      "type" => "date_picker" ],
	[ "key" => "field_e2e_datetime",  "label" => "E2E Datetime",  "name" => "e2e_datetime",  "type" => "date_time_picker" ],
	[ "key" => "field_e2e_time",      "label" => "E2E Time",      "name" => "e2e_time",      "type" => "time_picker" ],
	[ "key" => "field_e2e_wysiwyg",   "label" => "E2E Editor",    "name" => "e2e_wysiwyg",   "type" => "wysiwyg", "toolbar" => "basic", "tabs" => "all", "media_upload" => 0 ],
	[ "key" => "field_e2e_image",     "label" => "E2E Image",     "name" => "e2e_image",     "type" => "image", "preview_size" => "thumbnail" ],
	[ "key" => "field_e2e_message",   "label" => "E2E Message",   "name" => "",              "type" => "message", "message" => "Rendered message." ],
	[ "key" => "field_e2e_separator", "label" => "E2E Separator", "name" => "",              "type" => "separator" ],
	[ "key" => "field_e2e_related",   "label" => "E2E Related",   "name" => "e2e_related",   "type" => "relationship" ],
];
$group = [
	"key"      => "group_e2e_acf",
	"title"    => "E2E Custom Fields",
	"fields"   => $fields,
	"location" => [ [ [ "param" => "post_type", "operator" => "==", "value" => "product" ] ] ],
	"active"   => true,
];
// Re-runnable: importing without the ID would create a duplicate group.
$existing = acf_get_field_group( "group_e2e_acf" );
if ( $existing && ! empty( $existing["ID"] ) ) {
	$group["ID"] = $existing["ID"];
}
acf_import_field_group( $group );
echo "ACF field group seeded.\n";
'
	# A unique marker keeps stray PHP notices on stdout from being mistaken for a match.
	if ! $WP_CLI eval 'echo get_posts( [ "post_type" => "attachment", "title" => "E2E Image", "fields" => "ids" ] ) ? "E2E_IMAGE_PRESENT" : "E2E_IMAGE_MISSING";' | grep -q E2E_IMAGE_PRESENT; then
		$WP_CLI media import "$( cd "$( dirname "$0" )/../../.." && pwd )/assets/frontend/images/storesuite-logo-dark.png" --title="E2E Image"
	fi
fi

echo "Provisioning complete."
