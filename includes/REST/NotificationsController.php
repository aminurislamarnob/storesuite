<?php

namespace PluginizeLab\StoreSuite\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PluginizeLab\StoreSuite\Notification\NotificationManager;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Server;

/**
 * REST controller for dashboard notifications.
 *
 * The poll endpoint is designed to be near-free when idle: it compares the
 * client's cursor against a single cached value and only touches the
 * notifications table when something actually changed.
 */
class NotificationsController extends WP_REST_Controller {

	/**
	 * The constructor.
	 */
	public function __construct() {
		$this->namespace = 'storesuite/v1';
		$this->rest_base = 'notifications';
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/poll',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'poll' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'cursor' => array(
							'description'       => __( 'The latest notification ID the client already knows about.', 'storesuite' ),
							'type'              => 'integer',
							'default'           => 0,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/seen',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'mark_seen' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'ids' => array(
							'description' => __( 'Notification IDs to mark as seen.', 'storesuite' ),
							'type'        => 'array',
							'items'       => array( 'type' => 'integer' ),
							'default'     => array(),
						),
						'all' => array(
							'description' => __( 'Mark all notifications as seen.', 'storesuite' ),
							'type'        => 'boolean',
							'default'     => false,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/clear',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'clear' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);
	}

	/**
	 * Delete all notifications for the current user.
	 *
	 * @return \WP_REST_Response
	 */
	public function clear() {
		$manager = new NotificationManager();
		$deleted = $manager->delete_all_for_user( get_current_user_id() );

		return rest_ensure_response(
			array(
				'deleted'      => $deleted,
				'unseen_count' => 0,
			)
		);
	}

	/**
	 * Only store managers receive notifications.
	 *
	 * @return bool
	 */
	public function permissions_check() {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Poll for new notifications.
	 *
	 * Idle path: the cached cursor hasn't moved past the client's, so we
	 * return immediately without querying the notifications table.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return \WP_REST_Response
	 */
	public function poll( WP_REST_Request $request ) {
		$manager   = new NotificationManager();
		$cursor    = (int) $request->get_param( 'cursor' );
		$latest_id = $manager->get_cursor();

		if ( $cursor >= $latest_id ) {
			return rest_ensure_response(
				array(
					'latest_id' => $latest_id,
					'changed'   => false,
				)
			);
		}

		$user_id = get_current_user_id();

		return rest_ensure_response(
			array(
				'latest_id'    => $latest_id,
				'changed'      => true,
				'unseen_count' => $manager->count_unseen( $user_id ),
				'items'        => $manager->get_recent( $user_id, 5 ),
			)
		);
	}

	/**
	 * Mark notifications as seen for the current user.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return \WP_REST_Response
	 */
	public function mark_seen( WP_REST_Request $request ) {
		$manager = new NotificationManager();
		$user_id = get_current_user_id();

		if ( $request->get_param( 'all' ) ) {
			$manager->mark_all_seen( $user_id );
		} else {
			$ids = (array) $request->get_param( 'ids' );
			if ( empty( $ids ) ) {
				return rest_ensure_response(
					array(
						'unseen_count' => $manager->count_unseen( $user_id ),
					)
				);
			}
			$manager->mark_seen( $user_id, $ids );
		}

		return rest_ensure_response(
			array(
				'unseen_count' => $manager->count_unseen( $user_id ),
			)
		);
	}
}
