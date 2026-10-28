<?php
/**
 * Tests for the Yoast SEO term form integration.
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test\Integration;

use PluginizeLab\StoreSuite\Integration\YoastSeoTermIntegration;
use PluginizeLab\StoreSuite\Test\StoreSuiteTestCase;

/**
 * Yoast SEO is not installed on CI, so the integration's Yoast touch points
 * are overridden by a test double; term meta reads and writes are recorded
 * in arrays instead of Yoast's `wpseo_taxonomy_meta` option.
 */
class YoastSeoTermIntegrationTest extends StoreSuiteTestCase {

	/**
	 * Category term under test.
	 *
	 * @var int
	 */
	private $category_id;

	/**
	 * Set up the test fixture.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->category_id = self::factory()->term->create( array( 'taxonomy' => 'product_cat' ) );
		wp_set_current_user( $this->shop_manager_id );
	}

	/**
	 * Tear down the test fixture.
	 *
	 * @return void
	 */
	public function tear_down() {
		$_POST = array();

		parent::tear_down();
	}

	/**
	 * Build the integration with Yoast reported as active or inactive.
	 *
	 * The double records every `set_term_values()` call in its public
	 * `$written` array and serves reads from its public `$stored` array.
	 *
	 * @param bool  $active  Whether Yoast SEO should look active.
	 * @param array $options Yoast option values keyed by option name.
	 *
	 * @return YoastSeoTermIntegration
	 */
	private function make_integration( bool $active = true, array $options = array() ): YoastSeoTermIntegration {
		$options = wp_parse_args(
			$options,
			array(
				'enable_cornerstone_content'      => true,
				// Yoast's default: advanced settings are locked down to users with the capability.
				'disableadvanced_meta'            => true,
				'opengraph'                       => true,
				'twitter'                         => true,
				'breadcrumbs-enable'              => true,
				'display-metabox-tax-product_cat' => true,
				'display-metabox-tax-product_tag' => true,
				'display-metabox-tax-product_brand' => true,
			)
		);

		return new class( $active, $options ) extends YoastSeoTermIntegration {

			/**
			 * Whether Yoast should look active.
			 *
			 * @var bool
			 */
			public static $active;

			/**
			 * Fake Yoast options.
			 *
			 * @var array
			 */
			public static $options;

			/**
			 * Values handed to set_term_values(), one entry per call:
			 * array{term_id: int, taxonomy: string, values: array}.
			 *
			 * @var array
			 */
			public $written = array();

			/**
			 * Stored Yoast term values served by get_term_values(), keyed by
			 * "{$taxonomy}:{$term_id}".
			 *
			 * @var array
			 */
			public $stored = array();

			/**
			 * Constructor.
			 *
			 * @param bool  $active  Whether Yoast should look active.
			 * @param array $options Fake Yoast options.
			 */
			public function __construct( $active, $options ) {
				self::$active  = $active;
				self::$options = $options;
				parent::__construct();
			}

			/**
			 * Report the faked active state.
			 *
			 * @return bool
			 */
			protected function is_yoast_active(): bool {
				return self::$active;
			}

			/**
			 * Read a faked Yoast option.
			 *
			 * @param string $key      Option name.
			 * @param mixed  $fallback Value when the option is unknown.
			 *
			 * @return mixed
			 */
			protected function get_yoast_option( string $key, $fallback = null ) {
				return array_key_exists( $key, self::$options ) ? self::$options[ $key ] : $fallback;
			}

			/**
			 * Report the faked Yoast advanced-metadata capability.
			 *
			 * @return bool
			 */
			protected function current_user_has_yoast_advanced_capability(): bool {
				return ! empty( self::$options['__has_advanced_capability'] );
			}

			/**
			 * Report the faked page context.
			 *
			 * @return bool
			 */
			protected function is_term_form_page(): bool {
				return ! empty( self::$options['__is_term_form_page'] );
			}

			/**
			 * Return the faked title separator.
			 *
			 * @return string
			 */
			protected function get_title_separator(): string {
				return '|';
			}

			/**
			 * Record the values instead of writing Yoast's option.
			 *
			 * @param int    $term_id  Term ID.
			 * @param string $taxonomy Taxonomy name.
			 * @param array  $values   Values keyed by Yoast key.
			 *
			 * @return void
			 */
			protected function set_term_values( int $term_id, string $taxonomy, array $values ): void {
				$this->written[] = array(
					'term_id'  => $term_id,
					'taxonomy' => $taxonomy,
					'values'   => $values,
				);
			}

			/**
			 * Serve the faked stored values.
			 *
			 * @param int    $term_id  Term ID.
			 * @param string $taxonomy Taxonomy name.
			 *
			 * @return array
			 */
			protected function get_term_values( int $term_id, string $taxonomy ): array {
				return $this->stored[ $taxonomy . ':' . $term_id ] ?? array();
			}
		};
	}

