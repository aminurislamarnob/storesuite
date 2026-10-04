<?php

namespace PluginizeLab\StoreSuite\Coupon;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin coupon controller class
 */
class CouponController {
	/**
	 * The constructor.
	 */
	public function __construct() {
		add_action( 'storesuite_load_coupons_template', array( $this, 'load_coupons_template' ) );
		add_action( 'storesuite_dashboard_coupon_add_form', array( $this, 'load_coupon_add_form' ) );
		add_action( 'storesuite_dashboard_coupon_edit_form', array( $this, 'load_coupon_edit_form' ) );
		add_action( 'wp_ajax_storesuite_add_coupon', array( $this, 'handle_add_coupon' ) );
		add_action( 'wp_ajax_storesuite_edit_coupon', array( $this, 'handle_edit_coupon' ) );
		add_action( 'wp_ajax_storesuite_delete_coupon', array( $this, 'handle_delete_coupon' ) );
		add_action( 'wp_ajax_storesuite_duplicate_coupon', array( $this, 'handle_duplicate_coupon' ) );
		add_action( 'storesuite_dashboard_title_after', array( $this, 'render_title_add_coupon_button' ) );
	}

	/**
	 * Render an "Add Coupon" button beside the Coupons page title.
	 *
	 * Hooked on `storesuite_dashboard_title_after`; only renders on the
	 * Coupons list page. Hidden on desktop where the toolbar button is
	 * shown instead (see `assets/frontend/responsive.css`).
	 *
	 * @return void
	 */
	public function render_title_add_coupon_button() {
		$query = pluginizelab_storesuite()->get_storesuite_query();
		if ( ! $query || 'coupons' !== $query->get_current_endpoint() ) {
			return;
		}
		?>
		<a href="<?php echo esc_url( storesuite_get_navigation_url( 'add-new-coupon' ) ); ?>" class="my-storesuite-button storesuite-title-action">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">
				<path d="M23,11H13V1a1,1,0,0,0-1-1h0a1,1,0,0,0-1,1V11H1a1,1,0,0,0-1,1H0a1,1,0,0,0,1,1H11V23a1,1,0,0,0,1,1h0a1,1,0,0,0,1-1V13H23a1,1,0,0,0,1-1h0A1,1,0,0,0,23,11Z"/>
			</svg>
			<?php esc_html_e( 'Add Coupon', 'storesuite' ); ?>
		</a>
		<?php
	}

	/**
	 * Load the coupons list template.
	 *
	 * @param array $query_vars The query variables.
	 *
	 * @return void
	 */
	public function load_coupons_template( $query_vars ) {
		$template_args = array(
			'query_vars' => $query_vars,
		);
		storesuite_get_template_part( 'coupons/coupons', '', $template_args );
	}

	/**
	 * Load the coupon add form template.
	 *
	 * @param array $query_vars The query variables.
	 *
	 * @return void
	 */
	public function load_coupon_add_form( $query_vars ) {
		$template_args = array(
			'query_vars'    => $query_vars,
			'template_type' => 'add-new-coupon',
		);
		storesuite_get_template_part( 'coupons/coupon-form', '', $template_args );
	}

	/**
	 * Load the coupon edit form template.
	 *
	 * @param array $query_vars The query variables.
	 *
	 * @return void
	 */
	public function load_coupon_edit_form( $query_vars ) {
		$template_args = array(
			'query_vars'    => $query_vars,
			'template_type' => 'edit-coupon',
		);
		storesuite_get_template_part( 'coupons/coupon-form', '', $template_args );
	}

