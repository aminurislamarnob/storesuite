<?php
/**
 * Tests for AI product copy generation.
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test\Product;

use PluginizeLab\StoreSuite\Product\ProductAI;
use PluginizeLab\StoreSuite\Test\StoreSuiteAjaxTestCase;
use WP_Error;

/**
 * CI has no AI provider, so a fake generator is supplied through the
 * `storesuite_ai_text_generator` filter. It records every call so tests can
 * inspect the prompt and system instruction the request produced.
 *
 * @group storesuite-product
 * @group storesuite-ajax
 */
class ProductAITest extends StoreSuiteAjaxTestCase {

	/**
	 * Every generator call made during the current test: [ prompt, instruction, field ].
	 *
	 * @var array<int, array{0: string, 1: string, 2: string}>
	 */
	private $calls = array();

	/**
	 * What the fake generator returns next; a string, a WP_Error, or a callable.
	 *
	 * @var mixed
	 */
	private $reply = 'Generated text';

	/**
	 * Set up the test fixture.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->calls = array();
		$this->reply = 'Generated text';

		add_filter( 'storesuite_ai_text_generator', array( $this, 'fake_generator' ) );
		ProductAI::reset_ai_support_cache();

		$this->_setRole( 'shop_manager' );
	}

	/**
	 * Tear down the test fixture.
	 *
	 * @return void
	 */
	public function tear_down() {
		remove_filter( 'storesuite_ai_text_generator', array( $this, 'fake_generator' ) );
		ProductAI::reset_ai_support_cache();
		delete_option( 'storesuite_settings' );

		parent::tear_down();
	}

	/**
	 * Supply the fake generator.
	 *
	 * @return callable
	 */
	public function fake_generator() {
		return function ( $prompt, $instruction, $field ) {
			$this->calls[] = array( $prompt, $instruction, $field );

			return is_callable( $this->reply ) ? call_user_func( $this->reply, $prompt, $instruction, $field ) : $this->reply;
		};
	}

	/**
	 * Post a field generation request as the current user.
	 *
	 * @param string $field  Field key.
	 * @param array  $fields Extra POST fields.
	 *
	 * @return array Decoded JSON response.
	 */
	private function generate( string $field, array $fields = array() ): array {
		return $this->do_ajax(
			'storesuite_generate_product_field',
			array_merge( array( 'field' => $field ), $fields ),
			'_storesuite_ai_',
			'nonce'
		);
	}

	/**
	 * Store plugin settings.
	 *
	 * @param array $settings Settings to merge into the storesuite_settings option.
	 *
	 * @return void
	 */
	private function set_settings( array $settings ): void {
		update_option( 'storesuite_settings', array_merge( (array) get_option( 'storesuite_settings', array() ), $settings ) );
	}

	/* ----------------------------------------------------------------------
	 * Characterization: the three built-in fields
	 * -------------------------------------------------------------------- */

	/**
	 * A supplied generator makes text AI count as available.
	 *
	 * @return void
	 */
	public function test_custom_generator_makes_text_ai_available() {
		$this->assertTrue( ProductAI::is_text_supported() );

		remove_filter( 'storesuite_ai_text_generator', array( $this, 'fake_generator' ) );
		ProductAI::reset_ai_support_cache();

		$this->assertFalse( ProductAI::is_text_supported(), 'Without a generator (and no core provider on CI) text AI is unavailable.' );
	}

	/**
	 * The title is generated from the form context and returned sanitized.
	 *
	 * @return void
	 */
	public function test_generates_title_from_form_context() {
		$this->reply = "  \"Ceramic Mug <b>Deluxe</b>\"  ";

		$response = $this->generate(
			'title',
			array(
				'product_title'             => 'ceramic mug',
				'product_short_description' => 'A sturdy 350ml mug.',
				'categories'                => array( 'Kitchen', 'Mugs' ),
			)
		);

		$this->assertTrue( $response['success'] );
		$this->assertSame( 'title', $response['data']['field'] );
		$this->assertSame( 'Ceramic Mug Deluxe', $response['data']['content'], 'Wrapping quotes and HTML are stripped from titles.' );

		$this->assertCount( 1, $this->calls );
		list( $prompt, $instruction, $field ) = $this->calls[0];
		$this->assertSame( 'title', $field );
		$this->assertStringContainsString( 'Generate a product title', $prompt );
		$this->assertStringContainsString( 'Product name / keywords: ceramic mug', $prompt );
		$this->assertStringContainsString( 'Categories: Kitchen, Mugs', $prompt );
		$this->assertStringContainsString( 'Existing summary: A sturdy 350ml mug.', $prompt );
		$this->assertStringContainsString( '(seed:', $prompt );
		$this->assertSame( ProductAI::default_system_instructions()['title'], $instruction );
	}

