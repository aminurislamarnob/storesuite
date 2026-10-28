<?php

namespace PluginizeLab\StoreSuite\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PluginizeLab\StoreSuite\Product\ProductAI;
use WP_Term;

/**
 * Lets shop managers edit a term's Yoast SEO settings from the StoreSuite
 * category, tag and brand add/edit forms.
 *
 * The class is fully self-contained: the term forms only expose the generic
 * `storesuite_term_form_after_fields` hook, and this integration renders its
 * card there and persists the values on the core `created_term` / `edit_term`
 * actions — before Yoast's Indexable_Term_Watcher rebuilds the term indexable
 * at PHP_INT_MAX, so the archive reflects the change immediately.
 *
 * Every touch point with Yoast (active check, options, the
 * `wpseo_taxonomy_meta` reads and writes) is a small protected method so the
 * integration can be tested without Yoast installed.
 */
class YoastSeoTermIntegration {

	/**
	 * Prefix of the form field names this integration owns.
	 */
	const FIELD_PREFIX = 'storesuite_yoast_term_';

	/**
	 * Hidden marker posted by the SEO card. The core term hooks fire for every
	 * term write on the site; without the marker a save did not come from a
	 * form showing the card, so nothing is touched.
	 */
	const FORM_MARKER = 'storesuite_yoast_term_seo';