	/**
	 * Simulate the term form submission with the card marker present.
	 *
	 * @param array $fields Posted SEO fields.
	 *
	 * @return void
	 */
	private function post_form( array $fields ): void {
		$_POST = array_merge( array( 'storesuite_yoast_term_seo' => '1' ), $fields );
	}

	/**
	 * The values of the last (and only) recorded write.
	 *
	 * @param YoastSeoTermIntegration $integration Integration double.
	 *
	 * @return array
	 */
	private function last_written( YoastSeoTermIntegration $integration ): array {
		$this->assertNotEmpty( $integration->written, 'A write was recorded.' );

		return end( $integration->written )['values'];
	}

	/**
	 * Inactive Yoast means no hooks at all.
	 *
	 * @return void
	 */
	public function test_registers_no_hooks_when_yoast_is_inactive() {
		$integration = $this->make_integration( false );

		$this->assertFalse( has_action( 'storesuite_term_form_after_fields', array( $integration, 'render_card' ) ) );
		$this->assertFalse( has_action( 'created_term', array( $integration, 'save' ) ) );
		$this->assertFalse( has_action( 'edit_term', array( $integration, 'save' ) ) );
	}

	/**
	 * Active Yoast wires the form card and the two core term save hooks.
	 *
	 * @return void
	 */
	public function test_registers_hooks_when_yoast_is_active() {
		$integration = $this->make_integration();

		$this->assertNotFalse( has_action( 'storesuite_term_form_after_fields', array( $integration, 'render_card' ) ) );
		$this->assertNotFalse( has_action( 'created_term', array( $integration, 'save' ) ) );
		$this->assertNotFalse( has_action( 'edit_term', array( $integration, 'save' ) ) );
		$this->assertNotFalse( has_action( 'wp_enqueue_scripts', array( $integration, 'enqueue_assets' ) ) );
	}

	/**
	 * A term save without the card marker never touches the Yoast option:
	 * `created_term` / `edit_term` fire for every term write on the site.
	 *
	 * @return void
	 */
	public function test_save_without_card_marker_writes_nothing() {
		$_POST = array( 'storesuite_yoast_term_title' => 'Sneaky' );

		$integration = $this->make_integration();
		$integration->save( $this->category_id, 0, 'product_cat' );

		$this->assertSame( array(), $integration->written );
	}

	/**
	 * Taxonomies outside the product_cat/product_tag/product_brand whitelist
	 * are never written, marker or not.
	 *
	 * @return void
	 */
	public function test_save_ignores_taxonomies_outside_the_whitelist() {
		$tag_id = self::factory()->term->create( array( 'taxonomy' => 'post_tag' ) );
		$this->post_form( array( 'storesuite_yoast_term_title' => 'Blog tag' ) );

		$integration = $this->make_integration();
		$integration->save( $tag_id, 0, 'post_tag' );

		$this->assertSame( array(), $integration->written );
	}

