<?php
/**
 * Tests for the simple ACF input types on the product form.
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test\Integration;

use PluginizeLab\StoreSuite\Integration\Acf\FieldSanitizer;
use PluginizeLab\StoreSuite\Test\StoreSuiteAjaxTestCase;

/**
 * @covers \PluginizeLab\StoreSuite\Integration\Acf\AcfIntegration
 * @covers \PluginizeLab\StoreSuite\Integration\Acf\FieldRenderer
 * @covers \PluginizeLab\StoreSuite\Integration\Acf\FieldSanitizer
 * @group storesuite-acf
 * @group storesuite-ajax
 */
class AcfSimpleFieldsTest extends StoreSuiteAjaxTestCase {

	use AcfTestHelpers;

	const GROUP_KEY = 'group_storesuite_simple';

	/**
	 * Field definitions keyed by type: key, name and type-specific settings.
	 *
	 * @var array<string, array>
	 */
	private static $fields = array(
		'textarea'     => array(
			'key'         => 'field_ss_simple_textarea',
			'name'        => 'ss_simple_textarea',
			'rows'        => 3,
			'maxlength'   => 200,
			'placeholder' => 'Tell a story',
		),
		'number'       => array(
			'key'     => 'field_ss_simple_number',
			'name'    => 'ss_simple_number',
			'min'     => 0,
			'max'     => 10000,
			'step'    => '0.5',
			'prepend' => '$',
			'append'  => 'per kg',
		),
		'range'        => array(
			'key'  => 'field_ss_simple_range',
			'name' => 'ss_simple_range',
			'min'  => 10,
			'max'  => 50,
			'step' => 5,
		),
		'email'        => array(
			'key'     => 'field_ss_simple_email',
			'name'    => 'ss_simple_email',
			'prepend' => '@',
		),
		'url'          => array(
			'key'         => 'field_ss_simple_url',
			'name'        => 'ss_simple_url',
			'placeholder' => 'https://',
		),
		'password'     => array(
			'key'  => 'field_ss_simple_password',
			'name' => 'ss_simple_password',
		),
		'color_picker' => array(
			'key'  => 'field_ss_simple_color',
			'name' => 'ss_simple_color',
		),
		'message'      => array(
			'key'       => 'field_ss_simple_message',
			'name'      => 'ss_simple_message',
			'message'   => "Keep it <strong>short</strong>.\n<script>alert(1)</script>",
			'new_lines' => 'br',
			'esc_html'  => 0,
		),
		'separator'    => array(
			'key'  => 'field_ss_simple_separator',
			'name' => 'ss_simple_separator',
		),
	);

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
	 * Register a group containing every simple type, located on products.
	 *
	 * @return void
	 */
	private function register_simple_group() {
		$fields = array();

		foreach ( self::$fields as $type => $settings ) {
			$fields[] = array_merge(
				array(
					'label' => ucfirst( str_replace( '_', ' ', $type ) ) . ' field',
					'type'  => $type,
				),
				$settings
			);
		}

		$this->register_acf_field_group(
			array(
				'key'      => self::GROUP_KEY,
				'title'    => 'Simple Types',
				'fields'   => $fields,
				'location' => $this->acf_product_location(),
			)
		);
	}

	/**
	 * Dispatch the edit-product AJAX action as a shop manager.
	 *
	 * @param int   $product_id Product to edit.
	 * @param array $acf        storesuite_acf values keyed by field key.
	 * @return array Decoded response.
	 */
	private function edit_with_acf( $product_id, array $acf ) {
		$this->_setRole( 'shop_manager' );

		return $this->do_ajax(
			'storesuite_edit_product_action',
			array(
				'product_id'     => $product_id,
				'product_title'  => get_the_title( $product_id ),
				'storesuite_acf' => $acf,
			),
			'_storesuite_edit_product_',
			'storesuite_edit_product_nonce'
		);
	}

	// -------------------------------------------------------------------------
	// Sanitiser branches (no ACF needed).
	// -------------------------------------------------------------------------

	/**
	 * @dataProvider sanitizer_provider
	 */
	public function test_sanitizer_branches( $type, $raw, $expected ) {
		$sanitizer = new FieldSanitizer();

		$this->assertSame( $expected, $sanitizer->sanitize( array( 'type' => $type ), $raw ) );
	}

