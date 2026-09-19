<?php

namespace PluginizeLab\StoreSuite\Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product inline cell edit service class.
 *
 * Saves a single field edited directly inside a products list table cell
 * (price, stock quantity, status or SKU) and returns the re-rendered row.
 *
 * This is a deliberate partial-update path: it writes only the edited field
 * through the WooCommerce CRUD layer so an inline edit can never wipe data
 * the full product form would have posted.
 */
class ProductInlineEdit {

	/**
	 * Nonce action for the inline cell edit AJAX request.
	 */
	const NONCE_ACTION = 'storesuite_product_inline_cell_edit';

	/**
	 * Post statuses an inline status edit may switch between.
	 *
	 * @var string[]
	 */
	const EDITABLE_STATUSES = array( 'publish', 'draft', 'pending' );

	/**
	 * The constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_storesuite_product_inline_cell_edit', array( $this, 'handle_inline_cell_edit_ajax' ) );
	}

	/**
	 * Product types whose price fields may be edited inline.
	 *
	 * Variable and grouped products derive their displayed price from children,
	 * so their price cells stay read-only.
	 *
	 * @return string[]
	 */
	public static function get_price_editable_types() {
		/**
		 * Filter the product types whose price can be edited inline in the products list.
		 *
		 * @param string[] $types Product type slugs.
		 */
		return apply_filters( 'storesuite_inline_edit_price_types', array( 'simple', 'external' ) );
	}

	/**
	 * AJAX: save one inline-edited cell and return the refreshed row.
	 *
	 * @return void
	 */
	public function handle_inline_cell_edit_ajax() {
		check_ajax_referer( self::NONCE_ACTION, 'security' );

		$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		$field      = isset( $_POST['field'] ) ? sanitize_key( wp_unslash( $_POST['field'] ) ) : '';
		$context    = isset( $_POST['context'] ) ? sanitize_key( wp_unslash( $_POST['context'] ) ) : 'products';

		try {
			if ( ! $product_id || 'product' !== get_post_type( $product_id ) ) {
				throw new \RuntimeException( esc_html__( 'Invalid product.', 'storesuite' ), 400 );
			}

			if ( ! current_user_can( 'edit_post', $product_id ) ) {
				throw new \RuntimeException( esc_html__( 'You are not allowed to edit this product.', 'storesuite' ), 403 );
			}

			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				throw new \RuntimeException( esc_html__( 'Product not found.', 'storesuite' ), 404 );
			}

			switch ( $field ) {
				case 'price':
					$this->apply_price( $product );
					break;

				case 'stock_quantity':
					$this->apply_stock_quantity( $product );
					break;

				case 'stock_status':
					$this->apply_stock_status( $product );
					break;

				case 'status':
					$this->apply_status( $product );
					break;

				case 'sku':
					$this->apply_sku( $product );
					break;

				default:
					throw new \RuntimeException( esc_html__( 'This field cannot be edited inline.', 'storesuite' ), 400 );
			}

			$product->save();

			/**
			 * After a successful inline cell edit save.
			 *
			 * @param int    $product_id Product ID.
			 * @param string $field      Edited field key.
			 */
			do_action( 'storesuite_product_inline_cell_edited', $product_id, $field );
		} catch ( \WC_Data_Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
				),
				422
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

