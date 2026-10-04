<?php

namespace PluginizeLab\StoreSuite\Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin product controller class
 */
class ProductController {
	/**
	 * The constructor.
	 */
	public function __construct() {
		add_action( 'storesuite_load_new_product_template', array( $this, 'load_new_product_template' ) );
		add_action( 'storesuite_load_edit_product_template', array( $this, 'load_edit_product_template' ) );
		add_action( 'storesuite_dashboard_product_add_form', array( $this, 'load_product_form' ) );
		add_action( 'storesuite_dashboard_product_edit_form', array( $this, 'load_product_edit_form' ) );
		add_action( 'wp_ajax_storesuite_add_product_action', array( $this, 'handle_add_product' ) );
		add_action( 'wp_ajax_storesuite_edit_product_action', array( $this, 'handle_edit_product' ) );
		add_action( 'wp_ajax_storesuite_delete_product', array( $this, 'handle_delete_product' ) );
	}
	/**
	 * Load the new product template.
	 *
	 * @param array $query_vars The query variables.
	 *
	 * @return void
	 */
	public function load_new_product_template( $query_vars ) {
		$template_args = array(
			'query_vars' => $query_vars,
		);
		storesuite_get_template_part( 'products/product-icons' );
		storesuite_get_template_part( 'products/add-new-product', '', $template_args );
	}

	/**
	 * Load the edit product template.
	 *
	 * @param array $query_vars The query variables.
	 *
	 * @return void
	 */
	public function load_edit_product_template( $query_vars ) {
		$template_args = array(
			'query_vars' => $query_vars,
		);
		storesuite_get_template_part( 'products/product-icons' );
		storesuite_get_template_part( 'products/edit-product', '', $template_args );
	}

	/**
	 * Load the product add form template.
	 *
	 * @param array $query_vars The query variables.
	 *
	 * @return void
	 */
	public function load_product_form( $query_vars ) {
		$template_args = array(
			'query_vars'    => $query_vars,
			'template_type' => 'add-new-product',
		);
		storesuite_get_template_part( 'products/product-form', '', $template_args );
	}

	/**
	 * Load the product edit form template.
	 *
	 * @param array $query_vars The query variables.
	 *
	 * @return void
	 */
	public function load_product_edit_form( $query_vars ) {
		$template_args = array(
			'query_vars'    => $query_vars,
			'template_type' => 'edit-product',
		);
		storesuite_get_template_part( 'products/product-form', '', $template_args );
	}

