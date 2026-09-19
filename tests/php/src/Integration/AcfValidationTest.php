<?php
/**
 * Tests for server-side ACF validation on the product form save path.
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test\Integration;

use PluginizeLab\StoreSuite\Test\StoreSuiteAjaxTestCase;
use WP_Error;

/**
 * @covers \PluginizeLab\StoreSuite\Integration\Acf\AcfIntegration
 * @covers \PluginizeLab\StoreSuite\Product\ProductController
 * @group storesuite-acf
 * @group storesuite-ajax
 */
class AcfValidationTest extends StoreSuiteAjaxTestCase {

	use AcfTestHelpers;

	const GROUP_KEY = 'group_storesuite_validation';

	/**
	 * Tear down the test fixture.
	 *
	 * @return void
	 */
	public function tear_down() {
		$this->remove_acf_field_groups();
		remove_all_filters( 'acf/validate_value/key=field_ss_val_sku' );
		parent::tear_down();
	}

	/**
	 * Register a group with rules to break: required text with maxlength,
	 * bounded number, a custom-validated text and a required repeater
	 * (unsupported, must never block).
	 *
	 * @return void
	 */
	private function register_validation_group() {
		$this->register_acf_field_group(
			array(
				'key'      => self::GROUP_KEY,
				'title'    => 'Rules',
				'fields'   => array(
					array(
						'key'       => 'field_ss_val_material',
						'name'      => 'ss_val_material',
						'label'     => 'Material',
						'type'      => 'text',
						'required'  => 1,
						'maxlength' => 5,
					),
					array(
						'key'   => 'field_ss_val_weight',
						'name'  => 'ss_val_weight',
						'label' => 'Weight',
						'type'  => 'number',
						'min'   => 1,
						'max'   => 10,
					),
					array(
						'key'   => 'field_ss_val_sku',
						'name'  => 'ss_val_sku',
						'label' => 'Supplier SKU',
						'type'  => 'text',
					),
					array(
						'key'      => 'field_ss_val_sizes',
						'name'     => 'ss_val_sizes',
						'label'    => 'Sizes',
						'type'     => 'repeater',
						'required' => 1,
					),
				),
				'location' => $this->acf_product_location(),
			)
		);
	}