		// Deliberately outside the try/catch: wp_die() inside wp_send_json_*
		// is replaced with an exception by the AJAX test framework, and the
		// success response must not be swallowed by our own catch block.
		wp_send_json_success(
			array(
				'message' => __( 'Product updated.', 'storesuite' ),
				'row'     => storesuite_get_product_list_row_html( $product_id, $context ),
			)
		);
	}

	/**
	 * Apply an inline regular/sale price edit.
	 *
	 * @param \WC_Product $product Product being edited.
	 * @return void
	 * @throws \RuntimeException When the type does not support it or the values are invalid.
	 */
	private function apply_price( $product ) {
		if ( ! $product->is_type( self::get_price_editable_types() ) ) {
			throw new \RuntimeException( esc_html__( 'The price of this product type cannot be edited inline.', 'storesuite' ), 400 );
		}

		$regular = $this->read_price_field( 'regular_price' );
		$sale    = $this->read_price_field( 'sale_price' );

		if ( '' !== $regular && '' !== $sale && (float) $sale >= (float) $regular ) {
			throw new \RuntimeException( esc_html__( 'The sale price must be lower than the regular price.', 'storesuite' ), 422 );
		}

		$product->set_regular_price( $regular );
		$product->set_sale_price( $sale );
	}

	/**
	 * Read and normalise one posted price value.
	 *
	 * @param string $key POST key.
	 * @return string Decimal string, or '' to clear the price.
	 * @throws \RuntimeException When the value is not a valid number.
	 */
	private function read_price_field( $key ) {
		$raw = isset( $_POST[ $key ] ) ? wc_clean( wp_unslash( $_POST[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in handle_inline_cell_edit_ajax().

		if ( '' === trim( (string) $raw ) ) {
			return '';
		}

		$value = wc_format_decimal( $raw );
		if ( '' === $value || ! is_numeric( $value ) || (float) $value < 0 ) {
			/* translators: %s: submitted value. */
			throw new \RuntimeException( sprintf( esc_html__( '"%s" is not a valid price.', 'storesuite' ), esc_html( (string) $raw ) ), 422 );
		}

		return $value;
	}

	/**
	 * Apply an inline stock quantity edit.
	 *
	 * @param \WC_Product $product Product being edited.
	 * @return void
	 * @throws \RuntimeException When the product does not manage stock at product level.
	 */
	private function apply_stock_quantity( $product ) {
		if ( ! $product->managing_stock() || $product->is_type( 'variable' ) ) {
			throw new \RuntimeException( esc_html__( 'Stock is not managed at product level for this product.', 'storesuite' ), 400 );
		}

		$raw = isset( $_POST['value'] ) ? wc_clean( wp_unslash( $_POST['value'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in handle_inline_cell_edit_ajax().
		if ( '' === trim( (string) $raw ) || ! is_numeric( $raw ) ) {
			throw new \RuntimeException( esc_html__( 'Please enter a valid stock quantity.', 'storesuite' ), 422 );
		}

		$product->set_stock_quantity( wc_stock_amount( $raw ) );
	}

	/**
	 * Apply an inline stock status edit.
	 *
	 * Only meaningful when stock is not managed by quantity — with managed
	 * stock WooCommerce derives the status from the quantity on save.
	 *
	 * @param \WC_Product $product Product being edited.
	 * @return void
	 * @throws \RuntimeException When the product manages stock or the status is invalid.
	 */
	private function apply_stock_status( $product ) {
		if ( $product->managing_stock() ) {
			throw new \RuntimeException( esc_html__( 'Stock status is derived from the stock quantity for this product.', 'storesuite' ), 400 );
		}

		$value = isset( $_POST['value'] ) ? sanitize_key( wp_unslash( $_POST['value'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in handle_inline_cell_edit_ajax().

		if ( ! array_key_exists( $value, wc_get_product_stock_status_options() ) ) {
			throw new \RuntimeException( esc_html__( 'Invalid stock status.', 'storesuite' ), 422 );
		}

		$product->set_stock_status( $value );
	}

	/**
	 * Apply an inline status edit.
	 *
	 * @param \WC_Product $product Product being edited.
	 * @return void
	 * @throws \RuntimeException When the requested status is not allowed.
	 */
	private function apply_status( $product ) {
		$value = isset( $_POST['value'] ) ? sanitize_key( wp_unslash( $_POST['value'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in handle_inline_cell_edit_ajax().

		if ( ! in_array( $value, self::EDITABLE_STATUSES, true ) ) {
			throw new \RuntimeException( esc_html__( 'Invalid product status.', 'storesuite' ), 422 );
		}

		$product->set_status( $value );
	}

	/**
	 * Apply an inline SKU edit.
	 *
	 * @param \WC_Product $product Product being edited.
	 * @return void
	 * @throws \WC_Data_Exception When the SKU is already used by another product.
	 */
	private function apply_sku( $product ) {
		$value = isset( $_POST['value'] ) ? wc_clean( wp_unslash( $_POST['value'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in handle_inline_cell_edit_ajax().

		$product->set_sku( $value );
	}
}