	/**
	 * Descriptions keep safe HTML; short descriptions become plain text.
	 *
	 * @return void
	 */
	public function test_description_output_sanitization() {
		$this->reply = '<p>Great mug.</p><script>alert(1)</script>';
		$response    = $this->generate( 'description', array( 'product_title' => 'Mug' ) );
		$this->assertSame( '<p>Great mug.</p>alert(1)', $response['data']['content'] );

		$this->reply = "<p>Great <em>mug</em>.</p>\nSecond line.";
		$response    = $this->generate( 'short_description', array( 'product_title' => 'Mug' ) );
		$this->assertSame( "Great mug.\nSecond line.", $response['data']['content'] );
	}

	/**
	 * The short description generator gets the long description as context, the others do not.
	 *
	 * @return void
	 */
	public function test_prompt_context_per_field() {
		$this->generate(
			'short_description',
			array(
				'product_title'       => 'Mug',
				'product_description' => '<p>Long <b>text</b></p>',
			)
		);
		$this->assertStringContainsString( 'Full description: Long text', $this->calls[0][0] );

		$this->generate(
			'description',
			array(
				'product_title'       => 'Mug',
				'product_description' => '<p>Long text</p>',
			)
		);
		$this->assertStringNotContainsString( 'Full description', $this->calls[1][0] );
	}

	/**
	 * Regenerating passes the previous suggestion to avoid.
	 *
	 * @return void
	 */
	public function test_regenerate_avoids_previous_suggestion() {
		$this->generate(
			'title',
			array(
				'product_title' => 'Mug',
				'previous'      => 'Old <b>title</b>',
			)
		);

		$this->assertStringContainsString( "<avoid>\nDo not repeat", $this->calls[0][0] );
		$this->assertStringContainsString( 'Old title', $this->calls[0][0] );
	}

	/**
	 * A custom instruction saved in settings replaces the default; a blank one falls back.
	 *
	 * @return void
	 */
	public function test_custom_system_instruction_overrides_default() {
		$this->set_settings( array( 'storesuite_ai_instruction_title' => 'Write titles in pirate speak.' ) );
		$this->generate( 'title', array( 'product_title' => 'Mug' ) );
		$this->assertSame( 'Write titles in pirate speak.', $this->calls[0][1] );

		$this->set_settings( array( 'storesuite_ai_instruction_title' => '   ' ) );
		$this->generate( 'title', array( 'product_title' => 'Mug' ) );
		$this->assertSame( ProductAI::default_system_instructions()['title'], $this->calls[1][1] );
	}

	/**
	 * Descriptions refuse to run without a title or keywords; titles do not.
	 *
	 * @return void
	 */
	public function test_descriptions_need_context_titles_do_not() {
		$response = $this->generate( 'description' );
		$this->assertFalse( $response['success'] );
		$this->assertSame( 'no_context', $response['data']['reason'] );
		$this->assertCount( 0, $this->calls );

		$response = $this->generate( 'title' );
		$this->assertTrue( $response['success'] );
	}

