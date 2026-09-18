<?php
/**
 * Tests for the Advanced Custom Fields product form integration.
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test\Integration;

use PluginizeLab\StoreSuite\Integration\Acf\AcfIntegration;
use PluginizeLab\StoreSuite\Test\StoreSuiteAjaxTestCase;

/**
 * @covers \PluginizeLab\StoreSuite\Integration\Acf\AcfIntegration
 * @covers \PluginizeLab\StoreSuite\Integration\Acf\FieldRenderer
 * @covers \PluginizeLab\StoreSuite\Integration\Acf\FieldSanitizer
 * @group storesuite-acf
 * @group storesuite-ajax
 */
class AcfProductFieldsTest extends StoreSuiteAjaxTestCase {

	use AcfTestHelpers;

	const GROUP_KEY  = 'group_storesuite_test_specs';
	const TEXT_KEY   = 'field_storesuite_test_material';
	const TEXT_NAME  = 'storesuite_test_material';
	const OTHER_KEY  = 'field_storesuite_test_sizes';
	const OTHER_NAME = 'storesuite_test_sizes';

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
	 * Register the standard "Product Specs" group: one text field plus one
	 * unsupported (repeater) field, located on products.
	 *
	 * @param array $overrides Group overrides (e.g. a different location).
	 * @return array
	 */
	private function register_specs_group( array $overrides = array() ) {
		return $this->register_acf_field_group(
			array_merge(
				array(
					'key'      => self::GROUP_KEY,
					'title'    => 'Product Specs',
					'fields'   => array(
						array(
							'key'           => self::TEXT_KEY,
							'label'         => 'Material',
							'name'          => self::TEXT_NAME,
							'type'          => 'text',
							'instructions'  => 'Main fabric or material.',
							'required'      => 1,
							'default_value' => 'Cotton',
							'placeholder'   => 'e.g. Cotton',
							'wrapper'       => array(
								'width' => '50',
								'class' => 'spec-material',
								'id'    => 'spec-material-wrap',
							),
						),
						array(
							'key'   => self::OTHER_KEY,
							'label' => 'Sizes',
							'name'  => self::OTHER_NAME,
							'type'  => 'repeater',
						),
					),
					'location' => $this->acf_product_location(),
				),
				$overrides
			)
		);
	}

	/**
	 * Dispatch the add-product AJAX action as a shop manager.
	 *
	 * @param array $fields Extra request fields.
	 * @return array Decoded response.
	 */
	private function add_product_ajax( array $fields ) {
		$this->_setRole( 'shop_manager' );

		return $this->do_ajax(
			'storesuite_add_product_action',
			array_merge( array( 'product_title' => 'ACF Tee' ), $fields ),
			'_storesuite_add_product_',
			'storesuite_add_product_nonce'
		);
	}

	/**
	 * Dispatch the edit-product AJAX action as a shop manager.
	 *
	 * @param int   $product_id Product to edit.
	 * @param array $fields     Extra request fields.
	 * @return array Decoded response.
	 */
	private function edit_product_ajax( $product_id, array $fields ) {
		$this->_setRole( 'shop_manager' );

		return $this->do_ajax(
			'storesuite_edit_product_action',
			array_merge(
				array(
					'product_id'    => $product_id,
					'product_title' => get_the_title( $product_id ),
				),
				$fields
			),
			'_storesuite_edit_product_',
			'storesuite_edit_product_nonce'
		);
	}

	/**
	 * Product ID from a successful add response (the response only carries a message).
	 *
	 * @return int
	 */
	private function last_created_product_id() {
		$ids = wc_get_products(
			array(
				'limit'   => 1,
				'orderby' => 'ID',
				'order'   => 'DESC',
				'return'  => 'ids',
				'status'  => 'any',
			)
		);

		return (int) reset( $ids );
	}

	// -------------------------------------------------------------------------
	// Without ACF (runs everywhere).
	// -------------------------------------------------------------------------

