<?php
/**
 * Tests for the product tag CRUD AJAX endpoints.
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test\ProductTag;

use PluginizeLab\StoreSuite\Test\StoreSuiteAjaxTestCase;

/**
 * @covers \PluginizeLab\StoreSuite\ProductTag\TagController
 * @group storesuite-taxonomy
 * @group storesuite-ajax
 */
class TagAjaxTest extends StoreSuiteAjaxTestCase {

	public function test_tag_is_created() {
		$this->_setRole( 'shop_manager' );

		$response = $this->do_ajax(
			'storesuite_add_product_tag',
			array(
				'name'        => 'Summer',
				'description' => 'Warm weather picks',
			),
			'_storesuite_add_product_tag_',
			'storesuite_add_product_tag_nonce'
		);

		$this->assertTrue( $response['success'] );

		$term = get_term_by( 'slug', 'summer', 'product_tag' );
		$this->assertNotFalse( $term );
		$this->assertSame( 'Warm weather picks', $term->description );
	}

	public function test_tag_can_be_renamed() {
		$this->_setRole( 'shop_manager' );
		$term = wp_insert_term( 'Wnter', 'product_tag' );

		$response = $this->do_ajax(
			'storesuite_edit_product_tag',
			array(
				'tag_id' => $term['term_id'],
				'name'   => 'Winter',
				'slug'   => 'winter',
			),
			'_storesuite_edit_product_tag_',
			'storesuite_edit_product_tag_nonce'
		);

		$this->assertTrue( $response['success'] );
		$this->assertSame( 'Winter', get_term( $term['term_id'], 'product_tag' )->name );
	}

	public function test_tag_can_be_deleted() {
		$this->_setRole( 'shop_manager' );
		$term = wp_insert_term( 'Fleeting', 'product_tag' );

		$response = $this->do_ajax(
			'storesuite_delete_product_tag',
			array( 'id' => $term['term_id'] ),
			'_storesuite_delete_nonce_',
			'storesuite_delete_product_tag_nonce'
		);

		$this->assertTrue( $response['success'] );
		$this->assertNull( get_term( $term['term_id'], 'product_tag' ) );
	}

	public function test_customer_cannot_create_tags() {
		$this->_setRole( 'subscriber' );

		$response = $this->do_ajax(
			'storesuite_add_product_tag',
			array( 'name' => 'Forbidden' ),
			'_storesuite_add_product_tag_',
			'storesuite_add_product_tag_nonce'
		);

		$this->assertFalse( $response['success'] );
		$this->assertFalse( get_term_by( 'slug', 'forbidden', 'product_tag' ) );
	}
}