	/**
	 * Yoast's own metabox gate applies on save: with
	 * `display-metabox-tax-{taxonomy}` off the taxonomy is not editable.
	 *
	 * @return void
	 */
	public function test_save_respects_yoast_metabox_gate() {
		$this->post_form( array( 'storesuite_yoast_term_title' => 'Hidden' ) );

		$integration = $this->make_integration( true, array( 'display-metabox-tax-product_cat' => false ) );
		$integration->save( $this->category_id, 0, 'product_cat' );

		$this->assertSame( array(), $integration->written );
	}

	/**
	 * The core term hooks are not scoped to StoreSuite's nonce-verified AJAX
	 * handlers, so the capability is re-checked on save.
	 *
	 * @return void
	 */
	public function test_save_requires_manage_woocommerce() {
		wp_set_current_user( $this->customer_id );
		$this->post_form( array( 'storesuite_yoast_term_title' => 'Customer' ) );

		$integration = $this->make_integration();
		$integration->save( $this->category_id, 0, 'product_cat' );

		$this->assertSame( array(), $integration->written );
	}

	/**
	 * Text fields are stored under Yoast's term keys, sanitized, with
	 * variables intact — and `metadesc` maps to `wpseo_desc`.
	 *
	 * @return void
	 */
	public function test_saves_sanitized_text_fields() {
		$this->post_form(
			array(
				'storesuite_yoast_term_focuskw'  => ' blue <b>widgets</b> ',
				'storesuite_yoast_term_title'    => '%%term_title%% %%sep%% Buy <script>alert(1)</script>now',
				'storesuite_yoast_term_metadesc' => "The best\ncategory \\'ever\\'",
			)
		);

		$integration = $this->make_integration();
		$integration->save( $this->category_id, 0, 'product_cat' );

		$values = $this->last_written( $integration );
		$this->assertSame( 'blue widgets', $values['wpseo_focuskw'] );
		$this->assertSame( '%%term_title%% %%sep%% Buy now', $values['wpseo_title'] );
		$this->assertSame( "The best category 'ever'", $values['wpseo_desc'] );
	}

	/**
	 * Values this integration does not manage (Yoast's own analysis scores)
	 * are sent back unchanged: Yoast's `set_values()` starts from defaults, so
	 * anything missing from the payload would be wiped.
	 *
	 * @return void
	 */
	public function test_save_preserves_unmanaged_stored_values() {
		$this->post_form( array( 'storesuite_yoast_term_title' => 'New title' ) );

		$integration = $this->make_integration();
		$integration->stored[ 'product_cat:' . $this->category_id ] = array(
			'wpseo_linkdex' => '73',
			'wpseo_desc'    => 'Old description',
		);
		$integration->save( $this->category_id, 0, 'product_cat' );

		$values = $this->last_written( $integration );
		$this->assertSame( '73', $values['wpseo_linkdex'] );
		$this->assertSame( 'Old description', $values['wpseo_desc'], 'A field that was not posted keeps its stored value.' );
		$this->assertSame( 'New title', $values['wpseo_title'] );
	}

	/**
	 * The cornerstone toggle stores "1" when checked and "0" when the card was
	 * shown but the box left unchecked; with the feature off it is ignored.
	 *
	 * @return void
	 */
	public function test_cornerstone_toggle_follows_feature_and_checkbox() {
		$integration = $this->make_integration();

		$this->post_form( array( 'storesuite_yoast_term_is_cornerstone' => 'yes' ) );
		$integration->save( $this->category_id, 0, 'product_cat' );
		$this->assertSame( '1', $this->last_written( $integration )['wpseo_is_cornerstone'] );

		$this->post_form( array() );
		$integration->save( $this->category_id, 0, 'product_cat' );
		$this->assertSame( '0', $this->last_written( $integration )['wpseo_is_cornerstone'], 'Unchecked checkboxes are not posted; the marker proves the card was shown.' );

		$disabled = $this->make_integration( true, array( 'enable_cornerstone_content' => false ) );
		$this->post_form( array( 'storesuite_yoast_term_is_cornerstone' => 'yes' ) );
		$disabled->save( $this->category_id, 0, 'product_cat' );
		$this->assertArrayNotHasKey( 'wpseo_is_cornerstone', $this->last_written( $disabled ) );
	}

