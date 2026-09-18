<?php
/**
 * CouponManager tests: coupon create/update/delete against real WC_Coupon
 * objects, including status/visibility mapping and lifecycle actions.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Tests\Coupon;

use PluginizeLab\StoreSuite\Coupon\CouponManager;
use WC_Coupon;
use WP_UnitTestCase;

/**
 * Tests for PluginizeLab\StoreSuite\Coupon\CouponManager.
 */
class CouponManagerTest extends WP_UnitTestCase {

	/**
	 * Manager under test.
	 *
	 * @var CouponManager
	 */
	private $manager;

	/**
	 * Fresh manager per test.
	 */
	public function set_up() {
		parent::set_up();
		$this->manager = new CouponManager();
	}

	/**
	 * Minimal valid coupon payload; callers override what they care about.
	 *
	 * @param array $overrides Fields to override.
	 * @return array
	 */
	private function coupon_data( array $overrides = array() ) {
		return array_merge(
			array(
				'coupon_code'   => 'save10',
				'discount_type' => 'percent',
				'coupon_amount' => '10',
				'description'   => 'Ten percent off',
			),
			$overrides
		);
	}

	public function test_create_coupon_persists_core_properties() {
		$coupon_id = $this->manager->create_coupon(
			$this->coupon_data(
				array(
					'usage_limit'    => '5',
					'free_shipping'  => 1,
					'customer_email' => 'a@example.com, b@example.com',
					'minimum_amount' => '25',
				)
			)
		);

		$this->assertIsInt( $coupon_id );
		$this->assertGreaterThan( 0, $coupon_id );

		$coupon = new WC_Coupon( $coupon_id );
		$this->assertSame( 'save10', $coupon->get_code() );
		$this->assertSame( 'percent', $coupon->get_discount_type() );
		$this->assertSame( '10', $coupon->get_amount() );
		$this->assertSame( 'Ten percent off', $coupon->get_description() );
		$this->assertSame( 5, $coupon->get_usage_limit() );
		$this->assertTrue( $coupon->get_free_shipping() );
		$this->assertSame( array( 'a@example.com', 'b@example.com' ), $coupon->get_email_restrictions() );
		$this->assertSame( '25', $coupon->get_minimum_amount() );
		$this->assertSame( 'publish', get_post_status( $coupon_id ), 'Default status is publish.' );
	}

	public function test_create_coupon_normalizes_the_code_like_woocommerce() {
		$coupon_id = $this->manager->create_coupon( $this->coupon_data( array( 'coupon_code' => 'SAVE10' ) ) );

		$this->assertSame( wc_format_coupon_code( 'SAVE10' ), ( new WC_Coupon( $coupon_id ) )->get_code() );
	}

	public function test_create_coupon_fires_the_created_action() {
		$fired = 0;
		add_action(
			'storesuite_new_coupon_created',
			function () use ( &$fired ) {
				++$fired;
			}
		);

		$this->manager->create_coupon( $this->coupon_data() );

		$this->assertSame( 1, $fired );
	}

	public function test_private_visibility_wins_over_status() {
		$coupon_id = $this->manager->create_coupon(
			$this->coupon_data(
				array(
					'coupon_status'     => 'publish',
					'coupon_visibility' => 'private',
				)
			)
		);

		$this->assertSame( 'private', get_post_status( $coupon_id ) );
	}

	public function test_unrecognized_status_and_visibility_fall_back_to_defaults() {
		$coupon_id = $this->manager->create_coupon(
			$this->coupon_data(
				array(
					'coupon_status'     => 'totally-bogus',
					'coupon_visibility' => 'also-bogus',
				)
			)
		);

		$this->assertSame( 'publish', get_post_status( $coupon_id ) );
	}

	public function test_draft_status_is_respected() {
		$coupon_id = $this->manager->create_coupon( $this->coupon_data( array( 'coupon_status' => 'draft' ) ) );

		$this->assertSame( 'draft', get_post_status( $coupon_id ) );
	}

	public function test_update_coupon_changes_properties_and_fires_action() {
		$coupon_id = $this->manager->create_coupon( $this->coupon_data() );

		$fired = 0;
		add_action(
			'storesuite_coupon_updated',
			function () use ( &$fired ) {
				++$fired;
			}
		);

		$result = $this->manager->update_coupon(
			$coupon_id,
			$this->coupon_data(
				array(
					'coupon_code'   => 'save20',
					'coupon_amount' => '20',
				)
			)
		);

		$this->assertTrue( $result );
		$this->assertSame( 1, $fired );

		$coupon = new WC_Coupon( $coupon_id );
		$this->assertSame( 'save20', $coupon->get_code() );
		$this->assertSame( '20', $coupon->get_amount() );
	}

	public function test_update_unknown_coupon_returns_wp_error() {
		$result = $this->manager->update_coupon( 999999, $this->coupon_data() );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_coupon', $result->get_error_code() );
	}

	public function test_delete_coupon_trashes_by_default() {
		$coupon_id = $this->manager->create_coupon( $this->coupon_data() );

		$this->assertTrue( $this->manager->delete_coupon( $coupon_id ) );
		$this->assertSame( 'trash', get_post_status( $coupon_id ) );
	}

	public function test_delete_coupon_force_removes_the_post() {
		$coupon_id = $this->manager->create_coupon( $this->coupon_data() );

		$this->assertTrue( $this->manager->delete_coupon( $coupon_id, true ) );
		$this->assertFalse( get_post_status( $coupon_id ) );
	}

	public function test_delete_unknown_coupon_returns_wp_error() {
		$result = $this->manager->delete_coupon( 999999 );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_coupon', $result->get_error_code() );
	}
}
