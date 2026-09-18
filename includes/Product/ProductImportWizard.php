<?php

namespace PluginizeLab\StoreSuite\Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Include WooCommerce's product CSV importer wizard controller.
 *
 * WooCommerce only loads this class in wp-admin (via WC_Admin_Importers). StoreSuite's dashboard is a
 * frontend page, so we pull it in directly through the always-defined WC_ABSPATH constant before
 * declaring our subclass.
 */
if ( ! class_exists( '\WC_Product_CSV_Importer_Controller' ) && defined( 'WC_ABSPATH' ) ) {
	include_once WC_ABSPATH . 'includes/admin/importers/class-wc-product-csv-importer-controller.php';
}

// The controller's get_importer() instantiates WC_Product_CSV_Importer without including it (WC only
// loads it in wp-admin). Pull it in so the mapping/import steps work on the frontend.
if ( ! class_exists( '\WC_Product_CSV_Importer' ) && defined( 'WC_ABSPATH' ) ) {
	include_once WC_ABSPATH . 'includes/import/class-wc-product-csv-importer.php';
}

/**
 * Product CSV import wizard for the StoreSuite frontend dashboard.
 *
 * Reuses WooCommerce's import wizard (upload → mapping → import → done) and only overrides the chrome:
 * the StoreSuite dashboard shell is rendered by templates/products/import-products.php around dispatch(),
 * so the WC admin page wrapper is replaced with a light container, and the done screen links back to the
 * StoreSuite products page. All mapping/auto-map/batch logic is inherited.
 */
class ProductImportWizard extends \WC_Product_CSV_Importer_Controller {

	/**
	 * Output the wizard header.
	 *
	 * Replaces WooCommerce's admin `.wrap` chrome with a light container (the StoreSuite shell wraps this).
	 *
	 * @return void
	 */
	protected function output_header() {
		// `woocommerce-progress-form-wrapper` is the scope WooCommerce's admin.css uses for the wizard
		// steps bar, form table and progress bar; without it the wizard renders unstyled.
		echo '<div class="storesuite-import-wizard woocommerce woocommerce-progress-form-wrapper">';
	}

	/**
	 * Output the wizard footer.
	 *
	 * @return void
	 */
	protected function output_footer() {
		echo '</div>';
	}

	/**
	 * Output wizard errors.
	 *
	 * Replaces WooCommerce's admin `.error.inline` notice with the dashboard's notice component.
	 *
	 * @return void
	 */
	protected function output_errors() {
		if ( ! $this->errors ) {
			return;
		}

		storesuite_get_template_part( 'products/import-notice', '', array( 'errors' => $this->errors ) );
	}

	/**
	 * Output the step indicator.
	 *
	 * Replaces WooCommerce's admin `.wc-progress-steps` bar with a StoreSuite-themed stepper.
	 *
	 * @return void
	 */
	protected function output_steps() {
		storesuite_get_template_part(
			'products/import-steps',
			'',
			array(
				'steps'        => $this->steps,
				'current_step' => $this->step,
			)
		);
	}

	/**
	 * Output the upload step form.
	 *
	 * Replaces WooCommerce's admin upload form view with a StoreSuite-themed dropzone while keeping the
	 * inherited field names so the parent upload handler continues to work.
	 *
	 * @return void
	 */
	protected function upload_form() {
		$bytes      = apply_filters( 'import_upload_size_limit', wp_max_upload_size() );
		$size       = size_format( $bytes );
		$upload_dir = wp_upload_dir();

		storesuite_get_template_part(
			'products/import-upload-form',
			'',
			array(
				'bytes'      => $bytes,
				'size'       => $size,
				'upload_dir' => $upload_dir,
			)
		);
	}

