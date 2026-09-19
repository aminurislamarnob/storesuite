<?php
/**
 * Tests for the ACF choice types on the product form.
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
class AcfChoiceFieldsTest extends StoreSuiteAjaxTestCase {

	use AcfTestHelpers;

	const GROUP_KEY = 'group_storesuite_choice';

	/**
	 * Shared choices for every choice field.
	 *
	 * @var array<string, string>
	 */
	private static $choices = array(
		'red'   => 'Red',
		'green' => 'Green',
		'blue'  => 'Blue',
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
	 * Register a group with one field of every choice type, located on products.
	 *
	 * @param array $overrides Per-field overrides keyed by type.
	 * @return void
	 */
	private function register_choice_group( array $overrides = array() ) {
		$defaults = array(
			'select'       => array(
				'key'        => 'field_ss_choice_select',
				'name'       => 'ss_choice_select',
				'label'      => 'Colour',
				'type'       => 'select',
				'choices'    => self::$choices,
				'allow_null' => 1,
			),
			'multi'        => array(
				'key'      => 'field_ss_choice_multi',
				'name'     => 'ss_choice_multi',
				'label'    => 'Colours',
				'type'     => 'select',
				'choices'  => self::$choices,
				'multiple' => 1,
			),
			'checkbox'     => array(
				'key'     => 'field_ss_choice_checkbox',
				'name'    => 'ss_choice_checkbox',
				'label'   => 'Features',
				'type'    => 'checkbox',
				'choices' => self::$choices,
				'layout'  => 'horizontal',
			),
			'radio'        => array(
				'key'     => 'field_ss_choice_radio',
				'name'    => 'ss_choice_radio',
				'label'   => 'Finish',
				'type'    => 'radio',
				'choices' => self::$choices,
			),
			'button_group' => array(
				'key'           => 'field_ss_choice_buttons',
				'name'          => 'ss_choice_buttons',
				'label'         => 'Size',
				'type'          => 'button_group',
				'choices'       => self::$choices,
				'default_value' => 'green',
			),
			'true_false'   => array(
				'key'     => 'field_ss_choice_switch',
				'name'    => 'ss_choice_switch',
				'label'   => 'Limited edition',
				'type'    => 'true_false',
				'message' => 'Show the limited edition badge',
			),
		);

		$fields = array();
		foreach ( $defaults as $type => $field ) {
			$fields[] = isset( $overrides[ $type ] ) ? array_merge( $field, $overrides[ $type ] ) : $field;
		}

		$this->register_acf_field_group(
			array(
				'key'      => self::GROUP_KEY,
				'title'    => 'Choices',
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
	public function test_sanitizer_branches( array $field, $raw, $expected ) {
		$sanitizer = new FieldSanitizer();

		$this->assertSame( $expected, $sanitizer->sanitize( array_merge( array( 'choices' => self::$choices ), $field ), $raw ) );
	}

	public function sanitizer_provider() {
		$select   = array( 'type' => 'select' );
		$nullable = array(
			'type'       => 'select',
			'allow_null' => 1,
		);
		$multi    = array(
			'type'     => 'select',
			'multiple' => 1,
		);
		$checkbox = array( 'type' => 'checkbox' );
		$radio    = array( 'type' => 'radio' );
		$buttons  = array( 'type' => 'button_group' );
		$switch   = array( 'type' => 'true_false' );

		return array(
			'select in list'                     => array( $select, 'red', 'red' ),
			'select outside list rejected'       => array( $select, 'purple', null ),
			'select empty without allow_null'    => array( $select, '', null ),
			'select empty with allow_null'       => array( $nullable, '', '' ),
			'select array rejected'              => array( $select, array( 'red' ), null ),
			'multi keeps valid values in order'  => array( $multi, array( 'blue', 'purple', 'red' ), array( 'blue', 'red' ) ),
			'multi drops duplicates'             => array( $multi, array( 'red', 'red' ), array( 'red' ) ),
			'multi sentinel clears'              => array( $multi, '', '' ),
			'multi all invalid clears'           => array( $multi, array( 'purple' ), '' ),
			'checkbox valid'                     => array( $checkbox, array( 'green' ), array( 'green' ) ),
			'checkbox sentinel clears'           => array( $checkbox, '', '' ),
			'checkbox nested arrays dropped'     => array( $checkbox, array( array( 'red' ), 'blue' ), array( 'blue' ) ),
			'radio valid'                        => array( $radio, 'blue', 'blue' ),
			'radio invalid rejected'             => array( $radio, 'x', null ),
			'radio empty without allow_null'     => array( $radio, '', null ),
			'button group valid'                 => array( $buttons, 'green', 'green' ),
			'button group invalid rejected'      => array( $buttons, '1', null ),
			'true_false on'                      => array( $switch, '1', '1' ),
			'true_false off'                     => array( $switch, '0', '0' ),
			'true_false anything else is off'    => array( $switch, 'yes', '0' ),
			'integer choice keys compare as str' => array(
				array(
					'type'    => 'radio',
					'choices' => array(
						1 => 'One',
						2 => 'Two',
					),
				),
				'2',
				'2',
			),
		);
	}

	// -------------------------------------------------------------------------
	// Rendering.
	// -------------------------------------------------------------------------

	public function test_add_form_renders_every_choice_type_with_defaults() {
		$this->require_acf();
		$this->register_choice_group();

		$html = $this->render_acf_cards( 0 );

		// Single select with the allow_null empty option.
		$this->assertMatchesRegularExpression( '/<select[^>]*name="storesuite_acf\[field_ss_choice_select\]"[^>]*>\s*<option value="">/s', $html );
		$this->assertStringContainsString( '<option value="red" >Red</option>', $html );

		// Multi select: sentinel + selectWoo class + [] name.
		$this->assertStringContainsString( '<input type="hidden" name="storesuite_acf[field_ss_choice_multi]" value="">', $html );
		$this->assertMatchesRegularExpression( '/<select[^>]*class="storesuite-form-control storesuite-select2"[^>]*name="storesuite_acf\[field_ss_choice_multi\]\[\]"[^>]*multiple/s', $html );

		// Checkbox: sentinel, one input per choice, horizontal layout.
		$this->assertStringContainsString( '<input type="hidden" name="storesuite_acf[field_ss_choice_checkbox]" value="">', $html );
		$this->assertSame( 3, preg_match_all( '/type="checkbox"[^>]*name="storesuite_acf\[field_ss_choice_checkbox\]\[\]"/', $html ) );
		$this->assertStringContainsString( 'storesuite-acf-choices-horizontal', $html );

		// Radio and button group.
		$this->assertSame( 3, preg_match_all( '/type="radio"[^>]*name="storesuite_acf\[field_ss_choice_radio\]"/', $html ) );
		$this->assertSame( 3, preg_match_all( '/type="radio"[^>]*class="storesuite-acf-button-group-input"[^>]*name="storesuite_acf\[field_ss_choice_buttons\]"/', $html ) );
		$this->assertMatchesRegularExpression( '/name="storesuite_acf\[field_ss_choice_buttons\]"[^>]*value="green"[^>]*checked/s', $html, 'default_value pre-selects the button group on the add form.' );

		// True/false: sentinel + switch.
		$this->assertStringContainsString( '<input type="hidden" name="storesuite_acf[field_ss_choice_switch]" value="0">', $html );
		$this->assertMatchesRegularExpression( '/<div class="storesuite-form-group storesuite-form-switch storesuite-acf-switch">\s*<input type="checkbox"[^>]*name="storesuite_acf\[field_ss_choice_switch\]" value="1" >/s', $html );
		$this->assertStringContainsString( 'Show the limited edition badge', $html );
		$this->assertStringNotContainsString( 'storesuite-acf-field-unsupported-note', $html );
	}

	public function test_edit_form_reflects_stored_values() {
		$this->require_acf();
		$this->register_choice_group();

		$product_id = self::factory()->product->create()->get_id();
		update_field( 'field_ss_choice_select', 'blue', $product_id );
		update_field( 'field_ss_choice_multi', array( 'red', 'blue' ), $product_id );
		update_field( 'field_ss_choice_checkbox', array( 'green' ), $product_id );
		update_field( 'field_ss_choice_radio', 'red', $product_id );
		update_field( 'field_ss_choice_buttons', 'blue', $product_id );
		update_field( 'field_ss_choice_switch', 1, $product_id );

		$html = $this->render_acf_cards( $product_id );

		$this->assertMatchesRegularExpression( '/name="storesuite_acf\[field_ss_choice_select\]".*?<option value="blue"\s+selected=\'selected\'>/s', $html );
		preg_match( '/<select[^>]*name="storesuite_acf\[field_ss_choice_multi\]\[\]".*?<\/select>/s', $html, $multi );
		$this->assertSame( 2, substr_count( $multi[0], "selected='selected'" ) );
		$this->assertMatchesRegularExpression( '/<option value="red"\s+selected=\'selected\'>/', $multi[0] );
		$this->assertMatchesRegularExpression( '/<option value="blue"\s+selected=\'selected\'>/', $multi[0] );
		$this->assertMatchesRegularExpression( '/name="storesuite_acf\[field_ss_choice_checkbox\]\[\]"[^>]*value="green"[^>]*checked/s', $html );
		$this->assertMatchesRegularExpression( '/name="storesuite_acf\[field_ss_choice_radio\]"[^>]*value="red"[^>]*checked/s', $html );
		$this->assertMatchesRegularExpression( '/name="storesuite_acf\[field_ss_choice_buttons\]"[^>]*value="blue"[^>]*checked/s', $html );
		$this->assertMatchesRegularExpression( '/name="storesuite_acf\[field_ss_choice_switch\]" value="1"\s+checked/s', $html );
	}

	// -------------------------------------------------------------------------
	// Saving.
	// -------------------------------------------------------------------------

	public function test_every_choice_type_round_trips() {
		$this->require_acf();
		$this->register_choice_group();

		$product_id = self::factory()->product->create()->get_id();

		$response = $this->edit_with_acf(
			$product_id,
			array(
				'field_ss_choice_select'   => 'green',
				'field_ss_choice_multi'    => array( 'blue', 'red' ),
				'field_ss_choice_checkbox' => array( 'red', 'green' ),
				'field_ss_choice_radio'    => 'blue',
				'field_ss_choice_buttons'  => 'red',
				'field_ss_choice_switch'   => '1',
			)
		);

		$this->assertTrue( $response['success'], wp_json_encode( $response ) );
		$this->assertSame( 'green', get_field( 'field_ss_choice_select', $product_id ) );
		$this->assertSame( array( 'blue', 'red' ), get_field( 'field_ss_choice_multi', $product_id ) );
		$this->assertSame( array( 'red', 'green' ), get_field( 'field_ss_choice_checkbox', $product_id ) );
		$this->assertSame( 'blue', get_field( 'field_ss_choice_radio', $product_id ) );
		$this->assertSame( 'red', get_field( 'field_ss_choice_buttons', $product_id ) );
		$this->assertTrue( get_field( 'field_ss_choice_switch', $product_id ) );
		$this->assertSame( '1', get_post_meta( $product_id, 'ss_choice_switch', true ) );
		$this->assertSame( 'field_ss_choice_multi', get_post_meta( $product_id, '_ss_choice_multi', true ) );
	}

	public function test_clearing_every_choice_clears_the_stored_value() {
		$this->require_acf();
		$this->register_choice_group();

		$product_id = self::factory()->product->create()->get_id();
		update_field( 'field_ss_choice_multi', array( 'red' ), $product_id );
		update_field( 'field_ss_choice_checkbox', array( 'red', 'blue' ), $product_id );
		update_field( 'field_ss_choice_select', 'red', $product_id );
		update_field( 'field_ss_choice_switch', 1, $product_id );

		// What the browser sends when nothing is picked: the sentinels only.
		$response = $this->edit_with_acf(
			$product_id,
			array(
				'field_ss_choice_multi'    => '',
				'field_ss_choice_checkbox' => '',
				'field_ss_choice_select'   => '',
				'field_ss_choice_switch'   => '0',
			)
		);

		$this->assertTrue( $response['success'], wp_json_encode( $response ) );
		$this->assertSame( array(), get_field( 'field_ss_choice_multi', $product_id ) );
		$this->assertSame( '', get_post_meta( $product_id, 'ss_choice_checkbox', true ) );
		$this->assertSame( '', get_post_meta( $product_id, 'ss_choice_select', true ), 'allow_null select clears to empty.' );
		$this->assertFalse( get_field( 'field_ss_choice_switch', $product_id ) );
		$this->assertSame( '0', get_post_meta( $product_id, 'ss_choice_switch', true ) );
	}

	public function test_values_outside_the_choices_are_rejected() {
		$this->require_acf();
		$this->register_choice_group();

		$product_id = self::factory()->product->create()->get_id();
		update_field( 'field_ss_choice_radio', 'red', $product_id );
		update_field( 'field_ss_choice_checkbox', array( 'red' ), $product_id );

		$response = $this->edit_with_acf(
			$product_id,
			array(
				'field_ss_choice_radio'    => 'purple',
				'field_ss_choice_checkbox' => array( 'purple', 'blue' ),
				'field_ss_choice_buttons'  => '',
			)
		);

		$this->assertTrue( $response['success'], wp_json_encode( $response ) );
		$this->assertSame( 'red', get_post_meta( $product_id, 'ss_choice_radio', true ), 'An unknown radio value leaves the stored one alone.' );
		$this->assertSame( array( 'blue' ), get_field( 'field_ss_choice_checkbox', $product_id ), 'Unknown checkbox values are dropped, valid ones kept.' );
		$this->assertSame( '', get_post_meta( $product_id, 'ss_choice_buttons', true ), 'Empty without allow_null is not written.' );
		$this->assertSame( array(), get_post_meta( $product_id, '_ss_choice_buttons' ) );
	}
}
