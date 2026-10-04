<?php

namespace PluginizeLab\StoreSuite\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WC_Order;

/**
 * Surfaces Advanced Shipment Tracking for WooCommerce (AST, free) inside the
 * StoreSuite frontend order list and order details pages.
 *
 * Shop managers can view, add and delete tracking without wp-admin. Writes go
 * through AST's own PHP API, so its customer emails, order notes and order
 * status changes behave exactly as they do from the wp-admin order screen.
 * Carrier management and AST settings stay in wp-admin.
 *
 * The class is fully self-contained: the order templates only expose generic
 * StoreSuite extension hooks, and this integration injects the tracking UI.
 */
class ShipmentTrackingIntegration {

	/**
	 * Frontend script handle.
	 *
	 * @var string
	 */
	const SCRIPT_HANDLE = 'storesuite_order_tracking_script';

	/**
	 * AJAX action (and nonce action) for adding tracking.
	 *
	 * @var string
	 */
	const ADD_ACTION = 'storesuite_add_order_tracking';

	/**
	 * AJAX action (and nonce action) for deleting tracking.
	 *
	 * @var string
	 */
	const DELETE_ACTION = 'storesuite_delete_order_tracking';

	/**
	 * Key of the tracking column in the orders list.
	 *
	 * @var string
	 */
	const COLUMN_KEY = 'shipment_tracking';

