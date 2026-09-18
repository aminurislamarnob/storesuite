<?php

namespace PluginizeLab\StoreSuite\Notification;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Listens to store events and records notifications, plus the daily
 * retention cleanup cron.
 */
class NotificationHooks {

	/**
	 * Cron hook name for the daily retention cleanup.
	 *
	 * @var string
	 */
	const CLEANUP_HOOK = 'storesuite_notifications_cleanup';

	/**
	 * The constructor.
	 */
	public function __construct() {
		// Classic checkout and block (Store API) checkout fire different hooks.
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'on_new_order' ) );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'on_new_order' ) );
		add_action( 'woocommerce_created_customer', array( $this, 'on_new_customer' ) );
		add_action( 'comment_post', array( $this, 'on_new_comment' ), 10, 2 );
		add_action( self::CLEANUP_HOOK, array( $this, 'cleanup_old_notifications' ) );
	}

	/**
	 * Record a notification for a newly placed order.
	 *
	 * @param int|\WC_Order $order Order ID (classic checkout) or order object (Store API).
	 * @return void
	 */
	public function on_new_order( $order ) {
		$order_id = $order instanceof \WC_Order ? $order->get_id() : absint( $order );

		if ( $order_id ) {
			( new NotificationManager() )->insert( 'new_order', $order_id );
		}
	}

	/**
	 * Record a notification for a newly registered customer.
	 *
	 * @param int $customer_id New customer user ID.
	 * @return void
	 */
	public function on_new_customer( $customer_id ) {
		( new NotificationManager() )->insert( 'new_customer', $customer_id );
	}

	/**
	 * Record a notification when a product review is submitted.
	 *
	 * @param int        $comment_id Comment ID.
	 * @param int|string $approved   1, 0, or 'spam'.
	 * @return void
	 */
	public function on_new_comment( $comment_id, $approved ) {
		if ( 'spam' === $approved ) {
			return;
		}

		$comment = get_comment( $comment_id );

		// Only top-level reviews on products, not replies.
		if ( ! $comment || ! empty( $comment->comment_parent ) || 'product' !== get_post_type( $comment->comment_post_ID ) ) {
			return;
		}

		( new NotificationManager() )->insert( 'product_review', $comment_id );
	}

	/**
	 * Daily cron: delete notifications older than the retention window.
	 *
	 * @return void
	 */
	public function cleanup_old_notifications() {
		( new NotificationManager() )->delete_older_than();
	}
}
