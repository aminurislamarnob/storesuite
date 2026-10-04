<?php
/**
 * Tests for the duplicate-product row action (Tier 2, issue #190).
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test\Product;

use PluginizeLab\StoreSuite\Test\StoreSuiteAjaxTestCase;

/**
 * @covers \PluginizeLab\StoreSuite\Product\ProductController::handle_duplicate_product
 * @covers \PluginizeLab\StoreSuite\Product\ProductManager::duplicate_product
 * @group storesuite-product
 * @group storesuite-ajax
 */
class ProductDuplicateTest extends StoreSuiteAjaxTestCase {

	const ACTION = 'storesuite_duplicate_product';
	const NONCE  = '_storesuite_duplicate_nonce_';

	public function test_shop_manager_gets_a_draft_copy_and_its_edit_url() {
		$this->_setRole( 'shop_manager' );
		$this->create_dashboard_page();
		$source = self::factory()->product->create(
			array(
				'name'          => 'Blue Hoodie',
				'sku'           => 'HOOD-1',
				'regular_price' => '40',
			)
		);

		$response = $this->do_ajax( self::ACTION, array( 'id' => $source->get_id() ), self::NONCE );

		$this->assertTrue( $response['success'], wp_json_encode( $response ) );

		$copy_id = (int) $response['data']['id'];
		$this->assertNotSame( $source->get_id(), $copy_id );
		$this->assertStringEndsWith( '/edit-product/' . $copy_id, untrailingslashit( $response['data']['redirect'] ) );

		$copy = wc_get_product( $copy_id );
		$this->assertSame( 'draft', $copy->get_status(), 'Copies are drafts until reviewed.' );
		$this->assertSame( 'Blue Hoodie (Copy)', $copy->get_name() );
		$this->assertSame( '40', $copy->get_regular_price() );
		$this->assertNotSame( 'HOOD-1', $copy->get_sku(), 'SKU must stay unique.' );
		$this->assertStringStartsWith( 'HOOD-1', $copy->get_sku() );

		$this->assertSame( 'publish', wc_get_product( $source->get_id() )->get_status(), 'The source is untouched.' );
	}

	public function test_unknown_product_is_rejected() {
		$this->_setRole( 'shop_manager' );

		$response = $this->do_ajax( self::ACTION, array( 'id' => 999999 ), self::NONCE );

		$this->assertFalse( $response['success'] );
	}

	public function test_customer_is_denied() {
		$this->_setRole( 'subscriber' );
		$source = self::factory()->product->create();

		$response = $this->do_ajax( self::ACTION, array( 'id' => $source->get_id() ), self::NONCE );

		$this->assertFalse( $response['success'] );
		$this->assertCount( 1, wc_get_products( array( 'status' => 'any', 'return' => 'ids', 'limit' => -1 ) ), 'Nothing was duplicated.' );
	}
}
