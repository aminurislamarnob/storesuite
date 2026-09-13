<?php

namespace PluginizeLab\StoreSuite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Assets {
	/**
	 * Default plugin slugs whose assets are kept on the StoreSuite dashboard.
	 * Third parties can add more via the storesuite_allowed_plugin_slugs filter.
	 *
	 * @var string[]
	 */
	private static $allowed_plugin_slugs = array( 'woocommerce', 'storesuite' );

	/**
	 * Default script/style handles that are never removed on the StoreSuite dashboard.
	 * Third parties can add more via the storesuite_allowed_asset_handles filter.
	 *
	 * @var string[]
	 */
	private static $allowed_asset_handles = array();

	/**
	 * The constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_all_scripts' ), 10 );

		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ), 10 );
		} else {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_front_scripts' ) );
		}

		// Priority 7: after most themes enqueue (0–6) but before wp_print_styles (8).
		add_action( 'wp_head', array( $this, 'remove_all_theme_assets' ), 7 );
	}

	/**
	 * Whether an asset URL should be removed (theme or disallowed plugin).
	 *
	 * @param string   $src             Asset src URL.
	 * @param string[] $theme_uris      Theme base URIs to match.
	 * @param string[] $allowed_plugins Plugin slugs to keep.
	 * @return bool
	 */
	private function should_remove_asset( $src, array $theme_uris, array $allowed_plugins ) {
		foreach ( $theme_uris as $uri ) {
			if ( strpos( $src, $uri ) === 0 ) {
				return true;
			}
		}

		$prefix = plugins_url( '', STORESUITE_PLUGIN_FILE );
		// Get the parent plugins directory URL.
		$plugins_base_url = dirname( $prefix );
		if ( strpos( $src, $plugins_base_url ) === false ) {
			return false;
		}

		$slug = strtok( substr( $src, strpos( $src, $plugins_base_url ) + strlen( $plugins_base_url ) + 1 ), '/?' );
		return $slug && ! in_array( $slug, $allowed_plugins, true );
	}

	/**
	 * Register all scripts and styles.
	 *
	 * @return void
	 */
	public function register_all_scripts() {
		$this->register_styles();
		$this->register_scripts();
	}

	/**
	 * Register scripts.
	 *
	 * @param array $scripts
	 *
	 * @return void
	 */
	public function register_scripts() {
		$admin_script                 = STORESUITE_PLUGIN_ASSET . '/admin/script.js';
		$frontend_script              = STORESUITE_PLUGIN_ASSET . '/frontend/script.js';
		$frontend_order_script        = STORESUITE_PLUGIN_ASSET . '/frontend/order.js';
		$frontend_product_script      = STORESUITE_PLUGIN_ASSET . '/frontend/product.js';
		$frontend_product_ai_script   = STORESUITE_PLUGIN_ASSET . '/frontend/product-ai.js';
		$frontend_form_handler_script = STORESUITE_PLUGIN_ASSET . '/frontend/form-handler.js';
		$frontend_sweetalert2         = STORESUITE_PLUGIN_ASSET . '/frontend/library/sweetalert2.min.js';
		$frontend_variation_script    = STORESUITE_PLUGIN_ASSET . '/frontend/product-variation.js';
		$frontend_product_export      = STORESUITE_PLUGIN_ASSET . '/frontend/product-export.js';
		$frontend_product_inline_edit = STORESUITE_PLUGIN_ASSET . '/frontend/product-inline-edit.js';
		$frontend_taxonomy_list       = STORESUITE_PLUGIN_ASSET . '/frontend/taxonomy-list.js';
		$frontend_coupon_bulk         = STORESUITE_PLUGIN_ASSET . '/frontend/coupon-bulk.js';

		wp_register_script( 'storesuite_admin_script', $admin_script, array(), STORESUITE_PLUGIN_VERSION, true );
		wp_register_script( 'storesuite_global_script', STORESUITE_PLUGIN_ASSET . '/frontend/global.js', array( 'jquery' ), STORESUITE_PLUGIN_VERSION, true );
		wp_register_script( 'storesuite_notifications_script', STORESUITE_PLUGIN_ASSET . '/frontend/notifications.js', array( 'jquery', 'storesuite_global_script' ), STORESUITE_PLUGIN_VERSION, true );
		wp_register_script( 'storesuite_script', $frontend_script, array( 'jquery' ), STORESUITE_PLUGIN_VERSION, true );

		// Dashboard scripts.
		wp_register_script( 'storesuite_form_handler_script', $frontend_form_handler_script, array( 'storesuite_selectWoo', 'jquery-ui-datepicker' ), STORESUITE_PLUGIN_VERSION, true );
		wp_register_script( 'storesuite_sweetalert2_script', $frontend_sweetalert2, array(), '11.14.5', true );

		// Order scripts.
		wp_register_script( 'storesuite_order_script', $frontend_order_script, array( 'storesuite_selectWoo', 'jquery-ui-datepicker' ), STORESUITE_PLUGIN_VERSION, true );
		wp_register_script( 'storesuite_product_script', $frontend_product_script, array( 'storesuite_script', 'storesuite_form_handler_script', 'storesuite_selectWoo', 'jquery-ui-datepicker', 'jquery-ui-sortable', 'storesuite_sweetalert2_script' ), STORESUITE_PLUGIN_VERSION, true );
		// AI copy generation, split from product.js. Depends on the product
		// script so the localized StoreSuite_Product global is available.
		wp_register_script( 'storesuite_product_ai_script', $frontend_product_ai_script, array( 'jquery', 'storesuite_product_script', 'storesuite_sweetalert2_script' ), STORESUITE_PLUGIN_VERSION, true );
		wp_register_script( 'storesuite_selectWoo', WC()->plugin_url() . '/assets/js/selectWoo/selectWoo.full.js', array( 'jquery' ), '4.0.3', true );
		wp_register_script( 'wc-accounting', WC()->plugin_url() . '/assets/js/accounting/accounting.min.js', array( 'jquery' ), '0.4.2', true );
		wp_register_script( 'storesuite_variation_script', $frontend_variation_script, array( 'jquery', 'storesuite_selectWoo', 'storesuite_sweetalert2_script', 'jquery-ui-sortable', 'jquery-ui-datepicker' ), STORESUITE_PLUGIN_VERSION, true );
		wp_register_script( 'storesuite_product_export_script', $frontend_product_export, array( 'jquery', 'storesuite_product_script', 'storesuite_selectWoo', 'storesuite_sweetalert2_script' ), STORESUITE_PLUGIN_VERSION, true );

		// Inline cell editing on the products list table.
		wp_register_script( 'storesuite_product_inline_edit_script', $frontend_product_inline_edit, array( 'jquery', 'storesuite_script', 'storesuite_sweetalert2_script' ), STORESUITE_PLUGIN_VERSION, true );

		// WooCommerce's product CSV import wizard JS (reused verbatim; drives the AJAX batch import).
		wp_register_script( 'wc-product-import', WC()->plugin_url() . '/assets/js/admin/wc-product-import.js', array( 'jquery' ), WC_VERSION, true );
		// wc-product-import.js relies on the global `ajaxurl`, which WordPress only defines in wp-admin.
		wp_add_inline_script( 'wc-product-import', 'window.ajaxurl = window.ajaxurl || ' . wp_json_encode( admin_url( 'admin-ajax.php' ) ) . ';', 'before' );

		// Shared bulk delete + quick edit behaviour for the taxonomy/attribute list pages.
		wp_register_script( 'storesuite_taxonomy_list_script', $frontend_taxonomy_list, array( 'jquery', 'storesuite_script', 'storesuite_sweetalert2_script' ), STORESUITE_PLUGIN_VERSION, true );

		// Bulk edit + bulk trash behaviour for the coupons list page.
		wp_register_script( 'storesuite_coupon_bulk_script', $frontend_coupon_bulk, array( 'jquery', 'storesuite_script', 'storesuite_sweetalert2_script' ), STORESUITE_PLUGIN_VERSION, true );
	}