	/**
	 * Post every advanced field with valid values.
	 *
	 * @return void
	 */
	private function post_advanced_fields(): void {
		$this->post_form(
			array(
				'storesuite_yoast_term_noindex'   => 'noindex',
				'storesuite_yoast_term_canonical' => 'https://example.org/canonical-category/',
				'storesuite_yoast_term_bctitle'   => ' Short <i>crumb</i> ',
			)
		);
	}

	/**
	 * Under Yoast's defaults a shop manager's advanced fields are ignored on save.
	 *
	 * @return void
	 */
	public function test_advanced_fields_ignored_for_shop_manager_by_default() {
		$this->post_advanced_fields();

		$integration = $this->make_integration();
		$integration->save( $this->category_id, 0, 'product_cat' );

		$this->assertFalse( $integration->can_edit_advanced() );
		$values = $this->last_written( $integration );
		$this->assertArrayNotHasKey( 'wpseo_noindex', $values );
		$this->assertArrayNotHasKey( 'wpseo_canonical', $values );
		$this->assertArrayNotHasKey( 'wpseo_bctitle', $values );
	}

	/**
	 * Each way of being allowed saves the advanced fields, sanitized.
	 *
	 * @dataProvider provide_advanced_access
	 *
	 * @param array $options    Faked Yoast options / capability.
	 * @param bool  $use_filter Whether to grant access through the StoreSuite filter.
	 *
	 * @return void
	 */
	public function test_advanced_fields_saved_when_allowed( array $options, bool $use_filter ) {
		if ( $use_filter ) {
			add_filter( 'storesuite_yoast_term_can_edit_advanced', '__return_true' );
		}

		$this->post_advanced_fields();
		$integration = $this->make_integration( true, $options );
		$integration->save( $this->category_id, 0, 'product_cat' );

		remove_filter( 'storesuite_yoast_term_can_edit_advanced', '__return_true' );

		$values = $this->last_written( $integration );
		$this->assertSame( 'noindex', $values['wpseo_noindex'] );
		$this->assertSame( 'https://example.org/canonical-category/', $values['wpseo_canonical'] );
		$this->assertSame( 'Short crumb', $values['wpseo_bctitle'] );
	}

	/**
	 * Ways a user can be allowed to edit advanced settings.
	 *
	 * @return array
	 */
	public function provide_advanced_access(): array {
		return array(
			'yoast capability'           => array( array( '__has_advanced_capability' => true ), false ),
			'yoast security setting off' => array( array( 'disableadvanced_meta' => false ), false ),
			'storesuite filter'          => array( array(), true ),
		);
	}

	/**
	 * The filter can also take access away from a user Yoast would allow.
	 *
	 * @return void
	 */
	public function test_filter_can_revoke_advanced_access() {
		add_filter( 'storesuite_yoast_term_can_edit_advanced', '__return_false' );
		$can = $this->make_integration( true, array( '__has_advanced_capability' => true ) )->can_edit_advanced();
		remove_filter( 'storesuite_yoast_term_can_edit_advanced', '__return_false' );

		$this->assertFalse( $can );
	}

	/**
	 * The noindex value follows Yoast's term enum — `default`/`index`/`noindex`,
	 * not the 0/1/2 post model — and anything else keeps the stored value.
	 *
	 * @return void
	 */
	public function test_noindex_enum_is_validated() {
		$integration = $this->make_integration( true, array( '__has_advanced_capability' => true ) );
		$integration->stored[ 'product_cat:' . $this->category_id ] = array( 'wpseo_noindex' => 'noindex' );

		$this->post_form( array( 'storesuite_yoast_term_noindex' => '1' ) );
		$integration->save( $this->category_id, 0, 'product_cat' );
		$this->assertSame( 'noindex', $this->last_written( $integration )['wpseo_noindex'], 'An invalid choice must not overwrite the stored value.' );

		$this->post_form( array( 'storesuite_yoast_term_noindex' => 'default' ) );
		$integration->save( $this->category_id, 0, 'product_cat' );
		$this->assertSame( 'default', $this->last_written( $integration )['wpseo_noindex'], 'The default is sent as-is: Yoast strips defaults itself.' );
	}

