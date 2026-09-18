<?php
/**
 * WooCommerce coupon factory for StoreSuite tests.
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test\Factories;

/**
 * Creates WooCommerce coupons through the WC CRUD layer.
 */
class CouponFactory {

	/**
	 * Code sequence for coupons created without an explicit code.
	 *
	 * @var int
	 */
	protected $sequence = 0;

	/**
	 * Create and save a coupon.
	 *
	 * @param array $args {
	 *     Optional coupon arguments.
	 *
	 *     @type string $code          Coupon code. Default auto-generated.
	 *     @type string $amount        Discount amount. Default '10'.
	 *     @type string $discount_type Discount type. Default 'fixed_cart'.
	 * }
	 * @return \WC_Coupon Saved coupon.
	 */
	public function create( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'code'          => '',
				'amount'        => '10',
				'discount_type' => 'fixed_cart',
			)
		);

		$coupon = new \WC_Coupon();
		$coupon->set_code( '' !== $args['code'] ? $args['code'] : sprintf( 'test-coupon-%d', ++$this->sequence ) );
		$coupon->set_amount( $args['amount'] );
		$coupon->set_discount_type( $args['discount_type'] );
		$coupon->save();

		return $coupon;
	}
}
