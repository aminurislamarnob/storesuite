<?php
/**
 * Custom Order Statuses — AJAX endpoints.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Modules\OrderStatuses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Save/delete custom statuses. Gated on the orders management capability, since
 * order statuses are an orders concern.
 */
class AjaxController {

	const NONCE = 'storesuite_order_statuses';

	/**
	 * Hook handlers. Called from Module::boot().
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp_ajax_storesuite_save_order_status', array( $this, 'save' ) );
		add_action( 'wp_ajax_storesuite_delete_order_status', array( $this, 'delete' ) );
	}

	/**
	 * @return void
	 */
	private function guard() {
		if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['security'] ) ), self::NONCE ) ) {
			wp_send_json_error( array( 'error' => __( 'Security check failed. Please reload and try again.', 'storesuite' ) ) );
		}
		if ( ! storesuite_current_user_can( 'orders' ) ) {
			wp_send_json_error( array( 'error' => __( 'You do not have permission to manage order statuses.', 'storesuite' ) ) );
		}
	}

	/**
	 * POST — create/update a status. Flags a rewrite/registration refresh via a
	 * one-off option so the new post status registers next request.
	 *
	 * @return void
	 */
	public function save() {
		$this->guard();

		$transitions = isset( $_POST['transitions'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['transitions'] ) ) : array();

		$result = StatusRepository::save(
			array(
				'slug'        => isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '',
				'label'       => isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '',
				'color'       => isset( $_POST['color'] ) ? sanitize_text_field( wp_unslash( $_POST['color'] ) ) : '',
				'is_paid'     => ! empty( $_POST['is_paid'] ),
				'in_reports'  => ! empty( $_POST['in_reports'] ),
				'transitions' => $transitions,
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'error' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Status saved.', 'storesuite' ),
				'slug'    => $result,
			)
		);
	}

	/**
	 * POST — delete a status, reassigning its orders.
	 *
	 * @return void
	 */
	public function delete() {
		$this->guard();

		$slug     = isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '';
		$fallback = isset( $_POST['fallback'] ) ? sanitize_text_field( wp_unslash( $_POST['fallback'] ) ) : 'on-hold';

		$result = StatusRepository::delete( $slug, $fallback );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'error' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Status deleted.', 'storesuite' ) ) );
	}
}
