<?php
/**
 * Tests for the Yoast SEO product form integration.
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test\Integration;

use PluginizeLab\StoreSuite\Integration\YoastSeoIntegration;
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
		$options = wp_parse_args( $options, array( 'enable_cornerstone_content' => true ) );

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
}
