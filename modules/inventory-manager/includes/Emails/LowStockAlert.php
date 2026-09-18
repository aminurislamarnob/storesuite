<?php
/**
 * Inventory Manager — immediate low/out-of-stock alert email.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Modules\InventoryManager\Emails;

use WC_Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Emails store staff the moment a product hits its low/no-stock threshold,
 * hooking WooCommerce's native low/no-stock notification actions (fired on
 * order-driven stock reduction). A per-product 24h de-dupe transient keeps an
 * oscillating SKU from spamming.
 */
class LowStockAlert extends WC_Email {

	/**
	 * Alert level for the send in progress: low|out.
	 *
	 * @var string
	 */
	public $alert_level = 'low';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'storesuite_low_stock_alert';
		$this->title          = __( 'StoreSuite Low Stock Alert', 'storesuite' );
		$this->description    = __( 'Sent to chosen recipient(s) immediately when a product drops to or below its low-stock threshold or runs out of stock.', 'storesuite' );
		$this->template_html  = 'emails/low-stock-alert.php';
		$this->template_plain = 'emails/plain/low-stock-alert.php';
		$this->template_base  = trailingslashit( dirname( __DIR__, 2 ) ) . 'templates/';
		$this->placeholders   = array(
			'{product_name}'   => '',
			'{product_sku}'    => '',
			'{stock_quantity}' => '',
			'{stock_status}'   => '',
		);

		add_action( 'woocommerce_low_stock_notification', array( $this, 'trigger_low' ) );
		add_action( 'woocommerce_no_stock_notification', array( $this, 'trigger_out' ) );

		parent::__construct();

		$this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );
	}

	/**
	 * @return string
	 */
	public function get_default_subject() {
		return __( '[{site_title}] Stock alert: {product_name}', 'storesuite' );
	}

	/**
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'Stock alert: {product_name}', 'storesuite' );
	}

	/**
	 * @param \WC_Product $product Product at low stock.
	 * @return void
	 */
	public function trigger_low( $product ) {
		$this->trigger( $product, 'low' );
	}

	/**
	 * @param \WC_Product $product Product out of stock.
	 * @return void
	 */
	public function trigger_out( $product ) {
		$this->trigger( $product, 'out' );
	}

	/**
	 * Send the alert, respecting the per-product 24h de-dupe.
	 *
	 * @param \WC_Product $product Product.
	 * @param string      $level   low|out.
	 * @return void
	 */
	public function trigger( $product, $level ) {
		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
			return;
		}

		$id     = $product->get_id();
		$dedupe = 'storesuite_low_stock_sent_' . $id;
		if ( get_transient( $dedupe ) ) {
			\storesuite_log(
				sprintf( '[inventory-manager] Skipping %1$s alert for product #%2$d — already alerted within the last 24h.', $level, $id ),
				'debug'
			);
			return;
		}
		set_transient( $dedupe, 1, DAY_IN_SECONDS );

		$this->setup_locale();

		$this->object      = $product;
		$this->alert_level = $level;

		$this->placeholders['{product_name}']   = $product->get_name();
		$this->placeholders['{product_sku}']    = $product->get_sku();
		$this->placeholders['{stock_quantity}'] = (string) (int) $product->get_stock_quantity();
		$this->placeholders['{stock_status}']   = 'out' === $level
			? __( 'out of stock', 'storesuite' )
			: __( 'low on stock', 'storesuite' );

		\storesuite_log(
			sprintf( '[inventory-manager] Dispatching immediate %1$s-stock alert for product #%2$d.', $level, $id ),
			'info'
		);

		$sent = $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );

		if ( $sent ) {
			\storesuite_log(
				sprintf( '[inventory-manager] Stock alert email for product #%1$d handed off to: %2$s', $id, $this->get_recipient() ),
				'info'
			);
		} else {
			\storesuite_log(
				sprintf( '[inventory-manager] Stock alert email for product #%1$d FAILED to send to: %2$s', $id, $this->get_recipient() ),
				'error'
			);
		}

		$this->restore_locale();
	}

	/**
	 * The product for the send in progress, or a sample one so the live
	 * preview on the WooCommerce email settings screen has data to render.
	 *
	 * @return \WC_Product
	 */
	private function get_template_product() {
		if ( $this->object instanceof \WC_Product ) {
			return $this->object;
		}

		$sample = new \WC_Product_Simple();
		$sample->set_name( __( 'Sample product', 'storesuite' ) );
		$sample->set_sku( 'SAMPLE-SKU' );
		$sample->set_stock_quantity( 1 );

		return $sample;
	}

	/**
	 * @return string
	 */
	public function get_content_html() {
		return wc_get_template_html(
			$this->template_html,
			array(
				'product'            => $this->get_template_product(),
				'level'              => $this->alert_level,
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
				'product'            => $this->get_template_product(),
				'level'              => $this->alert_level,
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
				'default' => 'yes',
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
