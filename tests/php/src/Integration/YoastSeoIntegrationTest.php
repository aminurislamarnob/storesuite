<?php
/**
 * Tests for the Yoast SEO product form integration.
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test\Integration;

use PluginizeLab\StoreSuite\Integration\YoastSeoIntegration;
use PluginizeLab\StoreSuite\Product\ProductAI;
use PluginizeLab\StoreSuite\Test\StoreSuiteTestCase;

/**
 * Yoast SEO is not installed on CI, so the integration's Yoast touch points
 * are overridden by a test double; meta writes fall through to post meta.
 */
class YoastSeoIntegrationTest extends StoreSuiteTestCase {

	/**
	 * Product under test.
	 *
	 * @var int
	 */
	private $product_id;

	/**
	 * Set up the test fixture.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->product_id = self::factory()->post->create( array( 'post_type' => 'product' ) );
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
	 * @param bool  $active  Whether Yoast SEO should look active.
	 * @param array $options Yoast option values keyed by option name.
	 *
	 * @return YoastSeoIntegration
	 */
	private function make_integration( bool $active = true, array $options = array() ): YoastSeoIntegration {
		$options = wp_parse_args(
			$options,
			array(
				'enable_cornerstone_content' => true,
				// Yoast's default: advanced settings are locked down to users with the capability.
				'disableadvanced_meta'       => true,
				'opengraph'                  => true,
				'twitter'                    => true,
			)
		);

		return new class( $active, $options ) extends YoastSeoIntegration {

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
			protected function is_product_form_page(): bool {
				return ! empty( self::$options['__is_product_form_page'] );
			}

			/**
			 * Return the faked title separator.
			 *
			 * @return string
			 */
			protected function get_title_separator(): string {
				return '|';
			}
		};
	}

	/**
	 * Simulate the product form submission.
	 *
	 * @param array $fields Posted SEO fields.
	 *
	 * @return void
	 */
	private function post_form( array $fields ): void {
		$_POST = array_merge( array( 'storesuite_yoast_seo' => '1' ), $fields );
	}

	/**
	 * Inactive Yoast means no hooks at all.
	 *
	 * @return void
	 */
	public function test_registers_no_hooks_when_yoast_is_inactive() {
		$integration = $this->make_integration( false );

		$this->assertFalse( has_action( 'storesuite_product_form_after_main_cards', array( $integration, 'render_card' ) ) );
		$this->assertFalse( has_action( 'storesuite_new_product_added', array( $integration, 'save' ) ) );
		$this->assertFalse( has_action( 'storesuite_product_updated', array( $integration, 'save' ) ) );
	}

	/**
	 * Active Yoast wires the form card and both save actions.
	 *
	 * @return void
	 */
	public function test_registers_hooks_when_yoast_is_active() {
		$integration = $this->make_integration();

		$this->assertNotFalse( has_action( 'storesuite_product_form_after_main_cards', array( $integration, 'render_card' ) ) );
		$this->assertNotFalse( has_action( 'storesuite_new_product_added', array( $integration, 'save' ) ) );
		$this->assertNotFalse( has_action( 'storesuite_product_updated', array( $integration, 'save' ) ) );
	}

	/**
	 * Text fields are stored under Yoast's meta keys, sanitized, with variables intact.
	 *
	 * @return void
	 */
	public function test_saves_sanitized_text_fields() {
		$this->post_form(
			array(
				'storesuite_yoast_focuskw'  => ' blue <b>widget</b> ',
				'storesuite_yoast_title'    => '%%title%% %%sep%% Buy <script>alert(1)</script>now',
				'storesuite_yoast_metadesc' => "The best\nwidget \\'ever\\'",
			)
		);

		$this->make_integration()->save( $this->product_id );

		$this->assertSame( 'blue widget', get_post_meta( $this->product_id, '_yoast_wpseo_focuskw', true ) );
		$this->assertSame( '%%title%% %%sep%% Buy now', get_post_meta( $this->product_id, '_yoast_wpseo_title', true ) );
		$this->assertSame( "The best widget 'ever'", get_post_meta( $this->product_id, '_yoast_wpseo_metadesc', true ) );
	}