	public function sanitizer_provider() {
		return array(
			'textarea keeps line breaks, strips tags' => array( 'textarea', "Line one\n<b>Line</b> two", "Line one\nLine two" ),
			'textarea array becomes empty'            => array( 'textarea', array( 'x' ), '' ),
			'number integer'                          => array( 'number', '42', '42' ),
			'number decimal'                          => array( 'number', ' 4.5 ', '4.5' ),
			'number thousands separator'              => array( 'number', '1,250', '1250' ),
			'number blank allowed'                    => array( 'number', '', '' ),
			'number rejects text'                     => array( 'number', 'twelve', null ),
			'number rejects array'                    => array( 'number', array( 1 ), '' ),
			'range numeric'                           => array( 'range', '25', '25' ),
			'range rejects text'                      => array( 'range', '25px', null ),
			'email valid'                             => array( 'email', 'Shop@Example.com', 'Shop@Example.com' ),
			'email invalid becomes empty'             => array( 'email', 'not an email', '' ),
			'url valid'                               => array( 'url', ' https://example.com/a?b=1 ', 'https://example.com/a?b=1' ),
			'url strips javascript'                   => array( 'url', 'javascript:alert(1)', '' ),
			'password blank keeps existing'           => array( 'password', '', null ),
			'password stored verbatim'                => array( 'password', 'p<ss> w0rd&', 'p<ss> w0rd&' ),
			'password strips line breaks'             => array( 'password', "se\ncret\r", 'secret' ),
			'color six digit'                         => array( 'color_picker', '#FFAA00', '#ffaa00' ),
			'color three digit'                       => array( 'color_picker', '#fa0', '#fa0' ),
			'color blank clears'                      => array( 'color_picker', '', '' ),
			'color rgba rejected'                     => array( 'color_picker', 'rgba(0,0,0,0.5)', null ),
			'color missing hash rejected'             => array( 'color_picker', 'ffaa00', null ),
			'message never saved'                     => array( 'message', 'anything', null ),
			'separator never saved'                   => array( 'separator', 'anything', null ),
			'unsupported never saved'                 => array( 'repeater', 'anything', null ),
		);
	}

	// -------------------------------------------------------------------------
	// Rendering.
	// -------------------------------------------------------------------------

	public function test_each_type_renders_with_its_attributes() {
		$this->require_acf();
		$this->register_simple_group();

		$html = $this->render_acf_cards( 0 );

		$this->assertMatchesRegularExpression( '/<textarea[^>]*name="storesuite_acf\[field_ss_simple_textarea\]"[^>]*rows="3"[^>]*placeholder="Tell a story"[^>]*maxlength="200"/s', $html );
		$this->assertMatchesRegularExpression( '/<input[^>]*type="number"[^>]*name="storesuite_acf\[field_ss_simple_number\]"[^>]*step="0.5"[^>]*min="0"[^>]*max="10000"/s', $html );
		$this->assertStringContainsString( '<span class="storesuite-acf-input-addon">$</span>', $html );
		$this->assertStringContainsString( '<span class="storesuite-acf-input-addon">per kg</span>', $html );
		$this->assertMatchesRegularExpression( '/<input[^>]*type="range"[^>]*name="storesuite_acf\[field_ss_simple_range\]"[^>]*value="10"[^>]*min="10"[^>]*max="50"[^>]*step="5"/s', $html );
		$this->assertStringContainsString( '<output class="storesuite-acf-range-output" for="storesuite_acf_field_ss_simple_range">10</output>', $html );
		$this->assertMatchesRegularExpression( '/<input[^>]*type="email"[^>]*name="storesuite_acf\[field_ss_simple_email\]"/s', $html );
		$this->assertStringContainsString( '<span class="storesuite-acf-input-addon">@</span>', $html );
		$this->assertMatchesRegularExpression( '/<input[^>]*type="url"[^>]*name="storesuite_acf\[field_ss_simple_url\]"[^>]*placeholder="https:\/\/"/s', $html );
		$this->assertMatchesRegularExpression( '/<input[^>]*type="password"[^>]*name="storesuite_acf\[field_ss_simple_password\]"[^>]*value=""/s', $html );
		$this->assertMatchesRegularExpression( '/<input[^>]*type="color"[^>]*data-target="#storesuite_acf_field_ss_simple_color"/s', $html );
		$this->assertMatchesRegularExpression( '/<input[^>]*type="text"[^>]*class="storesuite-form-control storesuite-acf-color-text"[^>]*name="storesuite_acf\[field_ss_simple_color\]"/s', $html );
		$this->assertStringNotContainsString( 'storesuite-acf-field-unsupported-note', $html );
	}

