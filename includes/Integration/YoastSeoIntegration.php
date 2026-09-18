<?php

namespace PluginizeLab\StoreSuite\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WC_Product;

/**
 * Lets shop managers edit a product's Yoast SEO settings from the StoreSuite
 * product add/edit form.
 *
 * The class is fully self-contained: the product form only exposes the generic
 * `storesuite_product_form_after_main_cards` hook, and this integration renders
 * its card there and persists the values once the product has been saved.
 *
 * Every touch point with Yoast (active check, options, meta writes) is a small
 * protected method so the integration can be tested without Yoast installed.
 */
class YoastSeoIntegration {

	/**
	 * Post meta prefix Yoast SEO stores its per-post values under.
	 */
	const META_PREFIX = '_yoast_wpseo_';

	/**
	 * Prefix of the form field names this integration owns.
	 */
	const FIELD_PREFIX = 'storesuite_yoast_';

	/**
	 * Hidden marker posted by the SEO card. Without it a product save did not
	 * come from a form showing the card, so nothing is touched.
	 */
	const FORM_MARKER = 'storesuite_yoast_seo';

	/**
	 * Constructor. Bails unless Yoast SEO is active.
	 */
	public function __construct() {
		if ( ! $this->is_yoast_active() ) {
			return;
		}

		add_action( 'storesuite_product_form_after_main_cards', array( $this, 'render_card' ), 10, 2 );
		add_action( 'storesuite_new_product_added', array( $this, 'save' ) );
		add_action( 'storesuite_product_updated', array( $this, 'save' ) );
		// Priority 20: after Assets has enqueued the product form script this one depends on.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );
	}

	/**
	 * Whether Yoast SEO is active.
	 *
	 * @return bool
	 */
	protected function is_yoast_active(): bool {
		return defined( 'WPSEO_VERSION' );
	}

	/**
	 * Read one of Yoast's site-wide options.
	 *
	 * @param string $key      Option name, e.g. `enable_cornerstone_content`.
	 * @param mixed  $fallback Value when Yoast's options API is unavailable.
	 *
	 * @return mixed
	 */
	protected function get_yoast_option( string $key, $fallback = null ) {
		if ( ! class_exists( 'WPSEO_Options' ) ) {
			return $fallback;
		}

		return \WPSEO_Options::get( $key, $fallback );
	}

	/**
	 * Store a Yoast meta value, or remove it when the value is empty so
	 * Yoast's site-wide defaults keep applying.
	 *
	 * @param string $key     Yoast meta key without the prefix.
	 * @param string $value   Sanitized value.
	 * @param int    $post_id Product ID.
	 *
	 * @return void
	 */
	protected function set_meta( string $key, string $value, int $post_id ): void {
		if ( '' === $value ) {
			delete_post_meta( $post_id, self::META_PREFIX . $key );
			return;
		}

		if ( class_exists( 'WPSEO_Meta' ) ) {
			\WPSEO_Meta::set_value( $key, $value, $post_id );
			return;
		}

		update_post_meta( $post_id, self::META_PREFIX . $key, wp_slash( $value ) );
	}

	/**
	 * Read a stored Yoast meta value.
	 *
	 * @param string $key     Yoast meta key without the prefix.
	 * @param int    $post_id Product ID.
	 *
	 * @return string
	 */
	protected function get_meta( string $key, int $post_id ): string {
		return (string) get_post_meta( $post_id, self::META_PREFIX . $key, true );
	}

	/**
	 * The title separator configured in Yoast, e.g. "-" or "|".
	 *
	 * @return string
	 */
	protected function get_title_separator(): string {
		if ( function_exists( 'YoastSEO' ) ) {
			return (string) YoastSEO()->helpers->options->get_title_separator();
		}

		return '-';
	}

	/**
	 * Whether the current request is the dashboard's product add/edit form.
	 *
	 * @return bool
	 */
	protected function is_product_form_page(): bool {
		return storesuite_is_dashboard_page()
			&& ( storesuite_is_endpoint_url( 'add-new-product' ) || storesuite_is_endpoint_url( 'edit-product' ) );
	}

