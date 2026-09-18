<?php
/**
 * Tests for the coupon CRUD AJAX endpoints.
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test\Coupon;

use PluginizeLab\StoreSuite\Test\StoreSuiteAjaxTestCase;

/**
 * @covers \PluginizeLab\StoreSuite\Coupon\CouponController
 * @covers \PluginizeLab\StoreSuite\Coupon\CouponManager
 * @group storesuite-coupon
 * @group storesuite-ajax
 */
class CouponAjaxTest extends StoreSuiteAjaxTestCase {

	/**
	 * Dispatch the add-coupon AJAX action.
	 *
	 * @param array $fields Request fields.
	 * @return array Decoded response.
	 */
	private function add_coupon_ajax( $fields ) {
		return $this->do_ajax( 'storesuite_add_coupon', $fields, '_storesuite_add_coupon_', 'storesuite_add_coupon_nonce' );
	}

	/**
	 * Dispatch the edit-coupon AJAX action.
	 *
	 * @param array $fields Request fields.
	 * @return array Decoded response.
	 */
	private function edit_coupon_ajax( $fields ) {
		return $this->do_ajax( 'storesuite_edit_coupon', $fields, '_storesuite_edit_coupon_', 'storesuite_edit_coupon_nonce' );
	}

	public function test_shop_manager_can_create_a_coupon() {
		$this->_setRole( 'shop_manager' );

		$response = $this->add_coupon_ajax(
			array(
				'coupon_code'   => 'SAVE10',
				'discount_type' => 'percent',
				'coupon_amount' => '10',
				'description'   => 'Ten percent off',
			)
		);

		$this->assertTrue( $response['success'] );

		$coupon_id = $response['data']['coupon_id'];
		$this->assertSame( $coupon_id, wc_get_coupon_id_by_code( 'save10' ), 'Coupon codes are stored in WooCommerce-normalized form.' );

		$coupon = new \WC_Coupon( $coupon_id );
		$this->assertSame( 'percent', $coupon->get_discount_type() );
		$this->assertSame( 10.0, (float) $coupon->get_amount() );
		$this->assertSame( 'publish', get_post_status( $coupon_id ), 'Coupons default to published.' );
	}

	public function test_duplicate_coupon_code_is_rejected() {
		$this->_setRole( 'shop_manager' );
		self::factory()->coupon->create( array( 'code' => 'twice' ) );

		$response = $this->add_coupon_ajax(
			array(
				'coupon_code'   => 'TWICE',
				'discount_type' => 'percent',
				'coupon_amount' => '10',
			)
		);

		$this->assertFalse( $response['success'] );
	}

	public function test_invalid_discount_type_is_rejected() {
		$this->_setRole( 'shop_manager' );

		$response = $this->add_coupon_ajax(
			array(
				'coupon_code'   => 'BROKEN',
				'discount_type' => 'mega_discount',
				'coupon_amount' => '10',
			)
		);

		$this->assertFalse( $response['success'] );
		$this->assertSame( 0, wc_get_coupon_id_by_code( 'broken' ), 'A rejected coupon must not be created.' );
	}

	public function test_customer_cannot_create_a_coupon() {
		$this->_setRole( 'subscriber' );

		$response = $this->add_coupon_ajax(
			array(
				'coupon_code'   => 'NOPE',
				'discount_type' => 'percent',
				'coupon_amount' => '10',
			)
		);

		$this->assertFalse( $response['success'] );
		$this->assertSame( 0, wc_get_coupon_id_by_code( 'nope' ) );
	}

	public function test_edit_updates_amount_and_status() {
		$this->_setRole( 'shop_manager' );
		$coupon = self::factory()->coupon->create( array( 'code' => 'editable' ) );

		$response = $this->edit_coupon_ajax(
			array(
				'coupon_id'     => $coupon->get_id(),
				'coupon_amount' => '25',
				'coupon_status' => 'draft',
			)
		);

		$this->assertTrue( $response['success'] );

		$updated = new \WC_Coupon( $coupon->get_id() );
		$this->assertSame( 25.0, (float) $updated->get_amount() );
		$this->assertSame( 'draft', get_post_status( $coupon->get_id() ) );
	}

	public function test_private_visibility_sets_private_post_status() {
		$this->_setRole( 'shop_manager' );
		$coupon = self::factory()->coupon->create( array( 'code' => 'hidden' ) );

		$response = $this->edit_coupon_ajax(
			array(
				'coupon_id'         => $coupon->get_id(),
				'coupon_status'     => 'publish',
				'coupon_visibility' => 'private',
			)
		);

		$this->assertTrue( $response['success'] );
		$this->assertSame( 'private', get_post_status( $coupon->get_id() ) );
	}

	public function test_delete_moves_coupon_to_trash() {
		$this->_setRole( 'shop_manager' );
		$coupon = self::factory()->coupon->create( array( 'code' => 'goner' ) );

		$response = $this->do_ajax(
			'storesuite_delete_coupon',
			array( 'coupon_id' => $coupon->get_id() ),
			'_storesuite_delete_coupon_',
			'storesuite_delete_coupon_nonce'
		);

		$this->assertTrue( $response['success'] );
		$this->assertSame( 'trash', get_post_status( $coupon->get_id() ), 'The dashboard delete is a soft delete.' );
	}
}
