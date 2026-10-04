<?php
/**
 * Tests for the server side of ACF conditional logic on the product form.
 *
 * The browser hides fields whose rules fail and disables their inputs, so
 * they are absent from the request; the server must then neither save nor
 * validate them.
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test\Integration;

use PluginizeLab\StoreSuite\Test\StoreSuiteAjaxTestCase;

/**
 * @covers \PluginizeLab\StoreSuite\Integration\Acf\AcfIntegration
 * @covers \PluginizeLab\StoreSuite\Integration\Acf\FieldRenderer
 * @group storesuite-acf
 * @group storesuite-ajax
 */
class AcfConditionalLogicTest extends StoreSuiteAjaxTestCase {

	use AcfTestHelpers;

	const GROUP_KEY = 'group_storesuite_conditional';

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
	 * Register a switch, a text field conditioned on it (required) and a
	 * repeater placeholder, located on products.
	 *
	 * @return void
	 */
	private function register_conditional_group() {
		$this->register_acf_field_group(
			array(
				'key'      => self::GROUP_KEY,
				'title'    => 'Warranty',
				'fields'   => array(
					array(
						'key'   => 'field_ss_cond_switch',
						'name'  => 'ss_cond_switch',
						'label' => 'Has warranty',
						'type'  => 'true_false',
					),
					array(
						'key'               => 'field_ss_cond_months',
						'name'              => 'ss_cond_months',
						'label'             => 'Warranty months',
						'type'              => 'number',
						'required'          => 1,
						'conditional_logic' => array(
							array(
								array(
									'field'    => 'field_ss_cond_switch',
									'operator' => '==',
									'value'    => '1',
								),
							),
							array(
								array(
									'field'    => 'field_ss_cond_sizes',
									'operator' => '!=empty',
								),
								array(
									'field'    => 'field_ss_cond_switch',
									'operator' => '!=',
									'value'    => '1',
								),
							),
						),
					),
					array(
						'key'   => 'field_ss_cond_sizes',
						'name'  => 'ss_cond_sizes',
						'label' => 'Sizes',
						'type'  => 'repeater',
					),
				),
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
				'product_title'  => 'Renamed by edit',
				'storesuite_acf' => $acf,
			),
			'_storesuite_edit_product_',
			'storesuite_edit_product_nonce'
		);
	}

	public function test_rules_and_placeholder_values_are_emitted_for_the_browser() {
		$this->require_acf();
		$this->register_conditional_group();

		$product_id = self::factory()->product->create()->get_id();
		update_post_meta( $product_id, 'ss_cond_sizes', '2' );
		update_post_meta( $product_id, '_ss_cond_sizes', 'field_ss_cond_sizes' );

		$html = $this->render_acf_cards( $product_id );

		$this->assertSame( 1, preg_match( '/data-key="field_ss_cond_months"[^>]*data-conditions="([^"]+)"/', $html, $m ) );
		$this->assertSame(
			array(
				array(
					array(
						'field'    => 'field_ss_cond_switch',
						'operator' => '==',
						'value'    => '1',
					),
				),
				array(
					array(
						'field'    => 'field_ss_cond_sizes',
						'operator' => '!=empty',
						'value'    => '',
					),
					array(
						'field'    => 'field_ss_cond_switch',
						'operator' => '!=',
						'value'    => '1',
					),
				),
			),
			json_decode( html_entity_decode( $m[1], ENT_QUOTES ), true )
		);

		// The switch has no rules and no data-value; the placeholder exposes its stored value.
		$this->assertDoesNotMatchRegularExpression( '/data-key="field_ss_cond_switch"[^>]*data-(conditions|value)=/', $html );
		$this->assertMatchesRegularExpression( '/data-key="field_ss_cond_sizes"[^>]*data-value="&quot;2&quot;"/', $html );
	}

	public function test_fields_absent_from_the_request_are_neither_saved_nor_validated() {
		$this->require_acf();
		$this->register_conditional_group();

		$product_id = self::factory()->product->create()->get_id();
		update_field( 'field_ss_cond_switch', 1, $product_id );
		update_field( 'field_ss_cond_months', '24', $product_id );

		// Switch turned off in the browser → months hidden and disabled → not posted.
		$response = $this->edit_with_acf( $product_id, array( 'field_ss_cond_switch' => '0' ) );

		$this->assertTrue( $response['success'], wp_json_encode( $response ) );
		$this->assertSame( '0', get_post_meta( $product_id, 'ss_cond_switch', true ) );
		$this->assertSame( '24', get_post_meta( $product_id, 'ss_cond_months', true ), 'The hidden field keeps its stored value.' );
	}

	public function test_the_same_field_visible_and_empty_is_still_validated() {
		$this->require_acf();
		$this->register_conditional_group();

		$product_id = self::factory()->product->create()->get_id();

		$response = $this->edit_with_acf(
			$product_id,
			array(
				'field_ss_cond_switch' => '1',
				'field_ss_cond_months' => '',
			)
		);

		$this->assertFalse( $response['success'] );
		$this->assertSame( array( 'Warranty months value is required' ), $response['data']['errors'] );
		$this->assertSame( '', get_post_meta( $product_id, 'ss_cond_switch', true ), 'Nothing was written.' );
	}
}
