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
	 * Whether the current user holds Yoast's capability for advanced metadata.
	 *
	 * @return bool
	 */
	protected function current_user_has_yoast_advanced_capability(): bool {
		if ( class_exists( 'WPSEO_Capability_Utils' ) ) {
			return \WPSEO_Capability_Utils::current_user_can( 'wpseo_edit_advanced_metadata' );
		}

		return current_user_can( 'wpseo_edit_advanced_metadata' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown -- Registered by Yoast SEO.
	}

	/**
	 * Whether the current user may edit a product's advanced SEO settings
	 * (robots, breadcrumb title, canonical URL).
	 *
	 * Mirrors the Yoast metabox: allowed with Yoast's advanced-metadata
	 * capability, or for everyone once the site owner switches off Yoast's
	 * "security: advanced settings for authors" option. A wrong noindex or
	 * canonical can remove a product from search results, so StoreSuite does
	 * not grant more than wp-admin does unless the filter says so.
	 *
	 * @return bool
	 */
	public function can_edit_advanced(): bool {
		$allowed = $this->current_user_has_yoast_advanced_capability()
			|| false === $this->get_yoast_option( 'disableadvanced_meta', true );

		/**
		 * Filters whether the current user may edit advanced Yoast SEO settings on the product form.
		 *
		 * @param bool $allowed Whether Yoast's own rules allow it.
		 */
		return (bool) apply_filters( 'storesuite_yoast_can_edit_advanced', $allowed );
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
	 * Social networks whose Yoast feature is switched on, keyed by Yoast's meta key prefix.
	 *
	 * @return array<string, string> Prefix => label.
	 */
	protected function get_social_networks(): array {
		$networks = array();

		if ( $this->get_yoast_option( 'opengraph', false ) ) {
			$networks['opengraph'] = __( 'Facebook', 'storesuite' );
		}

		if ( $this->get_yoast_option( 'twitter', false ) ) {
			$networks['twitter'] = __( 'X (Twitter)', 'storesuite' );
		}

		return $networks;
	}

	/**
	 * The Yoast meta keys this integration may write, mapped to their definition.
	 *
	 * This is the whitelist: a posted field that is not listed here — including
	 * one the current user is not allowed to edit — is never saved.
	 *
	 * Types: `text`, `checkbox` (stored as "1"), `url`, `choice` (only accepts its
	 * `choices` and removes the meta for its `default`) and `image` (an attachment
	 * ID; the image URL is derived server-side and stored under `url_key`).
	 *
	 * @return array<string, array>
	 */
	protected function get_fields(): array {
		$fields = array(
			'focuskw'  => array( 'type' => 'text' ),
			'title'    => array( 'type' => 'text' ),
			'metadesc' => array( 'type' => 'text' ),
		);

		if ( $this->is_cornerstone_enabled() ) {
			$fields['is_cornerstone'] = array( 'type' => 'checkbox' );
		}

		foreach ( array_keys( $this->get_social_networks() ) as $network ) {
			$fields[ $network . '-title' ]       = array( 'type' => 'text' );
			$fields[ $network . '-description' ] = array( 'type' => 'text' );
			$fields[ $network . '-image-id' ]    = array(
				'type'    => 'image',
				'url_key' => $network . '-image',
			);
		}

		if ( $this->can_edit_advanced() ) {
			$fields['meta-robots-noindex']  = array(
				'type'    => 'choice',
				'choices' => array( '0', '2', '1' ),
				'default' => '0',
			);
			$fields['meta-robots-nofollow'] = array(
				'type'    => 'choice',
				'choices' => array( '0', '1' ),
				'default' => '0',
			);
			$fields['bctitle']              = array( 'type' => 'text' );
			$fields['canonical']            = array( 'type' => 'url' );
		}

		return $fields;
	}

	/**
	 * Tabs shown on the card, keyed by slug. The tab strip only appears with more than one.
	 *
	 * @return array<string, string>
	 */
	protected function get_tabs(): array {
		$tabs = array(
			'seo' => __( 'SEO', 'storesuite' ),
		);

		if ( $this->get_social_networks() ) {
			$tabs['social'] = __( 'Social', 'storesuite' );
		}

		if ( $this->can_edit_advanced() ) {
			$tabs['advanced'] = __( 'Advanced', 'storesuite' );
		}

		return $tabs;
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

		$image_previews = array();

		foreach ( array_keys( $this->get_social_networks() ) as $network ) {
			$image_id                   = absint( $values[ $network . '-image-id' ] ?? 0 );
			$image_previews[ $network ] = $image_id ? (string) wp_get_attachment_image_url( $image_id, 'medium' ) : '';
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
				'social_networks'     => $this->get_social_networks(),
				'image_previews'      => $image_previews,
				'can_edit_advanced'   => $this->can_edit_advanced(),
				'noindex_by_default'  => (bool) $this->get_yoast_option( 'noindex-product', false ),
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

		foreach ( $this->get_fields() as $key => $field ) {
			$name = self::FIELD_PREFIX . $key;

			if ( 'checkbox' === $field['type'] ) {
				// Unchecked checkboxes are not posted; the form marker proves the card was shown.
				$this->set_meta( $key, empty( $_POST[ $name ] ) ? '' : '1', $product_id );
				continue;
			}

			if ( ! isset( $_POST[ $name ] ) || ! is_string( $_POST[ $name ] ) ) {
				continue;
			}

			$value = sanitize_text_field( wp_unslash( $_POST[ $name ] ) );

			if ( 'image' === $field['type'] ) {
				$this->save_image( $key, $field['url_key'], $value, $product_id );
				continue;
			}

			if ( 'choice' === $field['type'] ) {
				if ( ! in_array( $value, $field['choices'], true ) ) {
					continue;
				}
				$value = $value === $field['default'] ? '' : $value;
			} elseif ( 'url' === $field['type'] ) {
				$value = esc_url_raw( $value, array( 'http', 'https' ) );
			}

			$this->set_meta( $key, $value, $product_id );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Store a social image from its attachment ID.
	 *
	 * Only the ID comes from the browser. It must be a real image attachment, and
	 * the URL Yoast outputs is looked up here, so an arbitrary external URL can
	 * never be injected into the share image. An empty ID removes the image.
	 *
	 * @param string $id_key     Yoast meta key (without prefix) holding the attachment ID.
	 * @param string $url_key    Yoast meta key (without prefix) holding the image URL.
	 * @param string $value      Posted attachment ID.
	 * @param int    $product_id Product ID.
	 *
	 * @return void
	 */
	protected function save_image( string $id_key, string $url_key, string $value, int $product_id ): void {
		if ( '' === $value || '0' === $value ) {
			$this->set_meta( $id_key, '', $product_id );
			$this->set_meta( $url_key, '', $product_id );
			return;
		}

		$image_id = absint( $value );
		$url      = $image_id && wp_attachment_is_image( $image_id ) ? wp_get_attachment_url( $image_id ) : false;

		if ( ! $url ) {
			return;
		}

		$this->set_meta( $id_key, (string) $image_id, $product_id );
		$this->set_meta( $url_key, $url, $product_id );
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
