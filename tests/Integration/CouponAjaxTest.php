<?php
/**
 * Coupon AJAX integration tests: the add / delete form flow through
 * wp_ajax_storesuite_add_coupon and wp_ajax_storesuite_delete_coupon,
 * asserting on the WooCommerce coupon that actually lands in the database.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Tests\Integration;

use WC_Coupon;

/**
 * Exercises CouponController + CouponManager end to end.
 */
class CouponAjaxTest extends StoreSuiteAjaxTestCase {

	/**
	 * Log in as a shop manager — the primary dashboard persona.
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
	}

	public function test_add_coupon_creates_a_woocommerce_coupon_with_the_posted_fields() {
		$response = $this->dispatch(
			'storesuite_add_coupon',
			$this->nonce_field( 'add_coupon' ) + array(
				'coupon_code'    => 'SUMMER15',
				'discount_type'  => 'percent',
				'coupon_amount'  => '15',
				'description'    => 'Summer sale.',
				'free_shipping'  => 'on',
				'usage_limit'    => '100',
				'minimum_amount' => '50',
			)
		);

		$this->assertTrue( $response['success'], 'Coupon creation must succeed: ' . wp_json_encode( $response ) );

		$coupon_id = $response['data']['coupon_id'];
		$this->assertSame( $coupon_id, wc_get_coupon_id_by_code( 'summer15' ), 'Code is stored in WooCommerce-normalized form.' );

		$coupon = new WC_Coupon( $coupon_id );
		$this->assertSame( 'percent', $coupon->get_discount_type() );
		$this->assertSame( 15.0, (float) $coupon->get_amount() );
		$this->assertSame( 'Summer sale.', $coupon->get_description() );
		$this->assertTrue( $coupon->get_free_shipping() );
		$this->assertSame( 100, $coupon->get_usage_limit() );
		$this->assertSame( 50.0, (float) $coupon->get_minimum_amount() );
	}

	public function test_add_coupon_rejects_a_duplicate_code() {
		$this->dispatch(
			'storesuite_add_coupon',
			$this->nonce_field( 'add_coupon' ) + array(
				'coupon_code'   => 'ONCE',
				'discount_type' => 'percent',
				'coupon_amount' => '5',
			)
		);

		$response = $this->dispatch(
			'storesuite_add_coupon',
			$this->nonce_field( 'add_coupon' ) + array(
				'coupon_code'   => 'once',
				'discount_type' => 'percent',
				'coupon_amount' => '10',
			)
		);

		$this->assertFalse( $response['success'], 'Codes are case-normalized; "once" duplicates "ONCE".' );
		$this->assertStringContainsString( 'already exists', $response['data']['error'] );
	}

	public function test_add_coupon_requires_a_code() {
		$response = $this->dispatch(
			'storesuite_add_coupon',
			$this->nonce_field( 'add_coupon' )
		);

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Coupon code is required.', $response['data']['error'] );
	}

	public function test_add_coupon_requires_manage_woocommerce() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'customer' ) ) );

		$response = $this->dispatch(
			'storesuite_add_coupon',
			$this->nonce_field( 'add_coupon' ) + array( 'coupon_code' => 'NOPE' )
		);

		$this->assertFalse( $response['success'] );
		$this->assertSame( 0, wc_get_coupon_id_by_code( 'nope' ) );
	}

	public function test_delete_coupon_soft_deletes_to_trash() {
		$coupon = new WC_Coupon();
		$coupon->set_code( 'TRASHME' );
		$coupon->set_discount_type( 'percent' );
		$coupon->set_amount( 5 );
		$coupon->save();

		$response = $this->dispatch(
			'storesuite_delete_coupon',
			$this->nonce_field( 'delete_coupon' ) + array( 'coupon_id' => $coupon->get_id() )
		);

		$this->assertTrue( $response['success'] );
		$this->assertSame( 'trash', get_post_status( $coupon->get_id() ), 'Dashboard delete is a soft delete.' );
	}

	public function test_delete_coupon_requires_a_coupon_id() {
		$response = $this->dispatch(
			'storesuite_delete_coupon',
			$this->nonce_field( 'delete_coupon' )
		);

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Invalid coupon ID.', $response['data']['error'] );
	}
}
