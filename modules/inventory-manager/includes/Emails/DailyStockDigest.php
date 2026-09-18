<?php
/**
 * Inventory Manager — daily low-stock digest email.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Modules\InventoryManager\Emails;

use WC_Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Batches every low/out-of-stock event since the previous digest into a
 * single once-daily email. Items are queued (keyed by product id, so repeat
 * events collapse) as WooCommerce's low/no-stock notifications fire, and the
 * queue is flushed by the recurring `storesuite_inventory_digest` action that
 * Emails\Manager keeps scheduled while this email is enabled.
 */
class DailyStockDigest extends WC_Email {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'storesuite_daily_stock_digest';
		$this->title          = __( 'StoreSuite Daily Stock Digest', 'storesuite' );
		$this->description    = __( 'A once-daily digest sent to chosen recipient(s) listing every product that went low on stock or out of stock since the previous digest.', 'storesuite' );
		$this->template_html  = 'emails/daily-stock-digest.php';
		$this->template_plain = 'emails/plain/daily-stock-digest.php';
		$this->template_base  = trailingslashit( dirname( __DIR__, 2 ) ) . 'templates/';
		$this->placeholders   = array(
			'{item_count}' => '',
		);

		add_action( 'woocommerce_low_stock_notification', array( $this, 'queue_low' ) );
		add_action( 'woocommerce_no_stock_notification', array( $this, 'queue_out' ) );
		add_action( Manager::DIGEST_HOOK . '_notification', array( $this, 'trigger' ) );

		parent::__construct();