	/**
	 * Emptying a field removes the meta row instead of storing an empty string.
	 *
	 * @return void
	 */
	public function test_empty_value_deletes_meta() {
		update_post_meta( $this->product_id, '_yoast_wpseo_title', 'Old title' );
		$this->post_form( array( 'storesuite_yoast_title' => '   ' ) );

		$this->make_integration()->save( $this->product_id );

		$this->assertFalse( metadata_exists( 'post', $this->product_id, '_yoast_wpseo_title' ) );
	}

	/**
	 * A field that was not posted keeps its stored value.
	 *
	 * @return void
	 */
	public function test_unposted_field_is_left_untouched() {
		update_post_meta( $this->product_id, '_yoast_wpseo_metadesc', 'Keep me' );
		$this->post_form( array( 'storesuite_yoast_title' => 'New' ) );

		$this->make_integration()->save( $this->product_id );

		$this->assertSame( 'Keep me', get_post_meta( $this->product_id, '_yoast_wpseo_metadesc', true ) );
	}

	/**
	 * Saves that did not come from the SEO card (no marker field) change nothing.
	 *
	 * @return void
	 */
	public function test_save_without_card_marker_changes_nothing() {
		update_post_meta( $this->product_id, '_yoast_wpseo_is_cornerstone', '1' );
		$_POST = array( 'storesuite_yoast_title' => 'Sneaky' );

		$this->make_integration()->save( $this->product_id );

		$this->assertSame( '1', get_post_meta( $this->product_id, '_yoast_wpseo_is_cornerstone', true ) );
		$this->assertFalse( metadata_exists( 'post', $this->product_id, '_yoast_wpseo_title' ) );
	}

	/**
	 * The cornerstone toggle stores "1" when checked and removes the meta when unchecked.
	 *
	 * @return void
	 */
	public function test_cornerstone_toggle_round_trip() {
		$integration = $this->make_integration();

		$this->post_form( array( 'storesuite_yoast_is_cornerstone' => 'yes' ) );
		$integration->save( $this->product_id );
		$this->assertSame( '1', get_post_meta( $this->product_id, '_yoast_wpseo_is_cornerstone', true ) );

		$this->post_form( array() );
		$integration->save( $this->product_id );
		$this->assertFalse( metadata_exists( 'post', $this->product_id, '_yoast_wpseo_is_cornerstone' ) );
	}

	/**
	 * With Yoast's cornerstone feature off the toggle is ignored in both directions.
	 *
	 * @return void
	 */
	public function test_cornerstone_is_ignored_when_feature_disabled() {
		$integration = $this->make_integration( true, array( 'enable_cornerstone_content' => false ) );

		$this->post_form( array( 'storesuite_yoast_is_cornerstone' => 'yes' ) );
		$integration->save( $this->product_id );
		$this->assertFalse( metadata_exists( 'post', $this->product_id, '_yoast_wpseo_is_cornerstone' ) );

		update_post_meta( $this->product_id, '_yoast_wpseo_is_cornerstone', '1' );
		$this->post_form( array() );
		$integration->save( $this->product_id );
		$this->assertSame( '1', get_post_meta( $this->product_id, '_yoast_wpseo_is_cornerstone', true ) );
	}

	/**
	 * Keys outside the whitelist are never written, whatever prefix they use.
	 *
	 * @return void
	 */
	public function test_unknown_keys_are_never_written() {
		$this->post_form(
			array(
				'storesuite_yoast_canonical'           => 'https://evil.example/',
				'storesuite_yoast_meta-robots-noindex' => '1',
				'_yoast_wpseo_canonical'               => 'https://evil.example/',
				'yoast_wpseo_canonical'                => 'https://evil.example/',
			)
		);

		$this->make_integration()->save( $this->product_id );

		$this->assertFalse( metadata_exists( 'post', $this->product_id, '_yoast_wpseo_canonical' ) );
		$this->assertFalse( metadata_exists( 'post', $this->product_id, '_yoast_wpseo_meta-robots-noindex' ) );
	}

