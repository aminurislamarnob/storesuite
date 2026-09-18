<?php

namespace PluginizeLab\StoreSuite\Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX endpoints that drive the batched order CSV export from the dashboard.
 *
 * Mirrors ProductExportController: a step loop that appends rows to a temp
 * file in the uploads dir, then a download endpoint that streams and unlinks it.
 */
class OrderExportController {

	const EXPORT_NONCE = 'storesuite_order_export';

	const DOWNLOAD_NONCE = 'storesuite-download-order-csv';

	/**
	 * The constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_storesuite_order_export', array( $this, 'handle_order_export' ) );
		add_action( 'wp_ajax_storesuite_download_order_csv', array( $this, 'handle_download' ) );
	}

	/**
	 * AJAX: run one export step.
	 *
	 * @return void
	 */
	public function handle_order_export() {
		check_ajax_referer( self::EXPORT_NONCE, 'security' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'error' => __( 'You do not have permission to perform this action.', 'storesuite' ) ), 403 );
		}

		$step     = isset( $_POST['step'] ) ? absint( wp_unslash( $_POST['step'] ) ) : 1;
		$exporter = new OrderExporter();

		if ( ! empty( $_POST['columns'] ) ) {
			$exporter->set_column_names( wc_clean( wp_unslash( $_POST['columns'] ) ) );
		}

		if ( ! empty( $_POST['selected_columns'] ) ) {
			$exporter->set_columns_to_export( wc_clean( wp_unslash( $_POST['selected_columns'] ) ) );
		}

		if ( ! empty( $_POST['export_statuses'] ) ) {
			$exporter->set_statuses_to_export( wc_clean( wp_unslash( $_POST['export_statuses'] ) ) );
		}

		$date_from = isset( $_POST['date_from'] ) ? wc_clean( wp_unslash( $_POST['date_from'] ) ) : '';
		$date_to   = isset( $_POST['date_to'] ) ? wc_clean( wp_unslash( $_POST['date_to'] ) ) : '';
		$exporter->set_date_range( $date_from, $date_to );

		if ( ! empty( $_POST['export_order_ids'] ) ) {
			$order_ids = array_filter( array_map( 'absint', explode( ',', wc_clean( wp_unslash( $_POST['export_order_ids'] ) ) ) ) );
			$exporter->set_order_ids_to_export( $order_ids );
		}

		if ( ! empty( $_POST['filename'] ) ) {
			$exporter->set_filename( sanitize_file_name( wp_unslash( $_POST['filename'] ) ) );
		}

		$exporter->set_page( $step );
		$exporter->generate_file();

		if ( 100 === $exporter->get_percent_complete() ) {
			wp_send_json_success(
				array(
					'step'       => 'done',
					'percentage' => 100,
					'url'        => add_query_arg(
						array(
							'action'   => 'storesuite_download_order_csv',
							'nonce'    => wp_create_nonce( self::DOWNLOAD_NONCE ),
							'filename' => rawurlencode( $exporter->get_filename() ),
						),
						admin_url( 'admin-ajax.php' )
					),
				)
			);
		}

		wp_send_json_success(
			array(
				'step'       => ++$step,
				'percentage' => $exporter->get_percent_complete(),
				'columns'    => $exporter->get_column_names(),
			)
		);
	}

	/**
	 * AJAX: stream the generated CSV file to the browser.
	 *
	 * @return void
	 */
	public function handle_download() {
		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['nonce'] ) ), self::DOWNLOAD_NONCE ) ) {
			wp_die( esc_html__( 'Security verification failed.', 'storesuite' ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'storesuite' ) );
		}

		$filename = isset( $_GET['filename'] ) ? sanitize_file_name( wp_unslash( $_GET['filename'] ) ) : '';
		if ( empty( $filename ) ) {
			wp_die( esc_html__( 'Invalid export file.', 'storesuite' ) );
		}

		$exporter = new OrderExporter();
		$exporter->set_filename( $filename );
		$exporter->export();
	}
}