	/**
	 * The breadcrumbs title is only editable while Yoast's breadcrumbs are on.
	 *
	 * @return void
	 */
	public function test_bctitle_requires_breadcrumbs_enabled() {
		$this->post_form( array( 'storesuite_yoast_term_bctitle' => 'Crumb' ) );

		$integration = $this->make_integration(
			true,
			array(
				'__has_advanced_capability' => true,
				'breadcrumbs-enable'        => false,
			)
		);
		$integration->save( $this->category_id, 0, 'product_cat' );

		$this->assertArrayNotHasKey( 'wpseo_bctitle', $this->last_written( $integration ) );
	}

	/**
	 * Create an attachment of the given mime type.
	 *
	 * @param string $mime Mime type.
	 * @param string $file File path relative to the uploads directory.
	 *
	 * @return int
	 */
	private function make_attachment( string $mime, string $file ): int {
		return self::factory()->attachment->create(
			array(
				'post_mime_type' => $mime,
				'file'           => $file,
			)
		);
	}

	/**
	 * Fields of a social network whose Yoast feature is off are ignored on save.
	 *
	 * @return void
	 */
	public function test_social_fields_follow_yoast_feature_toggles_on_save() {
		$this->post_form(
			array(
				'storesuite_yoast_term_opengraph-title' => 'Facebook <b>title</b>',
				'storesuite_yoast_term_twitter-title'   => 'X title',
				'storesuite_yoast_term_twitter-description' => 'On X',
			)
		);

		$integration = $this->make_integration( true, array( 'opengraph' => false ) );
		$integration->save( $this->category_id, 0, 'product_cat' );

		$values = $this->last_written( $integration );
		$this->assertArrayNotHasKey( 'wpseo_opengraph-title', $values );
		$this->assertSame( 'X title', $values['wpseo_twitter-title'] );
		$this->assertSame( 'On X', $values['wpseo_twitter-description'] );
	}

	/**
	 * A valid image ID stores both the ID and the URL the server derives from
	 * it — Yoast stores URL and ID — and a client-supplied URL is never trusted.
	 *
	 * @return void
	 */
	public function test_social_image_stores_id_and_server_derived_url() {
		$image_id = $this->make_attachment( 'image/jpeg', '2026/10/share.jpg' );

		$this->post_form(
			array(
				'storesuite_yoast_term_opengraph-image-id' => (string) $image_id,
				'storesuite_yoast_term_opengraph-image' => 'https://attacker.example/pixel.gif',
			)
		);

		$integration = $this->make_integration();
		$integration->save( $this->category_id, 0, 'product_cat' );

		$values = $this->last_written( $integration );
		$this->assertSame( (string) $image_id, $values['wpseo_opengraph-image-id'] );
		$this->assertSame( wp_get_attachment_url( $image_id ), $values['wpseo_opengraph-image'] );
	}

	/**
	 * IDs that are not image attachments are rejected and both keys keep
	 * their stored values.
	 *
	 * @return void
	 */
	public function test_social_image_rejects_non_images() {
		$pdf_id = $this->make_attachment( 'application/pdf', '2026/10/manual.pdf' );

		foreach ( array( (string) $pdf_id, '999999' ) as $candidate ) {
			$this->post_form( array( 'storesuite_yoast_term_twitter-image-id' => $candidate ) );

			$integration = $this->make_integration();
			$integration->save( $this->category_id, 0, 'product_cat' );

			$values = $this->last_written( $integration );
			$this->assertArrayNotHasKey( 'wpseo_twitter-image-id', $values, $candidate );
			$this->assertArrayNotHasKey( 'wpseo_twitter-image', $values, $candidate );
		}
	}

