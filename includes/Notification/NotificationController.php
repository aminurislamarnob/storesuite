<?php

namespace PluginizeLab\StoreSuite\Notification;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads the frontend notifications page template.
 */
class NotificationController {

	/**
	 * The constructor.
	 */
	public function __construct() {
		add_action( 'storesuite_load_notifications_template', array( $this, 'load_notifications_template' ) );
	}

	/**
	 * Load the notifications list template.
	 *
	 * @param array $query_vars The query variables.
	 * @return void
	 */
	public function load_notifications_template( $query_vars ) {
		$user_id  = get_current_user_id();
		$page     = max( 1, absint( get_query_var( 'paged' ) ) );
		/**
		 * Filters how many notifications are shown per page on the
		 * notifications dashboard page.
		 *
		 * @param int $per_page Items per page.
		 */
		$per_page = (int) apply_filters( 'storesuite_notifications_per_page', 15 );

		$manager     = new NotificationManager();
		$total_items = $manager->count_for_user( $user_id );

		$template_args = array(
			'query_vars'    => $query_vars,
			'notifications' => $manager->get_for_user( $user_id, $page, $per_page ),
			'unseen_count'  => $manager->count_unseen( $user_id ),
			'total_items'   => $total_items,
			'total_pages'   => (int) ceil( $total_items / $per_page ),
			'current_page'  => $page,
			'per_page'      => $per_page,
		);

		storesuite_get_template_part( 'notifications/notifications', '', $template_args );
	}
}
