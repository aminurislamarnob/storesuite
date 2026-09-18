<?php
/**
 * Tests for the product brand CRUD AJAX endpoints.
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test\ProductBrand;

use PluginizeLab\StoreSuite\Test\StoreSuiteAjaxTestCase;

/**
 * @covers \PluginizeLab\StoreSuite\ProductBrand\BrandController
 * @group storesuite-taxonomy
 * @group storesuite-ajax
 */
class BrandAjaxTest extends StoreSuiteAjaxTestCase {

	public function test_brand_is_created() {
		$this->_setRole( 'shop_manager' );

		$response = $this->do_ajax(
			'storesuite_add_product_brand',
			array(
				'product_brand_name'        => 'Acme',
				'product_brand_description' => 'Everything for coyotes',
			),
			'_storesuite_add_product_brand_',
			'storesuite_add_product_brand_nonce'
		);

		$this->assertTrue( $response['success'] );

		$term = get_term_by( 'slug', 'acme', 'product_brand' );
		$this->assertNotFalse( $term );
		$this->assertSame( 'Everything for coyotes', $term->description );
	}

	public function test_brand_can_be_deleted() {
		$this->_setRole( 'shop_manager' );
		$term = wp_insert_term( 'Bygone', 'product_brand' );

		$response = $this->do_ajax(
			'storesuite_delete_product_brand',
			array( 'id' => $term['term_id'] ),
			'_storesuite_delete_nonce_',
			'storesuite_delete_product_brand_nonce'
		);

		$this->assertTrue( $response['success'] );
		$this->assertNull( get_term( $term['term_id'], 'product_brand' ) );
	}

	public function test_customer_cannot_create_brands() {
		$this->_setRole( 'subscriber' );

		$response = $this->do_ajax(
			'storesuite_add_product_brand',
			array( 'product_brand_name' => 'Forbidden' ),
			'_storesuite_add_product_brand_',
			'storesuite_add_product_brand_nonce'
		);

		$this->assertFalse( $response['success'] );
		$this->assertFalse( get_term_by( 'slug', 'forbidden', 'product_brand' ) );
	}
}