	/**
	 * Dispatch the add-product AJAX action as a shop manager.
	 *
	 * @param array $acf storesuite_acf values keyed by field key.
	 * @return array Decoded response.
	 */
	private function add_with_acf( array $acf ) {
		$this->_setRole( 'shop_manager' );

		return $this->do_ajax(
			'storesuite_add_product_action',
			array(
				'product_title'  => 'Validated product',
				'storesuite_acf' => $acf,
			),
			'_storesuite_add_product_',
			'storesuite_add_product_nonce'
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

	/**
	 * Number of products with the given title.
	 *
	 * @param string $title Product title.
	 * @return int
	 */
	private function count_products_titled( $title ) {
		return count(
			get_posts(
				array(
					'post_type'   => 'product',
					'post_status' => 'any',
					'title'       => $title,
					'fields'      => 'ids',
				)
			)
		);
	}

	// -------------------------------------------------------------------------
	// The generic pre-save filter (no ACF needed).
	// -------------------------------------------------------------------------

	public function test_pre_save_filter_lets_any_code_abort_the_save() {
		$seen = array();
		add_filter(
			'storesuite_product_pre_save_validation',
			function ( $error, $data, $context ) use ( &$seen ) {
				$seen[] = $context;
				return new WP_Error( 'blocked', 'Blocked by a test hook' );
			},
			10,
			3
		);

		$response = $this->add_with_acf( array() );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Blocked by a test hook', $response['data']['error'] );
		$this->assertSame( array( 'Blocked by a test hook' ), $response['data']['errors'] );
		$this->assertSame( 'add', $response['data']['context'] );
		$this->assertSame( array( 'add' ), $seen );
		$this->assertSame( 0, $this->count_products_titled( 'Validated product' ), 'Nothing was created.' );

		remove_all_filters( 'storesuite_product_pre_save_validation' );
		\pluginizelab_storesuite()->storesuite_acf_integration && add_filter( 'storesuite_product_pre_save_validation', array( \pluginizelab_storesuite()->storesuite_acf_integration, 'validate_fields' ), 10, 3 );
	}

	// -------------------------------------------------------------------------
	// ACF rules.
	// -------------------------------------------------------------------------

	public function test_required_field_left_empty_blocks_add_and_edit() {
		$this->require_acf();
		$this->register_validation_group();

		$response = $this->add_with_acf( array( 'field_ss_val_material' => '' ) );
		$this->assertFalse( $response['success'] );
		$this->assertSame( array( 'Material value is required' ), $response['data']['errors'] );
		$this->assertSame( 0, $this->count_products_titled( 'Validated product' ) );

		$product_id = self::factory()->product->create( array( 'name' => 'Original name' ) )->get_id();
		update_field( 'field_ss_val_material', 'Wool', $product_id );

		$response = $this->edit_with_acf( $product_id, array( 'field_ss_val_material' => '' ) );
		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Material value is required', $response['data']['error'] );
		$this->assertSame( 'Original name', get_the_title( $product_id ), 'The product was not updated.' );
		$this->assertSame( 'Wool', get_post_meta( $product_id, 'ss_val_material', true ), 'The field was not cleared.' );
	}

	public function test_maxlength_min_max_and_custom_rules_all_surface_together() {
		$this->require_acf();
		$this->register_validation_group();

		add_filter(
			'acf/validate_value/key=field_ss_val_sku',
			function ( $valid, $value ) {
				return 0 === strpos( (string) $value, 'SUP-' ) ? $valid : 'Supplier SKUs start with SUP-';
			},
			10,
			2
		);

		$response = $this->add_with_acf(
			array(
				'field_ss_val_material' => 'Polyester',
				'field_ss_val_weight'   => '42',
				'field_ss_val_sku'      => 'ABC-1',
			)
		);

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Please fix the following before saving:', $response['data']['error'] );
		$this->assertCount( 3, $response['data']['errors'] );
		$this->assertStringContainsString( 'Material', $response['data']['errors'][0] );
		$this->assertStringContainsString( '5', $response['data']['errors'][0], 'maxlength message mentions the limit.' );
		$this->assertStringContainsString( 'Weight', $response['data']['errors'][1] );
		$this->assertStringContainsString( '10', $response['data']['errors'][1], 'max message mentions the bound.' );
		$this->assertSame( 'Supplier SKU: Supplier SKUs start with SUP-', $response['data']['errors'][2] );
		$this->assertSame( 0, $this->count_products_titled( 'Validated product' ) );
	}

	public function test_a_rejected_value_is_reported_with_acf_message() {
		$this->require_acf();
		$this->register_validation_group();

		$response = $this->add_with_acf(
			array(
				'field_ss_val_material' => 'Wool',
				'field_ss_val_weight'   => 'heavy',
			)
		);

		$this->assertFalse( $response['success'] );
		$this->assertSame( array( 'Weight: Value must be a number' ), $response['data']['errors'] );
	}

	public function test_valid_submission_saves_and_unsupported_required_field_never_blocks() {
		$this->require_acf();
		$this->register_validation_group();

		$response = $this->add_with_acf(
			array(
				'field_ss_val_material' => 'Wool',
				'field_ss_val_weight'   => '5',
				'field_ss_val_sku'      => 'SUP-1',
			)
		);

		$this->assertTrue( $response['success'], wp_json_encode( $response ) );
		$this->assertSame( 1, $this->count_products_titled( 'Validated product' ) );

		$product_id = self::factory()->product->create()->get_id();
		$response   = $this->edit_with_acf( $product_id, array( 'field_ss_val_material' => 'Silk' ) );
		$this->assertTrue( $response['success'], wp_json_encode( $response ) );
		$this->assertSame( 'Silk', get_post_meta( $product_id, 'ss_val_material', true ) );
	}

	public function test_blank_password_is_not_validated_as_missing() {
		$this->require_acf();
		$this->register_acf_field_group(
			array(
				'key'      => self::GROUP_KEY,
				'title'    => 'Secret',
				'fields'   => array(
					array(
						'key'      => 'field_ss_val_secret',
						'name'     => 'ss_val_secret',
						'label'    => 'Portal key',
						'type'     => 'password',
						'required' => 1,
					),
				),
				'location' => $this->acf_product_location(),
			)
		);

		$product_id = self::factory()->product->create()->get_id();
		update_field( 'field_ss_val_secret', 'keep-me', $product_id );

		$response = $this->edit_with_acf( $product_id, array( 'field_ss_val_secret' => '' ) );

		$this->assertTrue( $response['success'], wp_json_encode( $response ) );
		$this->assertSame( 'keep-me', get_post_meta( $product_id, 'ss_val_secret', true ) );
	}
}