	public function test_posted_acf_values_are_dropped_when_nothing_sanitises_them() {
		$integration = \pluginizelab_storesuite()->storesuite_acf_integration;
		if ( $integration ) {
			remove_filter( 'storesuite_sanitize_acf_fields', array( $integration, 'sanitize_fields' ), 10 );
		}

		$response = $this->add_product_ajax(
			array( 'storesuite_acf' => array( 'field_anything' => 'value' ) )
		);

		$this->assertTrue( $response['success'] );
		$product_id = $this->last_created_product_id();
		$this->assertSame( array(), get_post_meta( $product_id, 'field_anything' ), 'Unsanitised ACF input is never written.' );

		if ( $integration ) {
			add_filter( 'storesuite_sanitize_acf_fields', array( $integration, 'sanitize_fields' ), 10, 3 );
		}
	}

	public function test_integration_is_registered_in_the_container() {
		$this->assertInstanceOf( AcfIntegration::class, \pluginizelab_storesuite()->storesuite_acf_integration );
	}

	// -------------------------------------------------------------------------
	// Rendering.
	// -------------------------------------------------------------------------

	public function test_add_form_renders_the_group_card_with_the_text_field() {
		$this->require_acf();
		$this->register_specs_group();

		$html = $this->render_acf_cards( 0 );

		$this->assertStringContainsString( 'storesuite-acf-field-group', $html );
		$this->assertStringContainsString( 'data-group-key="' . self::GROUP_KEY . '"', $html );
		$this->assertStringContainsString( '<h3 class="storesuite-card-title">Product Specs</h3>', $html );
		$this->assertStringContainsString( 'Material', $html );
		$this->assertStringContainsString( '<span class="req">*</span>', $html );
		$this->assertStringContainsString( 'Main fabric or material.', $html );
		$this->assertStringContainsString( 'name="storesuite_acf[' . self::TEXT_KEY . ']"', $html );
		$this->assertStringContainsString( 'id="storesuite_acf_' . self::TEXT_KEY . '"', $html );
		$this->assertStringContainsString( 'placeholder="e.g. Cotton"', $html );
		$this->assertMatchesRegularExpression( '/<input[^>]*name="storesuite_acf\[' . self::TEXT_KEY . '\]"[^>]*required/s', $html, 'HTML5 required is set.' );
	}

	public function test_wrapper_width_class_and_id_are_mapped() {
		$this->require_acf();
		$this->register_specs_group();

		$html = $this->render_acf_cards( 0 );

		$this->assertMatchesRegularExpression( '/class="col-md-6 storesuite-acf-field storesuite-acf-field-text spec-material" id="spec-material-wrap"/', $html );
		// The repeater has no width → full row.
		$this->assertStringContainsString( 'col-md-12 storesuite-acf-field storesuite-acf-field-repeater', $html );
	}

	/**
	 * @dataProvider width_provider
	 */
	public function test_width_percentages_map_to_grid_columns( $width, $columns ) {
		$this->require_acf();

		$renderer = new \PluginizeLab\StoreSuite\Integration\Acf\FieldRenderer( new AcfIntegration() );

		$this->assertSame( $columns, $renderer->get_columns( array( 'wrapper' => array( 'width' => $width ) ) ) );
	}

	public function width_provider() {
		return array(
			'empty' => array( '', 12 ),
			'20'    => array( '20', 3 ),
			'25'    => array( '25', 3 ),
			'33'    => array( '33', 4 ),
			'50'    => array( '50', 6 ),
			'66'    => array( '66', 8 ),
			'75'    => array( '75', 9 ),
			'80'    => array( '80', 12 ),
			'100'   => array( '100', 12 ),
		);
	}

	public function test_default_value_is_prefilled_on_the_add_form_only() {
		$this->require_acf();
		$this->register_specs_group();

		$add_html = $this->render_acf_cards( 0 );
		$this->assertStringContainsString( 'value="Cotton"', $add_html );

		$product_id = self::factory()->product->create()->get_id();
		$edit_html  = $this->render_acf_cards( $product_id );
		$this->assertStringNotContainsString( 'value="Cotton"', $edit_html );
		$this->assertStringContainsString( 'value=""', $edit_html );
	}

