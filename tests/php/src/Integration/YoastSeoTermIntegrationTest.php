<?php
/**
 * Tests for the Yoast SEO term form integration.
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test\Integration;

use PluginizeLab\StoreSuite\Integration\YoastSeoIntegration;
use PluginizeLab\StoreSuite\Integration\YoastSeoTermIntegration;
use PluginizeLab\StoreSuite\Product\ProductAI;
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

		// Enqueue assertions need a fresh queue; core keeps it in a global between tests.
		$GLOBALS['wp_scripts'] = null;

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
			 * Whitelist extra taxonomies so gates below the whitelist (like
			 * the taxonomy's `public` flag) can be exercised in isolation.
			 *
			 * @return string[]
			 */
			protected function get_supported_taxonomies(): array {
				return array_merge( parent::get_supported_taxonomies(), self::$options['__extra_taxonomies'] ?? array() );
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
			 * Report the faked page context: the taxonomy of the term form
			 * being viewed, '' anywhere else.
			 *
			 * @return string
			 */
			protected function get_form_taxonomy(): string {
				return (string) ( self::$options['__form_taxonomy'] ?? '' );
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
	 * A real term create and update — core `created_term` / `edit_term`
	 * firing from `wp_insert_term()` / `wp_update_term()` — without the card
	 * marker writes nothing: the registered hooks themselves are guarded, not
	 * just a direct `save()` call.
	 *
	 * @return void
	 */
	public function test_plain_term_saves_through_core_hooks_write_nothing() {
		$integration = $this->make_integration();

		$_POST = array(
			'tag-name'                    => 'Plain',
			'storesuite_yoast_term_title' => 'Sneaky',
		);

		$created = wp_insert_term( 'Plain core save', 'product_cat' );
		$this->assertIsArray( $created );
		wp_update_term( $created['term_id'], 'product_cat', array( 'description' => 'Still plain' ) );

		$this->assertSame( array(), $integration->written );
	}

	/**
	 * With the marker posted, every supported taxonomy persists on both core
	 * paths: `created_term` from `wp_insert_term()` and `edit_term` from
	 * `wp_update_term()`.
	 *
	 * @return void
	 */
	public function test_core_term_hooks_persist_for_all_supported_taxonomies() {
		$integration = $this->make_integration();

		foreach ( array( 'product_cat', 'product_tag', 'product_brand' ) as $taxonomy ) {
			$this->post_form( array( 'storesuite_yoast_term_title' => 'Created in ' . $taxonomy ) );
			$created = wp_insert_term( 'Hooked ' . $taxonomy, $taxonomy );
			$this->assertIsArray( $created, $taxonomy );

			$write = end( $integration->written );
			$this->assertSame( $created['term_id'], $write['term_id'], $taxonomy );
			$this->assertSame( $taxonomy, $write['taxonomy'] );
			$this->assertSame( 'Created in ' . $taxonomy, $write['values']['wpseo_title'] );

			$this->post_form( array( 'storesuite_yoast_term_title' => 'Updated in ' . $taxonomy ) );
			wp_update_term( $created['term_id'], $taxonomy, array( 'description' => 'Edited' ) );

			$write = end( $integration->written );
			$this->assertSame( $created['term_id'], $write['term_id'], $taxonomy );
			$this->assertSame( 'Updated in ' . $taxonomy, $write['values']['wpseo_title'] );
		}

		$this->assertCount( 6, $integration->written, 'Exactly one write per created_term and edit_term firing.' );
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
	 * A non-public taxonomy is blocked even when whitelisted and not hidden by
	 * Yoast's metabox toggle: Yoast only manages public archives, so the card
	 * does not render and nothing saves.
	 *
	 * @return void
	 */
	public function test_non_public_taxonomy_blocks_render_and_save() {
		register_taxonomy( 'storesuite_hidden_tax', 'product', array( 'public' => false ) );

		$integration = $this->make_integration(
			true,
			array(
				'__extra_taxonomies' => array( 'storesuite_hidden_tax' ),
				'display-metabox-tax-storesuite_hidden_tax' => true,
			)
		);

		$term_id = self::factory()->term->create( array( 'taxonomy' => 'storesuite_hidden_tax' ) );
		$this->post_form( array( 'storesuite_yoast_term_title' => 'Hidden archive' ) );
		$integration->save( $term_id, 0, 'storesuite_hidden_tax' );

		$this->assertSame( array(), $integration->written );

		ob_start();
		$integration->render_card( 'storesuite_hidden_tax', get_term( $term_id, 'storesuite_hidden_tax' ) );
		$this->assertSame( '', ob_get_clean() );

		unregister_taxonomy( 'storesuite_hidden_tax' );
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
	 * Each way of being allowed also renders the Advanced tab and its fields —
	 * the same access paths gate render and save alike.
	 *
	 * @dataProvider provide_advanced_access
	 *
	 * @param array $options    Faked Yoast options / capability.
	 * @param bool  $use_filter Whether to grant access through the StoreSuite filter.
	 *
	 * @return void
	 */
	public function test_advanced_tab_renders_for_each_access_path( array $options, bool $use_filter ) {
		if ( $use_filter ) {
			add_filter( 'storesuite_yoast_term_can_edit_advanced', '__return_true' );
		}

		$html = $this->render_card( $this->make_integration( true, $options ) );

		remove_filter( 'storesuite_yoast_term_can_edit_advanced', '__return_true' );

		$this->assertStringContainsString( 'data-seo-tab="advanced"', $html );
		$this->assertStringContainsString( 'name="storesuite_yoast_term_noindex"', $html );
		$this->assertStringContainsString( 'name="storesuite_yoast_term_canonical"', $html );
	}

	/**
	 * When a user without advanced access saves, the term's stored advanced
	 * values ride along unchanged — neither wiped by Yoast's
	 * rebuild-from-defaults `set_values()` nor replaced by posted ones.
	 *
	 * @return void
	 */
	public function test_non_advanced_save_preserves_stored_advanced_values() {
		$integration = $this->make_integration();
		$integration->stored[ 'product_cat:' . $this->category_id ] = array(
			'wpseo_noindex'   => 'noindex',
			'wpseo_canonical' => 'https://example.org/keep/',
			'wpseo_bctitle'   => 'Keep crumb',
		);

		$this->post_form(
			array(
				'storesuite_yoast_term_title'     => 'Fresh title',
				'storesuite_yoast_term_noindex'   => 'index',
				'storesuite_yoast_term_canonical' => 'https://attacker.example/',
			)
		);
		$integration->save( $this->category_id, 0, 'product_cat' );

		$values = $this->last_written( $integration );
		$this->assertSame( 'noindex', $values['wpseo_noindex'] );
		$this->assertSame( 'https://example.org/keep/', $values['wpseo_canonical'] );
		$this->assertSame( 'Keep crumb', $values['wpseo_bctitle'] );
		$this->assertSame( 'Fresh title', $values['wpseo_title'] );
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
	 * `index` is a valid enum value, and an invalid value with nothing stored
	 * writes no noindex key at all — missing means "default" to Yoast, so an
	 * invalid submission can never flip a term's robots setting.
	 *
	 * @return void
	 */
	public function test_noindex_accepts_index_and_drops_invalid_without_stored_value() {
		$integration = $this->make_integration( true, array( '__has_advanced_capability' => true ) );

		$this->post_form( array( 'storesuite_yoast_term_noindex' => 'index' ) );
		$integration->save( $this->category_id, 0, 'product_cat' );
		$this->assertSame( 'index', $this->last_written( $integration )['wpseo_noindex'] );

		$this->post_form( array( 'storesuite_yoast_term_noindex' => 'nofollow' ) );
		$integration->save( $this->category_id, 0, 'product_cat' );
		$this->assertArrayNotHasKey( 'wpseo_noindex', $this->last_written( $integration ), 'Terms have no nofollow; the invalid value is dropped, not coerced.' );
	}

	/**
	 * The canonical URL is cleaned server-side: trimmed, kept verbatim for
	 * http(s), and emptied for any other scheme so a script or FTP URL can
	 * never become the canonical.
	 *
	 * @return void
	 */
	public function test_canonical_url_is_cleaned_to_http_schemes() {
		$integration = $this->make_integration( true, array( '__has_advanced_capability' => true ) );

		$this->post_form( array( 'storesuite_yoast_term_canonical' => '  https://example.org/c/?a=1&b=2  ' ) );
		$integration->save( $this->category_id, 0, 'product_cat' );
		$this->assertSame( 'https://example.org/c/?a=1&b=2', $this->last_written( $integration )['wpseo_canonical'] );

		foreach ( array( 'javascript:alert(1)', 'ftp://example.org/file' ) as $bad_url ) {
			$this->post_form( array( 'storesuite_yoast_term_canonical' => $bad_url ) );
			$integration->save( $this->category_id, 0, 'product_cat' );
			$this->assertSame( '', $this->last_written( $integration )['wpseo_canonical'], $bad_url );
		}
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
	 * The twitter toggle gates its fields symmetrically — including the image
	 * pair, which is never written while the network is off.
	 *
	 * @return void
	 */
	public function test_twitter_fields_and_image_ignored_when_twitter_is_off() {
		$image_id = $this->make_attachment( 'image/jpeg', '2026/10/x-share.jpg' );

		$this->post_form(
			array(
				'storesuite_yoast_term_opengraph-title'    => 'Facebook title',
				'storesuite_yoast_term_twitter-title'      => 'X title',
				'storesuite_yoast_term_twitter-image-id'   => (string) $image_id,
			)
		);

		$integration = $this->make_integration( true, array( 'twitter' => false ) );
		$integration->save( $this->category_id, 0, 'product_cat' );

		$values = $this->last_written( $integration );
		$this->assertSame( 'Facebook title', $values['wpseo_opengraph-title'] );
		$this->assertArrayNotHasKey( 'wpseo_twitter-title', $values );
		$this->assertArrayNotHasKey( 'wpseo_twitter-image-id', $values );
		$this->assertArrayNotHasKey( 'wpseo_twitter-image', $values );
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
	 * The add form starts empty: no stored values can leak into a new term's
	 * fields, even when other terms of the taxonomy have values.
	 *
	 * @return void
	 */
	public function test_add_form_renders_empty_values() {
		$integration = $this->make_integration( true, array( '__has_advanced_capability' => true ) );
		$integration->stored[ 'product_cat:' . $this->category_id ] = array(
			'wpseo_title' => 'Another term title',
		);

		$html = $this->render_card( $integration, false );

		$this->assertStringContainsString( 'name="storesuite_yoast_term_title" value=""', $html );
		$this->assertStringContainsString( 'name="storesuite_yoast_term_canonical" value=""', $html );
		$this->assertStringNotContainsString( 'Another term title', $html );
		$this->assertMatchesRegularExpression( '/<option value="default"\s+selected/', $html, 'noindex starts on the site default.' );
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
	 * The preview script knows which form fields feed it, per taxonomy: the
	 * category and brand forms use prefixed ids, the tag form bare ones.
	 *
	 * @return void
	 */
	public function test_script_data_exposes_form_selectors_per_taxonomy() {
		$integration = $this->make_integration();

		$this->assertSame(
			array(
				'name'        => '#product_category_name',
				'slug'        => '#product_category_slug',
				'description' => '#product_category_description',
			),
			$integration->get_script_data( 'product_cat' )['fields']
		);
		$this->assertSame(
			array(
				'name'        => '#name',
				'slug'        => '#slug',
				'description' => '#description',
			),
			$integration->get_script_data( 'product_tag' )['fields']
		);
		$this->assertSame(
			array(
				'name'        => '#product_brand_name',
				'slug'        => '#product_brand_slug',
				'description' => '#product_brand_description',
			),
			$integration->get_script_data( 'product_brand' )['fields']
		);
	}

	/**
	 * Each taxonomy gets its Yoast description alias — `category_description`
	 * for hierarchical taxonomies, `tag_description` for tags — both as the
	 * live-resolved alias and in the Insert-variable menu.
	 *
	 * @return void
	 */
	public function test_script_data_description_alias_follows_taxonomy() {
		$integration = $this->make_integration();

		$category_data = $integration->get_script_data( 'product_cat' );
		$this->assertSame( 'category_description', $category_data['descriptionAlias'] );
		$this->assertTrue( $category_data['hierarchical'] );
		$this->assertSame( 'product_cat', $category_data['taxonomy'] );
		$category_variables = array_column( $category_data['variables'], 'name' );
		$this->assertContains( 'category_description', $category_variables );
		$this->assertNotContains( 'tag_description', $category_variables );

		$tag_data = $integration->get_script_data( 'product_tag' );
		$this->assertSame( 'tag_description', $tag_data['descriptionAlias'] );
		$this->assertFalse( $tag_data['hierarchical'] );
		$tag_variables = array_column( $tag_data['variables'], 'name' );
		$this->assertContains( 'tag_description', $tag_variables );
		$this->assertNotContains( 'category_description', $tag_variables );
	}

	/**
	 * `%%term_hierarchy%%` is the term's ancestors joined by the separator;
	 * the script appends the live name itself, so the data carries only the
	 * ancestor path — empty on the add form and for top-level terms.
	 *
	 * @return void
	 */
	public function test_script_data_resolves_hierarchy_base_from_ancestors() {
		$integration = $this->make_integration();

		$grandparent = self::factory()->term->create(
			array(
				'taxonomy' => 'product_cat',
				'name'     => 'Clothing',
			)
		);
		$parent      = self::factory()->term->create(
			array(
				'taxonomy' => 'product_cat',
				'name'     => 'Shirts',
				'parent'   => $grandparent,
			)
		);
		$child       = self::factory()->term->create(
			array(
				'taxonomy' => 'product_cat',
				'name'     => 'Polos',
				'parent'   => $parent,
			)
		);

		$data = $integration->get_script_data( 'product_cat', get_term( $child, 'product_cat' ) );
		$this->assertSame( 'Clothing | Shirts', $data['hierarchyBase'] );

		$top = $integration->get_script_data( 'product_cat', get_term( $grandparent, 'product_cat' ) );
		$this->assertSame( '', $top['hierarchyBase'] );

		$add_form = $integration->get_script_data( 'product_cat' );
		$this->assertSame( '', $add_form['hierarchyBase'] );
	}

	/**
	 * The preview URL base is the term archive path the slug is appended to.
	 *
	 * @return void
	 */
	public function test_script_data_exposes_the_term_archive_url_base() {
		$data = $this->make_integration()->get_script_data( 'product_cat' );

		$this->assertStringEndsWith( '/', $data['urlBase'] );
		$this->assertStringContainsString( 'product', $data['urlBase'] );
		$this->assertStringNotContainsString( '?', $data['urlBase'] );
	}

	/**
	 * The shared engine and the term adapter only load on the six term form
	 * endpoints, and the adapter receives the StoreSuite_TermSeo data.
	 *
	 * @return void
	 */
	public function test_term_scripts_enqueued_only_on_term_form_pages() {
		$this->make_integration()->enqueue_assets();
		$this->assertFalse( wp_script_is( 'storesuite_seo_preview_script', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'storesuite_term_seo_script', 'enqueued' ) );

		$this->make_integration( true, array( '__form_taxonomy' => 'product_cat' ) )->enqueue_assets();
		$this->assertTrue( wp_script_is( 'storesuite_seo_preview_script', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'storesuite_term_seo_script', 'enqueued' ) );

		$localized = wp_scripts()->get_data( 'storesuite_term_seo_script', 'data' );
		$this->assertStringContainsString( 'StoreSuite_TermSeo', $localized );
		$this->assertStringContainsString( '"taxonomy":"product_cat"', $localized );
	}

	/**
	 * A taxonomy Yoast hides SEO settings for gets no scripts either.
	 *
	 * @return void
	 */
	public function test_enqueue_respects_the_taxonomy_gate() {
		$this->make_integration(
			true,
			array(
				'__form_taxonomy'                 => 'product_cat',
				'display-metabox-tax-product_cat' => false,
			)
		)->enqueue_assets();

		$this->assertFalse( wp_script_is( 'storesuite_term_seo_script', 'enqueued' ) );
	}

	/**
	 * The social image pickers need wp.media, which the tag pages do not load
	 * on their own; it comes along exactly when a social network is enabled.
	 *
	 * @return void
	 */
	public function test_enqueue_media_follows_the_social_toggles() {
		$this->make_integration(
			true,
			array(
				'__form_taxonomy' => 'product_tag',
				'opengraph'       => false,
				'twitter'         => false,
			)
		)->enqueue_assets();
		$this->assertFalse( wp_script_is( 'media-editor', 'enqueued' ) );

		$this->make_integration( true, array( '__form_taxonomy' => 'product_tag' ) )->enqueue_assets();
		$this->assertTrue( wp_script_is( 'media-editor', 'enqueued' ) );
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

	/* ----------------------------------------------------------------------
	 * AI Generate for the term SEO fields
	 * -------------------------------------------------------------------- */

	/**
	 * Register the fake generator and build the prompt for one term SEO field.
	 *
	 * The term fields carry their context in the raw request (any form field
	 * marked `data-ai-context`), never in the product form context.
	 *
	 * @param string $field   AI field key.
	 * @param array  $request Raw request fields.
	 *
	 * @return array{0: string, 1: string} Prompt and system instruction the generator received.
	 */
	private function generate_term_seo_field( string $field, array $request ): array {
		$captured = array();
		$callable = function ( $prompt, $instruction ) use ( &$captured ) {
			$captured = array( $prompt, $instruction );
			return 'Generated';
		};
		$filter   = function () use ( $callable ) {
			return $callable;
		};
		add_filter( 'storesuite_ai_text_generator', $filter );
		ProductAI::reset_ai_support_cache();

		$fields = ProductAI::get_fields();
		$this->assertArrayHasKey( $field, $fields, "AI field $field is registered." );

		$ai = new ProductAI();
		// Drive the prompt through the same code path the AJAX handler uses.
		$method = new \ReflectionMethod( $ai, 'generate_one' );
		$method->setAccessible( true );
		$context = array(
			'title'             => '',
			'short_description' => '',
			'description'       => '',
			'categories'        => array(),
			'previous'          => '',
		);
		$method->invoke( $ai, $field, $context, $request );

		remove_filter( 'storesuite_ai_text_generator', $filter );
		ProductAI::reset_ai_support_cache();

		return $captured;
	}

	/**
	 * With Yoast active the six term SEO fields are registered; inactive, none are.
	 *
	 * @return void
	 */
	public function test_term_seo_ai_fields_follow_yoast_activation() {
		$this->make_integration();
		$keys = array_keys( ProductAI::get_fields() );
		foreach ( array( 'yoast_term_title', 'yoast_term_metadesc', 'yoast_term_opengraph-title', 'yoast_term_opengraph-description', 'yoast_term_twitter-title', 'yoast_term_twitter-description' ) as $key ) {
			$this->assertContains( $key, $keys );
		}

		$fields = ProductAI::get_fields();
		$this->assertSame( '#storesuite_yoast_term_title', $fields['yoast_term_title']['target'] );
		$this->assertSame( '#storesuite_yoast_term_twitter-description', $fields['yoast_term_twitter-description']['target'] );
		$this->assertSame( 60, $fields['yoast_term_title']['length'] );
		$this->assertSame( 156, $fields['yoast_term_metadesc']['length'] );
		$this->assertSame( 70, $fields['yoast_term_opengraph-title']['length'] );
		$this->assertSame( 200, $fields['yoast_term_opengraph-description']['length'] );
		$this->assertSame( 'yoast_seo', $fields['yoast_term_title']['group'] );
		$this->assertFalse( $fields['yoast_term_title']['needs_context'], 'Term fields never demand a product title from the shared AJAX handler.' );

		remove_all_filters( 'storesuite_ai_text_fields' );
		$this->make_integration( false );
		$this->assertNotContains( 'yoast_term_title', array_keys( ProductAI::get_fields() ) );
	}

	/**
	 * Social term fields are registered only for networks Yoast has enabled.
	 *
	 * @return void
	 */
	public function test_term_social_ai_fields_follow_yoast_feature_toggles() {
		$this->make_integration( true, array( 'twitter' => false ) );
		$keys = array_keys( ProductAI::get_fields() );

		$this->assertContains( 'yoast_term_opengraph-title', $keys );
		$this->assertNotContains( 'yoast_term_twitter-title', $keys );
	}

	/**
	 * The term fields point at the product Yoast group's settings keys, so
	 * admins configure Yoast AI once and the settings page shows one block.
	 *
	 * @return void
	 */
	public function test_term_seo_ai_fields_share_the_product_yoast_settings_keys() {
		$this->make_integration();
		$fields = ProductAI::get_fields();

		$this->assertSame( 'storesuite_ai_field_yoast_seo', $fields['yoast_term_title']['enabled_setting'] );
		$this->assertSame( 'storesuite_ai_field_yoast_seo', $fields['yoast_term_twitter-description']['enabled_setting'] );
		$this->assertSame( 'storesuite_ai_instruction_yoast_title', $fields['yoast_term_title']['instruction_setting'] );
		$this->assertSame( 'storesuite_ai_instruction_yoast_description', $fields['yoast_term_metadesc']['instruction_setting'] );
		$this->assertSame( 'storesuite_ai_instruction_yoast_description', $fields['yoast_term_opengraph-description']['instruction_setting'], 'All descriptions share one instruction.' );
		$this->assertSame( 'text', $fields['yoast_term_title']['output'] );
		$this->assertSame( 'textarea', $fields['yoast_term_metadesc']['output'] );

		// With the product Yoast fields registered alongside, the settings page
		// still shows a single Yoast SEO block: one toggle, two instructions.
		new class() extends YoastSeoIntegration {
			protected function is_yoast_active(): bool {
				return true;
			}

			protected function get_yoast_option( string $key, $fallback = null ) {
				$options = array(
					'opengraph' => true,
					'twitter'   => true,
				);
				return $options[ $key ] ?? $fallback;
			}
		};

		$yoast_groups = array_values(
			array_filter(
				ProductAI::get_settings_groups(),
				static function ( $group ) {
					return 'yoast_seo' === $group['key'];
				}
			)
		);

		$this->assertCount( 1, $yoast_groups, 'Product and term fields share one settings group.' );
		$this->assertSame( 'storesuite_ai_field_yoast_seo', $yoast_groups[0]['enabled_setting'] );
		$this->assertSame(
			array( 'storesuite_ai_instruction_yoast_title', 'storesuite_ai_instruction_yoast_description' ),
			array_column( $yoast_groups[0]['instructions'], 'setting' )
		);
	}

	/**
	 * The term SEO title prompt targets Google with the keyphrase, the archive
	 * wording and the term name, never the product wording.
	 *
	 * @return void
	 */
	public function test_term_seo_title_prompt() {
		$this->make_integration();
		list( $prompt, $instruction ) = $this->generate_term_seo_field(
			'yoast_term_title',
			array(
				'product_category_name'          => 'Coffee Mugs',
				'product_category_description'   => 'Hand-made ceramic mugs.',
				'storesuite_yoast_term_focuskw'  => 'ceramic <b>mugs</b>',
			)
		);

		$this->assertStringContainsString( 'archive page of an online store', $prompt );
		$this->assertStringContainsString( 'Page type: a product category archive', $prompt );
		$this->assertStringContainsString( 'Category name: Coffee Mugs', $prompt );
		$this->assertStringContainsString( 'Page details: Hand-made ceramic mugs.', $prompt );
		$this->assertStringContainsString( 'Focus keyphrase: ceramic mugs', $prompt );
		$this->assertStringContainsString( 'Language: English', $prompt );
		$this->assertStringContainsString( 'Platform: Google search result', $prompt );
		$this->assertStringContainsString( 'at most 60 characters', $prompt );
		$this->assertStringNotContainsString( 'product title', $prompt );
		$this->assertStringNotContainsString( '%%', $prompt, 'The prompt never teaches the model Yoast variables.' );
		$this->assertStringContainsString( 'SEO copywriter', $instruction );
	}

	/**
	 * Tag and brand forms get their own taxonomy wording from their posted
	 * context fields.
	 *
	 * @return void
	 */
	public function test_term_prompt_wording_follows_the_posted_taxonomy() {
		$this->make_integration();

		list( $tag ) = $this->generate_term_seo_field(
			'yoast_term_title',
			array(
				'name'        => 'Vintage',
				'description' => 'Old things.',
			)
		);
		$this->assertStringContainsString( 'Page type: a product tag archive', $tag );
		$this->assertStringContainsString( 'Tag name: Vintage', $tag );

		list( $brand ) = $this->generate_term_seo_field(
			'yoast_term_title',
			array(
				'product_brand_name'        => 'Acme',
				'product_brand_description' => 'Everything Acme.',
			)
		);
		$this->assertStringContainsString( 'Page type: a product brand archive', $brand );
		$this->assertStringContainsString( 'Brand name: Acme', $brand );
	}

	/**
	 * Without a keyphrase the keyphrase line is omitted, not left blank.
	 *
	 * @return void
	 */
	public function test_term_prompt_omits_empty_keyphrase() {
		$this->make_integration();
		list( $prompt ) = $this->generate_term_seo_field( 'yoast_term_title', array( 'product_category_name' => 'Mugs' ) );

		$this->assertStringNotContainsString( 'Focus keyphrase', $prompt );
	}

	/**
	 * Meta descriptions get the 120–156 target and a huge term description cut down.
	 *
	 * @return void
	 */
	public function test_term_meta_description_prompt() {
		$this->make_integration();
		$long           = str_repeat( 'word ', 800 ); // ~4,000 characters.
		list( $prompt ) = $this->generate_term_seo_field(
			'yoast_term_metadesc',
			array(
				'product_category_name'        => 'Mugs',
				'product_category_description' => $long,
			)
		);

		$this->assertStringContainsString( 'between 120 and 156 characters', $prompt );
		$this->assertStringContainsString( 'Platform: Google search result', $prompt );
		$this->assertMatchesRegularExpression( '/Page details: (word ){1,401}/', $prompt );
		$this->assertLessThan( 2600, strlen( $prompt ), 'A huge description is cut to about 2,000 characters.' );
	}

	/**
	 * Social and X term fields carry their platform lines and lengths.
	 *
	 * @return void
	 */
	public function test_term_social_prompts_carry_platform_and_length() {
		$this->make_integration();

		list( $og ) = $this->generate_term_seo_field( 'yoast_term_opengraph-title', array( 'product_category_name' => 'Mugs' ) );
		$this->assertStringContainsString( 'Platform: social media share', $og );
		$this->assertStringContainsString( 'at most 70 characters', $og );

		list( $x ) = $this->generate_term_seo_field( 'yoast_term_twitter-description', array( 'product_category_name' => 'Mugs' ) );
		$this->assertStringContainsString( 'Platform: X (Twitter) post', $x );
		$this->assertStringContainsString( 'at most 200 characters', $x );
	}

	/**
	 * The card renders Generate buttons only when text AI is available.
	 *
	 * @return void
	 */
	public function test_term_card_renders_generate_buttons_only_with_ai() {
		$without = $this->render_card( $this->make_integration() );
		$this->assertStringNotContainsString( 'storesuite-ai-generate', $without );

		$filter = function () {
			return function () {
				return 'x';
			};
		};
		add_filter( 'storesuite_ai_text_generator', $filter );
		ProductAI::reset_ai_support_cache();

		$with = $this->render_card( $this->make_integration() );

		remove_filter( 'storesuite_ai_text_generator', $filter );
		ProductAI::reset_ai_support_cache();

		$this->assertSame( 6, substr_count( $with, 'class="storesuite-ai-generate' ) );
		$this->assertStringContainsString( 'data-field="yoast_term_title" data-target="#storesuite_yoast_term_title"', $with );
		$this->assertStringContainsString( 'data-field="yoast_term_metadesc" data-target="#storesuite_yoast_term_metadesc"', $with );
		$this->assertStringContainsString( 'data-field="yoast_term_twitter-description" data-target="#storesuite_yoast_term_twitter-description"', $with );
	}

	/**
	 * The AI modals (and their icon sprite) render on term form pages only
	 * when text AI is available.
	 *
	 * @return void
	 */
	public function test_term_ai_modals_render_only_with_ai_on_term_form_pages() {
		$integration = $this->make_integration( true, array( '__is_term_form_page' => true ) );

		ob_start();
		$integration->render_ai_modals();
		$this->assertSame( '', ob_get_clean(), 'No modals without AI.' );

		$filter = function () {
			return function () {
				return 'x';
			};
		};
		add_filter( 'storesuite_ai_text_generator', $filter );
		ProductAI::reset_ai_support_cache();

		ob_start();
		$integration->render_ai_modals();
		$on_form_page = ob_get_clean();

		ob_start();
		$this->make_integration()->render_ai_modals();
		$elsewhere = ob_get_clean();

		remove_filter( 'storesuite_ai_text_generator', $filter );
		ProductAI::reset_ai_support_cache();

		$this->assertStringContainsString( 'id="storesuite-ai-modal"', $on_form_page );
		$this->assertStringContainsString( 'storesuite-icon-ai-magic', $on_form_page, 'The AI icon sprite ships with the modals.' );
		$this->assertSame( '', $elsewhere, 'No modals off the term form pages.' );
	}

	/**
	 * Term form pages enqueue the product AI script and localize the registry
	 * (including the term fields, which never need a product title) for it.
	 * Other pages and AI-less sites get nothing.
	 *
	 * @return void
	 */
	public function test_term_form_pages_enqueue_the_ai_script_with_the_term_fields() {
		$registered = array();
		foreach ( array( 'storesuite_product_script', 'storesuite_product_ai_script' ) as $handle ) {
			if ( ! wp_script_is( $handle, 'registered' ) ) {
				wp_register_script( $handle, false, array(), STORESUITE_PLUGIN_VERSION, true );
				$registered[] = $handle;
			}
		}

		$integration = $this->make_integration( true, array( '__is_term_form_page' => true ) );
		$integration->enqueue_ai_assets();
		$this->assertFalse( wp_script_is( 'storesuite_product_ai_script', 'enqueued' ), 'No AI, no script.' );

		$filter = function () {
			return function () {
				return 'x';
			};
		};
		add_filter( 'storesuite_ai_text_generator', $filter );
		ProductAI::reset_ai_support_cache();

		$this->make_integration()->enqueue_ai_assets();
		$this->assertFalse( wp_script_is( 'storesuite_product_ai_script', 'enqueued' ), 'Not a term form page.' );

		$integration->enqueue_ai_assets();
		$enqueued = wp_script_is( 'storesuite_product_ai_script', 'enqueued' );
		$data     = (string) wp_scripts()->get_data( 'storesuite_product_script', 'data' );

		remove_filter( 'storesuite_ai_text_generator', $filter );
		ProductAI::reset_ai_support_cache();
		wp_dequeue_script( 'storesuite_product_ai_script' );
		wp_dequeue_script( 'storesuite_product_script' );
		foreach ( $registered as $handle ) {
			wp_deregister_script( $handle );
		}

		$this->assertTrue( $enqueued );
		$this->assertStringContainsString( 'StoreSuite_Product', $data );
		$this->assertStringContainsString( 'yoast_term_title', $data );
		$this->assertStringContainsString( 'storesuite_generate_product_field', $data );
		$this->assertStringContainsString( 'ajax_url', $data );
	}

	/**
	 * Active Yoast wires the AI field registration, asset and modal hooks;
	 * inactive Yoast wires none.
	 *
	 * @return void
	 */
	public function test_registers_ai_hooks_only_when_yoast_is_active() {
		$integration = $this->make_integration();
		$this->assertNotFalse( has_filter( 'storesuite_ai_text_fields', array( $integration, 'register_ai_fields' ) ) );
		$this->assertNotFalse( has_action( 'wp_enqueue_scripts', array( $integration, 'enqueue_ai_assets' ) ) );
		$this->assertNotFalse( has_action( 'storesuite_dashboard_wrapper_end', array( $integration, 'render_ai_modals' ) ) );

		$inactive = $this->make_integration( false );
		$this->assertFalse( has_filter( 'storesuite_ai_text_fields', array( $inactive, 'register_ai_fields' ) ) );
		$this->assertFalse( has_action( 'wp_enqueue_scripts', array( $inactive, 'enqueue_ai_assets' ) ) );
		$this->assertFalse( has_action( 'storesuite_dashboard_wrapper_end', array( $inactive, 'render_ai_modals' ) ) );
	}
}
