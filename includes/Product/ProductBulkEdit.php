<?php

namespace PluginizeLab\StoreSuite\Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product bulk edit service class.
 */
class ProductBulkEdit {
	/**
	 * The constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_storesuite_bulk_edit_products', array( $this, 'handle_bulk_edit_products_ajax' ) );
		add_action( 'wp_ajax_storesuite_bulk_trash_products', array( $this, 'handle_bulk_trash_products_ajax' ) );
		add_action( 'wp_ajax_storesuite_bulk_delete_products', array( $this, 'handle_bulk_delete_products_ajax' ) );
	}

	/**
	 * AJAX: bulk edit products from the dashboard modal (core bulk_edit_posts + WooCommerce bulk meta).
	 *
	 * @return void
	 */
	public function handle_bulk_edit_products_ajax() {
		check_ajax_referer( 'storesuite_bulk_edit_products', 'security' );

		$post_type_object = get_post_type_object( 'product' );
		if ( ! $post_type_object || ! current_user_can( $post_type_object->cap->edit_posts ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You are not allowed to bulk edit products.', 'storesuite' ),
				),
				403
			);
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- bulk_edit_posts / WC sanitize internally.
		$post_data = wp_unslash( $_POST );

		$result = $this->run_product_bulk_edit( $post_data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
				)
			);
		}

		// Category/tag add/remove operations from the StoreSuite half of the
		// modal, applied to the products core just updated (they passed the
		// capability and lock checks inside bulk_edit_posts()).
		$updated_ids = isset( $result['updated'] ) ? array_map( 'absint', (array) $result['updated'] ) : array();
		$this->apply_bulk_taxonomy_operation( $updated_ids, 'product_cat', 'storesuite_bulk_cat_op', 'storesuite_bulk_cats' );
		$this->apply_bulk_taxonomy_operation( $updated_ids, 'product_tag', 'storesuite_bulk_tag_op', 'storesuite_bulk_tags' );

		wp_send_json_success(
			array(
				'message' => $this->format_bulk_edit_result_message( $result ),
				'updated' => count( $result['updated'] ),
				'skipped' => count( $result['skipped'] ),
				'locked'  => count( $result['locked'] ),
			)
		);
	}

	/**
	 * AJAX: bulk move products to trash from list actions.
	 *
	 * @return void
	 */
	public function handle_bulk_trash_products_ajax() {
		check_ajax_referer( 'storesuite_bulk_trash_products', 'security' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Array sanitized below.
		$product_ids = isset( $_POST['product_ids'] ) ? (array) wp_unslash( $_POST['product_ids'] ) : array();
		$product_ids = array_map( 'absint', $product_ids );
		$product_ids = array_values( array_unique( array_filter( $product_ids ) ) );

		if ( empty( $product_ids ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'No products selected.', 'storesuite' ),
				),
				400
			);
		}

		if ( ! function_exists( 'wp_check_post_lock' ) ) {
			require_once ABSPATH . 'wp-admin/includes/post.php';
		}

		$trashed = 0;
		$locked  = 0;

		foreach ( $product_ids as $post_id ) {
			if ( 'product' !== get_post_type( $post_id ) ) {
				continue;
			}

			if ( ! current_user_can( 'delete_post', $post_id ) ) {
				continue;
			}

			if ( wp_check_post_lock( $post_id ) ) {
				++$locked;
				continue;
			}

			if ( wp_trash_post( $post_id ) ) {
				++$trashed;
			}
		}

		wp_send_json_success(
			array(
				'message' => $this->format_bulk_trash_result_message( $trashed, $locked ),
				'trashed' => $trashed,
				'locked'  => $locked,
			)
		);
	}

	/**
	 * AJAX: bulk delete products permanently from list actions.
	 *
	 * @return void
	 */
	public function handle_bulk_delete_products_ajax() {
		check_ajax_referer( 'storesuite_bulk_delete_products', 'security' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Array sanitized below.
		$product_ids = isset( $_POST['product_ids'] ) ? (array) wp_unslash( $_POST['product_ids'] ) : array();
		$product_ids = array_map( 'absint', $product_ids );
		$product_ids = array_values( array_unique( array_filter( $product_ids ) ) );

		if ( empty( $product_ids ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'No products selected.', 'storesuite' ),
				),
				400
			);
		}

		if ( ! function_exists( 'wp_check_post_lock' ) ) {
			require_once ABSPATH . 'wp-admin/includes/post.php';
		}

		$deleted = 0;
		$locked  = 0;

		foreach ( $product_ids as $post_id ) {
			if ( 'product' !== get_post_type( $post_id ) ) {
				continue;
			}

			if ( ! current_user_can( 'delete_post', $post_id ) ) {
				continue;
			}

			if ( wp_check_post_lock( $post_id ) ) {
				++$locked;
				continue;
			}

			if ( wp_delete_post( $post_id, true ) ) {
				++$deleted;
			}
		}

		wp_send_json_success(
			array(
				'message' => $this->format_bulk_delete_result_message( $deleted, $locked ),
				'deleted' => $deleted,
				'locked'  => $locked,
			)
		);
	}

	/**
	 * Add or remove taxonomy terms on a set of products (bulk edit modal operation).
	 *
	 * @param int[]  $product_ids Product IDs to modify (already capability/lock checked).
	 * @param string $taxonomy    Taxonomy slug (product_cat|product_tag).
	 * @param string $op_key      POST key holding the operation ('' | add | remove).
	 * @param string $terms_key   POST key holding the selected term IDs.
	 * @return void
	 */
	private function apply_bulk_taxonomy_operation( array $product_ids, $taxonomy, $op_key, $terms_key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in handle_bulk_edit_products_ajax().
		$operation = isset( $_POST[ $op_key ] ) ? sanitize_key( wp_unslash( $_POST[ $op_key ] ) ) : '';
		if ( ! in_array( $operation, array( 'add', 'remove' ), true ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in handle_bulk_edit_products_ajax().
		$term_ids = isset( $_POST[ $terms_key ] ) ? array_map( 'absint', (array) wp_unslash( $_POST[ $terms_key ] ) ) : array();
		$term_ids = array_values( array_filter( $term_ids ) );

		if ( empty( $product_ids ) || empty( $term_ids ) ) {
			return;
		}

		foreach ( $product_ids as $product_id ) {
			if ( 'add' === $operation ) {
				wp_set_object_terms( $product_id, $term_ids, $taxonomy, true );
			} else {
				wp_remove_object_terms( $product_id, $term_ids, $taxonomy );
			}

			if ( function_exists( 'wc_delete_product_transients' ) ) {
				wc_delete_product_transients( $product_id );
			}
		}

		/**
		 * After a bulk add/remove of taxonomy terms on products.
		 *
		 * @param int[]  $product_ids Affected product IDs.
		 * @param string $taxonomy    Taxonomy slug.
		 * @param string $operation   'add' or 'remove'.
		 * @param int[]  $term_ids    Term IDs applied.
		 */
		do_action( 'storesuite_bulk_taxonomy_updated', $product_ids, $taxonomy, $operation, $term_ids );
	}

	/**
	 * Run bulk_edit_posts() for product post data.
	 *
	 * @param array<string, mixed> $post_data Unslashed POST-shaped array (must include post[], post_type, etc.).
	 * @return array<string, int[]>|\WP_Error Result array from bulk_edit_posts or error.
	 */
	private function run_product_bulk_edit( array $post_data ) {
		if ( empty( $post_data['post'] ) || ! is_array( $post_data['post'] ) ) {
			return new \WP_Error(
				'storesuite_bulk_no_posts',
				__( 'No products selected.', 'storesuite' )
			);
		}

		require_once ABSPATH . 'wp-admin/includes/post.php';

		if ( ! class_exists( 'WC_Admin_Post_Types', false ) ) {
			require_once WC()->plugin_path() . '/includes/admin/class-wc-admin-post-types.php';
		}

		$done = bulk_edit_posts( $post_data );

		if ( ! is_array( $done ) ) {
			return new \WP_Error(
				'storesuite_bulk_failed',
				__( 'Bulk update could not be completed.', 'storesuite' )
			);
		}

		return $done;
	}

	/**
	 * Human-readable summary for bulk edit counts (matches products list notice copy).
	 *
	 * @param array<string, int[]> $done Return value from bulk_edit_posts().
	 * @return string
	 */
	private function format_bulk_edit_result_message( array $done ) {
		$updated = isset( $done['updated'] ) ? count( $done['updated'] ) : 0;
		$skipped = isset( $done['skipped'] ) ? count( $done['skipped'] ) : 0;
		$locked  = isset( $done['locked'] ) ? count( $done['locked'] ) : 0;

		$parts = array();

		if ( $updated > 0 ) {
			$parts[] = sprintf(
				/* translators: %d: number of products updated */
				_n( '%d product updated.', '%d products updated.', $updated, 'storesuite' ),
				$updated
			);
		}
		if ( $skipped > 0 ) {
			$parts[] = sprintf(
				/* translators: %d: number of products skipped */
				_n(
					'%d product was not updated (permission or invalid data).',
					'%d products were not updated (permission or invalid data).',
					$skipped,
					'storesuite'
				),
				$skipped
			);
		}
		if ( $locked > 0 ) {
			$parts[] = sprintf(
				/* translators: %d: number of products locked by another user */
				_n(
					'%d product not updated, currently being edited by another user.',
					'%d products not updated, currently being edited by another user.',
					$locked,
					'storesuite'
				),
				$locked
			);
		}

		if ( empty( $parts ) ) {
			return __( 'No changes were applied.', 'storesuite' );
		}

		return implode( "\n", $parts );
	}

	/**
	 * Human-readable summary for bulk trash counts.
	 *
	 * @param int $trashed Number of products moved to trash.
	 * @param int $locked  Number of products skipped due to lock.
	 * @return string
	 */
	private function format_bulk_trash_result_message( $trashed, $locked ) {
		$parts = array();

		if ( $trashed > 0 ) {
			$parts[] = sprintf(
				/* translators: %d: number of products moved to trash */
				_n( '%d product moved to trash.', '%d products moved to trash.', $trashed, 'storesuite' ),
				$trashed
			);
		}

		if ( $locked > 0 ) {
			$parts[] = sprintf(
				/* translators: %d: number of products not trashed because another user holds the edit lock */
				_n(
					'%d product was not moved to trash (another user is editing it).',
					'%d products were not moved to trash (another user is editing them).',
					$locked,
					'storesuite'
				),
				$locked
			);
		}

		if ( empty( $parts ) ) {
			return __( 'No changes were applied.', 'storesuite' );
		}

		return implode( "\n", $parts );
	}

	/**
	 * Human-readable summary for bulk permanent-delete counts.
	 *
	 * @param int $deleted Number of products deleted permanently.
	 * @param int $locked  Number of products skipped due to lock.
	 * @return string
	 */
	private function format_bulk_delete_result_message( $deleted, $locked ) {
		$parts = array();

		if ( $deleted > 0 ) {
			$parts[] = sprintf(
				/* translators: %d: number of products deleted permanently */
				_n( '%d product permanently deleted.', '%d products permanently deleted.', $deleted, 'storesuite' ),
				$deleted
			);
		}

		if ( $locked > 0 ) {
			$parts[] = sprintf(
				/* translators: %d: number of products not deleted because another user holds the edit lock */
				_n(
					'%d product was not deleted (another user is editing it).',
					'%d products were not deleted (another user is editing them).',
					$locked,
					'storesuite'
				),
				$locked
			);
		}

		if ( empty( $parts ) ) {
			return __( 'No changes were applied.', 'storesuite' );
		}

		return implode( "\n", $parts );
	}
}
