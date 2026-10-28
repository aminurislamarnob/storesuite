<?php
/**
 * Tests for the shared AI endpoint serving the term SEO fields.
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test\Integration;

use PluginizeLab\StoreSuite\Integration\YoastSeoTermIntegration;
use PluginizeLab\StoreSuite\Product\ProductAI;
use PluginizeLab\StoreSuite\Test\StoreSuiteAjaxTestCase;

/**
 * @group storesuite-integration
 * @group storesuite-ajax
 */
class YoastSeoTermAiAjaxTest extends StoreSuiteAjaxTestCase {

	/**
	 * Every generator call made during the current test: [ prompt, instruction, field ].
	 *
	 * @var array<int, array{0: string, 1: string, 2: string}>
	 */
	private $calls = array();

	/**
	 * Set up the test fixture.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->calls = array();

		add_filter( 'storesuite_ai_text_generator', array( $this, 'fake_generator' ) );
		ProductAI::reset_ai_support_cache();

		new class() extends YoastSeoTermIntegration {
			protected function is_yoast_active(): bool {
				return true;
			}

			protected function get_yoast_option( string $key, $fallback = null ) {
				return in_array( $key, array( 'opengraph', 'twitter' ), true ) ? true : $fallback;
			}
		};

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

			return '  "Ceramic Mugs | Shop"  ';
		};
	}

	/**
	 * A term field is generated with no product id, title or short description
	 * posted, and the posted keyphrase and term name reach the prompt.
	 *
	 * @return void
	 */
	public function test_term_field_generates_without_product_context() {
		$response = $this->do_ajax(
			'storesuite_generate_product_field',
			array(
				'field'                         => 'yoast_term_title',
				'product_category_name'         => 'Mugs',
				'storesuite_yoast_term_focuskw' => 'ceramic mugs',
			),
			'_storesuite_ai_',
			'nonce'
		);

		$this->assertTrue( $response['success'], wp_json_encode( $response ) );
		$this->assertSame( 'yoast_term_title', $response['data']['field'] );
		$this->assertSame( 'Ceramic Mugs | Shop', $response['data']['content'] );
		$this->assertCount( 1, $this->calls );
		$this->assertStringContainsString( 'Focus keyphrase: ceramic mugs', $this->calls[0][0] );
		$this->assertStringContainsString( 'Category name: Mugs', $this->calls[0][0] );
		$this->assertSame( 'yoast_term_title', $this->calls[0][2] );
	}

	/**
	 * The shared Yoast toggle switches the term fields off as well.
	 *
	 * @return void
	 */
	public function test_term_field_follows_the_shared_yoast_toggle() {
		update_option( 'storesuite_settings', array( 'storesuite_ai_field_yoast_seo' => 'no' ) );

		$response = $this->do_ajax(
			'storesuite_generate_product_field',
			array( 'field' => 'yoast_term_metadesc' ),
			'_storesuite_ai_',
			'nonce'
		);

		$this->assertFalse( $response['success'] );
		$this->assertCount( 0, $this->calls );
	}
}