	public function test_message_and_separator_render_in_place_without_inputs() {
		$this->require_acf();
		$this->register_simple_group();

		$html = $this->render_acf_cards( 0 );

		$this->assertStringContainsString( '<div class="storesuite-acf-message">Keep it <strong>short</strong>.<br />', $html );
		$this->assertStringNotContainsString( '<script>', $html, 'Message content goes through post-safe KSES.' );
		$this->assertStringContainsString( '<hr class="storesuite-acf-separator">', $html );
		$this->assertStringNotContainsString( 'storesuite_acf[field_ss_simple_message]', $html );
		$this->assertStringNotContainsString( 'storesuite_acf[field_ss_simple_separator]', $html );

		// Order is preserved: the message sits between the colour picker and the separator.
		$color     = strpos( $html, 'field_ss_simple_color' );
		$message   = strpos( $html, 'storesuite-acf-message' );
		$separator = strpos( $html, 'storesuite-acf-separator' );
		$this->assertTrue( $color < $message && $message < $separator );
	}

	public function test_message_honours_the_escape_html_option() {
		$this->require_acf();
		$this->register_acf_field_group(
			array(
				'key'      => self::GROUP_KEY,
				'title'    => 'Escaped',
				'fields'   => array(
					array(
						'key'       => 'field_ss_simple_escaped',
						'name'      => 'ss_simple_escaped',
						'label'     => 'Escaped',
						'type'      => 'message',
						'message'   => 'Use <em>markup</em>',
						'esc_html'  => 1,
						'new_lines' => '',
					),
				),
				'location' => $this->acf_product_location(),
			)
		);

		$html = $this->render_acf_cards( 0 );

		$this->assertStringContainsString( 'Use &lt;em&gt;markup&lt;/em&gt;', $html );
	}

	public function test_edit_form_prefills_every_type_except_password() {
		$this->require_acf();
		$this->register_simple_group();

		$product_id = self::factory()->product->create()->get_id();
		update_field( 'field_ss_simple_textarea', "Line 1\nLine 2", $product_id );
		update_field( 'field_ss_simple_number', '12.5', $product_id );
		update_field( 'field_ss_simple_range', '35', $product_id );
		update_field( 'field_ss_simple_email', 'shop@example.com', $product_id );
		update_field( 'field_ss_simple_url', 'https://example.com', $product_id );
		update_field( 'field_ss_simple_password', 'top-secret', $product_id );
		update_field( 'field_ss_simple_color', '#ff8800', $product_id );

		$html = $this->render_acf_cards( $product_id );

		$this->assertStringContainsString( ">Line 1\nLine 2</textarea>", $html );
		$this->assertStringContainsString( 'value="12.5"', $html );
		$this->assertMatchesRegularExpression( '/type="range"[^>]*value="35"/s', $html );
		$this->assertStringContainsString( '>35</output>', $html );
		$this->assertStringContainsString( 'value="shop@example.com"', $html );
		$this->assertStringContainsString( 'value="https://example.com"', $html );
		$this->assertMatchesRegularExpression( '/type="color"[^>]*value="#ff8800"/s', $html );
		$this->assertMatchesRegularExpression( '/storesuite-acf-color-text"[^>]*value="#ff8800"/s', $html );
		$this->assertStringNotContainsString( 'top-secret', $html, 'The stored password is never sent to the browser.' );
	}

	// -------------------------------------------------------------------------
	// Saving.
	// -------------------------------------------------------------------------