	/**
	 * Whether Yoast's cornerstone content feature is switched on.
	 *
	 * @return bool
	 */
	protected function is_cornerstone_enabled(): bool {
		return (bool) $this->get_yoast_option( 'enable_cornerstone_content', false );
	}

	/**
	 * The Yoast meta keys this integration may write, mapped to their field type.
	 *
	 * This is the whitelist: a posted field that is not listed here is never saved.
	 *
	 * @return array<string, string>
	 */
	protected function get_fields(): array {
		$fields = array(
			'focuskw'  => 'text',
			'title'    => 'text',
			'metadesc' => 'text',
		);

		if ( $this->is_cornerstone_enabled() ) {
			$fields['is_cornerstone'] = 'checkbox';
		}

		return $fields;
	}

	/**
	 * Tabs shown on the card, keyed by slug. The tab strip only appears with more than one.
	 *
	 * @return array<string, string>
	 */
	protected function get_tabs(): array {
		return array(
			'seo' => __( 'SEO', 'storesuite' ),
		);
	}

	/**
	 * Render the "SEO (Yoast)" card on the product add/edit form.
	 *
	 * @param WC_Product|null $product      Product being edited, null on the add form.
	 * @param bool            $is_edit_mode Whether this is the edit form.
	 *
	 * @return void
	 */
	public function render_card( $product, $is_edit_mode ) {
		$post_id = $product instanceof WC_Product ? $product->get_id() : 0;
		$values  = array();

		foreach ( array_keys( $this->get_fields() ) as $key ) {
			$values[ $key ] = $post_id ? $this->get_meta( $key, $post_id ) : '';
		}

		storesuite_get_template_part(
			'products/product-seo-yoast',
			'',
			array(
				'product'             => $product,
				'is_edit_mode'        => (bool) $is_edit_mode,
				'form_marker'         => self::FORM_MARKER,
				'field_prefix'        => self::FIELD_PREFIX,
				'seo_tabs'            => $this->get_tabs(),
				'seo_values'          => $values,
				'title_template'      => (string) $this->get_yoast_option( 'title-product', '' ),
				'desc_template'       => (string) $this->get_yoast_option( 'metadesc-product', '' ),
				'cornerstone_enabled' => $this->is_cornerstone_enabled(),
			)
		);
	}

