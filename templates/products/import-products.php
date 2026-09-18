<?php
/**
 * StoreSuite product import page.
 *
 * Renders the dashboard shell and dispatches WooCommerce's product CSV import wizard
 * (PluginizeLab\StoreSuite\Product\ProductImportWizard) inside it.
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
		<main class="my-storesuite-page-content">
			<?php do_action( 'storesuite_dashboard_before_main_content' ); ?>
			<?php
			storesuite_get_template_part(
				'dashboard-title',
				'',
				array(
					'page_title'            => __( 'Import products', 'storesuite' ),
					'parent_endpoint_title' => __( 'Products', 'storesuite' ),
					'parent_endpoint_url'   => storesuite_get_navigation_url( 'products' ),
				)
			);

			( new \PluginizeLab\StoreSuite\Product\ProductImportWizard() )->dispatch();
			?>
		</main>
	</div>
</div>
<?php do_action( 'storesuite_dashboard_wrapper_end' ); ?>
