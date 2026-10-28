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

	public function test_created_hook_fires_with_the_insert_result() {
		$this->_setRole( 'shop_manager' );

		$captured = null;
		add_action(
			'storesuite_product_tag_created',
			function ( $new_tag ) use ( &$captured ) {
				$captured = $new_tag;
			}
		);

		$response = $this->do_ajax(
			'storesuite_add_product_tag',
			array( 'name' => 'Autumn' ),
			'_storesuite_add_product_tag_',
			'storesuite_add_product_tag_nonce'
		);

		$this->assertTrue( $response['success'] );
		$this->assertIsArray( $captured );

		$term = get_term_by( 'slug', 'autumn', 'product_tag' );
		$this->assertSame( $term->term_id, (int) $captured['term_id'] );
		$this->assertArrayHasKey( 'term_taxonomy_id', $captured );
	}

	public function test_updated_hook_fires_with_the_update_result() {
		$this->_setRole( 'shop_manager' );
		$term = wp_insert_term( 'Spring', 'product_tag' );

		$captured = null;
		add_action(
			'storesuite_product_tag_updated',
			function ( $updated_tag ) use ( &$captured ) {
				$captured = $updated_tag;
			}
		);

		$response = $this->do_ajax(
			'storesuite_edit_product_tag',
			array(
				'tag_id' => $term['term_id'],
				'name'   => 'Springtime',
			),
			'_storesuite_edit_product_tag_',
			'storesuite_edit_product_tag_nonce'
		);

		$this->assertTrue( $response['success'] );
		$this->assertIsArray( $captured );
		$this->assertSame( $term['term_id'], (int) $captured['term_id'] );
		$this->assertArrayHasKey( 'term_taxonomy_id', $captured );
	}

	public function test_deleted_hook_fires_with_the_term_id() {
		$this->_setRole( 'shop_manager' );
		$term = wp_insert_term( 'Ephemeral', 'product_tag' );

		$captured = null;
		add_action(
			'storesuite_product_tag_deleted',
			function ( $tag_id ) use ( &$captured ) {
				$captured = $tag_id;
			}
		);

		$response = $this->do_ajax(
			'storesuite_delete_product_tag',
			array( 'id' => $term['term_id'] ),
			'_storesuite_delete_nonce_',
			'storesuite_delete_product_tag_nonce'
		);

		$this->assertTrue( $response['success'] );
		$this->assertSame( $term['term_id'], $captured );
	}

	public function test_lifecycle_hooks_do_not_fire_on_failed_requests() {
		$this->_setRole( 'shop_manager' );
		wp_insert_term( 'Taken', 'product_tag', array( 'slug' => 'taken' ) );

		$fired = 0;
		add_action(
			'storesuite_product_tag_created',
			function () use ( &$fired ) {
				++$fired;
			}
		);

		$response = $this->do_ajax(
			'storesuite_add_product_tag',
			array(
				'name' => 'Taken Again',
				'slug' => 'taken',
			),
			'_storesuite_add_product_tag_',
			'storesuite_add_product_tag_nonce'
		);

		$this->assertFalse( $response['success'] );
		$this->assertSame( 0, $fired );
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