	/**
	 * A field switched off in settings is refused.
	 *
	 * @return void
	 */
	public function test_disabled_field_is_refused() {
		$this->set_settings( array( 'storesuite_ai_field_description' => 'no' ) );

		$response = $this->generate( 'description', array( 'product_title' => 'Mug' ) );

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'disabled', $response['data']['message'] );
		$this->assertCount( 0, $this->calls );
	}

	/**
	 * Unknown fields are rejected before any generation.
	 *
	 * @return void
	 */
	public function test_unknown_field_is_rejected() {
		$response = $this->generate( 'price', array( 'product_title' => 'Mug' ) );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Invalid field.', $response['data']['message'] );
		$this->assertCount( 0, $this->calls );
	}

	/**
	 * Generator errors and empty replies are reported, not inserted.
	 *
	 * @return void
	 */
	public function test_generator_error_and_empty_reply_are_reported() {
		$this->reply = new WP_Error( 'ai', 'Provider is down.' );
		$response    = $this->generate( 'title', array( 'product_title' => 'Mug' ) );
		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Provider is down.', $response['data']['message'] );

		$this->reply = '   ';
		$response    = $this->generate( 'title', array( 'product_title' => 'Mug' ) );
		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'No content was generated', $response['data']['message'] );
	}

	/**
	 * Only users who can manage WooCommerce may generate.
	 *
	 * @return void
	 */
	public function test_requires_manage_woocommerce() {
		$this->_setRole( 'customer' );

		$response = $this->generate( 'title', array( 'product_title' => 'Mug' ) );

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'permission', $response['data']['message'] );
		$this->assertCount( 0, $this->calls );
	}

	/**
	 * A bad nonce ends the request before the handler runs.
	 *
	 * @return void
	 */
	public function test_bad_nonce_is_rejected() {
		$_POST = array(
			'nonce'         => 'not-a-nonce',
			'field'         => 'title',
			'product_title' => 'Mug',
		);

		try {
			$this->_handleAjax( 'storesuite_generate_product_field' );
			$this->fail( 'Expected the request to die.' );
		} catch ( \WPAjaxDieStopException $e ) {
			$this->assertSame( '-1', $e->getMessage() );
		} catch ( \WPAjaxDieContinueException $e ) {
			$this->assertStringContainsString( '"success":false', $this->_last_response );
		}

		$this->assertCount( 0, $this->calls );
	}

	/**
	 * The bundle drafts title, then description, then short description, feeding each forward.
	 *
	 * @return void
	 */
	public function test_bundle_generates_three_fields_in_order() {
		$this->reply = function ( $prompt, $instruction, $field ) {
			return array(
				'title'             => 'Bundle Mug',
				'description'       => '<p>Long copy.</p>',
				'short_description' => 'Short copy.',
			)[ $field ];
		};

		$response = $this->do_ajax(
			'storesuite_generate_product_bundle',
			array( 'hint' => 'handmade mug' ),
			'_storesuite_ai_',
			'nonce'
		);

		$this->assertTrue( $response['success'] );
		$this->assertSame(
			array(
				'title'             => 'Bundle Mug',
				'description'       => '<p>Long copy.</p>',
				'short_description' => 'Short copy.',
			),
			$response['data']
		);
		$this->assertSame( array( 'title', 'description', 'short_description' ), array_column( $this->calls, 2 ) );
		$this->assertStringContainsString( 'Product name / keywords: handmade mug', $this->calls[0][0] );
		$this->assertStringContainsString( 'Product name / keywords: Bundle Mug', $this->calls[1][0] );
		$this->assertStringContainsString( 'Full description: Long copy.', $this->calls[2][0] );
	}

	/**
	 * The bundle can be switched off independently of the field buttons.
	 *
	 * @return void
	 */
	public function test_bundle_respects_its_toggle() {
		$this->set_settings( array( 'storesuite_ai_field_bundle' => 'no' ) );

		$response = $this->do_ajax(
			'storesuite_generate_product_bundle',
			array( 'hint' => 'handmade mug' ),
			'_storesuite_ai_',
			'nonce'
		);

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'unavailable', $response['data']['reason'] );
		$this->assertCount( 0, $this->calls );
	}

	/**
	 * The field buttons render only for supported, enabled fields.
	 *
	 * @return void
	 */
	public function test_field_buttons_render_for_enabled_fields_only() {
		$this->set_settings( array( 'storesuite_ai_field_short_description' => 'no' ) );
		$ai = new ProductAI();

		$render = function ( $field ) use ( $ai ) {
			ob_start();
			$ai->render_field_button( $field );
			return ob_get_clean();
		};

		$this->assertStringContainsString( 'data-field="title"', $render( 'title' ) );
		$this->assertStringContainsString( 'data-field="description"', $render( 'description' ) );
		$this->assertSame( '', $render( 'short_description' ), 'A field switched off renders no button.' );
		$this->assertSame( '', $render( 'featured' ), 'Non-text fields render no button.' );
	}
}