	/**
	 * Handle the AJAX request for adding a new coupon.
	 */
	public function handle_add_coupon() {
		// Verify the nonce.
		if ( ! isset( $_POST['storesuite_add_coupon_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['storesuite_add_coupon_nonce'] ) ), '_storesuite_add_coupon_' ) ) {
			wp_send_json_error( array( 'error' => __( 'Nonce verification failed', 'storesuite' ) ) );
		}

		// Check user permissions.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'error' => __( 'You do not have permission to perform this action.', 'storesuite' ) ) );
		}

		// Validate coupon code.
		if ( empty( $_POST['coupon_code'] ) ) {
			wp_send_json_error( array( 'error' => __( 'Coupon code is required.', 'storesuite' ) ) );
		}

		// Check for duplicate coupon code.
		$coupon_code = wc_format_coupon_code( sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) );
		$existing_id = wc_get_coupon_id_by_code( $coupon_code );

		if ( $existing_id ) {
			wp_send_json_error( array( 'error' => __( 'Coupon code already exists. Please choose a different code.', 'storesuite' ) ) );
		}

		// Build sanitized data array.
		$data = $this->sanitize_coupon_data( $_POST );

		$response = ( new CouponManager() )->create_coupon( $data );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'error' => $response->get_error_message() ) );
		}

		if ( is_int( $response ) && $response > 0 ) {
			wp_send_json_success(
				array(
					'message'   => __( 'Coupon successfully created', 'storesuite' ),
					'context'   => 'add',
					'coupon_id' => $response,
				)
			);
		} else {
			wp_send_json_error(
				array(
					'error'   => __( 'Something wrong, please try again later', 'storesuite' ),
					'context' => 'add',
				)
			);
		}
	}

	/**
	 * Handle the AJAX request for editing a coupon.
	 */
	public function handle_edit_coupon() {
		// Verify the nonce.
		if ( ! isset( $_POST['storesuite_edit_coupon_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['storesuite_edit_coupon_nonce'] ) ), '_storesuite_edit_coupon_' ) ) {
			wp_send_json_error( array( 'error' => __( 'Nonce verification failed', 'storesuite' ) ) );
		}

		// Check user permissions.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'error' => __( 'You do not have permission to perform this action.', 'storesuite' ) ) );
		}

		$coupon_id = isset( $_POST['coupon_id'] ) ? absint( $_POST['coupon_id'] ) : 0;

		if ( ! $coupon_id ) {
			wp_send_json_error( array( 'error' => __( 'Invalid coupon ID.', 'storesuite' ) ) );
		}

		// Validate coupon code if provided.
		if ( isset( $_POST['coupon_code'] ) && ! empty( $_POST['coupon_code'] ) ) {
			$coupon_code = wc_format_coupon_code( sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) );
			$existing_id = wc_get_coupon_id_by_code( $coupon_code );

			// Check if the code exists and belongs to a different coupon.
			if ( $existing_id && $existing_id !== $coupon_id ) {
				wp_send_json_error( array( 'error' => __( 'Coupon code already exists. Please choose a different code.', 'storesuite' ) ) );
			}
		}

		// Build sanitized data array.
		$data = $this->sanitize_coupon_data( $_POST );

		$response = ( new CouponManager() )->update_coupon( $coupon_id, $data );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'error' => $response->get_error_message() ) );
		}

		if ( $response ) {
			wp_send_json_success(
				array(
					'message' => __( 'Coupon successfully updated', 'storesuite' ),
					'context' => 'edit',
				)
			);
		} else {
			wp_send_json_error(
				array(
					'error'   => __( 'Something wrong, please try again later', 'storesuite' ),
					'context' => 'edit',
				)
			);
		}
	}

	/**
	 * AJAX: duplicate a coupon as a draft and hand back the edit URL of the copy.
	 *
	 * @return void
	 */
	public function handle_duplicate_coupon() {
		check_ajax_referer( '_storesuite_duplicate_nonce_', 'security' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'error' => __( 'You do not have permission to perform this action.', 'storesuite' ) ), 403 );
		}

		$coupon_id = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
		if ( ! $coupon_id || 'shop_coupon' !== get_post_type( $coupon_id ) ) {
			wp_send_json_error( array( 'error' => __( 'Invalid coupon ID', 'storesuite' ) ), 400 );
		}

		$manager = new CouponManager();
		$new_id  = $manager->duplicate_coupon( $coupon_id );

		if ( is_wp_error( $new_id ) ) {
			wp_send_json_error( array( 'error' => $new_id->get_error_message() ), 400 );
		}

		wp_send_json_success(
			array(
				'message'  => __( 'Coupon duplicated. Opening the copy…', 'storesuite' ),
				'id'       => $new_id,
				'redirect' => storesuite_get_navigation_url( 'edit-coupon' ) . $new_id,
			)
		);
	}

	/**
	 * Handle the AJAX request for deleting a coupon.
	 */
	public function handle_delete_coupon() {
		// Verify the nonce.
		if ( ! isset( $_POST['storesuite_delete_coupon_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['storesuite_delete_coupon_nonce'] ) ), '_storesuite_delete_coupon_' ) ) {
			wp_send_json_error( array( 'error' => __( 'Nonce verification failed', 'storesuite' ) ) );
		}

		// Check user permissions.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'error' => __( 'You do not have permission to perform this action.', 'storesuite' ) ) );
		}

		$coupon_id = isset( $_POST['coupon_id'] ) ? absint( $_POST['coupon_id'] ) : 0;

		if ( ! $coupon_id ) {
			wp_send_json_error( array( 'error' => __( 'Invalid coupon ID.', 'storesuite' ) ) );
		}

		// Use CouponManager so hooks like storesuite_coupon_deleted fire consistently.
		$response = ( new CouponManager() )->delete_coupon( $coupon_id, false ); // Soft delete (move to trash).

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'error' => $response->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Coupon successfully deleted', 'storesuite' ) ) );
	}

	/**
	 * Sanitize coupon data from $_POST.
	 *
	 * @param array $post_data Raw POST data.
	 * @return array Sanitized coupon data.
	 */
	private function sanitize_coupon_data( $post_data ) {
		$data = array();

		// Text fields.
		if ( isset( $post_data['coupon_code'] ) ) {
			$data['coupon_code'] = sanitize_text_field( wp_unslash( $post_data['coupon_code'] ) );
		}
		if ( isset( $post_data['discount_type'] ) ) {
			$data['discount_type'] = sanitize_text_field( wp_unslash( $post_data['discount_type'] ) );
		}
		if ( isset( $post_data['description'] ) ) {
			$data['description'] = sanitize_textarea_field( wp_unslash( $post_data['description'] ) );
		}
		if ( isset( $post_data['expiry_date'] ) ) {
			$data['expiry_date'] = sanitize_text_field( wp_unslash( $post_data['expiry_date'] ) );
		}
		if ( isset( $post_data['coupon_status'] ) ) {
			$data['coupon_status'] = sanitize_key( wp_unslash( $post_data['coupon_status'] ) );
		}
		if ( isset( $post_data['coupon_visibility'] ) ) {
			$data['coupon_visibility'] = sanitize_key( wp_unslash( $post_data['coupon_visibility'] ) );
		}
		if ( isset( $post_data['customer_email'] ) ) {
			$data['customer_email'] = sanitize_text_field( wp_unslash( $post_data['customer_email'] ) );
		}

		// Decimal/price fields.
		if ( isset( $post_data['coupon_amount'] ) ) {
			$data['coupon_amount'] = wc_format_decimal( wp_unslash( $post_data['coupon_amount'] ) );
		}
		if ( isset( $post_data['minimum_amount'] ) ) {
			$data['minimum_amount'] = wc_format_decimal( wp_unslash( $post_data['minimum_amount'] ) );
		}
		if ( isset( $post_data['maximum_amount'] ) ) {
			$data['maximum_amount'] = wc_format_decimal( wp_unslash( $post_data['maximum_amount'] ) );
		}

		// Integer fields.
		if ( isset( $post_data['usage_limit'] ) ) {
			$data['usage_limit'] = absint( $post_data['usage_limit'] );
		}
		if ( isset( $post_data['usage_limit_per_user'] ) ) {
			$data['usage_limit_per_user'] = absint( $post_data['usage_limit_per_user'] );
		}
		if ( isset( $post_data['limit_usage_to_x_items'] ) ) {
			$data['limit_usage_to_x_items'] = absint( $post_data['limit_usage_to_x_items'] );
		}

		// Checkbox/boolean fields (presence indicates true).
		if ( isset( $post_data['individual_use'] ) ) {
			$data['individual_use'] = true;
		}
		if ( isset( $post_data['free_shipping'] ) ) {
			$data['free_shipping'] = true;
		}
		if ( isset( $post_data['exclude_sale_items'] ) ) {
			$data['exclude_sale_items'] = true;
		}

		// Array of IDs.
		if ( isset( $post_data['product_ids'] ) ) {
			$data['product_ids'] = array_map( 'absint', (array) $post_data['product_ids'] );
		}
		if ( isset( $post_data['exclude_product_ids'] ) ) {
			$data['exclude_product_ids'] = array_map( 'absint', (array) $post_data['exclude_product_ids'] );
		}
		if ( isset( $post_data['product_categories'] ) ) {
			$data['product_categories'] = array_map( 'absint', (array) $post_data['product_categories'] );
		}
		if ( isset( $post_data['exclude_product_categories'] ) ) {
			$data['exclude_product_categories'] = array_map( 'absint', (array) $post_data['exclude_product_categories'] );
		}

		return $data;
	}
}
