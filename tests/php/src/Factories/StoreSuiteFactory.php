<?php
/**
 * StoreSuite test factory aggregate.
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test\Factories;

/**
 * Extends the core factory with WooCommerce entity factories.
 *
 * Wired in through the factory() override on the StoreSuite base test cases,
 * so tests read `self::factory()->product->create( array( ... ) )` while every
 * core factory (post, user, term, ...) keeps working unchanged.
 */
class StoreSuiteFactory extends \WP_UnitTest_Factory {

	/**
	 * Product factory.
	 *
	 * @var ProductFactory
	 */
	public $product;

	/**
	 * Coupon factory.
	 *
	 * @var CouponFactory
	 */
	public $coupon;

	/**
	 * Order factory.
	 *
	 * @var OrderFactory
	 */
	public $order;

	/**
	 * The constructor.
	 */
	public function __construct() {
		parent::__construct();

		$this->product = new ProductFactory();
		$this->coupon  = new CouponFactory();
		$this->order   = new OrderFactory();
	}
}
