<?php
/**
 * Dashboard Header Template
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<header class="storesuite-dashboard-header">
	<div class="row align-items-center justify-content-between">
		<div class="col-auto">
			<span
				class="storesuite-sidebar-trigger"
				role="button"
				tabindex="0"
				aria-expanded="true"
				aria-controls="storesuite-dashboard-sidebar"
				aria-label="<?php esc_attr_e( 'Toggle navigation menu', 'storesuite' ); ?>"
			>
				<svg xmlns="http://www.w3.org/2000/svg" id="Layer_1" data-name="Layer 1" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="M24,3c0,.55-.45,1-1,1H1c-.55,0-1-.45-1-1s.45-1,1-1H23c.55,0,1,.45,1,1ZM7,20H1c-.55,0-1,.45-1,1s.45,1,1,1H7c.55,0,1-.45,1-1s-.45-1-1-1ZM15,11H1c-.55,0-1,.45-1,1s.45,1,1,1H15c.55,0,1-.45,1-1s-.45-1-1-1Z"/></svg>
			</span>
		</div>
		<div class="col-auto">
			<div class="storesuite-header-right">
				<a href="<?php echo esc_url( get_home_url() ); ?>" class="my-storesuite-button" target="_blank">
					<svg xmlns="http://www.w3.org/2000/svg" id="Layer_1" data-name="Layer 1" viewBox="0 0 24 24" width="24" height="24"><path d="M20,11v8c0,2.757-2.243,5-5,5H5c-2.757,0-5-2.243-5-5V9c0-2.757,2.243-5,5-5H13c.552,0,1,.448,1,1s-.448,1-1,1H5c-1.654,0-3,1.346-3,3v10c0,1.654,1.346,3,3,3H15c1.654,0,3-1.346,3-3V11c0-.552,.448-1,1-1s1,.448,1,1ZM21,0h-7c-.552,0-1,.448-1,1s.448,1,1,1h6.586L8.293,14.293c-.391,.391-.391,1.023,0,1.414,.195,.195,.451,.293,.707,.293s.512-.098,.707-.293L22,3.414v6.586c0,.552,.448,1,1,1s1-.448,1-1V3c0-1.654-1.346-3-3-3Z"/></svg>
					<span class="storesuite-button-label"><?php esc_html_e( 'Visit Home', 'storesuite' ); ?></span>
				</a>
				<?php $storesuite_shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : get_home_url(); ?>
				<a href="<?php echo esc_url( $storesuite_shop_url ); ?>" class="storesuite-header-icon-link" target="_blank" aria-label="<?php esc_attr_e( 'Visit Store', 'storesuite' ); ?>" title="<?php esc_attr_e( 'Visit Store', 'storesuite' ); ?>">
					<svg xmlns="http://www.w3.org/2000/svg" id="Outline" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="M24,10a.988.988,0,0,0-.024-.217l-1.3-5.868A4.968,4.968,0,0,0,17.792,0H6.208a4.968,4.968,0,0,0-4.88,3.915L.024,9.783A.988.988,0,0,0,0,10v1a3.984,3.984,0,0,0,1,2.643V19a5.006,5.006,0,0,0,5,5H18a5.006,5.006,0,0,0,5-5V13.643A3.984,3.984,0,0,0,24,11ZM2,10.109l1.28-5.76A2.982,2.982,0,0,1,6.208,2H7V5A1,1,0,0,0,9,5V2h6V5a1,1,0,0,0,2,0V2h.792A2.982,2.982,0,0,1,20.72,4.349L22,10.109V11a2,2,0,0,1-2,2H19a2,2,0,0,1-2-2,1,1,0,0,0-2,0,2,2,0,0,1-2,2H11a2,2,0,0,1-2-2,1,1,0,0,0-2,0,2,2,0,0,1-2,2H4a2,2,0,0,1-2-2ZM18,22H6a3,3,0,0,1-3-3V14.873A3.978,3.978,0,0,0,4,15H5a3.99,3.99,0,0,0,3-1.357A3.99,3.99,0,0,0,11,15h2a3.99,3.99,0,0,0,3-1.357A3.99,3.99,0,0,0,19,15h1a3.978,3.978,0,0,0,1-.127V19A3,3,0,0,1,18,22Z"/></svg>
				</a>
				<?php if ( current_user_can( 'manage_woocommerce' ) ) : ?>
					<?php
					$storesuite_notification_manager = new \PluginizeLab\StoreSuite\Notification\NotificationManager();
					$storesuite_unseen_count         = $storesuite_notification_manager->count_unseen( get_current_user_id() );
					$storesuite_recent_notifications = $storesuite_notification_manager->get_recent( get_current_user_id(), 5 );
					?>
					<div class="storesuite-dropdown storesuite-header-dropdown storesuite-notifications-dropdown">
						<span
							class="storesuite-dropdown-icon storesuite-notification-bell"
							role="button"
							tabindex="0"
							aria-label="<?php esc_attr_e( 'Notifications', 'storesuite' ); ?>"
							title="<?php esc_attr_e( 'Notifications', 'storesuite' ); ?>"
						>
							<svg width="24" height="24" fill="currentColor" aria-hidden="true" focusable="false"><use href="#storesuite-icon-bell"></use></svg>
							<span class="storesuite-notification-badge" <?php echo $storesuite_unseen_count > 0 ? '' : 'hidden'; ?>>
								<?php echo esc_html( $storesuite_unseen_count > 9 ? '9+' : (string) $storesuite_unseen_count ); ?>
							</span>
						</span>
						<div class="storesuite-dropdown-menu storesuite-notifications-menu">
							<div class="storesuite-notifications-menu-header">
								<span class="storesuite-notifications-menu-title"><?php esc_html_e( 'Notifications', 'storesuite' ); ?></span>
							</div>
							<ul class="storesuite-dropdown-list storesuite-notifications-menu-list">
								<?php if ( ! empty( $storesuite_recent_notifications ) ) : ?>
									<?php foreach ( $storesuite_recent_notifications as $storesuite_notification ) : ?>
										<li class="storesuite-notification-item<?php echo $storesuite_notification['is_seen'] ? '' : ' is-unseen'; ?>">
											<a href="<?php echo esc_url( $storesuite_notification['url'] ? $storesuite_notification['url'] : storesuite_get_navigation_url( 'notifications' ) ); ?>" class="dropdown-link">
												<span class="storesuite-notification-title"><?php echo esc_html( $storesuite_notification['title'] ); ?></span>
												<span class="storesuite-notification-message"><?php echo esc_html( $storesuite_notification['message'] ); ?></span>
												<span class="storesuite-notification-time"><?php echo esc_html( $storesuite_notification['time_ago'] ); ?></span>
											</a>
										</li>
									<?php endforeach; ?>
								<?php else : ?>
									<li class="storesuite-notifications-empty"><?php esc_html_e( 'No notifications yet.', 'storesuite' ); ?></li>
								<?php endif; ?>
							</ul>
							<div class="storesuite-notifications-menu-footer">
								<a href="<?php echo esc_url( storesuite_get_navigation_url( 'notifications' ) ); ?>"><?php esc_html_e( 'View all', 'storesuite' ); ?></a>
							</div>
						</div>
					</div>
				<?php endif; ?>
				<button type="button" class="storesuite-theme-toggle" aria-label="<?php esc_attr_e( 'Toggle dark mode', 'storesuite' ); ?>" title="<?php esc_attr_e( 'Toggle dark mode', 'storesuite' ); ?>" aria-pressed="false">
					<svg class="storesuite-theme-toggle-moon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><g><path d="M13.273,5.865l.831,.303c.328,.12,.586,.373,.707,.693l.307,.82c.146,.391,.519,.65,.936,.65h0c.417,0,.79-.258,.937-.649l.307-.818c.122-.322,.38-.576,.708-.695l.831-.303c.395-.144,.657-.52,.657-.939s-.263-.795-.657-.939l-.831-.303c-.328-.12-.586-.373-.707-.694l-.308-.82c-.146-.391-.52-.649-.937-.649h0c-.417,0-.79,.259-.936,.65l-.306,.817c-.122,.322-.38,.576-.708,.695l-.831,.303c-.395,.144-.657,.52-.657,.939s.263,.795,.657,.939Z"/><path d="M22.386,12.003c-.402-.168-.87-.056-1.151,.279-.928,1.106-2.507,1.621-4.968,1.621-3.814,0-6.179-1.03-6.179-6.158,0-2.397,.532-4.019,1.626-4.957,.33-.284,.439-.749,.269-1.15-.17-.4-.571-.646-1.015-.604C5.285,1.572,1,6.277,1,11.977c0,6.062,4.944,10.994,11.022,10.994,5.72,0,10.438-4.278,10.973-9.951,.042-.436-.205-.848-.609-1.017Zm-10.363,8.967c-4.975,0-9.022-4.035-9.022-8.994,0-3.827,2.362-7.105,5.78-8.402-.464,1.134-.692,2.517-.692,4.17,0,7.312,4.668,8.158,8.179,8.158,1.216,0,2.761-.094,4.177-.673-1.306,3.396-4.588,5.74-8.421,5.74Z"/></g><g><circle cx="18.49" cy="11.349" r="1"/><circle cx="13.99" cy="10.766" r="1"/></g></svg>
					<svg class="storesuite-theme-toggle-sun" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="M12,17c-2.76,0-5-2.24-5-5s2.24-5,5-5,5,2.24,5,5-2.24,5-5,5Zm0-8c-1.65,0-3,1.35-3,3s1.35,3,3,3,3-1.35,3-3-1.35-3-3-3Zm1-5V1c0-.55-.45-1-1-1s-1,.45-1,1v3c0,.55,.45,1,1,1s1-.45,1-1Zm0,19v-3c0-.55-.45-1-1-1s-1,.45-1,1v3c0,.55,.45,1,1,1s1-.45,1-1ZM5,12c0-.55-.45-1-1-1H1c-.55,0-1,.45-1,1s.45,1,1,1h3c.55,0,1-.45,1-1Zm19,0c0-.55-.45-1-1-1h-3c-.55,0-1,.45-1,1s.45,1,1,1h3c.55,0,1-.45,1-1ZM6.71,6.71c.39-.39,.39-1.02,0-1.41l-2-2c-.39-.39-1.02-.39-1.41,0s-.39,1.02,0,1.41l2,2c.2,.2,.45,.29,.71,.29s.51-.1,.71-.29Zm14,14c.39-.39,.39-1.02,0-1.41l-2-2c-.39-.39-1.02-.39-1.41,0s-.39,1.02,0,1.41l2,2c.2,.2,.45,.29,.71,.29s.51-.1,.71-.29Zm-16,0l2-2c.39-.39,.39-1.02,0-1.41s-1.02-.39-1.41,0l-2,2c-.39,.39-.39,1.02,0,1.41,.2,.2,.45,.29,.71,.29s.51-.1,.71-.29ZM18.71,6.71l2-2c.39-.39,.39-1.02,0-1.41s-1.02-.39-1.41,0l-2,2c-.39,.39-.39,1.02,0,1.41,.2,.2,.45,.29,.71,.29s.51-.1,.71-.29Z"/></svg>
				</button>
				<div class="storesuite-dropdown storesuite-header-dropdown">
					<span class="storesuite-dropdown-icon">
						<?php
						if ( is_user_logged_in() ) {
							$avatar_url = get_avatar_url( get_current_user_id() );
							echo '<img src="' . esc_url( $avatar_url ) . '" />';
						}
						?>
					</span>
					<div class="storesuite-dropdown-menu">
						<?php $storesuite_current_user = wp_get_current_user(); ?>
						<div class="storesuite-dropdown-user">
							<span class="storesuite-dropdown-user-avatar">
								<?php echo get_avatar( get_current_user_id(), 80 ); ?>
							</span>
							<span class="storesuite-dropdown-user-meta">
								<span class="storesuite-dropdown-user-name"><?php echo esc_html( $storesuite_current_user->display_name ); ?></span>
								<span class="storesuite-dropdown-user-email"><?php echo esc_html( $storesuite_current_user->user_email ); ?></span>
							</span>
						</div>
						<ul class="storesuite-dropdown-list">
							<li>
								<a href="<?php echo esc_url( storesuite_get_navigation_url( 'edit-account-details' ) ); ?>" class="dropdown-link">
									<svg xmlns="http://www.w3.org/2000/svg" id="Layer_1" data-name="Layer 1" viewBox="0 0 24 24" width="24" height="24"><path d="M15,6c0-3.309-2.691-6-6-6S3,2.691,3,6s2.691,6,6,6,6-2.691,6-6Zm-6,4c-2.206,0-4-1.794-4-4s1.794-4,4-4,4,1.794,4,4-1.794,4-4,4Zm-.008,4.938c.068,.548-.32,1.047-.869,1.116-3.491,.436-6.124,3.421-6.124,6.946,0,.552-.448,1-1,1s-1-.448-1-1c0-4.531,3.386-8.37,7.876-8.93,.542-.069,1.047,.32,1.116,.869Zm13.704,4.195l-.974-.562c.166-.497,.278-1.019,.278-1.572s-.111-1.075-.278-1.572l.974-.562c.478-.276,.642-.888,.366-1.366-.277-.479-.887-.644-1.366-.366l-.973,.562c-.705-.794-1.644-1.375-2.723-1.594v-1.101c0-.552-.448-1-1-1s-1,.448-1,1v1.101c-1.079,.22-2.018,.801-2.723,1.594l-.973-.562c-.48-.277-1.09-.113-1.366,.366-.276,.479-.112,1.09,.366,1.366l.974,.562c-.166,.497-.278,1.019-.278,1.572s.111,1.075,.278,1.572l-.974,.562c-.478,.276-.642,.888-.366,1.366,.186,.321,.521,.5,.867,.5,.169,0,.341-.043,.499-.134l.973-.562c.705,.794,1.644,1.375,2.723,1.594v1.101c0,.552,.448,1,1,1s1-.448,1-1v-1.101c1.079-.22,2.018-.801,2.723-1.594l.973,.562c.158,.091,.33,.134,.499,.134,.346,0,.682-.179,.867-.5,.276-.479,.112-1.09-.366-1.366Zm-5.696,.866c-1.654,0-3-1.346-3-3s1.346-3,3-3,3,1.346,3,3-1.346,3-3,3Z"/></svg>
									<?php echo esc_html__( 'Account', 'storesuite' ); ?>
								</a>
							</li>
							<li>
								<a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>" class="dropdown-link">
									<svg xmlns="http://www.w3.org/2000/svg" id="Outline" viewBox="0 0 24 24" width="24" height="24"><path d="M22.829,9.172,18.95,5.293a1,1,0,0,0-1.414,1.414l3.879,3.879a2.057,2.057,0,0,1,.3.39c-.015,0-.027-.008-.042-.008h0L5.989,11a1,1,0,0,0,0,2h0l15.678-.032c.028,0,.051-.014.078-.016a2,2,0,0,1-.334.462l-3.879,3.879a1,1,0,1,0,1.414,1.414l3.879-3.879a4,4,0,0,0,0-5.656Z"/><path d="M7,22H5a3,3,0,0,1-3-3V5A3,3,0,0,1,5,2H7A1,1,0,0,0,7,0H5A5.006,5.006,0,0,0,0,5V19a5.006,5.006,0,0,0,5,5H7a1,1,0,0,0,0-2Z"/></svg>
									<?php echo esc_html__( 'Logout', 'storesuite' ); ?>
								</a>
							</li>
						</ul>
					</div>
				</div>
			</div>
		</div>
	</div>
</header>