<?php
/**
 * StoreSuite Notifications Page
 *
 * Args passed from NotificationController: $notifications, $unseen_count,
 * $total_items, $total_pages, $current_page, $per_page, $query_vars.
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
			<?php if ( ! empty( $notifications ) ) : ?>
				<div class="storesuite-card storesuite-card-with-header storesuite-mb-24 storesuite-notifications-card">
					<h3 class="storesuite-card-title">
						<?php esc_html_e( 'All Notifications', 'storesuite' ); ?>
						<span class="storesuite-notifications-actions">
							<?php if ( $unseen_count > 0 ) : ?>
								<button type="button" class="my-storesuite-button my-storesuite-button-light storesuite-notifications-mark-all">
									<?php esc_html_e( 'Mark all as read', 'storesuite' ); ?>
								</button>
							<?php endif; ?>
							<button type="button" class="my-storesuite-button my-storesuite-button-light storesuite-notifications-clear-all">
								<?php esc_html_e( 'Clear all', 'storesuite' ); ?>
							</button>
						</span>
					</h3>
					<div class="storesuite-card-content">
						<ul class="storesuite-notifications-list">
							<?php foreach ( $notifications as $notification ) : ?>
								<li class="storesuite-notification-item<?php echo $notification['is_seen'] ? '' : ' is-unseen'; ?>">
									<span class="storesuite-notification-icon storesuite-notification-icon-<?php echo esc_attr( $notification['type'] ); ?>" aria-hidden="true">
										<svg width="18" height="18" fill="currentColor" aria-hidden="true" focusable="false"><use href="#storesuite-icon-bell"></use></svg>
									</span>
									<span class="storesuite-notification-body">
										<span class="storesuite-notification-title"><?php echo esc_html( $notification['title'] ); ?></span>
										<span class="storesuite-notification-message">
											<?php if ( ! empty( $notification['url'] ) ) : ?>
												<a href="<?php echo esc_url( $notification['url'] ); ?>"><?php echo esc_html( $notification['message'] ); ?></a>
											<?php else : ?>
												<?php echo esc_html( $notification['message'] ); ?>
											<?php endif; ?>
										</span>
										<span class="storesuite-notification-time"><?php echo esc_html( $notification['time_ago'] ); ?></span>
									</span>
									<?php if ( ! $notification['is_seen'] ) : ?>
										<span class="storesuite-notification-unseen-dot" title="<?php esc_attr_e( 'Unread', 'storesuite' ); ?>"></span>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				</div>
				<?php
				if ( $total_pages > 1 ) {
					storesuite_get_template_part(
						'pagination',
						'',
						array(
							'total_items'  => $total_items,
							'total_pages'  => $total_pages,
							'current_page' => $current_page,
							'per_page'     => $per_page,
						)
					);
				}
				?>
			<?php else : ?>
				<?php
				storesuite_get_template_part(
					'not-found',
					'',
					array(
						'title' => esc_html__( 'No notifications yet!', 'storesuite' ),
						'desc'  => esc_html__( 'New orders, customer registrations, and product reviews will show up here.', 'storesuite' ),
					)
				);
				?>
			<?php endif; ?>
		</main>
		<?php do_action( 'storesuite_dashboard_content_after' ); ?>
	</div>
</div>
<?php do_action( 'storesuite_dashboard_wrapper_end' ); ?>
