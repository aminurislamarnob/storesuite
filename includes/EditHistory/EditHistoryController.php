<?php

namespace PluginizeLab\StoreSuite\EditHistory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * History page, per-product history card and the undo AJAX endpoint.
 */
class EditHistoryController {

	const NONCE_ACTION = 'storesuite_edit_history';

	/**
	 * The constructor.
	 */
	public function __construct() {
		add_action( 'storesuite_load_custom_template', array( $this, 'load_history_template' ) );
		add_action( 'storesuite_product_form_after_cards', array( $this, 'render_product_history_card' ), 10, 2 );
		add_action( 'wp_ajax_storesuite_undo_edit_batch', array( $this, 'handle_undo' ) );
	}

	/**
	 * Render the store-wide History page for the `edit-history` endpoint.
	 *
	 * @param array $query_vars Current query vars.
	 * @return void
	 */
	public function load_history_template( $query_vars ) {
		if ( ! isset( $query_vars['edit-history'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			storesuite_get_template_part( 'global/no-permission' );
			return;
		}

		$page     = max( 1, absint( get_query_var( 'paged' ) ) );
		$per_page = (int) apply_filters( 'storesuite_edit_history_per_page', 20 );
		$manager  = new EditHistoryManager();
		$total    = $manager->count_batches();

		storesuite_get_template_part(
			'edit-history/edit-history',
			'',
			array(
				'query_vars'   => $query_vars,
				'batches'      => $manager->get_batches( $page, $per_page ),
				'manager'      => $manager,
				'enabled'      => EditHistoryManager::is_enabled(),
				'total_items'  => $total,
				'total_pages'  => (int) ceil( $total / $per_page ),
				'current_page' => $page,
				'per_page'     => $per_page,
				'nonce'        => wp_create_nonce( self::NONCE_ACTION ),
			)
		);
	}

	/**
	 * Render the "History" card on the edit-product form.
	 *
	 * @param int    $product_id    Product being edited (0 on the add form).
	 * @param string $template_type add-product|edit-product.
	 * @return void
	 */
	public function render_product_history_card( $product_id, $template_type ) {
		if ( 'edit-product' !== $template_type || ! $product_id ) {
			return;
		}

		$manager = new EditHistoryManager();
		$rows    = $manager->get_for_object( 'product', $product_id, 50 );

		if ( empty( $rows ) && ! EditHistoryManager::is_enabled() ) {
			return;
		}

		storesuite_get_template_part(
			'edit-history/product-history-card',
			'',
			array(
				'product_id' => $product_id,
				'rows'       => $rows,
				'manager'    => $manager,
			)
		);
	}

	/**
	 * AJAX: revert a batch.
	 *
	 * @return void
	 */
	public function handle_undo() {
		check_ajax_referer( self::NONCE_ACTION, 'security' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'storesuite' ) ), 403 );
		}

		$batch_id = isset( $_POST['batch_id'] ) ? absint( wp_unslash( $_POST['batch_id'] ) ) : 0;
		$context  = isset( $_POST['context'] ) ? sanitize_key( wp_unslash( $_POST['context'] ) ) : '';

		if ( ! $batch_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'storesuite' ) ), 400 );
		}

		$manager = new EditHistoryManager();
		$result  = $manager->undo( $batch_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		$message = sprintf(
			/* translators: %d: number of changes reverted. */
			_n( '%d change reverted.', '%d changes reverted.', $result['reverted'], 'storesuite' ),
			$result['reverted']
		);
		if ( $result['skipped'] > 0 ) {
			$message .= ' ' . sprintf(
				/* translators: %d: number of changes skipped. */
				_n( '%d change was skipped because the value changed since.', '%d changes were skipped because the values changed since.', $result['skipped'], 'storesuite' ),
				$result['skipped']
			);
		}

		$rows = array();
		if ( in_array( $context, array( 'products', 'inventory' ), true ) && count( $result['object_ids'] ) <= 50 ) {
			foreach ( $result['object_ids'] as $product_id ) {
				$rows[ $product_id ] = storesuite_get_product_list_row_html( $product_id, $context );
			}
		}

		wp_send_json_success(
			array(
				'message'  => $message,
				'reverted' => $result['reverted'],
				'skipped'  => $result['skipped'],
				'batch_id' => $result['batch_id'],
				'rows'     => $rows,
			)
		);
	}

	/**
	 * Build the toast payload returned by an edit endpoint after it recorded a batch.
	 *
	 * @param int $batch_id Batch ID (0 when nothing was recorded).
	 * @return array{batch_id:int,nonce:string}|null
	 */
	public static function undo_payload( $batch_id ) {
		if ( ! $batch_id ) {
			return null;
		}

		return array(
			'batch_id' => (int) $batch_id,
			'nonce'    => wp_create_nonce( self::NONCE_ACTION ),
		);
	}
}
