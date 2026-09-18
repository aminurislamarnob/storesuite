<?php
/**
 * WooCommerce product factory for StoreSuite tests.
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test\Factories;

/**
 * Creates WooCommerce products through the WC CRUD layer.
 *
 * Not a WP_UnitTest_Factory_For_Thing: products are WC_Data objects, not bare
 * posts, and tests want the saved WC_Product instance back rather than an ID.
 */
class ProductFactory {

	/**
	 * Name sequence for products created without an explicit name.
	 *
	 * @var int
	 */
	protected $sequence = 0;

	/**
	 * Product classes by type slug.
	 *
	 * @var array<string, class-string>
	 */
	protected $type_classes = array(
		'simple'   => \WC_Product_Simple::class,
		'external' => \WC_Product_External::class,
		'grouped'  => \WC_Product_Grouped::class,
		'variable' => \WC_Product_Variable::class,
	);

	/**
	 * Create and save a product.
	 *
	 * @param array $args {
	 *     Optional product arguments.
	 *
	 *     @type string     $type             Product type slug. Default 'simple'.
	 *     @type string     $name             Product name. Default auto-generated.
	 *     @type string     $regular_price    Regular price; '' skips setting one. Default '10'.
	 *     @type string     $sale_price       Sale price.
	 *     @type string     $sku              SKU.
	 *     @type string     $status           Post status, e.g. 'draft'. Default 'publish' (WC default).
	 *     @type bool       $manage_stock     Whether to manage stock. Default false.
	 *     @type int        $stock_quantity   Stock quantity when stock is managed. Default 5.
	 *     @type int|string $low_stock_amount Per-product low stock threshold.
	 * }
	 * @return \WC_Product Saved product.
	 */
	public function create( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'type'          => 'simple',
				'name'          => '',
				'regular_price' => '10',
			)
		);

		$class   = isset( $this->type_classes[ $args['type'] ] ) ? $this->type_classes[ $args['type'] ] : \WC_Product_Simple::class;
		$product = new $class();

		$product->set_name( '' !== $args['name'] ? $args['name'] : sprintf( 'Test Product %d', ++$this->sequence ) );

		// Grouped and variable products derive their price from children.
		if ( '' !== $args['regular_price'] && ! in_array( $args['type'], array( 'grouped', 'variable' ), true ) ) {
			$product->set_regular_price( $args['regular_price'] );
		}

		if ( isset( $args['sale_price'] ) && '' !== $args['sale_price'] ) {
			$product->set_sale_price( $args['sale_price'] );
		}

		if ( isset( $args['sku'] ) ) {
			$product->set_sku( $args['sku'] );
		}

		if ( isset( $args['status'] ) ) {
			$product->set_status( $args['status'] );
		}

		if ( ! empty( $args['manage_stock'] ) ) {
			$product->set_manage_stock( true );
			$product->set_stock_quantity( isset( $args['stock_quantity'] ) ? $args['stock_quantity'] : 5 );
		}

		if ( isset( $args['low_stock_amount'] ) && null !== $args['low_stock_amount'] ) {
			$product->set_low_stock_amount( $args['low_stock_amount'] );
		}

		$product->save();

		return $product;
	}
}
