<?php

/**
 * HandlePaginations class.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HandlePaginations class.
 *
 * Handles pagination functionality of the plugin.
 */
class HandlePaginations {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'storesuite_products_per_page', array( $this, 'set_products_per_page' ) );
		add_filter( 'storesuite_inventory_per_page', array( $this, 'set_inventory_per_page' ) );
		add_filter( 'storesuite_orders_per_page', array( $this, 'set_orders_per_page' ) );
		add_filter( 'storesuite_categories_per_page', array( $this, 'set_category_per_page' ) );
		add_filter( 'storesuite_tags_per_page', array( $this, 'set_tag_per_page' ) );
		add_filter( 'storesuite_brands_per_page', array( $this, 'set_brand_per_page' ) );
		add_filter( 'storesuite_coupons_per_page', array( $this, 'set_coupon_per_page' ) );
	}

	/**
	 * Set products per page.
	 *
	 * @param int $products_per_page The number of products per page.
	 * @return int The number of products per page.
	 */
	public function set_products_per_page( $products_per_page ) {
		$storesuite_product_per_page = (int) storesuite_get_option_by_key( 'storesuite_product_per_page' );
		if ( $storesuite_product_per_page > 0 ) {
			return (int) $storesuite_product_per_page;
		}

		return $products_per_page;
	}

	/**
	 * Set orders per page.
	 *
	 * @param int $orders_per_page The number of orders per page.
	 * @return int The number of orders per page.
	 */
	public function set_orders_per_page( $orders_per_page ) {
		$storesuite_order_per_page = (int) storesuite_get_option_by_key( 'storesuite_order_per_page' );
		if ( $storesuite_order_per_page > 0 ) {
			return (int) $storesuite_order_per_page;
		}

		return $orders_per_page;
	}

	/**
	 * Set categories per page.
	 *
	 * @param int $category_per_page The number of categories per page.
	 * @return int The number of categories per page.
	 */
	public function set_category_per_page( $category_per_page ) {
		$storesuite_category_per_page = (int) storesuite_get_option_by_key( 'storesuite_category_per_page' );
		if ( $storesuite_category_per_page > 0 ) {
			return (int) $storesuite_category_per_page;
		}

		return $category_per_page;
	}

	/**
	 * Set products tags per page.
	 *
	 * @param int $tags_per_page The number of tags per page.
	 * @return int The number of tags per page.
	 */
	public function set_tag_per_page( $tags_per_page ) {
		$storesuite_tag_per_page = (int) storesuite_get_option_by_key( 'storesuite_tag_per_page' );
		if ( $storesuite_tag_per_page > 0 ) {
			return (int) $storesuite_tag_per_page;
		}

		return $tags_per_page;
	}

	/**
	 * Set products brands per page.
	 *
	 * @param int $brand_per_page The number of brands per page.
	 * @return int The number of brands per page.
	 */
	public function set_brand_per_page( $brand_per_page ) {
		$storesuite_brand_per_page = (int) storesuite_get_option_by_key( 'storesuite_brand_per_page' );
		if ( $storesuite_brand_per_page > 0 ) {
			return (int) $storesuite_brand_per_page;
		}

		return $brand_per_page;
	}

	/**
	 * Set products brands per page.
	 *
	 * @param int $brand_per_page The number of brands per page.
	 * @return int The number of brands per page.
	 */
	public function set_coupon_per_page( $coupons_per_page ) {
		$storesuite_coupon_per_page = (int) storesuite_get_option_by_key( 'storesuite_coupon_per_page' );
		if ( $storesuite_coupon_per_page > 0 ) {
			return (int) $storesuite_coupon_per_page;
		}

		return $coupons_per_page;
	}

	/**
	 * Set inventory items per page.
	 *
	 * @param int $inventory_per_page The number of inventory rows per page.
	 * @return int The number of inventory rows per page.
	 */
	public function set_inventory_per_page( $inventory_per_page ) {
		$storesuite_inventory_per_page = (int) storesuite_get_option_by_key( 'storesuite_inventory_per_page' );
		if ( $storesuite_inventory_per_page > 0 ) {
			return (int) $storesuite_inventory_per_page;
		}

		return $inventory_per_page;
	}
}
