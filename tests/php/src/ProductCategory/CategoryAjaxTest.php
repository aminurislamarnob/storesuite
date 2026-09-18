<?php
/**
 * Tests for the product category CRUD AJAX endpoints.
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test\ProductCategory;

use PluginizeLab\StoreSuite\Test\StoreSuiteAjaxTestCase;

/**
 * @covers \PluginizeLab\StoreSuite\ProductCategory\CategoryController
 * @group storesuite-taxonomy
 * @group storesuite-ajax
 */
class CategoryAjaxTest extends StoreSuiteAjaxTestCase {

	public function test_category_is_created_with_parent_and_description() {
		$this->_setRole( 'shop_manager' );
		$parent = wp_insert_term( 'Clothing', 'product_cat', array( 'slug' => 'clothing' ) );

		$response = $this->do_ajax(
			'storesuite_add_product_category',
			array(
				'product_category_name'        => 'Hoodies',
				'product_parent_category'      => 'clothing',
				'product_category_description' => 'Warm tops',
			),
			'_storesuite_add_product_category_',
			'storesuite_add_product_category_nonce'
		);

		$this->assertTrue( $response['success'] );

		$term = get_term_by( 'slug', 'hoodies', 'product_cat' );
		$this->assertNotFalse( $term, 'The slug is generated from the name when not provided.' );
		$this->assertSame( $parent['term_id'], $term->parent );
		$this->assertSame( 'Warm tops', $term->description );
	}

	public function test_duplicate_category_slug_is_rejected() {
		$this->_setRole( 'shop_manager' );
		wp_insert_term( 'Shoes', 'product_cat', array( 'slug' => 'shoes' ) );

		$response = $this->do_ajax(
			'storesuite_add_product_category',
			array(
				'product_category_name' => 'Other Shoes',
				'product_category_slug' => 'shoes',
			),
			'_storesuite_add_product_category_',
			'storesuite_add_product_category_nonce'
		);

		$this->assertFalse( $response['success'] );
	}

	public function test_category_can_be_renamed() {
		$this->_setRole( 'shop_manager' );
		$term = wp_insert_term( 'Old Name', 'product_cat' );

		$response = $this->do_ajax(
			'storesuite_edit_product_category',
			array(
				'category_id'           => $term['term_id'],
				'product_category_name' => 'New Name',
				'product_category_slug' => 'new-name',
			),
			'_storesuite_edit_product_category_',
			'storesuite_edit_product_category_nonce'
		);

		$this->assertTrue( $response['success'] );

		$updated = get_term( $term['term_id'], 'product_cat' );
		$this->assertSame( 'New Name', $updated->name );
		$this->assertSame( 'new-name', $updated->slug );
	}

	public function test_category_can_be_deleted() {
		$this->_setRole( 'shop_manager' );
		$term = wp_insert_term( 'Doomed', 'product_cat' );

		$response = $this->do_ajax(
			'storesuite_delete_product_category',
			array( 'id' => $term['term_id'] ),
			'_storesuite_delete_nonce_',
			'storesuite_delete_product_category_nonce'
		);

		$this->assertTrue( $response['success'] );
		$this->assertNull( get_term( $term['term_id'], 'product_cat' ) );
	}

	public function test_deleting_unknown_category_fails() {
		$this->_setRole( 'shop_manager' );

		$response = $this->do_ajax(
			'storesuite_delete_product_category',
			array( 'id' => 999999 ),
			'_storesuite_delete_nonce_',
			'storesuite_delete_product_category_nonce'
		);

		$this->assertFalse( $response['success'] );
	}

	public function test_customer_cannot_create_categories() {
		$this->_setRole( 'subscriber' );

		$response = $this->do_ajax(
			'storesuite_add_product_category',
			array( 'product_category_name' => 'Forbidden' ),
			'_storesuite_add_product_category_',
			'storesuite_add_product_category_nonce'
		);

		$this->assertFalse( $response['success'] );
		$this->assertFalse( get_term_by( 'slug', 'forbidden', 'product_cat' ) );
	}
}