	public function test_every_data_type_round_trips_through_the_edit_form() {
		$this->require_acf();
		$this->register_simple_group();

		$product_id = self::factory()->product->create()->get_id();

		$response = $this->edit_with_acf(
			$product_id,
			array(
				'field_ss_simple_textarea'  => "First\nSecond",
				'field_ss_simple_number'    => '1,024.5',
				'field_ss_simple_range'     => '45',
				'field_ss_simple_email'     => 'orders@example.com',
				'field_ss_simple_url'       => 'https://example.com/path',
				'field_ss_simple_password'  => 'hunter2',
				'field_ss_simple_color'     => '#ABCDEF',
				'field_ss_simple_message'   => 'ignored',
				'field_ss_simple_separator' => 'ignored',
			)
		);

		$this->assertTrue( $response['success'], wp_json_encode( $response ) );

		$this->assertSame( "First\nSecond", get_field( 'field_ss_simple_textarea', $product_id, false ) );
		$this->assertSame( '1024.5', get_field( 'field_ss_simple_number', $product_id, false ) );
		$this->assertSame( '45', get_field( 'field_ss_simple_range', $product_id, false ) );
		$this->assertSame( 'orders@example.com', get_field( 'field_ss_simple_email', $product_id, false ) );
		$this->assertSame( 'https://example.com/path', get_field( 'field_ss_simple_url', $product_id, false ) );
		$this->assertSame( 'hunter2', get_field( 'field_ss_simple_password', $product_id, false ) );
		$this->assertSame( '#abcdef', get_field( 'field_ss_simple_color', $product_id, false ) );

		foreach ( self::$fields as $type => $settings ) {
			if ( in_array( $type, array( 'message', 'separator' ), true ) ) {
				continue;
			}
			$this->assertSame( $settings['key'], get_post_meta( $product_id, '_' . $settings['name'], true ), 'ACF reference meta is written for ' . $settings['name'] );
		}
		$this->assertSame( array(), get_post_meta( $product_id, 'ss_simple_message' ), 'Message is never written.' );
		$this->assertSame( array(), get_post_meta( $product_id, 'ss_simple_separator' ), 'Separator is never written.' );
	}

	public function test_password_blank_keeps_the_stored_value_and_non_blank_replaces_it() {
		$this->require_acf();
		$this->register_simple_group();

		$product_id = self::factory()->product->create()->get_id();
		update_field( 'field_ss_simple_password', 'original', $product_id );

		$response = $this->edit_with_acf( $product_id, array( 'field_ss_simple_password' => '' ) );
		$this->assertTrue( $response['success'], wp_json_encode( $response ) );
		$this->assertSame( 'original', get_post_meta( $product_id, 'ss_simple_password', true ) );

		$response = $this->edit_with_acf( $product_id, array( 'field_ss_simple_password' => 'replaced' ) );
		$this->assertTrue( $response['success'], wp_json_encode( $response ) );
		$this->assertSame( 'replaced', get_post_meta( $product_id, 'ss_simple_password', true ) );
	}

	public function test_invalid_number_is_refused_and_stored_values_survive() {
		$this->require_acf();
		$this->register_simple_group();

		$product_id = self::factory()->product->create()->get_id();
		update_field( 'field_ss_simple_number', '7', $product_id );
		update_field( 'field_ss_simple_color', '#123456', $product_id );

		$response = $this->edit_with_acf(
			$product_id,
			array(
				'field_ss_simple_number' => 'seven',
				'field_ss_simple_color'  => 'rgba(1,2,3,0.4)',
			)
		);

		// The sanitiser refuses both; ACF has a message for the number, the
		// colour is simply not written.
		$this->assertFalse( $response['success'] );
		$this->assertSame( array( 'Number field: Value must be a number' ), $response['data']['errors'] );
		$this->assertSame( '7', get_post_meta( $product_id, 'ss_simple_number', true ) );
		$this->assertSame( '#123456', get_post_meta( $product_id, 'ss_simple_color', true ) );
	}

	public function test_invalid_colour_alone_is_dropped_without_blocking_the_save() {
		$this->require_acf();
		$this->register_simple_group();

		$product_id = self::factory()->product->create()->get_id();
		update_field( 'field_ss_simple_color', '#123456', $product_id );

		$response = $this->edit_with_acf(
			$product_id,
			array(
				'field_ss_simple_color' => 'rgba(1,2,3,0.4)',
				'field_ss_simple_email' => 'nope',
			)
		);

		$this->assertTrue( $response['success'], wp_json_encode( $response ) );
		$this->assertSame( '#123456', get_post_meta( $product_id, 'ss_simple_color', true ), 'Opacity values are never stored.' );
		$this->assertSame( '', get_post_meta( $product_id, 'ss_simple_email', true ), 'An invalid email is stored as empty, as sanitize_email() does.' );
	}
}
