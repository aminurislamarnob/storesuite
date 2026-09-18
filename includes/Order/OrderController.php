<?php

namespace PluginizeLab\StoreSuite\Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin order controller class
 */
class OrderController {

	/**
	 * The constructor.
	 */
	public function __construct() {
		add_action( 'storesuite_dashboard_order_form', array( $this, 'load_order_add_form' ) );
		add_action( 'storesuite_dashboard_order_edit_form', array( $this, 'load_order_edit_form' ) );
		add_action( 'wp_ajax_storesuite_add_order_note', array( $this, 'handle_add_order_note' ) );
		add_action( 'wp_ajax_storesuite_delete_order_note', array( $this, 'handle_delete_order_note' ) );
		add_action( 'wp_ajax_storesuite_add_shipping_to_order', array( $this, 'storesuite_add_shipping_to_order' ) );
		add_action( 'wp_ajax_storesuite_create_order', array( $this, 'storesuite_create_order' ) );
		add_action( 'template_redirect', array( $this, 'handle_order_bulk_actions' ) );
		add_action( 'storesuite_dashboard_title_after', array( $this, 'render_title_order_actions' ) );
	}

	/**
	 * Render Add Order + Filter buttons beside the Orders title on mobile.
	 *
	 * @return void
	 */
	public function render_title_order_actions() {
		$query = pluginizelab_storesuite()->get_storesuite_query();
		if ( ! $query || 'orders' !== $query->get_current_endpoint() ) {
			return;
		}
		?>
		<div class="storesuite-title-action storesuite-orders-title-actions">
			<a href="<?php echo esc_url( storesuite_get_navigation_url( 'add-new-order' ) ); ?>" class="my-storesuite-button">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">
					<path d="M23,11H13V1a1,1,0,0,0-1-1h0a1,1,0,0,0-1,1V11H1a1,1,0,0,0-1,1H0a1,1,0,0,0,1,1H11V23a1,1,0,0,0,1,1h0a1,1,0,0,0,1-1V13H23a1,1,0,0,0,1-1h0A1,1,0,0,0,23,11Z"/>
				</svg>
				<?php esc_html_e( 'Add Order', 'storesuite' ); ?>
			</a>
			<button type="button" class="my-storesuite-button storesuite-filter-toggle" id="storesuite-order-filter-toggle-title">
				<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-funnel" viewBox="0 0 16 16">
					<path d="M1.5 1.5A.5.5 0 0 1 2 1h12a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-.128.334L10 8.692V13.5a.5.5 0 0 1-.342.474l-3 1A.5.5 0 0 1 6 14.5V8.692L1.628 3.834A.5.5 0 0 1 1.5 3.5zm1 .5v1.308l4.372 4.858A.5.5 0 0 1 7 8.5v5.306l2-.666V8.5a.5.5 0 0 1 .128-.334L13.5 3.308V2z"/>
				</svg>
			</button>
		</div>
		<?php
	}

	public function load_order_add_form() {
		global $theorder;

		$template_args = array(
			'order'   => $theorder,
			'context' => 'add',
		);

		storesuite_get_template_part( 'orders/order-form', '', $template_args );
	}

	public function load_order_edit_form( $query_vars ) {
		if ( ! isset( $query_vars['edit-order'] ) ) {
			return;
		}

		$order_id = absint( $query_vars['edit-order'] );

		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$template_args = array(
			'order'   => $order,
			'context' => 'edit',
		);

		storesuite_get_template_part( 'orders/order-form', '', $template_args );
	}