	/**
	 * Removing the image sends both keys empty, so Yoast strips them as defaults.
	 *
	 * @return void
	 */
	public function test_removing_social_image_clears_id_and_url() {
		$this->post_form( array( 'storesuite_yoast_term_opengraph-image-id' => '' ) );

		$integration = $this->make_integration();
		$integration->save( $this->category_id, 0, 'product_cat' );

		$values = $this->last_written( $integration );
		$this->assertSame( '', $values['wpseo_opengraph-image-id'] );
		$this->assertSame( '', $values['wpseo_opengraph-image'] );
	}

	/**
	 * Render the card for the category under test and return the HTML.
	 *
	 * @param YoastSeoTermIntegration $integration Integration double.
	 * @param bool                    $edit        Whether to pass the term (edit) or null (add).
	 *
	 * @return string
	 */
	private function render_card( YoastSeoTermIntegration $integration, bool $edit = true ): string {
		ob_start();
		$integration->render_card( 'product_cat', $edit ? get_term( $this->category_id, 'product_cat' ) : null );

		return ob_get_clean();
	}

	/**
	 * The edit form card leads with the hidden marker, shows the tab strip and
	 * prefills the stored values, including the preview block the JS binds to.
	 *
	 * @return void
	 */
	public function test_card_renders_marker_tabs_and_prefilled_values() {
		$integration = $this->make_integration();
		$integration->stored[ 'product_cat:' . $this->category_id ] = array(
			'wpseo_title' => 'Stored "title"',
			'wpseo_desc'  => 'Stored description',
		);

		$html = $this->render_card( $integration );

		$this->assertStringContainsString( 'id="storesuite-yoast-term-seo"', $html );
		$this->assertStringContainsString( 'name="storesuite_yoast_term_seo"', $html );
		$this->assertStringContainsString( 'data-seo-tab="seo"', $html );
		$this->assertStringContainsString( 'data-seo-tab="social"', $html );
		$this->assertStringContainsString( 'data-seo-panel="social"', $html );
		$this->assertStringContainsString( 'value="Stored &quot;title&quot;"', $html );
		$this->assertStringContainsString( 'Stored description', $html );
		// The blocks T3's preview engine binds to.
		$this->assertStringContainsString( 'storesuite-seo-preview', $html );
		$this->assertStringContainsString( 'storesuite-seo-mode-switch', $html );
		$this->assertStringContainsString( 'storesuite-seo-progress', $html );
		$this->assertStringContainsString( 'storesuite-seo-insert-variable', $html );
		$this->assertMatchesRegularExpression( '/name="storesuite_yoast_term_focuskw"[^>]*data-ai-context/', $html );
	}

	/**
	 * The add form (no term yet) renders an empty card without errors.
	 *
	 * @return void
	 */
	public function test_card_renders_on_add_form() {
		$html = $this->render_card( $this->make_integration(), false );

		$this->assertStringContainsString( 'name="storesuite_yoast_term_seo"', $html );
		$this->assertStringContainsString( 'name="storesuite_yoast_term_focuskw"', $html );
		$this->assertStringContainsString( 'name="storesuite_yoast_term_title"', $html );
	}

	/**
	 * The same gates that guard the save hide the card at render.
	 *
	 * @return void
	 */
	public function test_card_hidden_when_taxonomy_gated() {
		$gated = $this->make_integration( true, array( 'display-metabox-tax-product_cat' => false ) );
		$this->assertSame( '', $this->render_card( $gated ) );

		ob_start();
		$this->make_integration()->render_card( 'post_tag', null );
		$this->assertSame( '', ob_get_clean() );
	}