	/**
	 * Column mapping step.
	 *
	 * Reuses WooCommerce's importer data prep (headers, auto-mapping, sample row, mapping options) but
	 * renders a StoreSuite-themed template. Columns are bucketed into field categories so the template can
	 * group them; the mapped/ignored status shown per row stays driven by the current select value.
	 *
	 * @return void
	 */
	protected function mapping_form() {
		check_admin_referer( 'woocommerce-csv-importer' );
		self::validate_file_path( $this->file );

		$args = array(
			'lines'              => 1,
			'delimiter'          => $this->delimiter,
			'character_encoding' => $this->character_encoding,
		);

		$importer     = self::get_importer( $this->file, $args );
		$headers      = $importer->get_raw_keys();
		$mapped_items = $this->auto_map_columns( $headers );
		$sample       = current( $importer->get_raw_data() );

		if ( empty( $sample ) ) {
			$this->add_error(
				__( 'The file is empty or using a different encoding than UTF-8, please try again with a new file.', 'storesuite' ),
				array(
					array(
						'url'   => storesuite_get_navigation_url( 'import-products' ),
						'label' => __( 'Upload a new file', 'storesuite' ),
					),
				)
			);

			$this->output_errors();
			return;
		}

		// Ordered category buckets. A column is grouped by the field WooCommerce auto-detected for it, so it
		// keeps its group even if the user later sets the select to "Do not import".
		$group_labels = array(
			'general'    => __( 'General', 'storesuite' ),
			'pricing'    => __( 'Pricing', 'storesuite' ),
			'inventory'  => __( 'Inventory & shipping', 'storesuite' ),
			'linked'     => __( 'Linked products', 'storesuite' ),
			'downloads'  => __( 'External & downloads', 'storesuite' ),
			'attributes' => __( 'Attributes', 'storesuite' ),
			'meta'       => __( 'Meta data', 'storesuite' ),
			'unmapped'   => __( 'Unrecognized columns', 'storesuite' ),
		);

		$groups = array();
		foreach ( $group_labels as $key => $label ) {
			$groups[ $key ] = array(
				'label'   => $label,
				'columns' => array(),
			);
		}

		$mapped_count = 0;
		foreach ( $headers as $index => $name ) {
			$mapped_value = isset( $mapped_items[ $index ] ) ? $mapped_items[ $index ] : '';

			if ( '' !== (string) $mapped_value ) {
				++$mapped_count;
			}

			$category = $this->get_mapping_field_category( (string) $mapped_value );

			$groups[ $category ]['columns'][] = array(
				'index'        => $index,
				'name'         => $name,
				'sample'       => isset( $sample[ $index ] ) ? $sample[ $index ] : '',
				'mapped_value' => $mapped_value,
				'options'      => $this->get_mapping_options( $mapped_value ),
			);
		}

		// Drop empty categories so the template only renders groups that have columns.
		$groups = array_filter(
			$groups,
			static function ( $group ) {
				return ! empty( $group['columns'] );
			}
		);

		storesuite_get_template_part(
			'products/import-mapping',
			'',
			array(
				'groups'             => $groups,
				'total_columns'      => count( $headers ),
				'mapped_count'       => $mapped_count,
				'next_step_url'      => $this->get_next_step_link(),
				'back_url'           => storesuite_get_navigation_url( 'import-products' ),
				'file'               => $this->file,
				'delimiter'          => $this->delimiter,
				'update_existing'    => $this->update_existing,
				'character_encoding' => $args['character_encoding'],
			)
		);
	}

	/**
	 * Import step.
	 *
	 * Mirrors WooCommerce's import step (nonce check, mapping persistence, script localisation) but renders
	 * a StoreSuite-themed progress screen. The `woocommerce-importer` / `woocommerce-importer-progress`
	 * hooks the importer script binds to are preserved in the template.
	 *
	 * @return void
	 */
	public function import() {
		// Displaying this page triggers the Ajax import with a valid nonce, so it needs nonce protection too.
		check_admin_referer( 'woocommerce-csv-importer' );
		self::validate_file_path( $this->file );

		if ( empty( $_POST['map_from'] ) || empty( $_POST['map_to'] ) ) {
			wp_safe_redirect( esc_url_raw( $this->get_next_step_link( 'upload' ) ) );
			exit;
		}

		$mapping_from = wc_clean( wp_unslash( $_POST['map_from'] ) );
		$mapping_to   = wc_clean( wp_unslash( $_POST['map_to'] ) );

		// Save mapping preferences for future imports.
		update_user_option( get_current_user_id(), 'woocommerce_product_import_mapping', $mapping_to );

		wp_localize_script(
			'wc-product-import',
			'wc_product_import_params',
			array(
				'import_nonce'       => wp_create_nonce( 'wc-product-import' ),
				'mapping'            => array(
					'from' => $mapping_from,
					'to'   => $mapping_to,
				),
				'file'               => $this->file,
				'update_existing'    => $this->update_existing,
				'delimiter'          => $this->delimiter,
				'character_encoding' => $this->character_encoding,
			)
		);
		wp_enqueue_script( 'wc-product-import' );

		storesuite_get_template_part(
			'products/import-progress',
			'',
			array( 'file_name' => basename( $this->file ) )
		);
	}

