<?php
/**
 * Tests for the product bulk actions (issue #187).
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test\Product;

use PluginizeLab\StoreSuite\Test\StoreSuiteAjaxTestCase;

/**
 * @covers \PluginizeLab\StoreSuite\Product\ProductBulkEdit
 * @group storesuite-product
 * @group storesuite-ajax
 */
class ProductBulkEditTest extends StoreSuiteAjaxTestCase {

	public function test_bulk_trash_moves_products_to_trash_and_skips_non_products() {
		$this->_setRole( 'administrator' );
		$product_a = self::factory()->product->create( array( 'name' => 'Trash A' ) );
		$product_b = self::factory()->product->create( array( 'name' => 'Trash B' ) );
		$page_id   = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$response = $this->do_ajax(
			'storesuite_bulk_trash_products',
			array(
				'product_ids' => array( $product_a->get_id(), $product_b->get_id(), $page_id ),
			)
		);

		$this->assertTrue( $response['success'] );
		$this->assertSame( 2, $response['data']['trashed'] );
		$this->assertSame( 'trash', get_post_status( $product_a->get_id() ) );
		$this->assertSame( 'trash', get_post_status( $product_b->get_id() ) );
		$this->assertNotSame( 'trash', get_post_status( $page_id ), 'Non-product posts must be skipped.' );
	}

	public function test_bulk_delete_removes_products_permanently() {
		$this->_setRole( 'administrator' );
		$product = self::factory()->product->create( array( 'name' => 'Delete Me' ) );

		$response = $this->do_ajax(
			'storesuite_bulk_delete_products',
			array(
				'product_ids' => array( $product->get_id() ),
			)
		);

		$this->assertTrue( $response['success'] );
		$this->assertSame( 1, $response['data']['deleted'] );
		$this->assertNull( get_post( $product->get_id() ) );
	}

	public function test_bulk_edit_adds_and_removes_categories() {
		$this->_setRole( 'administrator' );
		$product = self::factory()->product->create( array( 'name' => 'Category Target' ) );
		$term    = wp_insert_term( 'Bulk Cat', 'product_cat' );
		$term_id = $term['term_id'];

		// Add.
		$response = $this->do_ajax(
			'storesuite_bulk_edit_products',
			array(
				'post'                   => array( $product->get_id() ),
				'post_type'              => 'product',
				'_status'                => '-1',
				'storesuite_bulk_cat_op' => 'add',
				'storesuite_bulk_cats'   => array( $term_id ),
			)
		);

		$this->assertTrue( $response['success'] );
		$this->assertTrue( has_term( $term_id, 'product_cat', $product->get_id() ) );

		// Remove.
		$response = $this->do_ajax(
			'storesuite_bulk_edit_products',
			array(
				'post'                   => array( $product->get_id() ),
				'post_type'              => 'product',
				'_status'                => '-1',
				'storesuite_bulk_cat_op' => 'remove',
				'storesuite_bulk_cats'   => array( $term_id ),
			)
		);

		$this->assertTrue( $response['success'] );
		$this->assertFalse( has_term( $term_id, 'product_cat', $product->get_id() ) );
	}

	public function test_bulk_trash_requires_capability() {
		$this->_setRole( 'administrator' );
		$product = self::factory()->product->create( array( 'name' => 'Protected' ) );

		$this->_setRole( 'subscriber' );
		$response = $this->do_ajax(
			'storesuite_bulk_trash_products',
			array(
				'product_ids' => array( $product->get_id() ),
			)
		);

		// Subscriber lacks delete_post on the product, so nothing may be trashed.
		$this->assertSame( 'publish', get_post_status( $product->get_id() ) );
		$this->assertTrue( empty( $response['data']['trashed'] ) );
	}

	public function test_bulk_edit_requires_capability() {
		$this->_setRole( 'administrator' );
		$product = self::factory()->product->create( array( 'name' => 'Edit Protected' ) );

		$this->_setRole( 'subscriber' );
		$response = $this->do_ajax(
			'storesuite_bulk_edit_products',
			array(
				'post'      => array( $product->get_id() ),
				'post_type' => 'product',
				'_status'   => 'draft',
			)
		);

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'publish', get_post_status( $product->get_id() ) );
	}
}