	/**
	 * Persist the posted SEO fields once the product has been created or updated.
	 *
	 * The product controller has already verified the form nonce and the
	 * `manage_woocommerce` capability before these actions fire.
	 *
	 * @param int $product_id Product ID.
	 *
	 * @return void
	 */
	public function save( $product_id ) {
		$product_id = absint( $product_id );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified by ProductController before the save actions fire.
		if ( ! $product_id || empty( $_POST[ self::FORM_MARKER ] ) ) {
			return;
		}

		foreach ( $this->get_fields() as $key => $type ) {
			$field = self::FIELD_PREFIX . $key;

			if ( 'checkbox' === $type ) {
				// Unchecked checkboxes are not posted; the form marker proves the card was shown.
				$this->set_meta( $key, empty( $_POST[ $field ] ) ? '' : '1', $product_id );
				continue;
			}

			if ( ! isset( $_POST[ $field ] ) || ! is_string( $_POST[ $field ] ) ) {
				continue;
			}

			$this->set_meta( $key, sanitize_text_field( wp_unslash( $_POST[ $field ] ) ), $product_id );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Snippet variables offered by the "Insert variable" menu, recommended ones first.
	 *
	 * Only variables the preview can resolve from the product form are listed;
	 * any other Yoast variable can still be typed by hand.
	 *
	 * @return array<int, array{name: string, label: string, recommended: bool}>
	 */
	protected function get_variables(): array {
		$recommended = array(
			'sitename' => __( 'Site title', 'storesuite' ),
			'title'    => __( 'Title', 'storesuite' ),
			'sep'      => __( 'Separator', 'storesuite' ),
		);

		$others = array(
			'excerpt'          => __( 'Excerpt', 'storesuite' ),
			'excerpt_only'     => __( 'Excerpt only', 'storesuite' ),
			'primary_category' => __( 'Primary category', 'storesuite' ),
			'category'         => __( 'Category', 'storesuite' ),
			'tag'              => __( 'Tag', 'storesuite' ),
			'focuskw'          => __( 'Focus keyphrase', 'storesuite' ),
			'sitedesc'         => __( 'Tagline', 'storesuite' ),
			'pt_single'        => __( 'Post type (singular)', 'storesuite' ),
			'pt_plural'        => __( 'Post type (plural)', 'storesuite' ),
			'currentdate'      => __( 'Current date', 'storesuite' ),
			'currentday'       => __( 'Current day', 'storesuite' ),
			'currentmonth'     => __( 'Current month', 'storesuite' ),
			'currentyear'      => __( 'Current year', 'storesuite' ),
		);

		$variables = array();

		foreach ( $recommended as $name => $label ) {
			$variables[] = array(
				'name'        => $name,
				'label'       => $label,
				'recommended' => true,
			);
		}

		foreach ( $others as $name => $label ) {
			$variables[] = array(
				'name'        => $name,
				'label'       => $label,
				'recommended' => false,
			);
		}

		return $variables;
	}

	/**
	 * Data localized for the snippet preview script.
	 *
	 * @return array
	 */
	public function get_script_data(): array {
		$post_type = get_post_type_object( 'product' );
		$site_icon = get_site_icon_url( 32 );

		return array(
			'titleTemplate' => (string) $this->get_yoast_option( 'title-product', '%%title%% %%page%% %%sep%% %%sitename%%' ),
			'descTemplate'  => (string) $this->get_yoast_option( 'metadesc-product', '' ),
			'titleMaxWidth' => 600,
			'descMinLength' => 120,
			'descMaxLength' => 156,
			'homeUrl'       => home_url( '/' ),
			'siteIcon'      => $site_icon ? $site_icon : '',
			'variables'     => $this->get_variables(),
			// Values the form cannot supply; the script resolves the rest from the live fields.
			'replacements'  => array(
				'sep'          => $this->get_title_separator(),
				'sitename'     => wp_strip_all_tags( get_bloginfo( 'name' ), true ),
				'sitedesc'     => wp_strip_all_tags( get_bloginfo( 'description' ), true ),
				'pt_single'    => $post_type ? $post_type->labels->singular_name : '',
				'pt_plural'    => $post_type ? $post_type->labels->name : '',
				'currentdate'  => date_i18n( get_option( 'date_format' ) ),
				'currentday'   => date_i18n( 'j' ),
				'currentmonth' => date_i18n( 'F' ),
				'currentyear'  => date_i18n( 'Y' ),
				'page'         => '',
			),
			'i18n'          => array(
				'noMatches'        => __( 'No matching variables', 'storesuite' ),
				'titleFallback'    => __( 'Please provide an SEO title by editing the snippet below.', 'storesuite' ),
				'descFallback'     => __( 'Please provide a meta description by editing the snippet below. If you don’t, Google will try to find a relevant part of your product to show in the search results.', 'storesuite' ),
				'switchToDesktop'  => __( 'Switch to desktop preview', 'storesuite' ),
				'switchToMobile'   => __( 'Switch to mobile preview', 'storesuite' ),
				'recommendedGroup' => __( 'Recommended', 'storesuite' ),
			),
		);
	}

	/**
	 * Load the snippet preview script on the product add/edit form only.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->is_product_form_page() ) {
			return;
		}

		// Like the core assets: file mtime under SCRIPT_DEBUG so edits show up without a version bump.
		$script  = STORESUITE_DIR . '/assets/frontend/product-seo.js';
		$version = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG && file_exists( $script ) ? (string) filemtime( $script ) : STORESUITE_PLUGIN_VERSION;

		wp_enqueue_script(
			'storesuite_product_seo_script',
			STORESUITE_PLUGIN_ASSET . '/frontend/product-seo.js',
			array( 'jquery', 'storesuite_product_script' ),
			$version,
			true
		);
		wp_localize_script( 'storesuite_product_seo_script', 'StoreSuite_ProductSeo', $this->get_script_data() );
	}
}