	/**
	 * Handle the AJAX request for adding a new product.
	 */
	public function handle_add_product() {

		// Verify the nonce.
		if ( ! isset( $_POST['storesuite_add_product_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['storesuite_add_product_nonce'] ) ), '_storesuite_add_product_' ) ) {
			wp_send_json_error( array( 'error' => __( 'Nonce verification failed', 'storesuite' ) ) );
		}

		// Check user permissions.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'error' => __( 'You do not have permission to perform this action.', 'storesuite' ) ) );
		}

		// Build sanitized data array.
		$data = $this->sanitize_product_data( $_POST );

		$this->abort_if_invalid( $data, 'add' );

		$response = ( new ProductManager() )->storesuite_save_product( $data );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( $response->get_error_message() );
		}

		if ( is_int( $response ) ) {
			wp_send_json_success(
				array(
					'message' => __( 'Product successfully created', 'storesuite' ),
					'context' => 'add',
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
	 * Handle the AJAX request for update a product.
	 */
	public function handle_edit_product() {
		// Verify the nonce.
		if ( ! isset( $_POST['storesuite_edit_product_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['storesuite_edit_product_nonce'] ) ), '_storesuite_edit_product_' ) ) {
			wp_send_json_error( array( 'error' => __( 'Nonce verification failed', 'storesuite' ) ) );
		}

		// Check user permissions.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'error' => __( 'You do not have permission to perform this action.', 'storesuite' ) ) );
		}

		// Build sanitized data array.
		$data = $this->sanitize_product_data( $_POST );

		$this->abort_if_invalid( $data, 'edit' );

		$response = ( new ProductManager() )->storesuite_save_product( $data );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( $response->get_error_message() );
		}

		if ( is_int( $response ) ) {
			$product = wc_get_product( $response );
			wp_send_json_success(
				array(
					'message'   => __( 'Product successfully updated', 'storesuite' ),
					'context'   => 'edit',
					'permalink' => get_permalink( $response ),
					'slug'      => $product ? $product->get_slug() : '',
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
	 * Handle the AJAX request for deleting a product (move to trash).
	 */
	public function handle_delete_product() {
		if ( ! isset( $_POST['storesuite_delete_product_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['storesuite_delete_product_nonce'] ) ), '_storesuite_delete_nonce_' ) ) {
			wp_send_json_error( array( 'error' => __( 'Nonce verification failed', 'storesuite' ) ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'error' => __( 'You do not have permission to perform this action.', 'storesuite' ) ) );
		}

		$product_id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! $product_id ) {
			wp_send_json_error( array( 'error' => __( 'Invalid product ID', 'storesuite' ) ) );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( array( 'error' => __( 'Product not found', 'storesuite' ) ) );
		}

		$result = wp_trash_post( $product_id );
		if ( ! $result ) {
			wp_send_json_error( array( 'error' => __( 'Failed to delete product', 'storesuite' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Product successfully deleted', 'storesuite' ) ) );
	}

	/**
	 * Give integrations a chance to refuse the save before anything is written.
	 *
	 * Runs after the nonce / capability checks and sanitisation. A WP_Error
	 * returned by the filter ends the request with every error message listed,
	 * so the product is neither created nor updated.
	 *
	 * @param array  $data    Sanitised product data.
	 * @param string $context 'add' or 'edit'.
	 * @return void
	 */
	private function abort_if_invalid( array $data, string $context ) {
		/**
		 * Filters the pre-save validation result for the product form.
		 *
		 * Return a WP_Error to abort the save. Add one message per failing
		 * field; they are shown as a list in the form's error dialog.
		 *
		 * @param \WP_Error|null $error   Null to allow the save.
		 * @param array          $data    Sanitised product data.
		 * @param string         $context 'add' or 'edit'.
		 */
		$error = apply_filters( 'storesuite_product_pre_save_validation', null, $data, $context );

		if ( ! is_wp_error( $error ) || ! $error->has_errors() ) {
			return;
		}

		$messages = $error->get_error_messages();

		wp_send_json_error(
			array(
				'error'   => 1 === count( $messages ) ? $messages[0] : __( 'Please fix the following before saving:', 'storesuite' ),
				'errors'  => $messages,
				'context' => $context,
			)
		);
	}

	/**
	 * Sanitize product data from $_POST.
	 *
	 * @param array $post_data Raw POST data.
	 * @return array Sanitized product data.
	 */
	private function sanitize_product_data( $post_data ) {
		$data = array();

		// Product ID for updates.
		if ( isset( $post_data['product_id'] ) ) {
			$data['product_id'] = absint( $post_data['product_id'] );
		}

		// Text fields.
		if ( isset( $post_data['product_title'] ) ) {
			$data['product_title'] = sanitize_text_field( wp_unslash( $post_data['product_title'] ) );
		}
		if ( isset( $post_data['product_slug'] ) ) {
			$data['product_slug'] = sanitize_title( wp_unslash( $post_data['product_slug'] ) );
		}
		if ( isset( $post_data['post_type'] ) ) {
			$data['post_type'] = sanitize_key( wp_unslash( $post_data['post_type'] ) );
		}
		if ( isset( $post_data['post_status'] ) ) {
			$data['post_status'] = sanitize_key( wp_unslash( $post_data['post_status'] ) );
		}
		if ( isset( $post_data['_visibility'] ) ) {
			$data['_visibility'] = sanitize_key( wp_unslash( $post_data['_visibility'] ) );
		}
		if ( isset( $post_data['_sku'] ) ) {
			$data['_sku'] = sanitize_text_field( wp_unslash( $post_data['_sku'] ) );
		}
		if ( isset( $post_data['_global_unique_id'] ) ) {
			$data['_global_unique_id'] = sanitize_text_field( wp_unslash( $post_data['_global_unique_id'] ) );
		}
		if ( isset( $post_data['_stock_status'] ) ) {
			$data['_stock_status'] = sanitize_key( wp_unslash( $post_data['_stock_status'] ) );
		}
		if ( isset( $post_data['_backorders'] ) ) {
			$data['_backorders'] = sanitize_key( wp_unslash( $post_data['_backorders'] ) );
		}
		if ( isset( $post_data['comment_status'] ) ) {
			$data['comment_status'] = sanitize_key( wp_unslash( $post_data['comment_status'] ) );
		}

		// HTML content fields.
		if ( isset( $post_data['product_description'] ) ) {
			$data['product_description'] = wp_kses_post( wp_unslash( $post_data['product_description'] ) );
		}
		if ( isset( $post_data['product_short_description'] ) ) {
			$data['product_short_description'] = wp_kses_post( wp_unslash( $post_data['product_short_description'] ) );
		}
		if ( isset( $post_data['_purchase_note'] ) ) {
			$data['_purchase_note'] = wp_kses_post( wp_unslash( $post_data['_purchase_note'] ) );
		}

		// Price/decimal fields.
		if ( isset( $post_data['regular_price'] ) ) {
			$data['regular_price'] = $post_data['regular_price'] === '' ? '' : wc_format_decimal( wp_unslash( $post_data['regular_price'] ) );
		}
		if ( isset( $post_data['sale_price'] ) ) {
			$data['sale_price'] = $post_data['sale_price'] === '' ? '' : wc_format_decimal( wp_unslash( $post_data['sale_price'] ) );
		}
		if ( isset( $post_data['weight'] ) ) {
			$data['weight'] = wc_format_decimal( wp_unslash( $post_data['weight'] ) );
		}
		if ( isset( $post_data['length'] ) ) {
			$data['length'] = wc_format_decimal( wp_unslash( $post_data['length'] ) );
		}
		if ( isset( $post_data['width'] ) ) {
			$data['width'] = wc_format_decimal( wp_unslash( $post_data['width'] ) );
		}
		if ( isset( $post_data['height'] ) ) {
			$data['height'] = wc_format_decimal( wp_unslash( $post_data['height'] ) );
		}

		// Integer fields.
		if ( isset( $post_data['product_thumbnail_id'] ) ) {
			$data['product_thumbnail_id'] = absint( $post_data['product_thumbnail_id'] );
		}
		if ( isset( $post_data['menu_order'] ) ) {
			$data['menu_order'] = absint( $post_data['menu_order'] );
		}
		if ( isset( $post_data['_stock_quantity'] ) ) {
			$data['_stock_quantity'] = wc_stock_amount( wp_unslash( $post_data['_stock_quantity'] ) );
		}
		if ( isset( $post_data['_low_stock_amount'] ) ) {
			$data['_low_stock_amount'] = wc_stock_amount( wp_unslash( $post_data['_low_stock_amount'] ) );
		}
		if ( isset( $post_data['product_shipping_class'] ) ) {
			$data['product_shipping_class'] = absint( $post_data['product_shipping_class'] );
		}

		// Checkbox/boolean fields.
		if ( isset( $post_data['_manage_stock'] ) ) {
			$data['_manage_stock'] = sanitize_key( wp_unslash( $post_data['_manage_stock'] ) );
		}
		if ( isset( $post_data['_sold_individually'] ) ) {
			$data['_sold_individually'] = sanitize_key( wp_unslash( $post_data['_sold_individually'] ) );
		}
		if ( isset( $post_data['_featured'] ) ) {
			$data['_featured'] = sanitize_key( wp_unslash( $post_data['_featured'] ) );
		}
		if ( isset( $post_data['_virtual'] ) ) {
			$data['_virtual'] = sanitize_key( wp_unslash( $post_data['_virtual'] ) );
		}
		if ( isset( $post_data['_downloadable'] ) ) {
			$data['_downloadable'] = sanitize_key( wp_unslash( $post_data['_downloadable'] ) );
		}
		if ( isset( $post_data['_product_url'] ) ) {
			$data['_product_url'] = esc_url_raw( wp_unslash( $post_data['_product_url'] ) );
		}
		if ( isset( $post_data['_button_text'] ) ) {
			$data['_button_text'] = sanitize_text_field( wp_unslash( $post_data['_button_text'] ) );
		}
		if ( isset( $post_data['_cogs_value'] ) ) {
			$data['_cogs_value'] = wc_clean( wp_unslash( $post_data['_cogs_value'] ) );
		}
		$data['_visible_in_pos'] = isset( $post_data['_visible_in_pos'] ) && 'yes' === wc_clean( wp_unslash( $post_data['_visible_in_pos'] ) );

		// Date fields.
		if ( isset( $post_data['_sale_price_dates_from'] ) ) {
			$data['_sale_price_dates_from'] = sanitize_text_field( wp_unslash( $post_data['_sale_price_dates_from'] ) );
		}
		if ( isset( $post_data['_sale_price_dates_to'] ) ) {
			$data['_sale_price_dates_to'] = sanitize_text_field( wp_unslash( $post_data['_sale_price_dates_to'] ) );
		}

		// Gallery images - comma-separated IDs.
		if ( isset( $post_data['product_image_gallery'] ) ) {
			$data['product_image_gallery'] = sanitize_text_field( wp_unslash( $post_data['product_image_gallery'] ) );
		}

		// Array of IDs.
		if ( isset( $post_data['product_category'] ) ) {
			$data['product_category'] = array_map( 'absint', (array) $post_data['product_category'] );
		}
		if ( isset( $post_data['product_brand'] ) ) {
			$data['product_brand'] = array_map( 'absint', (array) $post_data['product_brand'] );
		}
		if ( isset( $post_data['product_tags'] ) ) {
			$data['product_tags'] = array_map( 'absint', (array) $post_data['product_tags'] );
		}
		if ( isset( $post_data['upsell_ids'] ) ) {
			$data['upsell_ids'] = array_map( 'absint', (array) $post_data['upsell_ids'] );
		}
		if ( isset( $post_data['crosssell_ids'] ) ) {
			$data['crosssell_ids'] = array_map( 'absint', (array) $post_data['crosssell_ids'] );
		}
		if ( isset( $post_data['grouped_products'] ) ) {
			$data['grouped_products'] = array_map( 'absint', (array) $post_data['grouped_products'] );
		}

		// Those are sanitized inside prepare_downloads.
		$data['downloads'] = self::prepare_downloads(
			isset( $post_data['_wc_file_names'] ) ? wp_unslash( $post_data['_wc_file_names'] ) : array(), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			isset( $post_data['_wc_file_urls'] ) ? wp_unslash( $post_data['_wc_file_urls'] ) : array(), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			isset( $post_data['_wc_file_hashes'] ) ? wp_unslash( $post_data['_wc_file_hashes'] ) : array() // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		);

		if ( isset( $post_data['_download_limit'] ) ) {
			$limit               = wc_clean( wp_unslash( $post_data['_download_limit'] ) );
			$data['_download_limit'] = '' === $limit ? '' : absint( $limit );
		}
		if ( isset( $post_data['_download_expiry'] ) ) {
			$expiry                   = wc_clean( wp_unslash( $post_data['_download_expiry'] ) );
			$data['_download_expiry'] = '' === $expiry ? '' : absint( $expiry );
		}

		// Attributes (when the attributes section was submitted with the form).
		if ( isset( $post_data['storesuite_attributes_submitted'] ) ) {
			$data['attributes'] = $this->prepare_attributes_from_post( $post_data );
		}

		// Custom fields rendered by an integration (inputs named storesuite_acf[<field_key>]).
		// Nothing is accepted unless an integration sanitises it, so with no
		// integration active the posted values are dropped on the floor.
		if ( isset( $post_data['storesuite_acf'] ) && is_array( $post_data['storesuite_acf'] ) ) {
			$raw_acf = wp_unslash( $post_data['storesuite_acf'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitised per field type by the filter below.

			/**
			 * Filters the sanitised ACF values handed to the product manager.
			 *
			 * @param array $sanitized  Sanitised values keyed by ACF field key. Empty by default.
			 * @param array $raw        Raw (unslashed) posted values keyed by ACF field key.
			 * @param int   $product_id Product being edited, 0 when adding.
			 */
			$data['storesuite_acf'] = (array) apply_filters( 'storesuite_sanitize_acf_fields', array(), $raw_acf, isset( $data['product_id'] ) ? $data['product_id'] : 0 );
		}

		return $data;
	}

	/**
	 * Build a product attributes array from posted form fields.
	 *
	 * Attribute changes are persisted as part of the product form submission.
	 *
	 * @param array $post_data Raw POST data.
	 * @return array Prepared attributes keyed for WC_Product::set_attributes().
	 */
	private function prepare_attributes_from_post( $post_data ) {
		$attribute_names  = isset( $post_data['attribute_names'] ) ? stripslashes_deep( (array) $post_data['attribute_names'] ) : array();
		$attribute_values = isset( $post_data['attribute_values'] ) ? stripslashes_deep( (array) $post_data['attribute_values'] ) : array();

		// No attribute rows submitted: return an empty set (clears existing
		// attributes). Avoids passing empty arrays into WC's prepare_attributes(),
		// where max( array_keys( $attribute_names ) ) would throw on PHP 8.
		if ( empty( $attribute_names ) ) {
			return array();
		}

		// Custom (non-taxonomy) attributes must be a "|"-separated string so
		// WC treats the values as text, not term IDs.
		if ( ! empty( $attribute_names ) && ! empty( $attribute_values ) ) {
			foreach ( $attribute_names as $index => $name ) {
				if ( empty( $name ) || ! isset( $attribute_values[ $index ] ) ) {
					continue;
				}

				if ( 0 === strpos( $name, 'pa_' ) ) {
					continue;
				}

				if ( is_array( $attribute_values[ $index ] ) ) {
					$clean_values = array();
					foreach ( $attribute_values[ $index ] as $val ) {
						$val = wc_clean( wp_unslash( $val ) );
						if ( '' !== $val ) {
							$clean_values[] = $val;
						}
					}

					$attribute_values[ $index ] = implode( ' | ', $clean_values );
				}
			}
		}

		$data = array(
			'attribute_names'      => $attribute_names,
			'attribute_values'     => $attribute_values,
			'attribute_visibility' => isset( $post_data['attribute_visibility'] ) ? stripslashes_deep( (array) $post_data['attribute_visibility'] ) : array(),
			'attribute_variation'  => isset( $post_data['attribute_variation'] ) ? stripslashes_deep( (array) $post_data['attribute_variation'] ) : array(),
			'attribute_position'   => isset( $post_data['attribute_position'] ) ? stripslashes_deep( (array) $post_data['attribute_position'] ) : array(),
		);

		return \WC_Meta_Box_Product_Data::prepare_attributes( $data );
	}

	/**
	 * Prepare downloads for save.
	 *
	 * @param array $file_names File names.
	 * @param array $file_urls File urls.
	 * @param array $file_hashes File hashes.
	 *
	 * @return array
	 */
	private static function prepare_downloads( $file_names, $file_urls, $file_hashes ) {
		$downloads = array();

		if ( ! empty( $file_urls ) ) {
			$file_url_size = count( $file_urls );

			for ( $i = 0; $i < $file_url_size; $i++ ) {
				if ( ! empty( $file_urls[ $i ] ) ) {
					$downloads[] = array(
						'name'        => wc_clean( $file_names[ $i ] ),
						'file'        => wp_unslash( trim( $file_urls[ $i ] ) ),
						'download_id' => wc_clean( $file_hashes[ $i ] ),
					);
				}
			}
		}
		return $downloads;
	}
}
