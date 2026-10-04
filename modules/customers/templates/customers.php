<?php
/**
 * Customer CRM — React mount point.
 *
 * Rendered by Module::load_template() on the `/customers/` endpoint. The CRM is
 * a WooCommerce-admin React app (see modules/customers/src/), so this template
 * only provides the dashboard shell + a mount node, mirroring
 * templates/analytics/analytics.php.
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'storesuite_dashboard_wrapper_start' );
?>
<div class="my-storesuite-container">
	<aside id="storesuite-dashboard-sidebar" class="my-storesuite-sidebar" role="navigation" aria-label="<?php esc_attr_e( 'Store dashboard navigation', 'storesuite' ); ?>">
		<?php do_action( 'storesuite_dashboard_navigation' ); ?>
	</aside>
	<div class="my-storesuite-wrapper">
		<?php do_action( 'storesuite_dashboard_content_before' ); ?>
		<main class="my-storesuite-page-content storesuite-main-dashboard">
			<?php do_action( 'storesuite_dashboard_before_main_content' ); ?>
			<?php do_action( 'storesuite_before_customers_app' ); ?>
			<div id="storesuite-customers-app" class="storesuite-customers-wrapper"></div>
			<?php do_action( 'storesuite_after_customers_app' ); ?>
		</main>
	</div>
</div>
<?php do_action( 'storesuite_dashboard_wrapper_end' ); ?>