	/**
	 * Resolve the category bucket for an auto-mapped field value.
	 *
	 * @param string $value Mapped field key (e.g. 'weight', 'meta:foo', 'attributes:name0').
	 *
	 * @return string Category key matching the $group_labels keys in mapping_form().
	 */
	protected function get_mapping_field_category( $value ) {
		if ( '' === $value ) {
			return 'unmapped';
		}
		if ( 0 === strpos( $value, 'meta:' ) ) {
			return 'meta';
		}
		if ( 0 === strpos( $value, 'attributes:' ) ) {
			return 'attributes';
		}
		if ( 0 === strpos( $value, 'downloads:' ) ) {
			return 'downloads';
		}

		$map = array(
			'general'   => array( 'id', 'type', 'sku', 'global_unique_id', 'name', 'published', 'featured', 'catalog_visibility', 'short_description', 'description', 'reviews_allowed', 'purchase_note', 'menu_order', 'cogs_value' ),
			'pricing'   => array( 'regular_price', 'sale_price', 'date_on_sale_from', 'date_on_sale_to', 'tax_status', 'tax_class' ),
			'inventory' => array( 'stock_status', 'stock_quantity', 'backorders', 'low_stock_amount', 'sold_individually', 'weight', 'length', 'width', 'height', 'shipping_class_id' ),
			'linked'    => array( 'category_ids', 'tag_ids', 'tag_ids_spaces', 'images', 'parent_id', 'upsell_ids', 'cross_sell_ids', 'grouped_products' ),
			'downloads' => array( 'product_url', 'button_text', 'download_limit', 'download_expiry' ),
		);

		foreach ( $map as $category => $keys ) {
			if ( in_array( $value, $keys, true ) ) {
				return $category;
			}
		}

		return 'general';
	}

	/**
	 * Add error message, rewriting wp-admin importer links to the frontend wizard.
	 *
	 * The inherited steps build error actions (e.g. mapping's "Upload a new file") pointing at the
	 * wp-admin importer page, which StoreSuite blocks for shop managers. Point them back here instead.
	 *
	 * @param string $message Error message.
	 * @param array  $actions List of actions with 'url' and 'label'.
	 *
	 * @return void
	 */
	protected function add_error( $message, $actions = array() ) {
		foreach ( $actions as $key => $action ) {
			if ( isset( $action['url'] ) && false !== strpos( $action['url'], 'page=product_importer' ) ) {
				$actions[ $key ]['url'] = storesuite_get_navigation_url( 'import-products' );
			}
		}

		parent::add_error( $message, $actions );
	}

	/**
	 * Done step.
	 *
	 * Renders a StoreSuite-themed summary whose "View products" button points to the dashboard products
	 * page instead of wp-admin.
	 *
	 * @return void
	 */
	protected function done() {
		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'woocommerce-csv-importer' ) ) {
			return;
		}

		$imported            = isset( $_REQUEST['products-imported'] ) ? absint( wp_unslash( $_REQUEST['products-imported'] ) ) : 0;
		$imported_variations = isset( $_REQUEST['products-imported-variations'] ) ? absint( wp_unslash( $_REQUEST['products-imported-variations'] ) ) : 0;
		$updated             = isset( $_REQUEST['products-updated'] ) ? absint( wp_unslash( $_REQUEST['products-updated'] ) ) : 0;
		$failed              = isset( $_REQUEST['products-failed'] ) ? absint( wp_unslash( $_REQUEST['products-failed'] ) ) : 0;
		$skipped             = isset( $_REQUEST['products-skipped'] ) ? absint( wp_unslash( $_REQUEST['products-skipped'] ) ) : 0;
		$errors              = array_filter( (array) get_user_option( 'product_import_error_log' ) );

		storesuite_get_template_part(
			'products/import-done',
			'',
			array(
				'imported'            => $imported,
				'imported_variations' => $imported_variations,
				'updated'             => $updated,
				'failed'              => $failed,
				'skipped'             => $skipped,
				'errors'              => $errors,
			)
		);
	}

	/**
	 * Validate that an uploaded import file path is safe and a CSV.
	 *
	 * Public wrapper around WooCommerce's protected validate_file_path() so the AJAX handler can reuse it.
	 *
	 * @param string $path Absolute file path.
	 *
	 * @throws \Exception When the path is invalid or not a CSV/TXT file.
	 *
	 * @return void
	 */
	public static function validate_import_file( $path ) {
		self::validate_file_path( $path );
	}
}