	public function test_edit_form_shows_the_stored_value() {
		$this->require_acf();
		$this->register_specs_group();

		$product_id = self::factory()->product->create()->get_id();
		update_field( self::TEXT_KEY, 'Linen <b>blend</b>', $product_id );

		$html = $this->render_acf_cards( $product_id );

		$this->assertStringContainsString( 'value="Linen &lt;b&gt;blend&lt;/b&gt;"', $html );
	}

	public function test_unsupported_field_type_renders_a_placeholder_without_an_input() {
		$this->require_acf();
		$this->register_specs_group();

		$html = $this->render_acf_cards( 0 );

		$this->assertStringContainsString( 'storesuite-acf-field-unsupported-note', $html );
		$this->assertStringContainsString( '&quot;repeater&quot; field type can&#039;t be edited here', $html );
		$this->assertStringNotContainsString( 'storesuite_acf[' . self::OTHER_KEY . ']', $html );
	}

	// -------------------------------------------------------------------------
	// Location rules.
	// -------------------------------------------------------------------------

	public function test_group_located_on_posts_never_renders() {
		$this->require_acf();
		$this->register_specs_group(
			array(
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'post',
						),
					),
				),
			)
		);

		$product_id = self::factory()->product->create()->get_id();

		$this->assertSame( '', $this->render_acf_cards( 0 ) );
		$this->assertSame( '', $this->render_acf_cards( $product_id ) );
	}

	public function test_category_location_rule_is_honoured_on_the_edit_form() {
		$this->require_acf();

		$category_id = self::factory()->term->create( array( 'taxonomy' => 'product_cat' ) );
		$this->register_specs_group(
			array(
				'location' => array(
					array(
						array(
							'param'    => 'post_taxonomy',
							'operator' => '==',
							'value'    => 'product_cat:' . get_term( $category_id )->slug,
						),
					),
				),
			)
		);

		$inside  = self::factory()->product->create()->get_id();
		$outside = self::factory()->product->create()->get_id();
		wp_set_object_terms( $inside, array( $category_id ), 'product_cat' );

		$this->assertStringContainsString( 'Product Specs', $this->render_acf_cards( $inside ) );
		$this->assertSame( '', $this->render_acf_cards( $outside ) );
		$this->assertSame( '', $this->render_acf_cards( 0 ), 'Add mode knows only the post type, so taxonomy rules do not match.' );
	}

	// -------------------------------------------------------------------------
	// Saving.
	// -------------------------------------------------------------------------

	public function test_adding_a_product_writes_the_value_and_acf_reference_meta() {
		$this->require_acf();
		$this->register_specs_group();

		$response = $this->add_product_ajax(
			array( 'storesuite_acf' => array( self::TEXT_KEY => ' Organic <script>x</script>cotton ' ) )
		);

		$this->assertTrue( $response['success'], wp_json_encode( $response ) );
		$product_id = $this->last_created_product_id();

		$this->assertSame( 'Organic cotton', get_post_meta( $product_id, self::TEXT_NAME, true ) );
		$this->assertSame( self::TEXT_KEY, get_post_meta( $product_id, '_' . self::TEXT_NAME, true ), 'ACF field-key reference meta is written so wp-admin displays the value.' );
		$this->assertSame( 'Organic cotton', get_field( self::TEXT_KEY, $product_id ) );
	}

	public function test_editing_a_product_updates_the_value() {
		$this->require_acf();
		$this->register_specs_group();

		$product_id = self::factory()->product->create()->get_id();
		update_field( self::TEXT_KEY, 'Linen', $product_id );

		$response = $this->edit_product_ajax(
			$product_id,
			array( 'storesuite_acf' => array( self::TEXT_KEY => 'Silk' ) )
		);

		$this->assertTrue( $response['success'], wp_json_encode( $response ) );
		$this->assertSame( 'Silk', get_post_meta( $product_id, self::TEXT_NAME, true ) );
	}

	public function test_unsupported_and_unknown_keys_are_never_written() {
		$this->require_acf();
		$this->register_specs_group();

		$product_id = self::factory()->product->create()->get_id();
		update_post_meta( $product_id, self::OTHER_NAME, '2' );
		update_post_meta( $product_id, '_' . self::OTHER_NAME, self::OTHER_KEY );

		$response = $this->edit_product_ajax(
			$product_id,
			array(
				'storesuite_acf' => array(
					self::TEXT_KEY      => 'Silk',
					self::OTHER_KEY     => 'tampered',
					'field_not_a_field' => 'tampered',
					'sku'               => 'tampered',
				),
			)
		);

		$this->assertTrue( $response['success'], wp_json_encode( $response ) );
		$this->assertSame( 'Silk', get_post_meta( $product_id, self::TEXT_NAME, true ) );
		$this->assertSame( '2', get_post_meta( $product_id, self::OTHER_NAME, true ), 'Unsupported field value is untouched.' );
		$this->assertSame( array(), get_post_meta( $product_id, 'field_not_a_field' ) );
		$this->assertSame( array(), get_post_meta( $product_id, 'not_a_field' ) );
		$this->assertSame( '', get_post_meta( $product_id, 'sku', true ) );
	}

	public function test_fields_outside_the_product_location_rules_are_not_written() {
		$this->require_acf();

		$category_id = self::factory()->term->create( array( 'taxonomy' => 'product_cat' ) );
		$this->register_specs_group(
			array(
				'location' => array(
					array(
						array(
							'param'    => 'post_taxonomy',
							'operator' => '==',
							'value'    => 'product_cat:' . get_term( $category_id )->slug,
						),
					),
				),
			)
		);

		$outside  = self::factory()->product->create()->get_id();
		$response = $this->edit_product_ajax(
			$outside,
			array( 'storesuite_acf' => array( self::TEXT_KEY => 'Silk' ) )
		);

		$this->assertTrue( $response['success'], wp_json_encode( $response ) );
		$this->assertSame( '', get_post_meta( $outside, self::TEXT_NAME, true ), 'A field that was not rendered for this product is not saved.' );
	}

	public function test_saving_without_acf_input_leaves_values_alone() {
		$this->require_acf();
		$this->register_specs_group();

		$product_id = self::factory()->product->create()->get_id();
		update_field( self::TEXT_KEY, 'Linen', $product_id );

		$response = $this->edit_product_ajax( $product_id, array() );

		$this->assertTrue( $response['success'], wp_json_encode( $response ) );
		$this->assertSame( 'Linen', get_post_meta( $product_id, self::TEXT_NAME, true ) );
	}

	// -------------------------------------------------------------------------
	// Importer regression.
	// -------------------------------------------------------------------------

	public function test_csv_import_update_path_leaves_acf_meta_intact() {
		$this->require_acf();
		$this->register_specs_group();

		$product_id = self::factory()->product->create( array( 'name' => 'Before import' ) )->get_id();
		update_field( self::TEXT_KEY, 'Linen', $product_id );

		$uploads = wp_upload_dir();
		$file    = trailingslashit( $uploads['basedir'] ) . 'storesuite-acf-import-' . uniqid() . '.csv';
		file_put_contents( $file, "ID,Name\n{$product_id},After import\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$this->_setRole( 'shop_manager' );
		$response = $this->do_ajax(
			'woocommerce_do_ajax_product_import',
			array(
				'file'            => $file,
				'mapping'         => array(
					'ID'   => 'id',
					'Name' => 'name',
				),
				'update_existing' => 1,
				'position'        => 0,
			),
			'wc-product-import'
		);
		unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink

		$this->assertTrue( $response['success'], wp_json_encode( $response ) );
		$this->assertSame( 1, $response['data']['updated'] );
		$this->assertSame( 'After import', get_the_title( $product_id ) );
		$this->assertSame( 'Linen', get_post_meta( $product_id, self::TEXT_NAME, true ) );
		$this->assertSame( self::TEXT_KEY, get_post_meta( $product_id, '_' . self::TEXT_NAME, true ) );
	}
}