	/**
	 * Resolve a cache-busting version string for a bundled asset.
	 *
	 * In production the plugin version is used so caches persist across page
	 * loads. When SCRIPT_DEBUG is enabled (development), the file's modification
	 * time is used instead so edits are picked up without a plugin version bump.
	 * Falls back to the plugin version if the file is unreadable.
	 *
	 * @param string $path Absolute filesystem path to the asset.
	 * @return string|int Version string usable as the wp_register_style() $ver.
	 */
	private function asset_version( string $path ) {
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			$mtime = @filemtime( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- graceful fallback below.
			if ( false !== $mtime ) {
				return $mtime;
			}
		}

		return STORESUITE_PLUGIN_VERSION;
	}

	/**
	 * Register styles.
	 *
	 * @return void
	 */
	public function register_styles() {
		$admin_style                      = STORESUITE_PLUGIN_ASSET . '/admin/style.css';
		$frontend_style                   = STORESUITE_PLUGIN_ASSET . '/frontend/style.css';
		$frontend_responsive_style        = STORESUITE_PLUGIN_ASSET . '/frontend/responsive.css';
		$bs_grid_style                    = STORESUITE_PLUGIN_ASSET . '/frontend/bootstrap-grid.min.css';
		$frontend_sweetalert2_style       = STORESUITE_PLUGIN_ASSET . '/frontend/library/sweetalert2.min.css';

		// Frequently-edited frontend stylesheets: version by plugin version in
		// production, or by file mtime under SCRIPT_DEBUG so local edits are
		// picked up without a plugin version bump.
		$frontend_style_ver     = $this->asset_version( STORESUITE_DIR . '/assets/frontend/style.css' );
		$frontend_responsive_ver = $this->asset_version( STORESUITE_DIR . '/assets/frontend/responsive.css' );

		wp_register_style( 'storesuite_admin_style', $admin_style, array(), STORESUITE_PLUGIN_VERSION );
		wp_register_style( 'storesuite_style', $frontend_style, array(), $frontend_style_ver );
		wp_register_style( 'storesuite_responsive_style', $frontend_responsive_style, array( 'storesuite_style' ), $frontend_responsive_ver );
		wp_register_style( 'storesuite_bs_grid', $bs_grid_style, array(), STORESUITE_PLUGIN_VERSION );

		wp_register_style( 'storesuite_sweetalert2_style', $frontend_sweetalert2_style, array(), '11.14.5' );
		wp_register_style( 'storesuite_jquery-ui-style', WC()->plugin_url() . '/assets/css/jquery-ui/jquery-ui.min.css', array(), STORESUITE_PLUGIN_VERSION );

		// WooCommerce admin styles power the reused product import wizard (steps bar, mapping table, progress).
		wp_register_style( 'woocommerce_admin_styles', WC()->plugin_url() . '/assets/css/admin.css', array(), WC_VERSION );
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @return void
	 */
	public function enqueue_admin_scripts() {
		$page = get_current_screen();
		if ( 'woocommerce_page_storesuite' === $page->id ) {
			wp_enqueue_media();

			$asset_file = include STORESUITE_DIR . '/assets/build/admin/script.asset.php';

			$admin_script_dependencies = array_values(
				array_unique(
					array_merge(
						$asset_file['dependencies'],
						array( 'media-editor' )
					)
				)
			);

			wp_enqueue_script(
				'storesuite-admin-page',
				STORESUITE_PLUGIN_ASSET . '/build/admin/script.js',
				$admin_script_dependencies,
				$asset_file['version'],
				true
			);

			wp_localize_script(
				'storesuite-admin-page',
				'storeSuiteAdmin',
				array(
					'logoUrl'                    => STORESUITE_PLUGIN_ASSET . '/frontend/images/storesuite-logo-dark.png',
					'aiDefaultInstructions'      => \PluginizeLab\StoreSuite\Product\ProductAI::default_system_instructions(),
					'aiDefaultImageInstruction'  => \PluginizeLab\StoreSuite\Product\ProductImageAI::default_image_instruction(),
					// Whether at least one AI provider is connected (text or image).
					'aiConnected'                => \PluginizeLab\StoreSuite\Product\ProductAI::is_text_supported() || \PluginizeLab\StoreSuite\Product\ProductImageAI::is_supported(),
					'connectorsUrl'              => admin_url( 'options-connectors.php' ),
				)
			);

			wp_enqueue_style(
				'storesuite-admin-styles',
				STORESUITE_PLUGIN_ASSET . '/build/admin/script.css',
				array( 'wp-components' ),
				$asset_file['version'] ?? null,
			);

			wp_enqueue_style( 'wp-components' );
		}
	}

	/**
	 * Enqueue front-end scripts.
	 *
	 * @return void
	 */
	public function enqueue_front_scripts() {
		if ( ! storesuite_is_dashboard_page() ) {
			return;
		}

		// Base shell styles — always needed.
		wp_enqueue_style( 'storesuite_style' );
		wp_enqueue_style( 'storesuite_responsive_style' );
		wp_enqueue_style( 'storesuite_bs_grid' );

		// Sidebar collapse, submenu, and dropdown behaviours run on every
		// dashboard page including the React root and analytics route.
		wp_enqueue_script( 'storesuite_global_script' );

		// Notifications bell polling — every dashboard page, managers only.
		if ( current_user_can( 'manage_woocommerce' ) ) {
			wp_enqueue_script( 'storesuite_notifications_script' );
			wp_localize_script(
				'storesuite_notifications_script',
				'StoreSuite_Notifications',
				array(
					'rest_url'          => esc_url_raw( rest_url( 'storesuite/v1/notifications' ) ),
					'nonce'             => wp_create_nonce( 'wp_rest' ),
					/**
					 * Filters the notifications poll interval in seconds.
					 *
					 * @param int $interval Poll interval in seconds.
					 */
					'interval'          => max( 15, (int) apply_filters( 'storesuite_notification_poll_interval', 60 ) ),
					'cursor'            => ( new \PluginizeLab\StoreSuite\Notification\NotificationManager() )->get_cursor(),
					'notifications_url' => storesuite_get_navigation_url( 'notifications' ),
					'i18n'              => array(
						'are_you_sure'      => __( 'Are you sure?', 'storesuite' ),
						'confirm_clear_all' => __( 'Delete all notifications? This cannot be undone.', 'storesuite' ),
						'yes_clear'         => __( 'Yes, clear all!', 'storesuite' ),
						'cancel_button'     => __( 'Cancel', 'storesuite' ),
					),
				)
			);

			// The Clear all confirmation on the notifications page uses SweetAlert2.
			if ( storesuite_is_endpoint_url( 'notifications' ) ) {
				wp_enqueue_script( 'storesuite_sweetalert2_script' );
				wp_enqueue_style( 'storesuite_sweetalert2_style' );
			}
		}

		// React-only routes (dashboard root + analytics) render with
		// @woocommerce/components and don't need the legacy jQuery stack
		// or wp_enqueue_media(). Enqueueing those here adds ~700KB of
		// unused media-views/mediaelement/plupload scripts to LCP.
		$is_dashboard_root = ! storesuite_is_endpoint_url();
		$is_analytics      = storesuite_is_endpoint_url( 'analytics' );
		if ( $is_dashboard_root || $is_analytics ) {
			return;
		}

		// Per-endpoint flags.
		$is_products = storesuite_is_endpoint_url( 'products' )
			|| storesuite_is_endpoint_url( 'add-new-product' )
			|| storesuite_is_endpoint_url( 'edit-product' );
		$is_orders = storesuite_is_endpoint_url( 'orders' )
			|| storesuite_is_endpoint_url( 'add-new-order' )
			|| storesuite_is_endpoint_url( 'edit-order' )
			|| storesuite_is_endpoint_url( 'order-details' );
		$is_coupons = storesuite_is_endpoint_url( 'coupons' )
			|| storesuite_is_endpoint_url( 'add-new-coupon' )
			|| storesuite_is_endpoint_url( 'edit-coupon' );
		$is_categories = storesuite_is_endpoint_url( 'categories' )
			|| storesuite_is_endpoint_url( 'add-new-category' )
			|| storesuite_is_endpoint_url( 'edit-category' );
		$is_tags = storesuite_is_endpoint_url( 'tags' )
			|| storesuite_is_endpoint_url( 'add-new-tag' )
			|| storesuite_is_endpoint_url( 'edit-tag' );
		$is_brands = storesuite_is_endpoint_url( 'brands' )
			|| storesuite_is_endpoint_url( 'add-new-brand' )
			|| storesuite_is_endpoint_url( 'edit-brand' );
		$is_attributes = storesuite_is_endpoint_url( 'attributes' )
			|| storesuite_is_endpoint_url( 'add-new-attribute' )
			|| storesuite_is_endpoint_url( 'edit-attribute' )
			|| storesuite_is_endpoint_url( 'attribute-terms' );
		$is_account   = storesuite_is_endpoint_url( 'edit-account-details' );
		$is_import    = storesuite_is_endpoint_url( 'import-products' );
		$is_inventory = storesuite_is_endpoint_url( 'inventory' );

		// List pages that get the shared bulk delete + quick edit behaviour.
		$is_taxonomy_list = storesuite_is_endpoint_url( 'categories' )
			|| storesuite_is_endpoint_url( 'tags' )
			|| storesuite_is_endpoint_url( 'brands' )
			|| storesuite_is_endpoint_url( 'attributes' )
			|| storesuite_is_endpoint_url( 'attribute-terms' );

		$needs_media        = $is_products || $is_categories || $is_brands || $is_account;
		$needs_form_handler = $is_products || $is_inventory || $is_coupons || $is_categories || $is_tags || $is_brands || $is_attributes || $is_account;
		$needs_sweetalert   = $needs_form_handler || $is_orders;
		$needs_select2      = $is_products || $is_inventory || $is_orders || $is_coupons;
		// jQuery UI datepicker styles: any page that renders .date-picker inputs
		// (the coupon expiry date, the product sale schedule, the order created
		// date, and the inventory list's inline editors).
		$needs_jquery_ui = $is_products || $is_inventory || $is_coupons || $is_orders;

		if ( $needs_jquery_ui ) {
			wp_enqueue_style( 'storesuite_jquery-ui-style' );
		}

		if ( $needs_select2 ) {
			wp_enqueue_style( 'select2' );
		}

		// Product import wizard reuses WooCommerce's importer UI. The `wc-product-import` script is
		// enqueued + localized by the wizard's import() step itself (it needs wc_product_import_params,
		// which only exists on that step), so we only load the styles here.
		if ( $is_import ) {
			wp_enqueue_style( 'woocommerce_admin_styles' );
		}

		// Account address tab needs WooCommerce's country/state select behaviour.
		if ( $is_account ) {
			wp_enqueue_style( 'select2' );
			wp_enqueue_script( 'selectWoo' );
			wp_enqueue_script( 'wc-country-select' );
			wp_enqueue_script( 'wc-address-i18n' );
		}

		wp_enqueue_script( 'storesuite_script' );
		wp_localize_script(
			'storesuite_script',
			'storeSuiteFrontScript',
			array(
				'upload_image_text'     => __( 'Upload Image', 'storesuite' ),
				'remove_image_text'     => __( 'Remove Image', 'storesuite' ),
				'upload_product_image'  => __( 'Upload Product Image', 'storesuite' ),
				'insert_image'          => __( 'Insert Image', 'storesuite' ),
				'product_image'         => __( 'Product Image', 'storesuite' ),
				'product_gallery_image' => __( 'Product Gallery Image', 'storesuite' ),
				'upload_category_image' => __( 'Upload Category Image', 'storesuite' ),
				'category_image'        => __( 'Category Image', 'storesuite' ),
				'upload_brand_image'    => __( 'Upload Brand Image', 'storesuite' ),
				'brand_image'           => __( 'Brand Image', 'storesuite' ),
				'upload_gallery_images' => __( 'Upload Product Gallery Images', 'storesuite' ),
				'upload_profile_picture' => __( 'Upload Profile Picture', 'storesuite' ),
				'default_avatar_url'    => get_avatar_url( get_current_user_id(), array( 'force_default' => true ) ),
			)
		);

		wp_localize_script(
			'storesuite_script',
			'storeSuiteDateRangesI18n',
			array(
				'today'      => __( 'Today', 'storesuite' ),
				'yesterday'  => __( 'Yesterday', 'storesuite' ),
				'last7'      => __( 'Last 7 Days', 'storesuite' ),
				'last30'     => __( 'Last 30 Days', 'storesuite' ),
				'this_month' => __( 'This Month', 'storesuite' ),
				'last_month' => __( 'Last Month', 'storesuite' ),
			)
		);

		if ( $needs_sweetalert ) {
			wp_enqueue_style( 'storesuite_sweetalert2_style' );
			wp_enqueue_script( 'storesuite_sweetalert2_script' );
		}

		if ( $needs_form_handler ) {
			wp_enqueue_script( 'storesuite_form_handler_script' );
			wp_localize_script(
				'storesuite_form_handler_script',
				'storeSuiteFormHandler',
				array(
					'ajax_url'                     => admin_url( 'admin-ajax.php' ),
					'storesuite_woo_delete_nonce_' => wp_create_nonce( '_storesuite_delete_nonce_' ),
					'search_products_nonce'        => wp_create_nonce( 'search-products' ),
					'coupon_code_generator'        => array(
						'generate_button_text' => esc_html__( 'Generate coupon code', 'storesuite' ),
						'characters'           => apply_filters( 'woocommerce_coupon_code_generator_characters', 'ABCDEFGHJKMNPQRSTUVWXYZ23456789' ),
						'char_length'          => apply_filters( 'woocommerce_coupon_code_generator_character_length', 8 ),
						'prefix'               => apply_filters( 'woocommerce_coupon_code_generator_prefix', '' ),
						'suffix'               => apply_filters( 'woocommerce_coupon_code_generator_suffix', '' ),
					),
					'i18n'                         => array(
						// Common messages.
						'processing'                     => __( 'Processing...', 'storesuite' ),
						'please_wait'                    => __( 'Please wait while we process your request.', 'storesuite' ),
						'success_title'                  => __( 'Success!', 'storesuite' ),
						'error_title'                    => __( 'Error!', 'storesuite' ),
						'ok_button'                      => __( 'OK', 'storesuite' ),
						'yes_button'                     => __( 'Yes', 'storesuite' ),
						'no_button'                      => __( 'No', 'storesuite' ),
						'cancel_button'                  => __( 'Cancel', 'storesuite' ),
						'unexpected_error'               => __( 'An unexpected error occurred. Please try again.', 'storesuite' ),

						// Category messages.
						'category_name_required'         => __( 'Please enter category name.', 'storesuite' ),
						'category_adding'                => __( 'Adding Category...', 'storesuite' ),
						'category_added_successfully'    => __( 'Category added successfully!', 'storesuite' ),
						'category_updating'              => __( 'Updating Category...', 'storesuite' ),
						'category_updated_successfully'  => __( 'Category updated successfully!', 'storesuite' ),
						'category_delete_confirm_title'  => __( 'Are you sure?', 'storesuite' ),
						'category_delete_confirm_text'   => __( 'Do you want to delete this category? This action cannot be undone.', 'storesuite' ),
						'category_delete_confirm_button' => __( 'Yes, delete it!', 'storesuite' ),
						'category_deleting'              => __( 'Deleting Category...', 'storesuite' ),
						'category_deleted_successfully'  => __( 'Category deleted successfully!', 'storesuite' ),

						// Tag messages.
						'tag_name_required'              => __( 'Please enter tag name.', 'storesuite' ),
						'tag_adding'                     => __( 'Adding Tag...', 'storesuite' ),
						'tag_added_successfully'         => __( 'Tag added successfully!', 'storesuite' ),
						'tag_updating'                   => __( 'Updating Tag...', 'storesuite' ),
						'tag_updated_successfully'       => __( 'Tag updated successfully!', 'storesuite' ),
						'tag_delete_confirm_title'       => __( 'Are you sure?', 'storesuite' ),
						'tag_delete_confirm_text'        => __( 'Do you want to delete this tag? This action cannot be undone.', 'storesuite' ),
						'tag_delete_confirm_button'      => __( 'Yes, delete it!', 'storesuite' ),
						'tag_deleting'                   => __( 'Deleting Tag...', 'storesuite' ),
						'tag_deleted_successfully'       => __( 'Tag deleted successfully!', 'storesuite' ),

						// Brand messages.
						'brand_name_required'            => __( 'Please enter brand name.', 'storesuite' ),
						'brand_adding'                   => __( 'Adding Brand...', 'storesuite' ),
						'brand_added_successfully'       => __( 'Brand added successfully!', 'storesuite' ),
						'brand_updating'                 => __( 'Updating Brand...', 'storesuite' ),
						'brand_updated_successfully'     => __( 'Brand updated successfully!', 'storesuite' ),
						'brand_delete_confirm_title'     => __( 'Are you sure?', 'storesuite' ),
						'brand_delete_confirm_text'      => __( 'Do you want to delete this brand? This action cannot be undone.', 'storesuite' ),
						'brand_delete_confirm_button'    => __( 'Yes, delete it!', 'storesuite' ),
						'brand_deleting'                 => __( 'Deleting Brand...', 'storesuite' ),
						'brand_deleted_successfully'     => __( 'Brand deleted successfully!', 'storesuite' ),

						// Attribute messages.
						'attribute_name_required'        => __( 'Please enter attribute name.', 'storesuite' ),
						'attribute_deleting'             => __( 'Deleting Attribute...', 'storesuite' ),
						'delete_attribute_warning'       => __( 'Do you want to delete this attribute?', 'storesuite' ),

						// Attribute term messages.
						'attribute_term_name_required'           => __( 'Please enter term name.', 'storesuite' ),
						'attribute_term_updating'               => __( 'Updating term...', 'storesuite' ),
						'attribute_term_deleting'               => __( 'Deleting term...', 'storesuite' ),
						'delete_attribute_term_warning'         => __( 'Do you want to delete this term?', 'storesuite' ),
						'edit_attribute_term'                   => __( 'Edit attribute term', 'storesuite' ),
						'attribute_term_name_placeholder'       => __( 'Term name (e.g. Blue)', 'storesuite' ),
						'attribute_term_slug_placeholder'       => __( 'Term slug (optional)', 'storesuite' ),
						'attribute_term_description_placeholder' => __( 'Optional term description', 'storesuite' ),
						'edit_label'                            => __( 'Edit', 'storesuite' ),
						'delete_label'                          => __( 'Delete', 'storesuite' ),

						// Product messages.
						'product_title_required'         => __( 'Please enter product title.', 'storesuite' ),
						'product_type_required'          => __( 'Please select a product type.', 'storesuite' ),
						'product_status_required'        => __( 'Please select a product status.', 'storesuite' ),
						'product_description_required'   => __( 'Please enter product description.', 'storesuite' ),
						'product_category_required'      => __( 'Please select at least one category.', 'storesuite' ),
						'product_price_required'         => __( 'Please enter product price.', 'storesuite' ),
						'product_adding'                 => __( 'Adding Product...', 'storesuite' ),
						'product_added_successfully'     => __( 'Product added successfully!', 'storesuite' ),
						'product_updating'               => __( 'Updating Product...', 'storesuite' ),
						'product_updated_successfully'   => __( 'Product updated successfully!', 'storesuite' ),
						'product_delete_confirm_title'   => __( 'Are you sure?', 'storesuite' ),
						'product_delete_warning'         => __( 'Do you want to delete this product? It will be moved to trash.', 'storesuite' ),
						'upload_image_text'              => __( 'Upload Image', 'storesuite' ),

						// Coupon messages.
						'coupon_code_required'           => __( 'Coupon code is required.', 'storesuite' ),
						'coupon_discount_type_required'  => __( 'Please select a discount type.', 'storesuite' ),
						'coupon_amount_required'         => __( 'Coupon amount is required.', 'storesuite' ),
						'coupon_adding'                  => __( 'Adding Coupon...', 'storesuite' ),
						'coupon_added_successfully'      => __( 'Coupon added successfully!', 'storesuite' ),
						'coupon_updating'                => __( 'Updating Coupon...', 'storesuite' ),
						'coupon_updated_successfully'    => __( 'Coupon updated successfully!', 'storesuite' ),
						'delete_coupon_warning'          => __( 'This will delete the coupon permanently. This action cannot be undone.', 'storesuite' ),
						'deleting'                       => __( 'Deleting...', 'storesuite' ),
						'are_you_sure'                   => __( 'Are you sure?', 'storesuite' ),
						'yes_delete'                     => __( 'Yes, delete it!', 'storesuite' ),
						'validation_error'               => __( 'Validation Error', 'storesuite' ),

						// Account details (edit account).
						'account_first_name_required'    => __( 'First name is required.', 'storesuite' ),
						'account_last_name_required'     => __( 'Last name is required.', 'storesuite' ),
						'account_display_name_required'  => __( 'Display name is required.', 'storesuite' ),
						'account_email_required'         => __( 'Email address is required.', 'storesuite' ),
						'account_password_mismatch'      => __( 'New password and confirm password do not match.', 'storesuite' ),
					),
					'coupons_url'                  => storesuite_get_navigation_url( 'coupons' ),
					'attribute_terms_url'          => storesuite_get_navigation_url( 'attribute-terms' ),
				)
			);
		}

		if ( $is_taxonomy_list ) {
			wp_enqueue_script( 'storesuite_taxonomy_list_script' );
			wp_localize_script(
				'storesuite_taxonomy_list_script',
				'StoreSuiteTaxonomyList',
				array(
					'ajax_url'        => admin_url( 'admin-ajax.php' ),
					'delete_nonce'    => wp_create_nonce( '_storesuite_delete_nonce_' ),
					'load_form_nonce' => wp_create_nonce( 'storesuite_list_quick_edit_form' ),
					'save_nonce'      => wp_create_nonce( 'storesuite_list_quick_edit' ),
					'i18n'            => array(
						'success_title'        => __( 'Success!', 'storesuite' ),
						'error_title'          => __( 'Error!', 'storesuite' ),
						'ok_button'            => __( 'OK', 'storesuite' ),
						'cancel_button'        => __( 'Cancel', 'storesuite' ),
						'are_you_sure'         => __( 'Are you sure?', 'storesuite' ),
						'yes_delete'           => __( 'Yes, delete it!', 'storesuite' ),
						'unexpected_error'     => __( 'An unexpected error occurred. Please try again.', 'storesuite' ),
						'select_items_title'   => __( 'Select items', 'storesuite' ),
						'select_items_message' => __( 'Choose at least one item first.', 'storesuite' ),
						'bulk_delete_warning'  => __( 'Do you want to delete the selected items? This action cannot be undone.', 'storesuite' ),
					),
				)
			);
		}

		if ( storesuite_is_endpoint_url( 'coupons' ) ) {
			wp_enqueue_script( 'storesuite_coupon_bulk_script' );
			wp_localize_script(
				'storesuite_coupon_bulk_script',
				'StoreSuiteCouponBulk',
				array(
					'ajax_url'    => admin_url( 'admin-ajax.php' ),
					'edit_nonce'  => wp_create_nonce( 'storesuite_bulk_edit_coupons' ),
					'trash_nonce' => wp_create_nonce( 'storesuite_bulk_trash_coupons' ),
					'i18n'        => array(
						'success_title'        => __( 'Success!', 'storesuite' ),
						'error_title'          => __( 'Error!', 'storesuite' ),
						'ok_button'            => __( 'OK', 'storesuite' ),
						'cancel_button'        => __( 'Cancel', 'storesuite' ),
						'are_you_sure'         => __( 'Are you sure?', 'storesuite' ),
						'yes_delete'           => __( 'Yes, move to trash!', 'storesuite' ),
						'unexpected_error'     => __( 'An unexpected error occurred. Please try again.', 'storesuite' ),
						'select_items_title'   => __( 'Select coupons', 'storesuite' ),
						'select_items_message' => __( 'Choose at least one coupon first.', 'storesuite' ),
						'bulk_trash_warning'   => __( 'Do you want to move the selected coupons to trash?', 'storesuite' ),
					),
				)
			);
		}

		if ( $needs_media ) {
			wp_enqueue_media();
		}

		if ( $is_orders ) {
			wp_enqueue_script( 'storesuite_selectWoo' );
			wp_enqueue_script( 'storesuite_order_script' );

			wp_enqueue_script( 'wc-accounting' );
			wp_localize_script(
				'wc-accounting',
				'accounting_params',
				array(
					'mon_decimal_point' => wc_get_price_decimal_separator(),
				)
			);

			$order_id = absint( get_query_var( 'order-details' ) );

			if ( ! $order_id ) {
				$order_id = absint( get_query_var( 'edit-order' ) );
			}

			if ( ! $order_id ) {
				global $theorder;
				$order_id = \Automattic\WooCommerce\Utilities\OrderUtil::get_post_or_order_id( $theorder );
			}
			$default_location = wc_get_customer_default_location();

			wp_localize_script(
				'storesuite_order_script',
				'StoreSuite_Order',
				array(
					'i18n_no_matches'                 => _x( 'No matches found', 'enhanced select', 'storesuite' ),
					'i18n_ajax_error'                 => _x( 'Loading failed', 'enhanced select', 'storesuite' ),
					'i18n_input_too_short_1'          => _x( 'Please enter 1 or more characters', 'enhanced select', 'storesuite' ),
					'i18n_input_too_short_n'          => _x( 'Please enter %qty% or more characters', 'enhanced select', 'storesuite' ),
					'i18n_input_too_long_1'           => _x( 'Please delete 1 character', 'enhanced select', 'storesuite' ),
					'i18n_input_too_long_n'           => _x( 'Please delete %qty% characters', 'enhanced select', 'storesuite' ),
					'i18n_selection_too_long_1'       => _x( 'You can only select 1 item', 'enhanced select', 'storesuite' ),
					'i18n_selection_too_long_n'       => _x( 'You can only select %qty% items', 'enhanced select', 'storesuite' ),
					'i18n_load_more'                  => _x( 'Loading more results&hellip;', 'enhanced select', 'storesuite' ),
					'i18n_searching'                  => _x( 'Searching&hellip;', 'enhanced select', 'storesuite' ),
					'ajax_url'                        => admin_url( 'admin-ajax.php' ),
					'search_products_nonce'           => wp_create_nonce( 'search-products' ),
					'search_customers_nonce'          => wp_create_nonce( 'search-customers' ),
					'search_categories_nonce'         => wp_create_nonce( 'search-categories' ),
					'search_taxonomy_terms_nonce'     => wp_create_nonce( 'search-taxonomy-terms' ),
					'search_product_attributes_nonce' => wp_create_nonce( 'search-product-attributes' ),
					'search_pages_nonce'              => wp_create_nonce( 'search-pages' ),
					'search_order_metakeys_nonce'     => wp_create_nonce( 'search-order-metakeys' ),
					'copy_billing'                    => __( 'Copy billing information to shipping information? This will remove any currently entered shipping information.', 'storesuite' ),
					'load_billing'                    => __( "Load the customer's billing information? This will remove any currently entered billing information.", 'storesuite' ),
					'load_shipping'                   => __( "Load the customer's shipping information? This will remove any currently entered shipping information.", 'storesuite' ),
					'no_customer_selected'            => __( 'No customer selected', 'storesuite' ),
					'get_customer_details_nonce'      => wp_create_nonce( 'get-customer-details' ),
					'add_order_note_nonce'            => wp_create_nonce( 'add-order-note' ),
					'delete_order_note_nonce'         => wp_create_nonce( 'delete-order-note' ),
					'post_id'                         => $order_id,
					'order_item_nonce'                => wp_create_nonce( 'order-item' ),
					'hide_new_customer_form'          => __( 'hide new customer form', 'storesuite' ),
					'add_new_customer_form'           => __( 'add a new customer', 'storesuite' ),
					'new_customer_or'                 => __( 'Or', 'storesuite' ),
					'tax_based_on'                    => esc_attr( get_option( 'woocommerce_tax_based_on' ) ),
					'i18n_apply_coupon'               => __( 'Enter a coupon code to apply. Discounts are applied to line totals, before taxes.', 'storesuite' ),
					'i18n_add_fee'                    => __( 'Enter a fixed amount or percentage.', 'storesuite' ),
					'calc_totals_nonce'               => wp_create_nonce( 'calc-totals' ),
					'countries'                       => wp_json_encode( array_merge( WC()->countries->get_allowed_country_states(), WC()->countries->get_shipping_country_states() ) ),
					'i18n_select_state_text'          => esc_attr__( 'Select an option&hellip;', 'storesuite' ),
					'default_country'                 => isset( $default_location['country'] ) ? $default_location['country'] : '',
					'default_state'                   => isset( $default_location['state'] ) ? $default_location['state'] : '',
					'placeholder_name'                => esc_attr__( 'Name (required)', 'storesuite' ),
					'placeholder_value'               => esc_attr__( 'Value (required)', 'storesuite' ),
					'i18n_delete_note'                => __( 'Are you sure you wish to delete this note? This action cannot be undone.', 'storesuite' ),
					'i18n_no_notes'                   => __( 'There are no notes yet.', 'storesuite' ),
					'remove_item_notice'              => __( 'Are you sure you want to remove the selected items?', 'storesuite' ),
					'remove_fee_notice'               => __( 'Are you sure you want to remove the selected fees?', 'storesuite' ),
					'remove_shipping_notice'          => __( 'Are you sure you want to remove the selected shipping?', 'storesuite' ),
					'remove_item_meta'                => __( 'Remove this item meta?', 'storesuite' ),
					'mon_decimal_point'               => wc_get_price_decimal_separator(),
					'rounding_precision'              => wc_get_rounding_precision(),
					'i18n_select_items'            => __( 'Please select some items.', 'storesuite' ),
					'i18n_do_refund'               => __( 'Are you sure you wish to process this refund request? This action cannot be undone.', 'storesuite' ),
					'i18n_delete_refund'           => __( 'Are you sure you wish to delete this refund? This action cannot be undone.', 'storesuite' ),
					'currency_format_num_decimals' => wc_get_price_decimals(),
					'currency_format_symbol'       => get_woocommerce_currency_symbol(),
					'currency_format_decimal_sep'  => esc_attr( wc_get_price_decimal_separator() ),
					'currency_format_thousand_sep' => esc_attr( wc_get_price_thousand_separator() ),
					'currency_format'              => esc_attr( str_replace( [ '%1$s', '%2$s' ], [ '%s', '%v' ], get_woocommerce_price_format() ) ), // For accounting JS
					'round_at_subtotal'            => get_option( 'woocommerce_tax_round_at_subtotal', 'no' ),
					'order_ok_button'              => __( 'OK', 'storesuite' ),
					'order_success_title'          => __( 'Success!', 'storesuite' ),
				)
			);
		}

		if ( $is_products || $is_inventory ) {
			wp_enqueue_script( 'storesuite_selectWoo' );
			wp_enqueue_script( 'storesuite_product_script' );
			if ( $is_products ) {
				wp_enqueue_script( 'storesuite_product_ai_script' );
			}

			$product_script_data = array(
				'i18n_global_unique_id_error' => __( 'Please enter only numbers and hyphens (-).', 'storesuite' ),
				'ajax_url'                    => admin_url( 'admin-ajax.php' ),
				'search_products_nonce'       => wp_create_nonce( 'search-products' ),
				'i18n'                        => array(
					// Common messages.
					'success_title'    => __( 'Success!', 'storesuite' ),
					'error_title'      => __( 'Error!', 'storesuite' ),
					'ok_button'        => __( 'OK', 'storesuite' ),
					'unexpected_error' => __( 'An unexpected error occurred. Please try again.', 'storesuite' ),
				),
				'bulk_edit'                   => array(
					'nonce'                   => wp_create_nonce( 'storesuite_bulk_edit_products' ),
					'trash_nonce'             => wp_create_nonce( 'storesuite_bulk_trash_products' ),
					'delete_nonce'            => wp_create_nonce( 'storesuite_bulk_delete_products' ),
					'select_products_title'   => __( 'Select products', 'storesuite' ),
					'select_products_message' => __( 'Choose at least one product to bulk edit.', 'storesuite' ),
					'success_title'           => __( 'Bulk update complete', 'storesuite' ),
					'trash_success_title'     => __( 'Bulk trash complete', 'storesuite' ),
					'delete_success_title'    => __( 'Bulk delete complete', 'storesuite' ),
					'delete_confirm_title'    => __( 'Delete permanently?', 'storesuite' ),
					'delete_confirm_message'  => __( 'The selected products will be deleted permanently. This cannot be undone.', 'storesuite' ),
					'delete_confirm_button'   => __( 'Yes, delete permanently', 'storesuite' ),
					'delete_recheck_title'    => __( 'Last chance', 'storesuite' ),
					/* translators: %d: number of products selected for permanent deletion */
					'delete_recheck_message'  => __( 'Permanently delete %d product(s)? There is no trash to restore from.', 'storesuite' ),
					'delete_recheck_button'   => __( 'Delete now', 'storesuite' ),
					'cancel_button'           => __( 'Cancel', 'storesuite' ),
					'ok_button'               => __( 'OK', 'storesuite' ),
				),
				'woocommerce_admin'          => array(
					'mon_decimal_point' => wc_get_price_decimal_separator(),
				),
				'woocommerce_quick_edit'     => array(
					'strings' => array(
						'allow_reviews' => __( 'Enable reviews', 'storesuite' ),
					),
				),
				'quick_edit'                 => array(
					'nonce'                 => wp_create_nonce( 'storesuite_product_quick_edit' ),
					'load_form_nonce'       => wp_create_nonce( 'storesuite_product_quick_edit_form' ),
					'success_title'         => __( 'Product updated', 'storesuite' ),
					'loading_text'          => __( 'Loading…', 'storesuite' ),
				),
				'ai'                         => array(
					'enabled'       => \PluginizeLab\StoreSuite\Product\ProductAI::is_text_supported(),
					'nonce'         => wp_create_nonce( '_storesuite_ai_' ),
					'action'        => 'storesuite_generate_product_field',
					'bundle_action' => 'storesuite_generate_product_bundle',
					'image'         => array(
						'enabled'         => \PluginizeLab\StoreSuite\Product\ProductImageAI::is_supported(),
						'generate_action' => 'storesuite_generate_product_image',
						'insert_action'   => 'storesuite_insert_product_image',
						'prompt_required' => __( 'Please describe the image you want to generate.', 'storesuite' ),
						'inserting'       => __( 'Inserting…', 'storesuite' ),
					),
					'i18n'          => array(
						'generate'        => __( 'Generate with AI', 'storesuite' ),
						'generating'      => __( 'Generating…', 'storesuite' ),
						'error_title'     => __( 'AI generation failed', 'storesuite' ),
						'no_context'      => __( 'Add a product title or a few keywords first.', 'storesuite' ),
						'unavailable'     => __( 'AI generation is not available. Connect an AI provider to use this feature.', 'storesuite' ),
						'insert'          => __( 'Insert', 'storesuite' ),
						'regenerate'      => __( 'Regenerate', 'storesuite' ),
						'regenerating'    => __( 'Regenerating…', 'storesuite' ),
						'subtitle'        => __( 'Review, edit and insert the suggestion or regenerate a new one.', 'storesuite' ),
						'prompt_required' => __( 'Please enter a few keywords first.', 'storesuite' ),
						'hint_required'   => __( 'Please describe your product first.', 'storesuite' ),
						'insert_all'      => __( 'Insert all', 'storesuite' ),
						'modal_titles'    => array(
							'title'             => __( 'Title suggestion', 'storesuite' ),
							'description'       => __( 'Description suggestion', 'storesuite' ),
							'short_description' => __( 'Short description suggestion', 'storesuite' ),
						),
					),
				),
			);

			wp_localize_script(
				'storesuite_product_script',
				'StoreSuite_Product',
				$product_script_data
			);

			if ( $is_products ) {
				wp_enqueue_script( 'storesuite_variation_script' );
				wp_localize_script(
                    'storesuite_variation_script', 'StoreSuiteVariation', array(
						'ajax_url'              => admin_url( 'admin-ajax.php' ),
						'nonce'                 => wp_create_nonce( 'storesuite-variation-nonce' ),
						'add_attribute_nonce'   => wp_create_nonce( 'add-attribute' ),
						'add_term_nonce'        => wp_create_nonce( '_storesuite_add_attribute_term_' ),
						'save_variations_nonce'  => wp_create_nonce( 'save-variations' ),
						'add_variation_nonce'    => wp_create_nonce( 'add-variation' ),
						'remove_variation_nonce' => wp_create_nonce( 'remove-variation' ),
						'bulk_edit_nonce'        => wp_create_nonce( 'bulk-edit-variations' ),
						'default_attributes_nonce' => wp_create_nonce( 'save-default-attributes' ),
						'product_id'            => absint( get_query_var( 'edit-product' ) ),
						'per_page'              => 15,
						'placeholder_img'       => wc_placeholder_img_src( 'thumbnail' ),
						'i18n'                  => array(
							'confirm_remove'     => __( 'Remove this variation?', 'storesuite' ),
							'confirm_remove_attribute' => __( 'Remove this attribute?', 'storesuite' ),
							'confirm_delete_all' => __( 'Delete all variations? This cannot be undone.', 'storesuite' ),
							'generated'          => __( 'variations created.', 'storesuite' ),
							'no_attributes'      => __( 'Add variation attributes first.', 'storesuite' ),
							'saved'              => __( 'Changes saved.', 'storesuite' ),
							'loading_variations'     => __( 'Loading variations…', 'storesuite' ),
							'no_variations'          => __( 'No variations found.', 'storesuite' ),
							/* translators: %1$s: start item, %2$s: end item, %3$s: total items */
							'showing'                => __( 'Showing %1$s to %2$s of %3$s', 'storesuite' ),
							'confirm_generate'       => __( 'Generate variations?', 'storesuite' ),
							'confirm_generate_text'  => __( 'This will create variations for all attribute combinations.', 'storesuite' ),
							'ok_button'              => __( 'OK', 'storesuite' ),
							'enter_price'            => __( 'Enter price', 'storesuite' ),
							'set_regular_price'      => __( 'Set regular price for all variations', 'storesuite' ),
							'set_sale_price'         => __( 'Set sale price for all variations', 'storesuite' ),
							'select_stock_status'    => __( 'Select stock status for all variations', 'storesuite' ),
							'confirm_toggle_enabled' => __( 'Toggle enabled/disabled status for all variations?', 'storesuite' ),
							'in_stock'               => __( 'In stock', 'storesuite' ),
							'out_of_stock'           => __( 'Out of stock', 'storesuite' ),
							'on_backorder'           => __( 'On backorder', 'storesuite' ),
							'enter_a_value'          => __( 'Enter a value', 'storesuite' ),
							'enter_value_fixed_or_percent' => __( 'Enter a value (fixed amount or percentage, e.g. 10 or 10%)', 'storesuite' ),
							'confirm_remove_cogs'    => __( 'Remove the custom cost from every variation?', 'storesuite' ),
							'sale_start_date'        => __( 'Sale start date (leave blank to skip)', 'storesuite' ),
							'sale_end_date'          => __( 'Sale end date (leave blank to skip)', 'storesuite' ),
							'next_button'            => __( 'Next', 'storesuite' ),
							'choose_variation_image' => __( 'Choose variation image', 'storesuite' ),
							'set_image'              => __( 'Set image', 'storesuite' ),
							'remove_image'           => __( 'Remove image', 'storesuite' ),
							'defaults_saved'         => __( 'Default attributes saved.', 'storesuite' ),
							'add_button'             => __( 'Add', 'storesuite' ),
							'add_term_title'         => __( 'Add new term', 'storesuite' ),
							'add_term_placeholder'   => __( 'Term name', 'storesuite' ),
							'term_required'          => __( 'Please enter a name.', 'storesuite' ),
						),
                    )
				);
			}

			wp_enqueue_script( 'storesuite_product_inline_edit_script' );
			wp_localize_script(
				'storesuite_product_inline_edit_script',
				'StoreSuite_ProductInlineEdit',
				array(
					'ajax_url'       => admin_url( 'admin-ajax.php' ),
					'nonce'          => wp_create_nonce( \PluginizeLab\StoreSuite\Product\ProductInlineEdit::NONCE_ACTION ),
					'context'        => $is_inventory ? 'inventory' : 'products',
					'statuses'       => array_intersect_key(
						storesuite_get_post_status(),
						array_flip( \PluginizeLab\StoreSuite\Product\ProductInlineEdit::EDITABLE_STATUSES )
					),
					'stock_statuses' => wc_get_product_stock_status_options(),
					'i18n'           => array(
						'error_title'      => __( 'Update failed', 'storesuite' ),
						'ok_button'        => __( 'OK', 'storesuite' ),
						'unexpected_error' => __( 'An unexpected error occurred. Please try again.', 'storesuite' ),
						'hint'             => __( 'Enter to save · Esc to cancel', 'storesuite' ),
						'regular_price'    => __( 'Regular price', 'storesuite' ),
						'sale_price'       => __( 'Sale price', 'storesuite' ),
						'stock_quantity'   => __( 'Stock quantity', 'storesuite' ),
						'sku'              => __( 'SKU', 'storesuite' ),
						'status'           => __( 'Status', 'storesuite' ),
						'stock_status'     => __( 'Stock status', 'storesuite' ),
					),
				)
			);

			if ( $is_products ) {
				wp_enqueue_script( 'storesuite_product_export_script' );
				wp_localize_script(
                    'storesuite_product_export_script',
                    'StoreSuite_ProductExport',
                    array(
						'ajax_url'     => admin_url( 'admin-ajax.php' ),
						'export_nonce' => wp_create_nonce( 'storesuite_product_export' ),
						'i18n'         => array(
							'error_title'             => __( 'Error!', 'storesuite' ),
							'ok_button'               => __( 'OK', 'storesuite' ),
							'unexpected_error'        => __( 'An unexpected error occurred. Please try again.', 'storesuite' ),
							'select_products_title'   => __( 'Select products', 'storesuite' ),
							'select_products_message' => __( 'Choose at least one product to export.', 'storesuite' ),
							/* translators: %1$s: number of products, %2$s: "clear your selection" link. */
							'bulk_export_notice'      => __( 'You are about to export %1$s products. To export all products, %2$s.', 'storesuite' ),
							'clear_selection'         => __( 'clear your selection', 'storesuite' ),
						),
                    )
				);
			}
		}
	}

	/**
	 * Remove theme and other-plugin assets on the StoreSuite dashboard (keep allowed plugins).
	 *
	 * @return void
	 */
	public function remove_all_theme_assets() {
		if ( ! storesuite_is_dashboard_page() ) {
			return;
		}

		$allowed_plugins = apply_filters( 'storesuite_allowed_plugin_slugs', self::$allowed_plugin_slugs );
		$allowed_plugins = array_filter( array_map( 'strval', (array) $allowed_plugins ) );

		$allowed_handles = apply_filters( 'storesuite_allowed_asset_handles', self::$allowed_asset_handles );
		$allowed_handles = array_filter( array_map( 'strval', (array) $allowed_handles ) );

		$theme_uris = array_filter(
			array_unique(
				array(
					rtrim( get_stylesheet_directory_uri(), '/' ),
					rtrim( get_template_directory_uri(), '/' ),
				)
			)
		);

		global $wp_styles, $wp_scripts;

		if ( ! empty( $wp_styles->registered ) ) {
			foreach ( $wp_styles->registered as $handle => $obj ) {
				if ( in_array( $handle, $allowed_handles, true ) ) {
					continue;
				}
				if ( isset( $obj->src ) && $this->should_remove_asset( $obj->src, $theme_uris, $allowed_plugins ) ) {
					wp_dequeue_style( $handle );
					wp_deregister_style( $handle );
				}
			}
		}

		if ( ! empty( $wp_scripts->registered ) ) {
			foreach ( $wp_scripts->registered as $handle => $obj ) {
				if ( in_array( $handle, $allowed_handles, true ) ) {
					continue;
				}
				if ( isset( $obj->src ) && $this->should_remove_asset( $obj->src, $theme_uris, $allowed_plugins ) ) {
					wp_dequeue_script( $handle );
					wp_deregister_script( $handle );
				}
			}
		}
	}
}
