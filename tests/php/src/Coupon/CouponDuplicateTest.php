<?php
/**
 * Tests for the duplicate-coupon row action (Tier 2, issue #190).
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test\Coupon;

use PluginizeLab\StoreSuite\Test\StoreSuiteAjaxTestCase;

/**
 * @covers \PluginizeLab\StoreSuite\Coupon\CouponController::handle_duplicate_coupon
 * @covers \PluginizeLab\StoreSuite\Coupon\CouponManager::duplicate_coupon
 * @group storesuite-coupon
 * @group storesuite-ajax
 */
class CouponDuplicateTest extends StoreSuiteAjaxTestCase {

	const ACTION = 'storesuite_duplicate_coupon';
	const NONCE  = '_storesuite_duplicate_nonce_';

	public function test_copy_is_a_draft_with_a_suffixed_code_and_reset_usage() {
		$this->_setRole( 'shop_manager' );
		$this->create_dashboard_page();
		$source = self::factory()->coupon->create(
			array(
				'code'   => 'welcome10',
				'amount' => '10',
			)
		);
		$source->set_discount_type( 'percent' );
		$source->set_usage_limit( 5 );
		$source->set_usage_count( 3 );
		$source->set_email_restrictions( array( 'vip@example.org' ) );
		$source->save();

		$response = $this->do_ajax( self::ACTION, array( 'id' => $source->get_id() ), self::NONCE );

		$this->assertTrue( $response['success'], wp_json_encode( $response ) );

		$copy_id = (int) $response['data']['id'];
		$this->assertStringEndsWith( '/edit-coupon/' . $copy_id, untrailingslashit( $response['data']['redirect'] ) );

		$copy = new \WC_Coupon( $copy_id );
		$this->assertSame( 'welcome10-copy', $copy->get_code() );
		$this->assertSame( 'draft', get_post_status( $copy_id ) );
		$this->assertSame( 'percent', $copy->get_discount_type() );
		$this->assertSame( 10.0, (float) $copy->get_amount() );
		$this->assertSame( 5, $copy->get_usage_limit() );
		$this->assertSame( 0, $copy->get_usage_count(), 'Usage counters start fresh.' );
		$this->assertSame( array( 'vip@example.org' ), $copy->get_email_restrictions() );
	}

	public function test_repeated_duplication_numbers_the_code() {
		$this->_setRole( 'shop_manager' );
		$source = self::factory()->coupon->create( array( 'code' => 'spring' ) );

		$first  = $this->do_ajax( self::ACTION, array( 'id' => $source->get_id() ), self::NONCE );
		$second = $this->do_ajax( self::ACTION, array( 'id' => $source->get_id() ), self::NONCE );

		$this->assertSame( 'spring-copy', ( new \WC_Coupon( $first['data']['id'] ) )->get_code() );
		$this->assertSame( 'spring-copy-2', ( new \WC_Coupon( $second['data']['id'] ) )->get_code() );
	}

	public function test_non_coupon_post_is_rejected() {
		$this->_setRole( 'shop_manager' );
		$product = self::factory()->product->create();

		$response = $this->do_ajax( self::ACTION, array( 'id' => $product->get_id() ), self::NONCE );

		$this->assertFalse( $response['success'] );
	}

	public function test_customer_is_denied() {
		$this->_setRole( 'subscriber' );
		$source = self::factory()->coupon->create( array( 'code' => 'nope' ) );

		$response = $this->do_ajax( self::ACTION, array( 'id' => $source->get_id() ), self::NONCE );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 0, wc_get_coupon_id_by_code( 'nope-copy' ) );
	}
}