	/**
	 * The card renders stored values and hides the cornerstone toggle when the feature is off.
	 *
	 * @return void
	 */
	public function test_card_renders_stored_values_and_gates_cornerstone() {
		update_post_meta( $this->product_id, '_yoast_wpseo_title', 'Stored "title"' );
		$product = wc_get_product( $this->product_id );

		ob_start();
		$this->make_integration()->render_card( $product, true );
		$with_cornerstone = ob_get_clean();

		$this->assertStringContainsString( 'name="storesuite_yoast_seo"', $with_cornerstone );
		$this->assertStringContainsString( 'value="Stored &quot;title&quot;"', $with_cornerstone );
		$this->assertStringContainsString( 'name="storesuite_yoast_is_cornerstone"', $with_cornerstone );

		ob_start();
		$this->make_integration( true, array( 'enable_cornerstone_content' => false ) )->render_card( $product, true );
		$without_cornerstone = ob_get_clean();

		$this->assertStringNotContainsString( 'storesuite_yoast_is_cornerstone', $without_cornerstone );
	}

	/**
	 * The add form (no product yet) renders an empty card without errors.
	 *
	 * @return void
	 */
	public function test_card_renders_on_add_form() {
		ob_start();
		$this->make_integration()->render_card( null, false );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'name="storesuite_yoast_focuskw"', $html );
	}

	/**
	 * The preview script receives Yoast's site-wide product templates, separator and site name.
	 *
	 * @return void
	 */
	public function test_script_data_exposes_templates_separator_and_site() {
		update_option( 'blogname', 'Widget Shop' );

		$data = $this->make_integration(
			true,
			array(
				'title-product'    => '%%title%% %%sep%% %%sitename%%',
				'metadesc-product' => 'Buy %%title%% today',
			)
		)->get_script_data();

		$this->assertSame( '%%title%% %%sep%% %%sitename%%', $data['titleTemplate'] );
		$this->assertSame( 'Buy %%title%% today', $data['descTemplate'] );
		$this->assertSame( '|', $data['replacements']['sep'] );
		$this->assertSame( 'Widget Shop', $data['replacements']['sitename'] );
		$this->assertSame( 600, $data['titleMaxWidth'] );
		$this->assertSame( 156, $data['descMaxLength'] );
	}

	/**
	 * The variable menu leads with Yoast's recommended product variables.
	 *
	 * @return void
	 */
	public function test_variable_menu_leads_with_recommended_variables() {
		$data = $this->make_integration()->get_script_data();

		$this->assertSame( array( 'sitename', 'title', 'sep' ), array_slice( array_column( $data['variables'], 'name' ), 0, 3 ) );
		$this->assertTrue( $data['variables'][0]['recommended'] );
		$this->assertNotEmpty( $data['variables'][0]['label'] );
	}

	/**
	 * The preview script only loads on the product add/edit form.
	 *
	 * @return void
	 */
	public function test_preview_script_only_enqueued_on_product_form() {
		$this->make_integration()->enqueue_assets();
		$this->assertFalse( wp_script_is( 'storesuite_product_seo_script', 'enqueued' ) );

		$this->make_integration( true, array( '__is_product_form_page' => true ) )->enqueue_assets();
		$this->assertTrue( wp_script_is( 'storesuite_product_seo_script', 'enqueued' ) );

		wp_dequeue_script( 'storesuite_product_seo_script' );
	}

	/**
	 * Post every advanced field with valid values.
	 *
	 * @return void
	 */
	private function post_advanced_fields(): void {
		$this->post_form(
			array(
				'storesuite_yoast_meta-robots-noindex'  => '1',
				'storesuite_yoast_meta-robots-nofollow' => '1',
				'storesuite_yoast_bctitle'              => ' Short <i>crumb</i> ',
				'storesuite_yoast_canonical'            => 'https://example.org/canonical-widget/',
			)
		);
	}

	/**
	 * Under Yoast's defaults a shop manager's advanced fields are ignored and existing values kept.
	 *
	 * @return void
	 */
	public function test_advanced_fields_ignored_for_shop_manager_by_default() {
		update_post_meta( $this->product_id, '_yoast_wpseo_canonical', 'https://example.org/original/' );
		$this->post_advanced_fields();

		$integration = $this->make_integration();
		$integration->save( $this->product_id );

		$this->assertFalse( $integration->can_edit_advanced() );
		$this->assertSame( 'https://example.org/original/', get_post_meta( $this->product_id, '_yoast_wpseo_canonical', true ) );
		$this->assertFalse( metadata_exists( 'post', $this->product_id, '_yoast_wpseo_meta-robots-noindex' ) );
		$this->assertFalse( metadata_exists( 'post', $this->product_id, '_yoast_wpseo_meta-robots-nofollow' ) );
		$this->assertFalse( metadata_exists( 'post', $this->product_id, '_yoast_wpseo_bctitle' ) );
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
			add_filter( 'storesuite_yoast_can_edit_advanced', '__return_true' );
		}

		$this->post_advanced_fields();
		$integration = $this->make_integration( true, $options );
		$integration->save( $this->product_id );

		remove_filter( 'storesuite_yoast_can_edit_advanced', '__return_true' );

		$this->assertSame( '1', get_post_meta( $this->product_id, '_yoast_wpseo_meta-robots-noindex', true ) );
		$this->assertSame( '1', get_post_meta( $this->product_id, '_yoast_wpseo_meta-robots-nofollow', true ) );
		$this->assertSame( 'Short crumb', get_post_meta( $this->product_id, '_yoast_wpseo_bctitle', true ) );
		$this->assertSame( 'https://example.org/canonical-widget/', get_post_meta( $this->product_id, '_yoast_wpseo_canonical', true ) );
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
		add_filter( 'storesuite_yoast_can_edit_advanced', '__return_false' );
		$can = $this->make_integration( true, array( '__has_advanced_capability' => true ) )->can_edit_advanced();
		remove_filter( 'storesuite_yoast_can_edit_advanced', '__return_false' );

		$this->assertFalse( $can );
	}

	/**
	 * Robots values outside Yoast's fixed sets are rejected; defaults remove the meta.
	 *
	 * @return void
	 */
	public function test_advanced_values_are_validated_and_defaults_delete() {
		$integration = $this->make_integration( true, array( '__has_advanced_capability' => true ) );

		update_post_meta( $this->product_id, '_yoast_wpseo_meta-robots-noindex', '2' );
		$this->post_form(
			array(
				'storesuite_yoast_meta-robots-noindex'  => '7',
				'storesuite_yoast_meta-robots-nofollow' => 'yes',
				'storesuite_yoast_canonical'            => 'javascript:alert(1)',
			)
		);
		$integration->save( $this->product_id );

		$this->assertSame( '2', get_post_meta( $this->product_id, '_yoast_wpseo_meta-robots-noindex', true ), 'An invalid choice must not overwrite the stored value.' );
		$this->assertFalse( metadata_exists( 'post', $this->product_id, '_yoast_wpseo_meta-robots-nofollow' ) );
		$this->assertFalse( metadata_exists( 'post', $this->product_id, '_yoast_wpseo_canonical' ) );

		$this->post_form(
			array(
				'storesuite_yoast_meta-robots-noindex'  => '0',
				'storesuite_yoast_meta-robots-nofollow' => '0',
			)
		);
		$integration->save( $this->product_id );

		$this->assertFalse( metadata_exists( 'post', $this->product_id, '_yoast_wpseo_meta-robots-noindex' ) );
		$this->assertFalse( metadata_exists( 'post', $this->product_id, '_yoast_wpseo_meta-robots-nofollow' ) );
	}

	/**
	 * The Advanced tab and its fields only render for users who may edit them.
	 *
	 * @return void
	 */
	public function test_advanced_tab_only_renders_when_allowed() {
		update_post_meta( $this->product_id, '_yoast_wpseo_canonical', 'https://example.org/stored/' );
		$product = wc_get_product( $this->product_id );

		ob_start();
		$this->make_integration()->render_card( $product, true );
		$locked = ob_get_clean();

		$this->assertStringNotContainsString( 'storesuite_yoast_canonical', $locked );
		$this->assertStringNotContainsString( 'data-seo-tab="advanced"', $locked );

		ob_start();
		$this->make_integration( true, array( '__has_advanced_capability' => true ) )->render_card( $product, true );
		$open = ob_get_clean();

		$this->assertStringContainsString( 'data-seo-tab="advanced"', $open );
		$this->assertStringContainsString( 'name="storesuite_yoast_meta-robots-noindex"', $open );
		$this->assertStringContainsString( 'value="https://example.org/stored/"', $open );
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
	 * Social titles and descriptions are saved as text and removed when emptied.
	 *
	 * @return void
	 */
	public function test_social_text_fields_round_trip() {
		$integration = $this->make_integration();

		$this->post_form(
			array(
				'storesuite_yoast_opengraph-title'       => 'Share <b>me</b>',
				'storesuite_yoast_opengraph-description' => 'On Facebook',
				'storesuite_yoast_twitter-title'         => '%%title%% on X',
				'storesuite_yoast_twitter-description'   => 'On X',
			)
		);
		$integration->save( $this->product_id );

		$this->assertSame( 'Share me', get_post_meta( $this->product_id, '_yoast_wpseo_opengraph-title', true ) );
		$this->assertSame( 'On Facebook', get_post_meta( $this->product_id, '_yoast_wpseo_opengraph-description', true ) );
		$this->assertSame( '%%title%% on X', get_post_meta( $this->product_id, '_yoast_wpseo_twitter-title', true ) );
		$this->assertSame( 'On X', get_post_meta( $this->product_id, '_yoast_wpseo_twitter-description', true ) );

		$this->post_form( array( 'storesuite_yoast_opengraph-title' => '' ) );
		$integration->save( $this->product_id );

		$this->assertFalse( metadata_exists( 'post', $this->product_id, '_yoast_wpseo_opengraph-title' ) );
	}

	/**
	 * A valid image ID stores both the ID and the URL the server derives from it.
	 *
	 * @return void
	 */
	public function test_social_image_stores_id_and_server_derived_url() {
		$image_id = $this->make_attachment( 'image/jpeg', '2026/09/share.jpg' );

		$this->post_form(
			array(
				'storesuite_yoast_opengraph-image-id' => (string) $image_id,
				// A client-supplied URL must never be trusted.
				'storesuite_yoast_opengraph-image'    => 'https://attacker.example/pixel.gif',
			)
		);
		$this->make_integration()->save( $this->product_id );

		$this->assertSame( (string) $image_id, get_post_meta( $this->product_id, '_yoast_wpseo_opengraph-image-id', true ) );
		$this->assertSame( wp_get_attachment_url( $image_id ), get_post_meta( $this->product_id, '_yoast_wpseo_opengraph-image', true ) );
	}

	/**
	 * IDs that are not image attachments are rejected and nothing is changed.
	 *
	 * @return void
	 */
	public function test_social_image_rejects_non_images() {
		$existing = $this->make_attachment( 'image/png', '2026/09/existing.png' );
		update_post_meta( $this->product_id, '_yoast_wpseo_twitter-image-id', (string) $existing );
		update_post_meta( $this->product_id, '_yoast_wpseo_twitter-image', 'https://example.org/existing.png' );

		$candidates = array(
			'a pdf attachment' => $this->make_attachment( 'application/pdf', '2026/09/manual.pdf' ),
			'a product'        => $this->product_id,
			'a missing post'   => 999999,
		);

		foreach ( $candidates as $label => $id ) {
			$this->post_form( array( 'storesuite_yoast_twitter-image-id' => (string) $id ) );
			$this->make_integration()->save( $this->product_id );

			$this->assertSame( (string) $existing, get_post_meta( $this->product_id, '_yoast_wpseo_twitter-image-id', true ), $label );
			$this->assertSame( 'https://example.org/existing.png', get_post_meta( $this->product_id, '_yoast_wpseo_twitter-image', true ), $label );
		}
	}

	/**
	 * Removing the image clears both stored values.
	 *
	 * @return void
	 */
	public function test_removing_social_image_clears_id_and_url() {
		update_post_meta( $this->product_id, '_yoast_wpseo_opengraph-image-id', '12' );
		update_post_meta( $this->product_id, '_yoast_wpseo_opengraph-image', 'https://example.org/old.jpg' );

		$this->post_form( array( 'storesuite_yoast_opengraph-image-id' => '' ) );
		$this->make_integration()->save( $this->product_id );

		$this->assertFalse( metadata_exists( 'post', $this->product_id, '_yoast_wpseo_opengraph-image-id' ) );
		$this->assertFalse( metadata_exists( 'post', $this->product_id, '_yoast_wpseo_opengraph-image' ) );
	}

	/**
	 * Fields of a social network whose Yoast feature is off are ignored on save.
	 *
	 * @return void
	 */
	public function test_social_fields_follow_yoast_feature_toggles_on_save() {
		$this->post_form(
			array(
				'storesuite_yoast_opengraph-title' => 'Facebook title',
				'storesuite_yoast_twitter-title'   => 'X title',
			)
		);
		$this->make_integration( true, array( 'opengraph' => false ) )->save( $this->product_id );

		$this->assertFalse( metadata_exists( 'post', $this->product_id, '_yoast_wpseo_opengraph-title' ) );
		$this->assertSame( 'X title', get_post_meta( $this->product_id, '_yoast_wpseo_twitter-title', true ) );
	}

	/**
	 * The Social tab shows only the networks Yoast has enabled, and disappears with both off.
	 *
	 * @return void
	 */
	public function test_social_tab_follows_yoast_feature_toggles_on_render() {
		$render = function ( array $options ) {
			ob_start();
			$this->make_integration( true, $options )->render_card( null, false );
			return ob_get_clean();
		};

		$both = $render( array() );
		$this->assertStringContainsString( 'data-seo-tab="social"', $both );
		$this->assertStringContainsString( 'name="storesuite_yoast_opengraph-title"', $both );
		$this->assertStringContainsString( 'name="storesuite_yoast_twitter-image-id"', $both );

		$x_only = $render( array( 'opengraph' => false ) );
		$this->assertStringNotContainsString( 'storesuite_yoast_opengraph-', $x_only );
		$this->assertStringContainsString( 'name="storesuite_yoast_twitter-title"', $x_only );

		$none = $render(
			array(
				'opengraph' => false,
				'twitter'   => false,
			)
		);
		$this->assertStringNotContainsString( 'data-seo-tab="social"', $none );
		$this->assertStringNotContainsString( 'data-seo-panel="social"', $none );
	}

	/**
	 * "Meta robots advanced" stores the allowed directives comma separated and drops anything else.
	 *
	 * @return void
	 */
	public function test_meta_robots_advanced_round_trip() {
		$integration = $this->make_integration( true, array( '__has_advanced_capability' => true ) );

		$_POST = array(
			'storesuite_yoast_seo'             => '1',
			'storesuite_yoast_meta-robots-adv' => array( 'nosnippet', 'noimageindex', 'noindex', '<b>bogus</b>' ),
		);
		$integration->save( $this->product_id );

		$this->assertSame( 'noimageindex,nosnippet', get_post_meta( $this->product_id, '_yoast_wpseo_meta-robots-adv', true ) );

		// Nothing selected: a multi-select posts no value at all.
		$this->post_form( array() );
		$integration->save( $this->product_id );

		$this->assertFalse( metadata_exists( 'post', $this->product_id, '_yoast_wpseo_meta-robots-adv' ) );
	}

	/**
	 * A user without advanced access can neither set nor clear "Meta robots advanced".
	 *
	 * @return void
	 */
	public function test_meta_robots_advanced_untouched_without_access() {
		update_post_meta( $this->product_id, '_yoast_wpseo_meta-robots-adv', 'noarchive' );

		$_POST = array(
			'storesuite_yoast_seo'             => '1',
			'storesuite_yoast_meta-robots-adv' => array( 'nosnippet' ),
		);
		$this->make_integration()->save( $this->product_id );
		$this->assertSame( 'noarchive', get_post_meta( $this->product_id, '_yoast_wpseo_meta-robots-adv', true ) );

		$this->post_form( array() );
		$this->make_integration()->save( $this->product_id );
		$this->assertSame( 'noarchive', get_post_meta( $this->product_id, '_yoast_wpseo_meta-robots-adv', true ) );
	}

	/**
	 * The Advanced and Social panels mirror the Yoast metabox: radios for following links,
	 * the robots multi-select with stored values selected, and Insert variable on social text.
	 *
	 * @return void
	 */
	public function test_panels_mirror_the_yoast_metabox() {
		update_post_meta( $this->product_id, '_yoast_wpseo_meta-robots-adv', 'noarchive,nosnippet' );
		update_post_meta( $this->product_id, '_yoast_wpseo_meta-robots-nofollow', '1' );

		ob_start();
		$this->make_integration( true, array( '__has_advanced_capability' => true ) )->render_card( wc_get_product( $this->product_id ), true );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'name="storesuite_yoast_meta-robots-adv[]"', $html );
		$this->assertMatchesRegularExpression( '/<option value="noarchive"\s+selected/', $html );
		$this->assertMatchesRegularExpression( '/<option value="noimageindex"\s*>/', $html );
		$this->assertMatchesRegularExpression( '/type="radio"[^>]+name="storesuite_yoast_meta-robots-nofollow"[^>]+value="1"[^>]+checked/', $html );
		$this->assertStringContainsString( 'Yes (current default for Products)', $html );
		$this->assertSame( 4, substr_count( $html, 'storesuite-seo-insert-variable' ) - 2, 'Social title and description of both networks offer Insert variable.' );
	}

	/* ----------------------------------------------------------------------
	 * AI Generate for the SEO fields
	 * -------------------------------------------------------------------- */

	/**
	 * Register the fake generator and build the prompt for one SEO field.
	 *
	 * @param YoastSeoIntegration $integration Integration under test.
	 * @param string              $field       AI field key.
	 * @param array               $request     Raw request fields.
	 *
	 * @return array{0: string, 1: string} Prompt and system instruction the generator received.
	 */
	private function generate_seo_field( YoastSeoIntegration $integration, string $field, array $request ): array {
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
			'title'             => $request['product_title'] ?? '',
			'short_description' => $request['product_short_description'] ?? '',
			'description'       => $request['product_description'] ?? '',
			'categories'        => array(),
			'previous'          => $request['previous'] ?? '',
		);
		$method->invoke( $ai, $field, $context, $request );

		remove_filter( 'storesuite_ai_text_generator', $filter );
		ProductAI::reset_ai_support_cache();

		return $captured;
	}

	/**
	 * With Yoast active the six SEO fields are registered; inactive, none are.
	 *
	 * @return void
	 */
	public function test_seo_ai_fields_follow_yoast_activation() {
		$this->make_integration();
		$keys = array_keys( ProductAI::get_fields() );
		foreach ( array( 'yoast_title', 'yoast_metadesc', 'yoast_opengraph-title', 'yoast_opengraph-description', 'yoast_twitter-title', 'yoast_twitter-description' ) as $key ) {
			$this->assertContains( $key, $keys );
		}

		$fields = ProductAI::get_fields();
		$this->assertSame( '#storesuite_yoast_title', $fields['yoast_title']['target'] );
		$this->assertSame( 60, $fields['yoast_title']['length'] );
		$this->assertSame( 156, $fields['yoast_metadesc']['length'] );
		$this->assertSame( 'yoast_seo', $fields['yoast_title']['group'] );

		remove_all_filters( 'storesuite_ai_text_fields' );
		$this->make_integration( false );
		$this->assertNotContains( 'yoast_title', array_keys( ProductAI::get_fields() ) );
	}

	/**
	 * Social fields are registered only for networks Yoast has enabled.
	 *
	 * @return void
	 */
	public function test_social_ai_fields_follow_yoast_feature_toggles() {
		$this->make_integration( true, array( 'twitter' => false ) );
		$keys = array_keys( ProductAI::get_fields() );

		$this->assertContains( 'yoast_opengraph-title', $keys );
		$this->assertNotContains( 'yoast_twitter-title', $keys );
	}

	/**
	 * The SEO title prompt targets Google with the keyphrase, language and length.
	 *
	 * @return void
	 */
	public function test_seo_title_prompt() {
		$integration = $this->make_integration();
		list( $prompt, $instruction ) = $this->generate_seo_field(
			$integration,
			'yoast_title',
			array(
				'product_title'             => 'Ceramic Mug',
				'product_short_description' => 'A sturdy 350ml mug.',
				'storesuite_yoast_focuskw'  => 'ceramic <b>mug</b>',
			)
		);

		$this->assertStringContainsString( 'Focus keyphrase: ceramic mug', $prompt );
		$this->assertStringContainsString( 'Language: English', $prompt );
		$this->assertStringContainsString( 'Platform: Google search result', $prompt );
		$this->assertStringContainsString( 'at most 60 characters', $prompt );
		$this->assertStringContainsString( 'Product name / keywords: Ceramic Mug', $prompt );
		$this->assertStringNotContainsString( '%%', $prompt, 'The prompt never teaches the model Yoast variables.' );
		$this->assertStringContainsString( 'SEO copywriter', $instruction );
	}

	/**
	 * Without a keyphrase the keyphrase line is omitted, not left blank.
	 *
	 * @return void
	 */
	public function test_prompt_omits_empty_keyphrase() {
		list( $prompt ) = $this->generate_seo_field( $this->make_integration(), 'yoast_title', array( 'product_title' => 'Mug' ) );

		$this->assertStringNotContainsString( 'Focus keyphrase', $prompt );
	}

	/**
	 * Descriptions get the 120–156 target, a call to action, and the long description cut down.
	 *
	 * @return void
	 */
	public function test_meta_description_prompt() {
		$long = str_repeat( 'word ', 800 ); // ~4,000 characters.
		list( $prompt, $instruction ) = $this->generate_seo_field(
			$this->make_integration(),
			'yoast_metadesc',
			array(
				'product_title'       => 'Mug',
				'product_description' => '<p>' . $long . '</p>',
			)
		);

		$this->assertStringContainsString( 'between 120 and 156 characters', $prompt );
		$this->assertStringContainsString( 'Platform: Google search result', $prompt );
		$this->assertMatchesRegularExpression( '/Product details: (word ){1,401}/', $prompt );
		$this->assertLessThan( 2600, strlen( $prompt ), 'A huge description is cut to about 2,000 characters.' );
		$this->assertStringContainsString( 'call to action', $instruction );
	}

	/**
	 * Social and X fields carry their platform lines and lengths.
	 *
	 * @return void
	 */
	public function test_social_prompts_carry_platform_and_length() {
		$integration = $this->make_integration();

		list( $og ) = $this->generate_seo_field( $integration, 'yoast_opengraph-title', array( 'product_title' => 'Mug' ) );
		$this->assertStringContainsString( 'Platform: social media share', $og );
		$this->assertStringContainsString( 'at most 70 characters', $og );

		list( $x ) = $this->generate_seo_field( $integration, 'yoast_twitter-description', array( 'product_title' => 'Mug' ) );
		$this->assertStringContainsString( 'Platform: X (Twitter) post', $x );
		$this->assertStringContainsString( 'at most 200 characters', $x );
	}

	/**
	 * The site language is passed as a language name.
	 *
	 * @return void
	 */
	public function test_prompt_uses_site_language() {
		add_filter( 'locale', $set = function () { return 'de_DE'; } );
		list( $prompt ) = $this->generate_seo_field( $this->make_integration(), 'yoast_title', array( 'product_title' => 'Tasse' ) );
		remove_filter( 'locale', $set );

		$this->assertStringContainsString( 'Language: German', $prompt );
	}

	/**
	 * Every SEO field posts through the shared AJAX action as plain text, with the group toggle honoured.
	 *
	 * @return void
	 */
	public function test_seo_field_generates_plain_text_and_honours_group_toggle() {
		$this->make_integration();
		$fields = ProductAI::get_fields();

		$this->assertSame( 'text', $fields['yoast_title']['output'] );
		$this->assertSame( 'textarea', $fields['yoast_metadesc']['output'] );
		$this->assertSame( 'storesuite_ai_field_yoast_seo', $fields['yoast_title']['enabled_setting'] );
		$this->assertSame( 'storesuite_ai_instruction_yoast_title', $fields['yoast_title']['instruction_setting'] );
		$this->assertSame( 'storesuite_ai_instruction_yoast_description', $fields['yoast_metadesc']['instruction_setting'] );
		$this->assertSame( 'storesuite_ai_instruction_yoast_description', $fields['yoast_twitter-description']['instruction_setting'], 'All descriptions share one instruction.' );
	}

	/**
	 * The card renders Generate buttons only when text AI is available.
	 *
	 * @return void
	 */
	public function test_card_renders_generate_buttons_only_with_ai() {
		$product = wc_get_product( $this->product_id );

		ob_start();
		$this->make_integration()->render_card( $product, true );
		$without = ob_get_clean();
		$this->assertStringNotContainsString( 'storesuite-ai-generate', $without );

		$filter = function () {
			return function () {
				return 'x';
			};
		};
		add_filter( 'storesuite_ai_text_generator', $filter );
		ProductAI::reset_ai_support_cache();

		ob_start();
		$this->make_integration()->render_card( $product, true );
		$with = ob_get_clean();

		remove_filter( 'storesuite_ai_text_generator', $filter );
		ProductAI::reset_ai_support_cache();

		$this->assertSame( 6, substr_count( $with, 'class="storesuite-ai-generate' ) );
		$this->assertStringContainsString( 'data-field="yoast_title" data-target="#storesuite_yoast_title"', $with );
		$this->assertStringContainsString( 'data-field="yoast_twitter-description" data-target="#storesuite_yoast_twitter-description"', $with );
	}
}