		$this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );
	}

	/**
	 * @return string
	 */
	public function get_default_subject() {
		return __( '[{site_title}] Daily low-stock digest', 'storesuite' );
	}

	/**
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'Daily low-stock digest', 'storesuite' );
	}

	/**
	 * @param \WC_Product $product Product at low stock.
	 * @return void
	 */
	public function queue_low( $product ) {
		$this->queue_item( $product, 'low' );
	}

	/**
	 * @param \WC_Product $product Product out of stock.
	 * @return void
	 */
	public function queue_out( $product ) {
		$this->queue_item( $product, 'out' );
	}

	/**
	 * Add a product to the pending digest queue.
	 *
	 * @param \WC_Product $product Product.
	 * @param string      $level   low|out.
	 * @return void
	 */
	private function queue_item( $product, $level ) {
		if ( ! $product instanceof \WC_Product || ! $this->is_enabled() ) {
			return;
		}

		$id           = $product->get_id();
		$queue        = (array) get_option( Manager::QUEUE_OPTION, array() );
		$queue[ $id ] = array(
			'name'  => $product->get_name(),
			'sku'   => $product->get_sku(),
			'qty'   => $product->get_stock_quantity(),
			'level' => $level,
		);
		update_option( Manager::QUEUE_OPTION, $queue );

		\storesuite_log(
			sprintf( '[inventory-manager] Queued product #%1$d (%2$s) for daily digest; queue now holds %3$d item(s).', $id, $level, count( $queue ) ),
			'info'
		);
	}

	/**
	 * Send the batched digest and clear the queue. Fired by the recurring
	 * Action Scheduler action (via WooCommerce's `_notification` wrapper).
	 *
	 * @return void
	 */
	public function trigger() {
		$queue = (array) get_option( Manager::QUEUE_OPTION, array() );
		if ( empty( $queue ) ) {
			\storesuite_log( '[inventory-manager] Daily digest action ran but the queue is empty — nothing to send.', 'debug' );
			return;
		}

		if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
			return;
		}

		$this->setup_locale();

		$this->object                        = $queue;
		$this->placeholders['{item_count}'] = (string) count( $queue );

		\storesuite_log(
			sprintf( '[inventory-manager] Sending daily digest for %d product(s).', count( $queue ) ),
			'info'
		);

		$sent = $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );

		if ( $sent ) {
			delete_option( Manager::QUEUE_OPTION );
			\storesuite_log(
				sprintf( '[inventory-manager] Daily digest email handed off to: %s', $this->get_recipient() ),
				'info'
			);
		} else {
			\storesuite_log(
				sprintf( '[inventory-manager] Daily digest email FAILED to send to: %s — keeping the queue for the next run.', $this->get_recipient() ),
				'error'
			);
		}

		$this->restore_locale();
	}

	/**
	 * The queued items for the send in progress, or sample rows so the live
	 * preview on the WooCommerce email settings screen has data to render.
	 *
	 * @return array
	 */
	private function get_template_items() {
		// The preview screen injects a dummy order as the object, so only
		// accept properly shaped queue rows.
		$items = is_array( $this->object ) ? $this->object : array();
		$items = array_filter(
			$items,
			static function ( $item ) {
				return is_array( $item ) && isset( $item['name'] );
			}
		);

		if ( ! empty( $items ) ) {
			return $items;
		}

		return array(
			array(
				'name'  => __( 'Sample product', 'storesuite' ),
				'sku'   => 'SAMPLE-SKU',
				'qty'   => 1,
				'level' => 'low',
			),
			array(
				'name'  => __( 'Another sample product', 'storesuite' ),
				'sku'   => '',
				'qty'   => 0,
				'level' => 'out',
			),
		);
	}

	/**
	 * @return string
	 */
	public function get_content_html() {
		return wc_get_template_html(
			$this->template_html,
			array(
				'items'              => $this->get_template_items(),
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'sent_to_admin'      => true,
				'plain_text'         => false,
				'email'              => $this,
			),
			'storesuite/',
			$this->template_base
		);
	}

	/**
	 * @return string
	 */
	public function get_content_plain() {
		return wc_get_template_html(
			$this->template_plain,
			array(
				'items'              => $this->get_template_items(),
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'sent_to_admin'      => true,
				'plain_text'         => true,
				'email'              => $this,
			),
			'storesuite/',
			$this->template_base
		);
	}

	/**
	 * Settings form shown on WooCommerce → Settings → Emails → Manage.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		/* translators: %s: list of placeholders */
		$placeholder_text = sprintf( __( 'Available placeholders: %s', 'storesuite' ), '<code>' . esc_html( implode( '</code>, <code>', array_keys( $this->placeholders ) ) ) . '</code>' );

		$this->form_fields = array(
			'enabled'            => array(
				'title'   => __( 'Enable/Disable', 'storesuite' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this email notification', 'storesuite' ),
				'default' => 'no',
			),
			'recipient'          => array(
				'title'       => __( 'Recipient(s)', 'storesuite' ),
				'type'        => 'text',
				/* translators: %s: admin email */
				'description' => sprintf( __( 'Enter recipients (comma separated) for this email. Defaults to %s.', 'storesuite' ), '<code>' . esc_attr( get_option( 'admin_email' ) ) . '</code>' ),
				'placeholder' => '',
				'default'     => '',
				'desc_tip'    => true,
			),
			'subject'            => array(
				'title'       => __( 'Subject', 'storesuite' ),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => $placeholder_text,
				'placeholder' => $this->get_default_subject(),
				'default'     => '',
			),
			'heading'            => array(
				'title'       => __( 'Email heading', 'storesuite' ),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => $placeholder_text,
				'placeholder' => $this->get_default_heading(),
				'default'     => '',
			),
			'additional_content' => array(
				'title'       => __( 'Additional content', 'storesuite' ),
				'description' => __( 'Text to appear below the main email content.', 'storesuite' ) . ' ' . $placeholder_text,
				'css'         => 'width:400px; height: 75px;',
				'placeholder' => __( 'N/A', 'storesuite' ),
				'type'        => 'textarea',
				'default'     => $this->get_default_additional_content(),
				'desc_tip'    => true,
			),
			'email_type'         => array(
				'title'       => __( 'Email type', 'storesuite' ),
				'type'        => 'select',
				'description' => __( 'Choose which format of email to send.', 'storesuite' ),
				'default'     => 'html',
				'class'       => 'email_type wc-enhanced-select',
				'options'     => $this->get_email_type_options(),
				'desc_tip'    => true,
			),
		);
	}
}
