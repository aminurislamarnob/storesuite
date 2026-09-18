<?php
/**
 * Staff Manager — dashboard landing page.
 *
 * Rendered by `Module::load_template()` when the
 * `/storesuite-dashboard/staff/` endpoint is requested. Follows the same
 * full-page shell (sidebar + main content) as the core dashboard templates.
 *
 * Available in scope:
 *   $screen   array Values from ScreenController::get().
 *   $settings array Values from Settings::get().
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$storesuite_screen   = isset( $screen ) && is_array( $screen ) ? $screen : array();
$storesuite_settings = isset( $settings ) && is_array( $settings ) ? $settings : array();

$storesuite_heading = isset( $storesuite_screen['landing_heading'] ) && '' !== $storesuite_screen['landing_heading']
	? $storesuite_screen['landing_heading']
	: __( 'Staff', 'storesuite' );

$storesuite_welcome = isset( $storesuite_settings['welcome_message'] ) ? $storesuite_settings['welcome_message'] : '';

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
			<div class="storesuite-table-header-part">
				<div class="row">
					<div class="col-md-12">
						<h2 class="storesuite-page-title"><?php echo esc_html( $storesuite_heading ); ?></h2>
					</div>
				</div>
			</div>
			<?php if ( '' !== $storesuite_welcome ) : ?>
				<p class="storesuite-staff-welcome"><?php echo esc_html( $storesuite_welcome ); ?></p>
			<?php endif; ?>

			<?php
			/**
			 * Fires inside the Staff landing page main content, after the
			 * heading. A fuller module would render its staff table here.
			 *
			 * @param array $storesuite_screen   Screen settings.
			 * @param array $storesuite_settings Module settings.
			 */
			do_action( 'storesuite_staff_manager_landing_content', $storesuite_screen, $storesuite_settings );
			?>
		</main>
	</div>
</div>
<?php do_action( 'storesuite_dashboard_wrapper_end' ); ?>
