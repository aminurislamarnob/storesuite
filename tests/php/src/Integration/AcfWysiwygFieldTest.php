<?php
/**
 * Tests for the ACF WYSIWYG type on the product form.
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test\Integration;

use PluginizeLab\StoreSuite\Integration\Acf\FieldSanitizer;
use PluginizeLab\StoreSuite\Test\StoreSuiteAjaxTestCase;

/**
 * @covers \PluginizeLab\StoreSuite\Integration\Acf\FieldSanitizer
 * @covers \PluginizeLab\StoreSuite\Integration\Acf\FieldRenderer
 * @group storesuite-acf
 * @group storesuite-ajax
 */
class AcfWysiwygFieldTest extends StoreSuiteAjaxTestCase {

	use AcfTestHelpers;

	const GROUP_KEY = 'group_storesuite_wysiwyg';

	/**
	 * Tear down the test fixture.
	 *
	 * @return void
	 */
	public function tear_down() {
		$this->remove_acf_field_groups();
		parent::tear_down();
	}

	/**
	 * Register a group with two editors of different configurations.
	 *
	 * @return void
	 */
	private function register_wysiwyg_group() {
		$this->register_acf_field_group(
			array(
				'key'      => self::GROUP_KEY,
				'title'    => 'Rich text',
				'fields'   => array(
					array(
						'key'          => 'field_ss_wys_full',
						'name'         => 'ss_wys_full',
						'label'        => 'Story',
						'type'         => 'wysiwyg',
						'toolbar'      => 'full',
						'tabs'         => 'all',
						'media_upload' => 1,
					),
					array(
						'key'          => 'field_ss_wys_basic',
						'name'         => 'ss_wys_basic',
						'label'        => 'Footnote',
						'type'         => 'wysiwyg',
						'toolbar'      => 'basic',
						'tabs'         => 'visual',
						'media_upload' => 0,
					),
				),
				'location' => $this->acf_product_location(),
			)
		);
	}

	public function test_sanitizer_keeps_post_safe_html_and_strips_the_rest() {
		$sanitizer = new FieldSanitizer();
		$field     = array( 'type' => 'wysiwyg' );

		$this->assertSame( '<p>Hello <strong>world</strong></p>', $sanitizer->sanitize( $field, '<p>Hello <strong>world</strong></p>' ) );
		$this->assertSame( '<p>Hi</p>alert(1)', $sanitizer->sanitize( $field, '<p onclick="x()">Hi</p><script>alert(1)</script>' ) );
		$this->assertSame( '<a href="https://example.com">x</a>', $sanitizer->sanitize( $field, '<a href="https://example.com" onmouseover="y()">x</a>' ) );
		$this->assertSame( '', $sanitizer->sanitize( $field, array( '<p>x</p>' ) ) );
	}

	public function test_editors_render_with_settings_mapped_from_acf() {
		$this->require_acf();
		$this->register_wysiwyg_group();

		// wp_editor() prints its scripts in the footer; render inside the
		// dashboard's usual non-admin context.
		$html = $this->render_acf_cards( 0 );

		// Two independent editors, ids derived from the field keys.
		$this->assertStringContainsString( 'id="wp-storesuite_acf_field_ss_wys_full-wrap"', $html );
		$this->assertStringContainsString( 'id="wp-storesuite_acf_field_ss_wys_basic-wrap"', $html );
		$this->assertStringContainsString( 'name="storesuite_acf[field_ss_wys_full]"', $html );
		$this->assertStringContainsString( 'name="storesuite_acf[field_ss_wys_basic]"', $html );

		// TinyMCE chrome only renders for rich-edit capable user agents, which
		// the CLI is not; the quicktags (Text tab) toolbar is what tabs maps to here.
		$this->assertStringContainsString( 'id="qt_storesuite_acf_field_ss_wys_full_toolbar"', $html, 'tabs=all keeps the Text tab.' );
		$this->assertStringNotContainsString( 'id="qt_storesuite_acf_field_ss_wys_basic_toolbar"', $html, 'tabs=visual drops the Text tab.' );
	}

	public function test_edit_form_prefills_stored_html() {
		$this->require_acf();
		$this->register_wysiwyg_group();

		$product_id = self::factory()->product->create()->get_id();
		update_field( 'field_ss_wys_full', '<p>Made in <em>Portugal</em></p>', $product_id );

		$html = $this->render_acf_cards( $product_id );

		$this->assertStringContainsString( 'Made in <em>Portugal</em>', $html );
	}

	public function test_content_round_trips_and_disallowed_html_is_stripped() {
		$this->require_acf();
		$this->register_wysiwyg_group();

		$product_id = self::factory()->product->create()->get_id();
		$this->_setRole( 'shop_manager' );

		$response = $this->do_ajax(
			'storesuite_edit_product_action',
			array(
				'product_id'     => $product_id,
				'product_title'  => get_the_title( $product_id ),
				'storesuite_acf' => array(
					'field_ss_wys_full'  => '<h2>Story</h2><p>Bold <strong>text</strong><script>alert(1)</script></p>',
					'field_ss_wys_basic' => '<p>Footnote</p>',
				),
			),
			'_storesuite_edit_product_',
			'storesuite_edit_product_nonce'
		);

		$this->assertTrue( $response['success'], wp_json_encode( $response ) );
		$this->assertSame( '<h2>Story</h2><p>Bold <strong>text</strong>alert(1)</p>', get_post_meta( $product_id, 'ss_wys_full', true ) );
		$this->assertSame( '<p>Footnote</p>', get_post_meta( $product_id, 'ss_wys_basic', true ) );
		$this->assertSame( 'field_ss_wys_full', get_post_meta( $product_id, '_ss_wys_full', true ) );
	}
}