	/**
	 * Constructor. Bails unless Yoast SEO is active.
	 */
	public function __construct() {
		if ( ! $this->is_yoast_active() ) {
			return;
		}

		add_action( 'storesuite_term_form_after_fields', array( $this, 'render_card' ), 10, 2 );
		add_action( 'created_term', array( $this, 'save' ), 10, 3 );
		add_action( 'edit_term', array( $this, 'save' ), 10, 3 );
		// Priority 20: after Assets has enqueued the dashboard scripts.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );
		$this->register_ai_hooks();
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
	 * @param string $key      Option name, e.g. `display-metabox-tax-product_cat`.
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
	 * Write a term's Yoast values into the `wpseo_taxonomy_meta` option.
	 *
	 * Yoast's `set_values()` validates every value and strips defaults itself,
	 * so callers send `'default'` / empty strings rather than deleting keys.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy name.
	 * @param array  $values   Values keyed by Yoast key (`wpseo_*`).
	 *
	 * @return void
	 */
	protected function set_term_values( int $term_id, string $taxonomy, array $values ): void {
		if ( class_exists( 'WPSEO_Taxonomy_Meta' ) ) {
			\WPSEO_Taxonomy_Meta::set_values( $term_id, $taxonomy, $values );
		}
	}

	/**
	 * Read a term's stored Yoast values, merged with Yoast's per-term defaults.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy name.
	 *
	 * @return array Values keyed by Yoast key (`wpseo_*`), empty when unavailable.
	 */
	protected function get_term_values( int $term_id, string $taxonomy ): array {
		if ( ! class_exists( 'WPSEO_Taxonomy_Meta' ) ) {
			return array();
		}

		$values = \WPSEO_Taxonomy_Meta::get_term_meta( $term_id, $taxonomy );

		return is_array( $values ) ? $values : array();
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
	 * Render the "SEO (Yoast)" card on the term add/edit form.
	 *
	 * @param string       $taxonomy Taxonomy of the form's term.
	 * @param WP_Term|null $term     Term being edited, null on the add form.
	 *
	 * @return void
	 */
	public function render_card( $taxonomy, $term = null ) {
		$taxonomy = (string) $taxonomy;

		if ( ! $this->is_supported_taxonomy( $taxonomy ) ) {
			return;
		}

		$term    = $term instanceof WP_Term ? $term : null;
		$term_id = $term ? $term->term_id : 0;
		$stored  = $term_id ? $this->get_term_values( $term_id, $taxonomy ) : array();
		$values  = array();

		foreach ( $this->get_fields() as $field_key => $field ) {
			$values[ $field_key ] = (string) ( $stored[ $field['key'] ] ?? '' );
		}

		if ( empty( $values['noindex'] ) && isset( $values['noindex'] ) ) {
			$values['noindex'] = 'default';
		}

		$image_previews = array();

		foreach ( array_keys( $this->get_social_networks() ) as $network ) {
			$image_id                   = absint( $values[ $network . '-image-id' ] ?? 0 );
			$image_previews[ $network ] = $image_id ? (string) wp_get_attachment_image_url( $image_id, 'medium' ) : '';
		}

		$taxonomy_object = get_taxonomy( $taxonomy );

		storesuite_get_template_part(
			'common/term-seo-yoast',
			'',
			array(
				'taxonomy'            => $taxonomy,
				'term'                => $term,
				'form_marker'         => self::FORM_MARKER,
				'field_prefix'        => self::FIELD_PREFIX,
				'seo_tabs'            => $this->get_tabs(),
				'seo_values'          => $values,
				'title_template'      => (string) $this->get_yoast_option( 'title-tax-' . $taxonomy, '' ),
				'desc_template'       => (string) $this->get_yoast_option( 'metadesc-tax-' . $taxonomy, '' ),
				'cornerstone_enabled' => $this->is_cornerstone_enabled(),
				'social_networks'     => $this->get_social_networks(),
				'image_previews'      => $image_previews,
				'can_edit_advanced'   => $this->can_edit_advanced(),
				'breadcrumbs_enabled' => $this->are_breadcrumbs_enabled(),
				'noindex_by_default'  => (bool) $this->get_yoast_option( 'noindex-tax-' . $taxonomy, false ),
				'taxonomy_label'      => $taxonomy_object && isset( $taxonomy_object->labels->name ) ? $taxonomy_object->labels->name : $taxonomy,
			)
		);
	}

	/**
	 * Taxonomies whose terms this integration may edit.
	 *
	 * @return string[]
	 */
	protected function get_supported_taxonomies(): array {
		return array( 'product_cat', 'product_tag', 'product_brand' );
	}

	/**
	 * Whether the card may show — and the fields may save — for a taxonomy.
	 *
	 * Mirrors Yoast's own term metabox gates: the taxonomy must be whitelisted
	 * here, public, and not hidden through Yoast's "Show SEO settings" toggle
	 * (`display-metabox-tax-{taxonomy}`).
	 *
	 * @param string $taxonomy Taxonomy name.
	 *
	 * @return bool
	 */
	protected function is_supported_taxonomy( string $taxonomy ): bool {
		if ( ! in_array( $taxonomy, $this->get_supported_taxonomies(), true ) ) {
			return false;
		}

		$taxonomy_object = get_taxonomy( $taxonomy );

		if ( ! $taxonomy_object || empty( $taxonomy_object->public ) ) {
			return false;
		}

		return (bool) $this->get_yoast_option( 'display-metabox-tax-' . $taxonomy, false );
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
	 * Whether the current user may edit a term's advanced SEO settings
	 * (noindex, canonical URL, breadcrumbs title).
	 *
	 * Mirrors the Yoast term metabox: allowed with Yoast's advanced-metadata
	 * capability, or for everyone once the site owner switches off Yoast's
	 * "security: advanced settings for authors" option. Unlike Yoast — whose
	 * term save loop trusts the rendered form — the rule is re-checked on save.
	 *
	 * @return bool
	 */
	public function can_edit_advanced(): bool {
		$allowed = $this->current_user_has_yoast_advanced_capability()
			|| false === $this->get_yoast_option( 'disableadvanced_meta', true );

		/**
		 * Filters whether the current user may edit advanced Yoast SEO settings on the term forms.
		 *
		 * @param bool $allowed Whether Yoast's own rules allow it.
		 */
		return (bool) apply_filters( 'storesuite_yoast_term_can_edit_advanced', $allowed );
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
	 * Whether Yoast's breadcrumbs are switched on.
	 *
	 * @return bool
	 */
	protected function are_breadcrumbs_enabled(): bool {
		return (bool) $this->get_yoast_option( 'breadcrumbs-enable', false );
	}

	/**
	 * Social networks whose Yoast feature is switched on, keyed by Yoast's key prefix.
	 *
	 * @return array<string, string> Prefix => label.
	 */
	protected function get_social_networks(): array {
		$networks = array();

		if ( $this->get_yoast_option( 'opengraph', false ) ) {
			// Yoast's name for the Open Graph block: it feeds Facebook, WhatsApp, LinkedIn and others.
			$networks['opengraph'] = __( 'Social media appearance', 'storesuite' );
		}

		if ( $this->get_yoast_option( 'twitter', false ) ) {
			$networks['twitter'] = __( 'X appearance', 'storesuite' );
		}

		return $networks;
	}

	/**
	 * The Yoast term keys this integration may write, keyed by field name
	 * (without the `storesuite_yoast_term_` prefix).
	 *
	 * This is the whitelist: a posted field that is not listed here — including
	 * one the current user is not allowed to edit — is never saved.
	 *
	 * Types: `text`, `checkbox` (stored as "1"/"0"), `url`, `choice` (only
	 * accepts its `choices`) and `image` (an attachment ID; the image URL is
	 * derived server-side and stored under `url_key`).
	 *
	 * @return array<string, array>
	 */
	protected function get_fields(): array {
		$fields = array(
			'focuskw'  => array(
				'type' => 'text',
				'key'  => 'wpseo_focuskw',
			),
			'title'    => array(
				'type' => 'text',
				'key'  => 'wpseo_title',
			),
			'metadesc' => array(
				'type' => 'text',
				'key'  => 'wpseo_desc',
			),
		);

		if ( $this->is_cornerstone_enabled() ) {
			$fields['is_cornerstone'] = array(
				'type' => 'checkbox',
				'key'  => 'wpseo_is_cornerstone',
			);
		}

		foreach ( array_keys( $this->get_social_networks() ) as $network ) {
			$fields[ $network . '-title' ]       = array(
				'type' => 'text',
				'key'  => 'wpseo_' . $network . '-title',
			);
			$fields[ $network . '-description' ] = array(
				'type' => 'text',
				'key'  => 'wpseo_' . $network . '-description',
			);
			$fields[ $network . '-image-id' ]    = array(
				'type'    => 'image',
				'key'     => 'wpseo_' . $network . '-image-id',
				'url_key' => 'wpseo_' . $network . '-image',
			);
		}

		if ( $this->can_edit_advanced() ) {
			// Yoast's term enum — not the 0/1/2 model posts use.
			$fields['noindex']   = array(
				'type'    => 'choice',
				'key'     => 'wpseo_noindex',
				'choices' => array( 'default', 'index', 'noindex' ),
			);
			$fields['canonical'] = array(
				'type' => 'url',
				'key'  => 'wpseo_canonical',
			);

			if ( $this->are_breadcrumbs_enabled() ) {
				$fields['bctitle'] = array(
					'type' => 'text',
					'key'  => 'wpseo_bctitle',
				);
			}
		}

		return $fields;
	}

	/**
	 * Persist the posted SEO fields once the term has been created or updated.
	 *
	 * Runs on the core `created_term` / `edit_term` actions, which fire for
	 * every term write on the site, so every gate is re-checked here: the card
	 * marker, the taxonomy whitelist, Yoast's metabox gates and the capability.
	 *
	 * Values the integration does not manage — or may not change, like another
	 * field left out of the form — are sent back unchanged: Yoast's
	 * `set_values()` rebuilds the term entry from defaults, so anything missing
	 * from the payload would be wiped.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID (unused).
	 * @param string $taxonomy Taxonomy name.
	 *
	 * @return void
	 */
	public function save( $term_id, $tt_id, $taxonomy ) {
		$term_id  = absint( $term_id );
		$taxonomy = (string) $taxonomy;

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified by the term AJAX controllers before the core term hooks fire; the marker, taxonomy whitelist and capability are re-checked because these hooks fire for every term write.
		if ( ! $term_id || empty( $_POST[ self::FORM_MARKER ] ) ) {
			return;
		}

		if ( ! $this->is_supported_taxonomy( $taxonomy ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$values = array();

		foreach ( $this->get_fields() as $field_key => $field ) {
			$name = self::FIELD_PREFIX . $field_key;

			if ( 'checkbox' === $field['type'] ) {
				// Unchecked checkboxes are not posted; the form marker proves the card was shown.
				$values[ $field['key'] ] = empty( $_POST[ $name ] ) ? '0' : '1';
				continue;
			}

			if ( ! isset( $_POST[ $name ] ) || ! is_string( $_POST[ $name ] ) ) {
				continue;
			}

			$value = sanitize_text_field( wp_unslash( $_POST[ $name ] ) );

			if ( 'image' === $field['type'] ) {
				$values = array_merge( $values, $this->get_image_values( $field['key'], $field['url_key'], $value ) );
				continue;
			}

			if ( 'choice' === $field['type'] && ! in_array( $value, $field['choices'], true ) ) {
				continue;
			}

			if ( 'url' === $field['type'] ) {
				$value = esc_url_raw( $value, array( 'http', 'https' ) );
			}

			$values[ $field['key'] ] = $value;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$this->set_term_values( $term_id, $taxonomy, array_merge( $this->get_term_values( $term_id, $taxonomy ), $values ) );
	}

	/**
	 * Resolve a posted social image into the two values Yoast stores.
	 *
	 * Only the ID comes from the browser. It must be a real image attachment,
	 * and the URL Yoast outputs is looked up here, so an arbitrary external URL
	 * can never be injected into the share image. An empty ID clears both
	 * values; an invalid one changes nothing.
	 *
	 * @param string $id_key  Yoast key holding the attachment ID.
	 * @param string $url_key Yoast key holding the image URL.
	 * @param string $value   Posted attachment ID.
	 *
	 * @return array<string, string> Values keyed by Yoast key.
	 */
	protected function get_image_values( string $id_key, string $url_key, string $value ): array {
		if ( '' === $value || '0' === $value ) {
			return array(
				$id_key  => '',
				$url_key => '',
			);
		}

		$image_id = absint( $value );
		$url      = $image_id && wp_attachment_is_image( $image_id ) ? wp_get_attachment_url( $image_id ) : false;

		if ( ! $url ) {
			return array();
		}

		return array(
			$id_key  => (string) $image_id,
			$url_key => $url,
		);
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
	 * Snippet variables offered by the "Insert variable" menu, recommended ones first.
	 *
	 * Only variables the preview can resolve from the term form are listed;
	 * any other Yoast variable can still be typed by hand.
	 *
	 * @param bool   $hierarchical      Whether the taxonomy is hierarchical.
	 * @param string $description_alias Yoast's description alias for the taxonomy,
	 *                                  `category_description` or `tag_description`.
	 *
	 * @return array<int, array{name: string, label: string, recommended: bool}>
	 */
	protected function get_variables( bool $hierarchical, string $description_alias = '' ): array {
		$recommended = array(
			'sitename'   => __( 'Site title', 'storesuite' ),
			'term_title' => __( 'Term title', 'storesuite' ),
			'sep'        => __( 'Separator', 'storesuite' ),
		);

		$others = array(
			'term_description' => __( 'Term description', 'storesuite' ),
			'sitedesc'         => __( 'Tagline', 'storesuite' ),
			'currentdate'      => __( 'Current date', 'storesuite' ),
			'currentday'       => __( 'Current day', 'storesuite' ),
			'currentmonth'     => __( 'Current month', 'storesuite' ),
			'currentyear'      => __( 'Current year', 'storesuite' ),
		);

		// Yoast's taxonomy-flavoured alias: both resolve to the term description.
		if ( 'category_description' === $description_alias ) {
			$others = array_merge(
				array( 'category_description' => __( 'Category description', 'storesuite' ) ),
				$others
			);
		} elseif ( 'tag_description' === $description_alias ) {
			$others = array_merge(
				array( 'tag_description' => __( 'Tag description', 'storesuite' ) ),
				$others
			);
		}

		if ( $hierarchical ) {
			$others = array_merge(
				array( 'term_hierarchy' => __( 'Term hierarchy', 'storesuite' ) ),
				$others
			);
		}

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
	 * Data localized for the term snippet preview script.
	 *
	 * @param string       $taxonomy Taxonomy of the form's term.
	 * @param WP_Term|null $term     Term being edited, null on the add form.
	 *
	 * @return array
	 */
	public function get_script_data( string $taxonomy, ?WP_Term $term = null ): array {
		$taxonomy_object = get_taxonomy( $taxonomy );
		$hierarchical    = $taxonomy_object && ! empty( $taxonomy_object->hierarchical );
		$alias           = $hierarchical ? 'category_description' : 'tag_description';
		$site_icon       = get_site_icon_url( 32 );

		return array(
			'taxonomy'         => $taxonomy,
			'hierarchical'     => $hierarchical,
			// The live form fields the preview reads, per taxonomy.
			'fields'           => $this->get_form_field_selectors( $taxonomy ),
			// The term archive path the live slug is appended to.
			'urlBase'          => $this->get_preview_url_base( $taxonomy, $term ),
			// Yoast's taxonomy-flavoured alias of %%term_description%%.
			'descriptionAlias' => $alias,
			// %%term_hierarchy%% ancestors; the script appends the live name.
			'hierarchyBase'    => $this->get_hierarchy_base( $taxonomy, $term ),
			'titleTemplate'    => (string) $this->get_yoast_option( 'title-tax-' . $taxonomy, '%%term_title%% Archives %%page%% %%sep%% %%sitename%%' ),
			'descTemplate'     => (string) $this->get_yoast_option( 'metadesc-tax-' . $taxonomy, '' ),
			// Yoast's page-title width assessment: 400-600px renders green.
			'titleMinWidth'    => 400,
			'titleMaxWidth'    => 600,
			'descMinLength'    => 120,
			'descMaxLength'    => 156,
			'homeUrl'          => home_url( '/' ),
			'siteIcon'         => $site_icon ? $site_icon : '',
			'variables'        => $this->get_variables( $hierarchical, $alias ),
			// Values the form cannot supply; the script resolves term_title,
			// term_description and the alias from the live name and description fields.
			'replacements'     => array(
				'sep'          => $this->get_title_separator(),
				'sitename'     => wp_strip_all_tags( get_bloginfo( 'name' ), true ),
				'sitedesc'     => wp_strip_all_tags( get_bloginfo( 'description' ), true ),
				'currentdate'  => date_i18n( get_option( 'date_format' ) ),
				'currentday'   => date_i18n( 'j' ),
				'currentmonth' => date_i18n( 'F' ),
				'currentyear'  => date_i18n( 'Y' ),
				'page'         => '',
			),
			'i18n'             => array(
				'noMatches'        => __( 'No matching variables', 'storesuite' ),
				'titleFallback'    => __( 'Please provide an SEO title by editing the snippet below.', 'storesuite' ),
				'descFallback'     => __( 'Please provide a meta description by editing the snippet below. If you don’t, Google will try to find a relevant part of this page to show in the search results.', 'storesuite' ),
				'switchToDesktop'  => __( 'Switch to desktop preview', 'storesuite' ),
				'switchToMobile'   => __( 'Switch to mobile preview', 'storesuite' ),
				'recommendedGroup' => __( 'Recommended', 'storesuite' ),
			),
		);
	}

	/**
	 * CSS selectors of the form fields that drive the live preview. The
	 * category and brand templates prefix their ids; the tag templates do not.
	 *
	 * @param string $taxonomy Taxonomy of the form's term.
	 *
	 * @return array{name: string, slug: string, description: string}|array
	 */
	protected function get_form_field_selectors( string $taxonomy ): array {
		switch ( $taxonomy ) {
			case 'product_cat':
				return array(
					'name'        => '#product_category_name',
					'slug'        => '#product_category_slug',
					'description' => '#product_category_description',
				);
			case 'product_tag':
				return array(
					'name'        => '#name',
					'slug'        => '#slug',
					'description' => '#description',
				);
			case 'product_brand':
				return array(
					'name'        => '#product_brand_name',
					'slug'        => '#product_brand_slug',
					'description' => '#product_brand_description',
				);
		}

		return array();
	}

	/**
	 * The term archive path shown in the Google preview, without the term's
	 * own slug — the script appends the live slug field itself.
	 *
	 * @param string       $taxonomy Taxonomy of the form's term.
	 * @param WP_Term|null $term     Term being edited, null on the add form.
	 *
	 * @return string
	 */
	protected function get_preview_url_base( string $taxonomy, ?WP_Term $term ): string {
		if ( $term ) {
			$link = get_term_link( $term, $taxonomy );

			// With plain permalinks the link is a query URL; fall back below.
			if ( ! is_wp_error( $link ) && false === strpos( $link, '?' ) ) {
				return preg_replace( '#[^/]+/$#', '', trailingslashit( $link ) );
			}
		}

		$taxonomy_object = get_taxonomy( $taxonomy );
		$rewrite         = $taxonomy_object && is_array( $taxonomy_object->rewrite ) && ! empty( $taxonomy_object->rewrite['slug'] )
			? $taxonomy_object->rewrite['slug']
			: $taxonomy;

		return home_url( user_trailingslashit( trailingslashit( ltrim( $rewrite, '/' ) ) ) );
	}

	/**
	 * `%%term_hierarchy%%` without its last segment: the term's ancestor
	 * names, root first, joined by Yoast's separator. The preview script
	 * appends the live name itself, so a rename shows up as typed.
	 *
	 * @param string       $taxonomy Taxonomy of the form's term.
	 * @param WP_Term|null $term     Term being edited, null on the add form.
	 *
	 * @return string Empty for flat taxonomies, top-level terms and the add form.
	 */
	protected function get_hierarchy_base( string $taxonomy, ?WP_Term $term ): string {
		if ( ! $term || ! is_taxonomy_hierarchical( $taxonomy ) ) {
			return '';
		}

		$names = array();

		// Closest parent first; the hierarchy reads root first.
		foreach ( get_ancestors( $term->term_id, $taxonomy, 'taxonomy' ) as $ancestor_id ) {
			$ancestor = get_term( $ancestor_id, $taxonomy );

			if ( $ancestor instanceof WP_Term ) {
				array_unshift( $names, $ancestor->name );
			}
		}

		return implode( ' ' . $this->get_title_separator() . ' ', $names );
	}

	/**
	 * Load the shared snippet-preview engine and the term adapter on the term
	 * add/edit forms only, mirroring the card's own taxonomy gates.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		$taxonomy = $this->get_form_taxonomy();

		if ( ! $taxonomy || ! $this->is_supported_taxonomy( $taxonomy ) ) {
			return;
		}

		// Like the core assets: file mtime under SCRIPT_DEBUG so edits show up without a version bump.
		$debug = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;

		$engine  = STORESUITE_DIR . '/assets/frontend/seo-preview.js';
		$adapter = STORESUITE_DIR . '/assets/frontend/term-seo.js';

		wp_enqueue_script(
			'storesuite_seo_preview_script',
			STORESUITE_PLUGIN_ASSET . '/frontend/seo-preview.js',
			array( 'jquery' ),
			$debug && file_exists( $engine ) ? (string) filemtime( $engine ) : STORESUITE_PLUGIN_VERSION,
			true
		);
		wp_enqueue_script(
			'storesuite_term_seo_script',
			STORESUITE_PLUGIN_ASSET . '/frontend/term-seo.js',
			array( 'jquery', 'storesuite_seo_preview_script' ),
			$debug && file_exists( $adapter ) ? (string) filemtime( $adapter ) : STORESUITE_PLUGIN_VERSION,
			true
		);
		wp_localize_script(
			'storesuite_term_seo_script',
			'StoreSuite_TermSeo',
			$this->get_script_data( $taxonomy, $this->get_editing_term( $taxonomy ) )
		);

		// The tag pages do not load wp.media on their own; the social image pickers need it.
		if ( $this->get_social_networks() ) {
			wp_enqueue_media();
		}
	}

	/* ----------------------------------------------------------------------
	 * AI Generate for the term SEO fields
	 * -------------------------------------------------------------------- */

	/**
	 * Hook the AI field registry, script and modals.
	 *
	 * The Generate buttons themselves are rendered by ProductAI on the card's
	 * field-label slots, and the product AJAX endpoint serves the fields.
	 *
	 * @return void
	 */
	protected function register_ai_hooks(): void {
		add_filter( 'storesuite_ai_text_fields', array( $this, 'register_ai_fields' ) );
		// Priority 30: after the card's own assets and Assets' registrations.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_ai_assets' ), 30 );
		add_action( 'storesuite_dashboard_wrapper_end', array( $this, 'render_ai_modals' ) );
	}

	/**
	 * The six SEO fields AI can generate: key => [ kind, platform, meta, length ].
	 *
	 * Mirrors the product card's set; social fields only for enabled networks.
	 *
	 * @return array<string, array{kind: string, platform: string, meta: string, length: int}>
	 */
	protected function get_ai_field_specs(): array {
		$specs = array(
			'yoast_term_title'    => array(
				'kind'     => 'title',
				'platform' => 'google',
				'meta'     => 'title',
				'length'   => 60,
			),
			'yoast_term_metadesc' => array(
				'kind'     => 'description',
				'platform' => 'google',
				'meta'     => 'metadesc',
				'length'   => 156,
			),
		);

		$platforms = array(
			'opengraph' => 'social',
			'twitter'   => 'x',
		);

		foreach ( array_keys( $this->get_social_networks() ) as $network ) {
			$specs[ 'yoast_term_' . $network . '-title' ]       = array(
				'kind'     => 'title',
				'platform' => $platforms[ $network ],
				'meta'     => $network . '-title',
				'length'   => 70,
			);
			$specs[ 'yoast_term_' . $network . '-description' ] = array(
				'kind'     => 'description',
				'platform' => $platforms[ $network ],
				'meta'     => $network . '-description',
				'length'   => 200,
			);
		}

		return $specs;
	}

	/**
	 * Register the term SEO fields with the AI field registry.
	 *
	 * They share the product Yoast fields' settings keys and group, so admins
	 * configure Yoast AI once.
	 *
	 * @param array $fields Registered AI text fields.
	 * @return array
	 */
	public function register_ai_fields( $fields ) {
		$instructions = YoastSeoIntegration::default_ai_instructions();
		$labels       = array(
			'yoast_term_title'                 => __( 'SEO title suggestion', 'storesuite' ),
			'yoast_term_metadesc'              => __( 'Meta description suggestion', 'storesuite' ),
			'yoast_term_opengraph-title'       => __( 'Social title suggestion', 'storesuite' ),
			'yoast_term_opengraph-description' => __( 'Social description suggestion', 'storesuite' ),
			'yoast_term_twitter-title'         => __( 'X title suggestion', 'storesuite' ),
			'yoast_term_twitter-description'   => __( 'X description suggestion', 'storesuite' ),
		);

		foreach ( $this->get_ai_field_specs() as $key => $spec ) {
			$is_title = 'title' === $spec['kind'];

			$fields[ $key ] = array(
				'label'               => $labels[ $key ],
				'group'               => 'yoast_seo',
				'instruction_label'   => $is_title
					? __( 'SEO title system instruction', 'storesuite' )
					: __( 'SEO description system instruction', 'storesuite' ),
				'output'              => $is_title ? 'text' : 'textarea',
				'instruction'         => $instructions[ $spec['kind'] ],
				'instruction_setting' => $is_title ? YoastSeoIntegration::AI_TITLE_INSTRUCTION_SETTING : YoastSeoIntegration::AI_DESCRIPTION_INSTRUCTION_SETTING,
				'enabled_setting'     => YoastSeoIntegration::AI_ENABLED_SETTING,
				'intro'               => $is_title
					? 'Write a title for the archive page of an online store.'
					: 'Write a description for the archive page of an online store.',
				// A term form has no product title; the term name and description arrive as raw request context.
				'needs_context'       => false,
				'target'              => '#' . self::FIELD_PREFIX . $spec['meta'],
				'rows'                => $is_title ? 2 : 4,
				'length'              => $spec['length'],
				'prompt_lines'        => function ( array $context, array $request ) use ( $spec ) {
					return $this->get_ai_prompt_lines( $spec, $request );
				},
			);
		}

		return $fields;
	}

	/**
	 * The posted fields naming a term and its description, per taxonomy.
	 *
	 * @return array<string, array{type: string, label: string, name: string, description: string}>
	 */
	protected function get_ai_request_fields(): array {
		return array(
			'product_cat'   => array(
				'type'        => 'category',
				'label'       => 'Category',
				'name'        => 'product_category_name',
				'description' => 'product_category_description',
			),
			'product_brand' => array(
				'type'        => 'brand',
				'label'       => 'Brand',
				'name'        => 'product_brand_name',
				'description' => 'product_brand_description',
			),
			'product_tag'   => array(
				'type'        => 'tag',
				'label'       => 'Tag',
				'name'        => 'name',
				'description' => 'description',
			),
		);
	}

	/**
	 * Prompt lines for a term SEO field: keyphrase, archive type, term name and details, then platform and length.
	 *
	 * @param array $spec    Field spec from get_ai_field_specs().
	 * @param array $request Raw request data (the form's fields flagged `data-ai-context`).
	 * @return string[]
	 */
	protected function get_ai_prompt_lines( array $spec, array $request ): array {
		$lines = array();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified by ProductAI::handle_generate() before this runs.
		$keyphrase = isset( $request[ self::FIELD_PREFIX . 'focuskw' ] ) ? sanitize_text_field( wp_unslash( $request[ self::FIELD_PREFIX . 'focuskw' ] ) ) : '';
		if ( '' !== $keyphrase ) {
			$lines[] = 'Focus keyphrase: ' . $keyphrase;
		}

		foreach ( $this->get_ai_request_fields() as $fields ) {
			if ( ! isset( $request[ $fields['name'] ] ) ) {
				continue;
			}

			$lines[] = 'Page type: a product ' . $fields['type'] . ' archive';

			$name = sanitize_text_field( wp_unslash( $request[ $fields['name'] ] ) );
			if ( '' !== $name ) {
				$lines[] = $fields['label'] . ' name: ' . $name;
			}

			// The description is context only, so markup is never sent.
			$details = isset( $request[ $fields['description'] ] ) ? trim( wp_strip_all_tags( wp_unslash( $request[ $fields['description'] ] ), true ) ) : '';
			if ( '' !== $details ) {
				if ( mb_strlen( $details ) > YoastSeoIntegration::AI_DESCRIPTION_CONTEXT_LENGTH ) {
					$details = rtrim( mb_substr( $details, 0, YoastSeoIntegration::AI_DESCRIPTION_CONTEXT_LENGTH ) ) . '…';
				}
				$lines[] = 'Page details: ' . $details;
			}
			break;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return array_merge( $lines, YoastSeoIntegration::get_ai_platform_lines( $spec, YoastSeoIntegration::site_language_name() ) );
	}

	/**
	 * Enqueue the product AI script on the term form pages, with its config.
	 *
	 * The script reads the localized StoreSuite_Product global, which only the
	 * product pages localize otherwise. Image AI has no place on term forms.
	 *
	 * @return void
	 */
	public function enqueue_ai_assets() {
		if ( ! $this->is_term_form_page() || ! ProductAI::is_text_supported() ) {
			return;
		}

		$config                     = ProductAI::get_script_config();
		$config['image']['enabled'] = false;

		wp_enqueue_script( 'storesuite_product_ai_script' );
		wp_localize_script(
			'storesuite_product_script',
			'StoreSuite_Product',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'i18n'     => array(
					'success_title'    => __( 'Success!', 'storesuite' ),
					'error_title'      => __( 'Error!', 'storesuite' ),
					'ok_button'        => __( 'OK', 'storesuite' ),
					'unexpected_error' => __( 'An unexpected error occurred. Please try again.', 'storesuite' ),
				),
				'ai'       => $config,
			)
		);
	}

	/**
	 * Render the AI suggestion modals (and their icon sprite) on the term form pages.
	 *
	 * Hooked on `storesuite_dashboard_wrapper_end`; only outputs when text
	 * generation is available.
	 *
	 * @return void
	 */
	public function render_ai_modals() {
		if ( ! $this->is_term_form_page() || ! ProductAI::is_text_supported() ) {
			return;
		}

		storesuite_get_template_part( 'products/product-icons' );
		storesuite_get_template_part( 'products/ai-text-modals' );
	}

	/**
	 * The taxonomy of the dashboard term add/edit form being viewed, or ''
	 * anywhere else.
	 *
	 * @return string
	 */
	protected function get_form_taxonomy(): string {
		if ( ! storesuite_is_dashboard_page() ) {
			return '';
		}

		$endpoints = array(
			'add-new-category' => 'product_cat',
			'edit-category'    => 'product_cat',
			'add-new-tag'      => 'product_tag',
			'edit-tag'         => 'product_tag',
			'add-new-brand'    => 'product_brand',
			'edit-brand'       => 'product_brand',
		);

		foreach ( $endpoints as $endpoint => $taxonomy ) {
			if ( storesuite_is_endpoint_url( $endpoint ) ) {
				return $taxonomy;
			}
		}

		return '';
	}

	/**
	 * The term the current edit form is for, null on the add forms.
	 *
	 * @param string $taxonomy Taxonomy of the form's term.
	 *
	 * @return WP_Term|null
	 */
	protected function get_editing_term( string $taxonomy ): ?WP_Term {
		foreach ( array( 'edit-category', 'edit-tag', 'edit-brand' ) as $endpoint ) {
			$term_id = absint( get_query_var( $endpoint ) );

			if ( $term_id ) {
				$term = get_term( $term_id, $taxonomy );

				return $term instanceof WP_Term ? $term : null;
			}
		}

		return null;
	}
}
