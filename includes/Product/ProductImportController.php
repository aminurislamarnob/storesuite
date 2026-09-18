<?php

namespace PluginizeLab\StoreSuite\Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Drives WooCommerce's product CSV import wizard on the StoreSuite frontend dashboard.
 *
 * Renders the wizard at the `import-products` endpoint, processes the upload step early (on
 * template_redirect, since the dashboard shortcode renders too late to send redirect headers), and
 * handles the AJAX batch import. Mirrors Dokan Pro's export-import module, minus the multi-vendor logic
 * (StoreSuite shop managers have manage_woocommerce and import all products normally).
 */
class ProductImportController {

	/**
	 * The constructor.
	 */
	public function __construct() {
		add_action( 'storesuite_load_import_products_template', array( $this, 'render' ) );
		add_action( 'template_redirect', array( $this, 'handle_step_submission' ) );
		// Priority 0 so this runs before WooCommerce's own admin-ajax handler (admin-ajax is admin context,
		// so WC_Admin_Importers also hooks this action). The referer guard defers wp-admin requests to WC.
		add_action( 'wp_ajax_woocommerce_do_ajax_product_import', array( $this, 'handle_ajax_import' ), 0 );
	}

	/**
	 * Render the import wizard page inside the dashboard shell.
	 *
	 * @param array $query_vars The query variables.
	 *
	 * @return void
	 */
	public function render( $query_vars ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			storesuite_get_template_part( 'global/no-permission' );
			return;
		}

		storesuite_get_template_part(
			'products/import-products',
			'',
			array(
				'query_vars' => $query_vars,
			)
		);
	}

	/**
	 * Process the upload step early so its redirect can fire before output starts.
	 *
	 * @return void
	 */
	public function handle_step_submission() {
		if ( ! storesuite_is_endpoint_url( 'import-products' ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// Only the upload step has a server-side handler (it redirects to the mapping step). The upload form
		// posts to the current URL, where `step` is unset or "upload"; later steps must not trigger it.
		$step = isset( $_REQUEST['step'] ) ? sanitize_key( wp_unslash( $_REQUEST['step'] ) ) : 'upload'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified below.
		if ( 'upload' !== $step || empty( $_REQUEST['save_step'] ) ) {
			return;
		}

		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'woocommerce-csv-importer' ) ) {
			return;
		}

		include_once ABSPATH . 'wp-admin/includes/file.php';

		$wizard = new ProductImportWizard();
		$wizard->upload_form_handler(); // Redirects to the mapping step and exits on success.
	}

	/**
	 * AJAX: run a single import batch step.
	 *
	 * @return void
	 */
	public function handle_ajax_import() {
		global $wpdb;

		check_ajax_referer( 'wc-product-import', 'security' );

		// Only process requests from the frontend dashboard; defer wp-admin requests to WooCommerce's handler.
		if ( substr( (string) wp_get_referer(), 0, strlen( get_admin_url() ) ) === get_admin_url() ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to import products.', 'storesuite' ) ) );
		}

		if ( empty( $_POST['file'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file found for product import.', 'storesuite' ) ) );
		}

		$file = wc_clean( wp_unslash( $_POST['file'] ) );

		include_once WC_ABSPATH . 'includes/import/class-wc-product-csv-importer.php';

		$params = array(
			'delimiter'          => ! empty( $_POST['delimiter'] ) ? wc_clean( wp_unslash( $_POST['delimiter'] ) ) : ',',
			'start_pos'          => isset( $_POST['position'] ) ? absint( wp_unslash( $_POST['position'] ) ) : 0,
			'mapping'            => isset( $_POST['mapping'] ) ? (array) wc_clean( wp_unslash( $_POST['mapping'] ) ) : array(),
			'update_existing'    => isset( $_POST['update_existing'] ) && (bool) $_POST['update_existing'],
			'character_encoding' => isset( $_POST['character_encoding'] ) ? wc_clean( wp_unslash( $_POST['character_encoding'] ) ) : '',
			'lines'              => apply_filters( 'woocommerce_product_import_batch_size', 30 ),
			'parse'              => true,
		);

		// Accumulate the error log across batches (reset on the first batch).
		$error_log = 0 !== $params['start_pos'] ? array_filter( (array) get_user_option( 'product_import_error_log' ) ) : array();

		try {
			ProductImportWizard::validate_import_file( $file );

			// get_importer() (not `new`) so the woocommerce_product_csv_importer_class/args filters apply.
			$importer         = ProductImportWizard::get_importer( $file, $params );
			$results          = $importer->import();
			$percent_complete = $importer->get_percent_complete();
		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}

		$error_log = array_merge( $error_log, $results['failed'], $results['skipped'] );

		update_user_option( get_current_user_id(), 'product_import_error_log', $error_log );

		$counts = array(
			'imported'            => count( $results['imported'] ),
			'imported_variations' => isset( $results['imported_variations'] ) ? count( (array) $results['imported_variations'] ) : 0,
			'failed'              => count( $results['failed'] ),
			'updated'             => count( $results['updated'] ),
			'skipped'             => count( $results['skipped'] ),
		);

		if ( 100 === $percent_complete ) {
			$this->cleanup_import( $wpdb );

			// wc-product-import.js appends the accumulated products-imported/updated/failed/skipped counts
			// to this URL, so we only add the step + nonce here.
			$url = add_query_arg(
				array(
					'step'     => 'done',
					'_wpnonce' => wp_create_nonce( 'woocommerce-csv-importer' ),
				),
				storesuite_get_navigation_url( 'import-products' )
			);

			wp_send_json_success(
				array_merge(
					array(
						'position'   => 'done',
						'percentage' => 100,
						'url'        => $url,
					),
					$counts
				)
			);
		}

		wp_send_json_success(
			array_merge(
				array(
					'position'   => $importer->get_file_position(),
					'percentage' => $percent_complete,
				),
				$counts
			)
		);
	}

	/**
	 * Remove temporary "importing" placeholders and orphaned data left by a completed import.
	 *
	 * @param \wpdb $wpdb WordPress database abstraction.
	 *
	 * @return void
	 */
	private function cleanup_import( $wpdb ) {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_original_id' ) );
		$wpdb->delete(
			$wpdb->posts,
			array(
				'post_type'   => 'product',
				'post_status' => 'importing',
			)
		);
		$wpdb->delete(
			$wpdb->posts,
			array(
				'post_type'   => 'product_variation',
				'post_status' => 'importing',
			)
		);

		$wpdb->query(
			"DELETE {$wpdb->posts}.* FROM {$wpdb->posts}
			LEFT JOIN {$wpdb->posts} wp ON wp.ID = {$wpdb->posts}.post_parent
			WHERE wp.ID IS NULL AND {$wpdb->posts}.post_type = 'product_variation'"
		);
		$wpdb->query(
			"DELETE {$wpdb->postmeta}.* FROM {$wpdb->postmeta}
			LEFT JOIN {$wpdb->posts} wp ON wp.ID = {$wpdb->postmeta}.post_id
			WHERE wp.ID IS NULL"
		);
		$wpdb->query(
			"DELETE tr.* FROM {$wpdb->term_relationships} tr
			LEFT JOIN {$wpdb->posts} wp ON wp.ID = tr.object_id
			LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
			WHERE wp.ID IS NULL
			AND tt.taxonomy IN ( '" . implode( "','", array_map( 'esc_sql', get_object_taxonomies( 'product' ) ) ) . "' )"
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	}
}