	/**
	 * Constructor. Bails unless the free AST plugin is active.
	 */
	public function __construct() {
		if ( ! self::is_ast_active() ) {
			return;
		}

		add_action( 'wp_ajax_' . self::ADD_ACTION, array( $this, 'handle_add_tracking' ) );
		add_action( 'wp_ajax_' . self::DELETE_ACTION, array( $this, 'handle_delete_tracking' ) );

		add_filter( 'storesuite_order_list_columns', array( $this, 'add_list_column' ) );
		add_action( 'storesuite_order_list_column_' . self::COLUMN_KEY, array( $this, 'render_list_column' ) );
		add_action( 'storesuite_order_list_row_actions', array( $this, 'render_list_row_action' ) );
		// Priority 8 so the card sits below Documents (5) and above notes / customer history (10).
		add_action( 'storesuite_after_order_details_action', array( $this, 'render_order_details_card' ), 8 );
		add_action( 'storesuite_after_order_form_submit', array( $this, 'render_order_form_card' ) );
		// Inside the page content, where the dashboard's form styles apply; the overlay itself is fixed.
		add_action( 'storesuite_dashboard_before_main_content', array( $this, 'render_modal' ), 99 );

		add_filter( 'storesuite_get_order_status_class', array( $this, 'add_status_classes' ) );

		// Assets registers its scripts on init 10; register after so the dashboard script handle exists.
		add_action( 'init', array( $this, 'register_script' ), 11 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_script' ), 20 );
	}

	/**
	 * Whether the free AST plugin is active.
	 *
	 * AST Pro replaces the free plugin and has a different feature set, so the
	 * integration stays off under Pro instead of rendering a half-working UI.
	 *
	 * @return bool
	 */
	public static function is_ast_active(): bool {
		if ( ! function_exists( 'wc_advanced_shipment_tracking' ) || ! class_exists( 'WC_Advanced_Shipment_Tracking_Actions' ) ) {
			return false;
		}

		if ( function_exists( 'ast_pro' ) ) {
			return false;
		}

		$active_plugins = (array) get_option( 'active_plugins', array() );

		if ( is_multisite() ) {
			$active_plugins = array_merge( $active_plugins, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}

		$pro_plugins = array( 'ast-pro/ast-pro.php', 'advanced-shipment-tracking-pro/advanced-shipment-tracking-pro.php' );

		return ! array_intersect( $pro_plugins, $active_plugins );
	}

	/**
	 * Whether the current user may view and manage tracking.
	 *
	 * Uses the capability AST itself gates on, so the dashboard matches wp-admin.
	 *
	 * @return bool
	 */
	public function can_manage(): bool {
		$capability = defined( 'AST_FREE_PLUGIN_ACCESS' ) ? AST_FREE_PLUGIN_ACCESS : 'manage_woocommerce';

		return current_user_can( $capability );
	}

	/**
	 * AST's tracking actions instance.
	 *
	 * @return \WC_Advanced_Shipment_Tracking_Actions
	 */
	protected function ast() {
		return \WC_Advanced_Shipment_Tracking_Actions::get_instance();
	}

	/**
	 * Carriers enabled in AST settings, grouped by country as AST's own form groups them.
	 *
	 * @return array<string, array<string, string>> Country label => ( carrier slug => carrier name ).
	 */
	public function get_enabled_carriers(): array {
		global $wpdb;

		$table = $this->ast()->table;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- AST's own carrier table has no API that reports which carriers are enabled; the table name comes from AST, never from user input.
		$rows = $wpdb->get_results( "SELECT ts_slug, provider_name, custom_provider_name, shipping_country FROM {$table} WHERE display_in_order = 1 ORDER BY shipping_country ASC, provider_name ASC" );

		if ( empty( $rows ) ) {
			return array();
		}

		$countries = WC()->countries->get_countries();
		$carriers  = array();

		foreach ( $rows as $row ) {
			$country = isset( $countries[ $row->shipping_country ] ) ? $countries[ $row->shipping_country ] : __( 'Global', 'storesuite' );
			$name    = ! empty( $row->custom_provider_name ) ? $row->custom_provider_name : $row->provider_name;

			$carriers[ $country ][ $row->ts_slug ] = $name;
		}

		return $carriers;
	}

	/**
	 * Whether a carrier slug is enabled in AST settings.
	 *
	 * @param string $slug Carrier slug.
	 *
	 * @return bool
	 */
	protected function is_carrier_enabled( string $slug ): bool {
		foreach ( $this->get_enabled_carriers() as $carriers ) {
			if ( isset( $carriers[ $slug ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether AST's "Partially Shipped" order status is enabled.
	 *
	 * @return bool
	 */
	protected function is_partial_shipped_enabled(): bool {
		return function_exists( 'get_ast_settings' ) && (bool) get_ast_settings( 'ast_general_settings', 'wc_ast_status_partial_shipped', '' );
	}

	/**
	 * The "Mark order as" choices, mirroring AST's own form.
	 *
	 * @return array<string, array{label:string, checked:bool}> Keyed by the posted value.
	 */
	public function get_mark_as_options(): array {
		$renamed = function_exists( 'get_ast_settings' ) && 1 === (int) get_ast_settings( 'ast_general_settings', 'wc_ast_status_shipped', 0 );

		$options = array(
			'shipped' => array(
				'label'   => $renamed ? __( 'Shipped', 'storesuite' ) : __( 'Completed', 'storesuite' ),
				'checked' => 1 === (int) apply_filters( 'wc_ast_default_mark_shipped', 1 ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- AST's own filter.
			),
		);

		if ( $this->is_partial_shipped_enabled() ) {
			$options['partial_shipped'] = array(
				'label'   => __( 'Partially Shipped', 'storesuite' ),
				'checked' => false,
			);
		}

		return $options;
	}

	/**
	 * Tracking items of an order, with AST's resolved carrier name, logo and link.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_tracking_items( int $order_id ): array {
		$items = $this->ast()->get_tracking_items( $order_id, true );

		if ( ! is_array( $items ) ) {
			return array();
		}

		foreach ( $items as &$item ) {
			$item = array_merge( $item, $this->describe_item( $item ) );
		}
		unset( $item );

		return $items;
	}

	/**
	 * The display details of a tracking item, matching AST's own wp-admin order box.
	 *
	 * @param array<string, mixed> $item Formatted AST tracking item.
	 *
	 * @return array{display_carrier:string, display_url:string, display_meta:string}
	 */
	protected function describe_item( array $item ): array {
		$carrier = ! empty( $item['formatted_tracking_provider'] ) ? $item['formatted_tracking_provider'] : ( isset( $item['tracking_provider'] ) ? $item['tracking_provider'] : '' );
		$url     = ! empty( $item['ast_tracking_link'] ) ? $item['ast_tracking_link'] : ( isset( $item['formatted_tracking_link'] ) ? $item['formatted_tracking_link'] : '' );
		$meta    = '';

		if ( ! empty( $item['date_shipped'] ) ) {
			/* translators: %s: date shipped. */
			$meta = sprintf( __( 'Shipped on %s', 'storesuite' ), date_i18n( wc_date_format(), (int) $item['date_shipped'] ) );
		}

		$user = ! empty( $item['user_id'] ) ? get_userdata( (int) $item['user_id'] ) : false;

		if ( $user ) {
			/* translators: %s: name of the user who added the tracking. */
			$meta .= ' ' . sprintf( __( 'by %s', 'storesuite' ), $user->display_name );

			if ( ! empty( $item['source'] ) ) {
				/* translators: %s: where the tracking was added from, e.g. "edit order". */
				$meta .= ' ' . sprintf( __( '(Added via %s)', 'storesuite' ), str_replace( '_', ' ', $item['source'] ) );
			}
		}

		return array(
			'display_carrier' => (string) $carrier,
			'display_url'     => (string) $url,
			'display_meta'    => trim( $meta ),
		);
	}

	/**
	 * Map AST's custom order statuses onto StoreSuite badge styles.
	 *
	 * @param array<string, string> $classes Status => badge style.
	 *
	 * @return array<string, string>
	 */
	public function add_status_classes( $classes ) {
		$classes['partial-shipped']  = 'info';
		$classes['updated-tracking'] = 'info';
		$classes['delivered']        = 'success';

		return $classes;
	}

	/**
	 * Register the tracking column in the orders list.
	 *
	 * @param array<string, string> $columns Column key => label.
	 *
	 * @return array<string, string>
	 */
	public function add_list_column( $columns ) {
		if ( $this->can_manage() ) {
			$columns[ self::COLUMN_KEY ] = __( 'Shipment Tracking', 'storesuite' );
		}

		return $columns;
	}

	/**
	 * Render the tracking cell of an orders list row.
	 *
	 * @param WC_Order $order Current order.
	 */
	public function render_list_column( $order ) {
		if ( ! $order instanceof WC_Order || ! $this->can_manage() ) {
			return;
		}
		?>
		<div class="storesuite-tracking-cell" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
			<?php echo $this->get_cell_html( $order->get_id() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the template. ?>
		</div>
		<?php
	}

	/**
	 * Render the "Add Tracking" item inside the order list row-actions dropdown.
	 *
	 * @param WC_Order $order Current order.
	 */
	public function render_list_row_action( $order ) {
		if ( ! $order instanceof WC_Order || ! $this->can_manage() || 'trash' === $order->get_status() ) {
			return;
		}
		?>
		<li>
			<?php // The icon and label must not be separated by whitespace: `.dropdown-link` is a block, so a text node between them renders as a leading space and offsets the label. ?>
			<a href="#" class="dropdown-link storesuite-add-tracking" role="button" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>" data-order-number="<?php echo esc_attr( $order->get_order_number() ); ?>"><svg width="16" height="16" fill="currentColor" aria-hidden="true" focusable="false"><use href="#storesuite-icon-truck"></use></svg><?php esc_html_e( 'Add Tracking', 'storesuite' ); ?></a>
		</li>
		<?php
	}

	/**
	 * Render the "Shipment Tracking" card in the order details sidebar.
	 *
	 * @param WC_Order $order Current order.
	 */
	public function render_order_details_card( $order ) {
		if ( ! $order instanceof WC_Order || ! $this->can_manage() || 'auto-draft' === $order->get_status() ) {
			return;
		}

		$this->render_card( $order, true );
	}

	/**
	 * Render the "Shipment Tracking" card in the sidebar of the add / edit order form.
	 *
	 * A new order is still an auto-draft: tracking can be attached, but its
	 * status is chosen in the form, so the "Mark order as" choice is withheld.
	 *
	 * @param WC_Order $order Current order.
	 */
	public function render_order_form_card( $order ) {
		if ( ! $order instanceof WC_Order || ! $this->can_manage() || ! $order->get_id() ) {
			return;
		}

		$this->render_card( $order, 'auto-draft' !== $order->get_status() );
	}

	/**
	 * Render the tracking card of an order.
	 *
	 * @param WC_Order $order         The order.
	 * @param bool     $status_change Whether the modal may offer "Mark order as".
	 */
	protected function render_card( WC_Order $order, bool $status_change ) {
		storesuite_get_template_part(
			'orders/tracking/card',
			'',
			array(
				'order'         => $order,
				'items_html'    => $this->get_items_html( $order->get_id() ),
				'status_change' => $status_change,
			)
		);
	}

	/**
	 * Render the shared add-tracking modal once on every order page that shows tracking.
	 */
	public function render_modal() {
		if ( ! $this->is_tracking_page() || ! $this->can_manage() ) {
			return;
		}

		storesuite_get_template_part(
			'orders/tracking/modal',
			'',
			array(
				'carriers' => $this->get_enabled_carriers(),
				'mark_as'  => $this->get_mark_as_options(),
				'today'    => current_time( 'Y-m-d' ),
			)
		);
	}

	/**
	 * Whether the current request is a dashboard page that shows tracking.
	 *
	 * @return bool
	 */
	protected function is_tracking_page(): bool {
		return storesuite_is_endpoint_url( 'orders' )
			|| storesuite_is_endpoint_url( 'order-details' )
			|| storesuite_is_endpoint_url( 'add-new-order' )
			|| storesuite_is_endpoint_url( 'edit-order' );
	}

	/**
	 * Markup of the shipment list shown in the order details card.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return string
	 */
	protected function get_items_html( int $order_id ): string {
		ob_start();
		storesuite_get_template_part(
			'orders/tracking/items',
			'',
			array(
				'order_id' => $order_id,
				'items'    => $this->get_tracking_items( $order_id ),
			)
		);

		return (string) ob_get_clean();
	}

	/**
	 * Markup of the tracking cell shown in the orders list.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return string
	 */
	protected function get_cell_html( int $order_id ): string {
		ob_start();
		storesuite_get_template_part(
			'orders/tracking/list-cell',
			'',
			array(
				'items' => $this->get_tracking_items( $order_id ),
			)
		);

		return (string) ob_get_clean();
	}

	/**
	 * Verify the nonce and capability of a tracking AJAX request, then return its order.
	 *
	 * Sends a JSON error and exits when any check fails.
	 *
	 * @param string $action AJAX action, which is also the nonce action.
	 *
	 * @return WC_Order
	 */
	protected function get_request_order( string $action ): WC_Order {
		if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['security'] ) ), $action ) ) {
			wp_send_json_error( array( 'error' => __( 'Nonce verification failed', 'storesuite' ) ) );
		}

		if ( ! $this->can_manage() ) {
			wp_send_json_error( array( 'error' => __( 'You do not have permission to perform this action.', 'storesuite' ) ) );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$order    = $order_id ? wc_get_order( $order_id ) : false;

		if ( ! $order instanceof WC_Order ) {
			wp_send_json_error( array( 'error' => __( 'Invalid order.', 'storesuite' ) ) );
		}

		return $order;
	}

	/**
	 * Send the refreshed tracking markup of an order as a JSON success response.
	 *
	 * @param WC_Order $order          The order.
	 * @param string   $message        Success message.
	 * @param bool     $status_changed Whether the order status changed during the request.
	 */
	protected function send_tracking_response( WC_Order $order, string $message, bool $status_changed = false ) {
		wp_send_json_success(
			array(
				'message'        => $message,
				'order_id'       => $order->get_id(),
				'items_html'     => $this->get_items_html( $order->get_id() ),
				'cell_html'      => $this->get_cell_html( $order->get_id() ),
				'status_changed' => $status_changed,
				'order_status'   => 'wc-' . $order->get_status(),
			)
		);
	}

	/**
	 * Handle the AJAX request for adding tracking to an order.
	 */
	public function handle_add_tracking() {
		// The nonce is verified in get_request_order().
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$order = $this->get_request_order( self::ADD_ACTION );

		$tracking_number = isset( $_POST['tracking_number'] ) ? trim( wc_clean( wp_unslash( $_POST['tracking_number'] ) ) ) : '';
		$carrier         = isset( $_POST['tracking_provider'] ) ? wc_clean( wp_unslash( $_POST['tracking_provider'] ) ) : '';
		$date_shipped    = isset( $_POST['date_shipped'] ) ? wc_clean( wp_unslash( $_POST['date_shipped'] ) ) : '';
		$mark_as         = isset( $_POST['mark_order_as'] ) ? wc_clean( wp_unslash( $_POST['mark_order_as'] ) ) : '';
		// phpcs:enable

		if ( '' === $tracking_number ) {
			wp_send_json_error( array( 'error' => __( 'Tracking number is required.', 'storesuite' ) ) );
		}

		if ( '' === $carrier ) {
			wp_send_json_error( array( 'error' => __( 'Please select a carrier.', 'storesuite' ) ) );
		}

		if ( ! $this->is_carrier_enabled( $carrier ) ) {
			wp_send_json_error( array( 'error' => __( 'The selected carrier is not enabled.', 'storesuite' ) ) );
		}

		if ( '' === $date_shipped ) {
			$date_shipped = current_time( 'Y-m-d' );
		}

		$date = \DateTime::createFromFormat( '!Y-m-d', $date_shipped );

		if ( ! $date || $date->format( 'Y-m-d' ) !== $date_shipped ) {
			wp_send_json_error( array( 'error' => __( 'Date shipped is not a valid date.', 'storesuite' ) ) );
		}

		if ( '' !== $mark_as && ! array_key_exists( $mark_as, $this->get_mark_as_options() ) ) {
			wp_send_json_error( array( 'error' => __( 'The selected order status is not available.', 'storesuite' ) ) );
		}

		$status_before = $order->get_status();

		// A new order is still being composed; its status comes from the order form.
		if ( 'auto-draft' === $status_before ) {
			$mark_as = '';
		}

		$args = array(
			'tracking_provider' => $carrier,
			'tracking_number'   => $tracking_number,
			'date_shipped'      => $date_shipped,
			'source'            => 'storesuite',
		);

		// AST's own flags: 1 marks the order shipped (completed), 2 partially shipped.
		if ( 'shipped' === $mark_as ) {
			$args['status_shipped'] = 1;
		} elseif ( 'partial_shipped' === $mark_as ) {
			$args['status_shipped'] = 2;
		}

		$this->ast()->add_tracking_item( $order->get_id(), $args );

		$order = wc_get_order( $order->get_id() );

		$this->send_tracking_response( $order, __( 'Tracking added.', 'storesuite' ), $order->get_status() !== $status_before );
	}

	/**
	 * Handle the AJAX request for deleting tracking from an order.
	 */
	public function handle_delete_tracking() {
		// The nonce is verified in get_request_order().
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$order       = $this->get_request_order( self::DELETE_ACTION );
		$tracking_id = isset( $_POST['tracking_id'] ) ? wc_clean( wp_unslash( $_POST['tracking_id'] ) ) : '';
		// phpcs:enable

		$order_id = $order->get_id();
		$items    = $this->get_tracking_items( $order_id );
		$item     = null;

		foreach ( $items as $candidate ) {
			if ( isset( $candidate['tracking_id'] ) && $candidate['tracking_id'] === $tracking_id ) {
				$item = $candidate;
				break;
			}
		}

		if ( '' === $tracking_id || null === $item ) {
			wp_send_json_error( array( 'error' => __( 'Tracking entry not found.', 'storesuite' ) ) );
		}

		// Mirrors AST's own delete handler, which notifies TrackShip and leaves an order note.
		do_action( 'delete_tracking_number_from_trackship', $items, $tracking_id, $order_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- AST's own action.

		$order->add_order_note(
			sprintf(
				/* translators: 1: carrier name, 2: tracking number. */
				__( 'Tracking info was deleted for tracking provider %1$s with tracking number %2$s', 'storesuite' ),
				isset( $item['formatted_tracking_provider'] ) ? $item['formatted_tracking_provider'] : '',
				$item['tracking_number']
			)
		);

		$this->ast()->delete_tracking_item( $order_id, $tracking_id );

		$this->send_tracking_response( wc_get_order( $order_id ), __( 'Tracking deleted.', 'storesuite' ) );
	}

	/**
	 * Register the frontend script.
	 */
	public function register_script() {
		wp_register_script(
			self::SCRIPT_HANDLE,
			STORESUITE_PLUGIN_ASSET . '/frontend/order-tracking.js',
			array( 'jquery', 'jquery-ui-datepicker', 'storesuite_script', 'storesuite_sweetalert2_script' ),
			STORESUITE_PLUGIN_VERSION,
			true
		);
	}

	/**
	 * Enqueue the frontend script on the order pages that show tracking only.
	 */
	public function enqueue_script() {
		if ( ! storesuite_is_dashboard_page() || ! $this->is_tracking_page() || ! $this->can_manage() ) {
			return;
		}

		wp_enqueue_script( self::SCRIPT_HANDLE );
		wp_localize_script(
			self::SCRIPT_HANDLE,
			'StoreSuiteOrderTracking',
			array(
				'ajax_url'       => admin_url( 'admin-ajax.php' ),
				'add_action'     => self::ADD_ACTION,
				'delete_action'  => self::DELETE_ACTION,
				'add_nonce'      => wp_create_nonce( self::ADD_ACTION ),
				'delete_nonce'   => wp_create_nonce( self::DELETE_ACTION ),
				/* translators: %s: order number. */
				'i18n_title'     => __( 'Add Tracking - Order #%s', 'storesuite' ),
				'i18n_delete'    => __( 'Delete this tracking entry?', 'storesuite' ),
				'i18n_confirm'   => __( 'Delete', 'storesuite' ),
				'i18n_cancel'    => __( 'Cancel', 'storesuite' ),
				'i18n_error'     => __( 'Something went wrong. Please try again.', 'storesuite' ),
				'i18n_no_number' => __( 'Tracking number is required.', 'storesuite' ),
				'i18n_no_carrier' => __( 'Please select a carrier.', 'storesuite' ),
			)
		);
	}
}