	/**
	 * The Advanced tab and its fields only render for users who may edit them.
	 *
	 * @return void
	 */
	public function test_advanced_tab_only_renders_when_allowed() {
		$integration = $this->make_integration();
		$integration->stored[ 'product_cat:' . $this->category_id ] = array( 'wpseo_canonical' => 'https://example.org/stored/' );

		$locked = $this->render_card( $integration );
		$this->assertStringNotContainsString( 'storesuite_yoast_term_canonical', $locked );
		$this->assertStringNotContainsString( 'data-seo-tab="advanced"', $locked );

		$allowed = $this->make_integration( true, array( '__has_advanced_capability' => true ) );
		$allowed->stored[ 'product_cat:' . $this->category_id ] = array( 'wpseo_canonical' => 'https://example.org/stored/' );

		$open = $this->render_card( $allowed );
		$this->assertStringContainsString( 'data-seo-tab="advanced"', $open );
		$this->assertStringContainsString( 'name="storesuite_yoast_term_noindex"', $open );
		$this->assertStringContainsString( 'name="storesuite_yoast_term_bctitle"', $open );
		$this->assertStringContainsString( 'value="https://example.org/stored/"', $open );
	}

	/**
	 * The Social tab follows Yoast's feature toggles on render and the tab
	 * strip disappears when only the SEO tab remains.
	 *
	 * @return void
	 */
	public function test_social_tab_follows_yoast_feature_toggles_on_render() {
		$x_only = $this->render_card( $this->make_integration( true, array( 'opengraph' => false ) ) );
		$this->assertStringNotContainsString( 'storesuite_yoast_term_opengraph-', $x_only );
		$this->assertStringContainsString( 'name="storesuite_yoast_term_twitter-title"', $x_only );

		$none = $this->render_card(
			$this->make_integration(
				true,
				array(
					'opengraph' => false,
					'twitter'   => false,
				)
			)
		);
		$this->assertStringNotContainsString( 'data-seo-panel="social"', $none );
		$this->assertStringNotContainsString( 'data-seo-tab="seo"', $none, 'The tab strip only shows with more than one tab.' );
	}

	/**
	 * The preview script receives Yoast's site-wide templates for the
	 * taxonomy, the separator and the site name.
	 *
	 * @return void
	 */
	public function test_script_data_exposes_taxonomy_templates_separator_and_site() {
		update_option( 'blogname', 'Widget Shop' );

		$data = $this->make_integration(
			true,
			array(
				'title-tax-product_cat'    => '%%term_title%% %%sep%% %%sitename%%',
				'metadesc-tax-product_cat' => 'Shop %%term_title%% today',
			)
		)->get_script_data( 'product_cat' );

		$this->assertSame( '%%term_title%% %%sep%% %%sitename%%', $data['titleTemplate'] );
		$this->assertSame( 'Shop %%term_title%% today', $data['descTemplate'] );
		$this->assertSame( '|', $data['replacements']['sep'] );
		$this->assertSame( 'Widget Shop', $data['replacements']['sitename'] );
		$this->assertSame( 400, $data['titleMinWidth'] );
		$this->assertSame( 600, $data['titleMaxWidth'] );
		$this->assertSame( 156, $data['descMaxLength'] );
	}

	/**
	 * The variable menu leads with the recommended term variables, and
	 * `%%term_hierarchy%%` is only offered for hierarchical taxonomies.
	 *
	 * @return void
	 */
	public function test_script_data_variables_follow_taxonomy_hierarchy() {
		$integration = $this->make_integration();

		$category_variables = array_column( $integration->get_script_data( 'product_cat' )['variables'], 'name' );
		$this->assertContains( 'term_title', $category_variables );
		$this->assertContains( 'term_description', $category_variables );
		$this->assertContains( 'term_hierarchy', $category_variables );

		$tag_variables = array_column( $integration->get_script_data( 'product_tag' )['variables'], 'name' );
		$this->assertContains( 'term_title', $tag_variables );
		$this->assertNotContains( 'term_hierarchy', $tag_variables, 'Flat taxonomies have no hierarchy.' );
	}

	/**
	 * The integration is registered in the plugin container.
	 *
	 * @return void
	 */
	public function test_integration_is_registered_in_the_container() {
		$this->assertInstanceOf(
			YoastSeoTermIntegration::class,
			\pluginizelab_storesuite()->storesuite_yoast_seo_term_integration
		);
	}
}