	/**
	 * Handle the AJAX request for adding a new order note.
	 */
	public function handle_add_order_note() {

		// Verify the nonce.
		if ( ! isset( $_POST['storesuite_add_order_note_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['storesuite_add_order_note_nonce'] ) ), '_storesuite_add_order_note_' ) ) {
			wp_send_json_error( array( 'error' => __( 'Nonce verification failed', 'storesuite' ) ) );
		}

		// Check user permissions.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'error' => __( 'You do not have permission to perform this action.', 'storesuite' ) ) );
		}

		// Validate inputs.
		$order_id         = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$order_note       = isset( $_POST['order_note'] ) ? wp_kses_post( trim( wp_unslash( $_POST['order_note'] ) ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by wp_kses_post().
		$note_type        = isset( $_POST['order_note_type'] ) ? sanitize_text_field( wp_unslash( $_POST['order_note_type'] ) ) : '';
		$is_customer_note = ( 'customer' === $note_type ) ? 1 : 0;

		if ( empty( $order_note ) ) {
			wp_send_json_error( array( 'error' => __( 'Order note is required', 'storesuite' ) ) );
		}

		if ( $order_id > 0 ) {
			$order = wc_get_order( $order_id );

			if ( ! $order instanceof \WC_Order ) {
				wp_send_json_error( array( 'error' => __( 'Invalid order.', 'storesuite' ) ) );
			}

			$comment_id = $order->add_order_note( $order_note, $is_customer_note, true );
			$note       = wc_get_order_note( $comment_id );

			$note_classes   = array( 'note' );
			$note_classes[] = $is_customer_note ? 'customer-note' : '';
			$note_classes   = apply_filters( 'woocommerce_order_note_class', array_filter( $note_classes ), $note );

			// Capture the <li> markup.
			ob_start();
			?>
			<li rel="<?php echo absint( $note->id ); ?>" data-id="<?php echo absint( $note->id ); ?>" class="<?php echo esc_attr( implode( ' ', $note_classes ) ); ?>">
				<div class="note_content">
				<?php echo wp_kses_post( wpautop( wptexturize( make_clickable( $note->content ) ) ) ); ?>
				</div>
				<p class="meta">
					<abbr class="exact-date" title="<?php echo esc_attr( $note->date_created->date( 'Y-m-d H:i:s' ) ); ?>">
					<?php
					printf(
						/* translators: $1: Date created, $2 Time created */
						esc_html__( '%1$s at %2$s', 'storesuite' ),
						esc_html( $note->date_created->date_i18n( wc_date_format() ) ),
						esc_html( $note->date_created->date_i18n( wc_time_format() ) )
					);
					?>
					</abbr>
						<?php
						if ( 'system' !== $note->added_by ) :
							/* translators: %s: note author */
							printf( ' ' . esc_html__( 'by %s', 'storesuite' ), esc_html( $note->added_by ) );
						endif;
						?>
					<a href="#" class="delete_note" x-on:click.prevent="handleDeleteNote" role="button"><?php esc_html_e( 'Delete note', 'storesuite' ); ?></a>
				</p>
			</li>
			<?php
			$note_html = ob_get_clean();

			// Send JSON success with the HTML.
			wp_send_json_success(
				array(
					'message'   => __( 'Order note successfully created', 'storesuite' ),
					'note_id'   => absint( $note->id ),
					'note_html' => $note_html,
				)
			);
		}
	}

	/**
	 * Delete order note via ajax.
	 */
	public static function handle_delete_order_note() {
		// Verify the nonce.
		if ( ! isset( $_POST['storesuite_delete_order_note_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['storesuite_delete_order_note_nonce'] ) ), '_storesuite_delete_nonce_' ) ) {
			wp_send_json_error( array( 'error' => __( 'Nonce verification failed', 'storesuite' ) ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) || ! isset( $_POST['note_id'] ) ) {
			wp_send_json_error( array( 'error' => __( 'You do not have permission to perform this action.', 'storesuite' ) ) );
		}

		$note_id = isset( $_POST['note_id'] ) ? absint( $_POST['note_id'] ) : 0;

		// Only comments that are actual order notes may be deleted through this handler.
		$note = $note_id > 0 ? get_comment( $note_id ) : null;
		if ( ! $note || 'order_note' !== $note->comment_type ) {
			wp_send_json_error( array( 'error' => __( 'Invalid order note.', 'storesuite' ) ) );
		}

		$is_deleted = wc_delete_order_note( $note_id );

		if ( ! $is_deleted ) {
			wp_send_json_error( array( 'error' => __( 'Failed to delete order note', 'storesuite' ) ) );
		} else {
			wp_send_json_success( array( 'message' => __( 'Note successfully deleted', 'storesuite' ) ) );
		}
	}

	/**
	 * Add shipping to order
	 */
	public function storesuite_add_shipping_to_order() {
		// Verify nonce
		check_ajax_referer( 'order-item', 'security' );

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( -1 );
		}

		$response = array();

		try {
			$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
			$order    = wc_get_order( $order_id );

			if ( ! $order ) {
				throw new \Exception( __( 'Invalid order', 'storesuite' ) );
			}

			$shipping_method_title = isset( $_POST['shipping_method_title'] ) ? sanitize_text_field( wp_unslash( $_POST['shipping_method_title'] ) ) : __( 'Shipping', 'storesuite' );
			$shipping_method_id    = isset( $_POST['shipping_method'] ) ? sanitize_text_field( wp_unslash( $_POST['shipping_method'] ) ) : '';
			$shipping_cost         = isset( $_POST['shipping_cost'] ) ? (float) sanitize_text_field( wp_unslash( $_POST['shipping_cost'] ) ) : 0;

			// Create shipping item
			$shipping_item = new \WC_Order_Item_Shipping();
			$shipping_item->set_method_title( $shipping_method_title );
			$shipping_item->set_method_id( $shipping_method_id );
			$shipping_item->set_total( $shipping_cost );

			// Add to order
			$order->add_item( $shipping_item );
			$order->calculate_totals();
			$order->save();

			ob_start();
			include WC()->plugin_path() . '/includes/admin/meta-boxes/views/html-order-items.php';
			$response['html'] = ob_get_clean();
		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'error' => $e->getMessage() ) );
		}

		// wp_send_json_success must be outside the try block not to break phpunit tests.
		wp_send_json_success( $response );
	}

	/**
	 * Handle the AJAX request for creating a new order.
	 */
	public function storesuite_create_order() {
		// Verify nonce
		check_ajax_referer( 'order-item', 'security' );

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( -1 );
		}

		$response = array();

		try {
			$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
			$order    = wc_get_order( $order_id );

			if ( ! $order ) {
				throw new \Exception( __( 'Invalid order', 'storesuite' ) );
			}

			// Set customer id.
			if ( isset( $_POST['customer_id'] ) ) {
				$order->set_customer_id( is_numeric( $_POST['customer_id'] ) ? absint( $_POST['customer_id'] ) : 0 );
			}

			// Handle button actions.
			if ( ! empty( $_POST['order_action'] ) ) { // @codingStandardsIgnoreLine

				$action = wc_clean( wp_unslash( $_POST['order_action'] ) ); // @codingStandardsIgnoreLine

				if ( 'send_order_details' === $action ) {
					/**
					 * Fires before an order email is resent.
					 *
					 * @since 1.0.0
					 */
					do_action( 'woocommerce_before_resend_order_emails', $order, 'customer_invoice' );

					// Send the customer invoice email.
					WC()->payment_gateways();
					WC()->shipping();
					WC()->mailer()->customer_invoice( $order );

					// Note the event.
					$order->add_order_note( __( 'Order details manually sent to customer.', 'storesuite' ), false, true );

					/**
					 * Fires after an order email has been resent.
					 *
					 * @since 1.0.0
					 */
					do_action( 'woocommerce_after_resend_order_email', $order, 'customer_invoice' );
				} elseif ( 'send_order_details_admin' === $action ) {
					do_action( 'woocommerce_before_resend_order_emails', $order, 'new_order' );

					WC()->payment_gateways();
					WC()->shipping();
					add_filter( 'woocommerce_new_order_email_allows_resend', '__return_true' );
					WC()->mailer()->emails['WC_Email_New_Order']->trigger( $order->get_id(), $order, true );
					remove_filter( 'woocommerce_new_order_email_allows_resend', '__return_true' );

					do_action( 'woocommerce_after_resend_order_email', $order, 'new_order' );
				} elseif ( 'regenerate_download_permissions' === $action ) {
					$data_store = \WC_Data_Store::load( 'customer-download' );
					$data_store->delete_by_order_id( $order_id );
					wc_downloadable_product_permissions( $order_id, true );
				} elseif ( ! did_action( 'woocommerce_order_action_' . sanitize_title( $action ) ) ) {
					do_action( 'woocommerce_order_action_' . sanitize_title( $action ), $order );
				}
			}

			// Update date.
			if ( empty( $_POST['order_date'] ) ) {
				$date = time();
			} else {
				if ( ! isset( $_POST['order_date_hour'] ) || ! isset( $_POST['order_date_minute'] ) || ! isset( $_POST['order_date_second'] ) ) {
					throw new \Exception( __( 'Order date, hour, minute and/or second are missing.', 'storesuite' ), 400 );
				}
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				$date = gmdate( 'Y-m-d H:i:s', strtotime( sanitize_text_field( wp_unslash( $_POST['order_date'] ) ) . ' ' . absint( $_POST['order_date_hour'] ) . ':' . absint( $_POST['order_date_minute'] ) . ':' . absint( $_POST['order_date_second'] ) ) );
			}

			$order_status = isset( $_POST['order_status'] ) ? sanitize_text_field( wp_unslash( $_POST['order_status'] ) ) : 'wc-pending';

			// Map and set billing address
			$billing_fields = array(
				'first_name',
				'last_name',
				'company',
				'address_1',
				'address_2',
				'city',
				'postcode',
				'country',
				'state',
				'email',
				'phone',
			);

			foreach ( $billing_fields as $field ) {
				$key = '_billing_' . $field;
				if ( isset( $_POST[ $key ] ) ) {
					$setter = "set_billing_{$field}";
					$value  = is_array( $_POST[ $key ] ) ? '' : sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
					if ( method_exists( $order, $setter ) ) {
						$order->$setter( $value );
					}
				}
			}

			// Map and set shipping address
			$shipping_fields = array(
				'first_name',
				'last_name',
				'company',
				'address_1',
				'address_2',
				'city',
				'postcode',
				'country',
				'state',
				'phone',
			);

			foreach ( $shipping_fields as $field ) {
				$key = '_shipping_' . $field;
				if ( isset( $_POST[ $key ] ) ) {
					$setter = "set_shipping_{$field}";
					$value  = is_array( $_POST[ $key ] ) ? '' : sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
					if ( method_exists( $order, $setter ) ) {
						$order->$setter( $value );
					}
				}
			}

			// Set customer note
			if ( isset( $_POST['customer_note'] ) ) {
				$order->set_customer_note( sanitize_textarea_field( wp_unslash( $_POST['customer_note'] ) ) );
			}

			// Set payment method
			if ( isset( $_POST['_payment_method'] ) ) {
				$order->set_payment_method( sanitize_text_field( wp_unslash( $_POST['_payment_method'] ) ) );
			}

			// Set transaction id
			if ( isset( $_POST['_transaction_id'] ) ) {
				$order->set_transaction_id( sanitize_text_field( wp_unslash( $_POST['_transaction_id'] ) ) );
			}

			// Set to order
			$order->set_date_created( $date );
			$order->set_status( $order_status );
			// $order->calculate_totals();
			$order->save();

			ob_start();
			include WC()->plugin_path() . '/includes/admin/meta-boxes/views/html-order-items.php';
			$items_html = ob_get_clean();

			ob_start();
			$notes = wc_get_order_notes( array( 'order_id' => $order_id ) );
			include WC()->plugin_path() . '/includes/admin/meta-boxes/views/html-order-notes.php';
			$notes_html = ob_get_clean();

			$response = array(
				'html'       => $items_html,
				'notes_html' => $notes_html,
			);

			if ( isset( $_POST['context'] ) && sanitize_text_field( wp_unslash( $_POST['context'] ) ) === 'add' ) {
				$response['redirect_url'] = esc_url( storesuite_get_navigation_url( 'edit-order' ) . $order_id );
				$response['message']      = __( 'Order created successfully!', 'storesuite' );
				$response['context']      = 'add';
				$response['is_order_editable'] = $order->is_editable();
			} else {
				$response['message'] = __( 'Order updated successfully!', 'storesuite' );
				$response['context'] = 'edit';
				$response['is_order_editable'] = $order->is_editable();
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'error' => $e->getMessage() ) );
		}

		// wp_send_json_success must be outside the try block not to break phpunit tests.
		wp_send_json_success( $response );
	}

	/**
	 * Handle order bulk actions.
	 */
	public function handle_order_bulk_actions() {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! isset( $_POST['storesuite_bulk_action_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['storesuite_bulk_action_nonce'] ) ), 'storesuite_order_bulk_action' ) ) {
			wp_safe_redirect( storesuite_get_navigation_url( 'orders' ) );
			exit;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_safe_redirect( storesuite_get_navigation_url( 'orders' ) );
			exit;
		}

		if ( ! isset( $_POST['bulk_order_ids'] ) || empty( $_POST['bulk_order_ids'] ) ) {
			wp_safe_redirect( storesuite_get_navigation_url( 'orders' ) );
			exit;
		}

		$action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';

		if ( '-1' === $action || empty( $action ) ) {
			wp_safe_redirect( storesuite_get_navigation_url( 'orders' ) );
			exit;
		}

		$order_ids = array_map( 'absint', wp_unslash( $_POST['bulk_order_ids'] ) );

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}

			if ( 'trash' === $action ) {
				$order->delete();
			} elseif ( 0 === strpos( $action, 'mark_' ) ) {
				// Generic "mark_{status}" handling: valid for any registered
				// order status, so custom statuses (e.g. from an order-status
				// module) work without extending this switch.
				$status = substr( $action, 5 );
				if ( array_key_exists( 'wc-' . $status, wc_get_order_statuses() ) ) {
					$order->update_status( $status );
				}
			}
		}

		// Redirect back.
		wp_safe_redirect( storesuite_get_navigation_url( 'orders' ) );
		exit;
	}
}

