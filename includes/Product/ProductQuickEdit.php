<?php

namespace PluginizeLab\StoreSuite\Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product quick edit service class.
 */
class ProductQuickEdit {
	/**
	 * The constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_storesuite_product_quick_edit', array( $this, 'handle_product_quick_edit_ajax' ) );
		add_action( 'wp_ajax_storesuite_get_product_quick_edit_form', array( $this, 'handle_get_product_quick_edit_form_ajax' ) );
	}

	/**
	 * AJAX: inline quick edit (`data` map from `[data-field-name]` fields).
	 *
	 * @return void
	 */
	public function handle_product_quick_edit_ajax() {
		check_ajax_referer( 'storesuite_product_quick_edit', 'security' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Field-level sanitization is handled while mapping inline quick edit fields.
		$data = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : null;
		if ( ! is_array( $data ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid request.', 'storesuite' ),
				),
				400
			);
		}

		try {
			/**
			 * Filter inline quick edit payload before validation.
			 *
			 * @param array $data Associative list keyed by `data-field-name`.
			 */
			$data = apply_filters( 'storesuite_update_product_quick_edit_data', $data );

			if ( empty( $data['woocommerce_quick_edit'] ) ) {
				throw new \RuntimeException( esc_html__( 'Invalid quick edit request.', 'storesuite' ), 400 );
			}

			if ( empty( $data['woocommerce_quick_edit_nonce'] ) || ! wp_verify_nonce( sanitize_key( $data['woocommerce_quick_edit_nonce'] ), 'woocommerce_quick_edit_nonce' ) ) {
				throw new \RuntimeException( esc_html__( 'Quick edit session expired. Please try again.', 'storesuite' ), 403 );
			}

			$post_id = isset( $data['ID'] ) ? absint( $data['ID'] ) : 0;
			if ( ! $post_id || 'product' !== get_post_type( $post_id ) ) {
				throw new \RuntimeException( esc_html__( 'Invalid product.', 'storesuite' ), 400 );
			}

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				throw new \RuntimeException( esc_html__( 'You are not allowed to edit this product.', 'storesuite' ), 403 );
			}

			$chosen_cats = isset( $data['chosen_product_cat'] ) ? array_map( 'absint', (array) $data['chosen_product_cat'] ) : array();
			$chosen_cats = array_values( array_filter( $chosen_cats ) );
			if ( empty( $chosen_cats ) ) {
				throw new \RuntimeException( esc_html__( 'Please select a product category.', 'storesuite' ), 422 );
			}

			$save_data = $this->build_save_product_data_from_quick_edit_data( $data );
			$response  = ( new ProductManager() )->storesuite_save_product( $save_data );
			if ( is_wp_error( $response ) ) {
				throw new \RuntimeException( $response->get_error_message(), 422 );
			}
			if ( ! is_int( $response ) ) {
				throw new \RuntimeException( esc_html__( 'Something wrong, please try again later', 'storesuite' ), 422 );
			}

			/**
			 * After a successful dashboard quick edit save.
			 *
			 * @param int   $post_id Product ID.
			 * @param array $data    Submitted inline field map.
			 */
			do_action( 'storesuite_product_quick_edit_updated', $response, $data );

			wp_send_json_success(
				array(
					'message' => __( 'Product updated.', 'storesuite' ),
					'row'     => $this->get_product_list_row_html( $response ),
				)
			);
		} catch ( \Exception $e ) {
			$status = $e->getCode();
			if ( $status < 400 || $status > 599 ) {
				$status = 422;
			}
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
				),
				$status
			);
		}
	}

	/**
	 * Map quick edit field payload to ProductManager::storesuite_save_product() args.
	 *
	 * @param array<string, mixed> $data Inline quick edit field map.
	 * @return array<string, mixed>
	 */
	private function build_save_product_data_from_quick_edit_data( array $data ) {
		$save_data = array(
			'product_id' => isset( $data['ID'] ) ? absint( $data['ID'] ) : 0,
			'post_type'  => isset( $data['product_type'] ) ? sanitize_key( wp_unslash( (string) $data['product_type'] ) ) : 'simple',
		);

		if ( isset( $data['post_title'] ) ) {
			$save_data['product_title'] = sanitize_text_field( wp_unslash( (string) $data['post_title'] ) );
		}
		if ( isset( $data['post_status'] ) ) {
			$save_data['post_status'] = sanitize_key( wp_unslash( (string) $data['post_status'] ) );
		}
		if ( isset( $data['_visibility'] ) ) {
			$save_data['_visibility'] = sanitize_key( wp_unslash( (string) $data['_visibility'] ) );
		}
		if ( isset( $data['sku'] ) ) {
			$save_data['_sku'] = sanitize_text_field( wp_unslash( (string) $data['sku'] ) );
		}
		if ( isset( $data['_regular_price'] ) ) {
			$save_data['regular_price'] =
				$data['_regular_price'] === ''
					? ''
					: wc_format_decimal( wp_unslash( (string) $data['_regular_price'] ) );
		}
		if ( isset( $data['_sale_price'] ) ) {
			$save_data['sale_price'] =
				$data['_sale_price'] === ''
					? ''
					: wc_format_decimal( wp_unslash( (string) $data['_sale_price'] ) );
		}
		if ( isset( $data['weight'] ) ) {
			$save_data['weight'] = wc_format_decimal( wp_unslash( (string) $data['weight'] ) );
		}
		if ( isset( $data['length'] ) ) {
			$save_data['length'] = wc_format_decimal( wp_unslash( (string) $data['length'] ) );
		}
		if ( isset( $data['width'] ) ) {
			$save_data['width'] = wc_format_decimal( wp_unslash( (string) $data['width'] ) );
		}
		if ( isset( $data['height'] ) ) {
			$save_data['height'] = wc_format_decimal( wp_unslash( (string) $data['height'] ) );
		}
		if ( ! empty( $data['manage_stock'] ) ) {
			$save_data['_manage_stock'] = 'yes';
		}
		if ( isset( $data['stock_quantity'] ) ) {
			$save_data['_stock_quantity'] = wc_stock_amount( wp_unslash( (string) $data['stock_quantity'] ) );
		}
		if ( isset( $data['stock_status'] ) ) {
			$save_data['_stock_status'] = sanitize_key( wp_unslash( (string) $data['stock_status'] ) );
		}
		if ( isset( $data['backorders'] ) ) {
			$save_data['_backorders'] = sanitize_key( wp_unslash( (string) $data['backorders'] ) );
		}

		$save_data['comment_status'] = ! empty( $data['reviews_allowed'] ) ? 'open' : 'closed';

		if ( isset( $data['chosen_product_cat'] ) ) {
			$save_data['product_category'] = array_map( 'absint', (array) $data['chosen_product_cat'] );
		}
		if ( isset( $data['product_tag'] ) ) {
			$save_data['product_tags'] = array_map( 'absint', (array) $data['product_tag'] );
		}

		if ( isset( $data['shipping_class_id'] ) ) {
			$shipping_class_slug = sanitize_title( wp_unslash( (string) $data['shipping_class_id'] ) );
			if ( '_no_shipping_class' === $shipping_class_slug ) {
				$save_data['product_shipping_class'] = 0;
			} elseif ( '' !== $shipping_class_slug ) {
				$shipping_class_term = get_term_by( 'slug', $shipping_class_slug, 'product_shipping_class' );
				if ( $shipping_class_term instanceof \WP_Term ) {
					$save_data['product_shipping_class'] = (int) $shipping_class_term->term_id;
				}
			}
		}

		return $save_data;
	}

	/**
	 * AJAX: HTML for the product quick edit form (modal body).
	 *
	 * @return void
	 */
	public function handle_get_product_quick_edit_form_ajax() {
		check_ajax_referer( 'storesuite_product_quick_edit_form', 'security' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- absint after wp_unslash.
		$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		if ( ! $product_id || 'product' !== get_post_type( $product_id ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Invalid product.', 'storesuite' ),
				),
				400
			);
		}

		if ( ! current_user_can( 'edit_post', $product_id ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'You are not allowed to edit this product.', 'storesuite' ),
				),
				403
			);
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Product not found.', 'storesuite' ),
				),
				404
			);
		}

		ob_start();
		storesuite_get_template_part(
			'products/html-product-quick-edit-form',
			'',
			array(
				'product_id'   => $product_id,
				'product'      => $product,
				'product_post' => get_post( $product_id ),
			)
		);
		$html = ob_get_clean();

		wp_send_json_success(
			array(
				'html' => $html,
			)
		);
	}

	/**
	 * HTML for one product table row (AJAX refresh after inline edit).
	 *
	 * @param int $post_id Product ID.
	 * @return string
	 */
	private function get_product_list_row_html( $post_id ) {
		return storesuite_get_product_list_row_html( $post_id );
	}
}
