<?php

namespace PluginizeLab\StoreSuite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rewrites {
	/**
	 * Query vars to add to wp.
	 *
	 * @var array
	 */
	public $query_vars = array();

	/**
	 * Store base slug
	 *
	 * @var string
	 */
	public $store_front_base = '';

	/**
	 * Hook into the functions
	 */
	public function __construct() {
		$this->store_front_base = $this->get_dashboard_page_slug();
		add_action( 'init', array( $this, 'add_endpoints' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );
		add_filter( 'woocommerce_get_query_vars', array( $this, 'resolve_wocommerce_my_acc_query_conflict' ) );
		$this->init_query_vars();
	}


	/**
	 * Init query vars by loading options.
	 */
	public function init_query_vars() {
		// Query vars to add to WP.
		$this->query_vars = apply_filters(
			'storesuite_query_var_filter',
			array(
				'analytics'        => get_option( 'storesuite_myshop_analytics_endpoint', 'analytics' ),
				'products'         => get_option( 'storesuite_myshop_products_endpoint', 'products' ),
				'import-products'  => get_option( 'storesuite_myshop_import_product_endpoint', 'import-products' ),
				'add-new-product'  => get_option( 'storesuite_myshop_new_product_endpoint', 'add-new-product' ),
				'edit-product'     => get_option( 'storesuite_myshop_edit_product_endpoint', 'edit-product' ),
				'orders'           => get_option( 'storesuite_myshop_orders_endpoint', 'orders' ),
				'add-new-order'    => get_option( 'storesuite_myshop_new_order_endpoint', 'add-new-order' ),
				'edit-order'       => get_option( 'storesuite_myshop_edit_order_endpoint', 'edit-order' ),
				'order-details'    => get_option( 'storesuite_myshop_order_details_endpoint', 'order-details' ),
				'categories'       => get_option( 'storesuite_myshop_categories_endpoint', 'categories' ),
				'add-new-category' => get_option( 'storesuite_myshop_new_category_endpoint', 'add-new-category' ),
				'edit-category'    => get_option( 'storesuite_myshop_edit_category_endpoint', 'edit-category' ),
				'tags'             => get_option( 'storesuite_myshop_tags_endpoint', 'tags' ),
				'add-new-tag'      => get_option( 'storesuite_myshop_new_tag_endpoint', 'add-new-tag' ),
				'edit-tag'         => get_option( 'storesuite_myshop_edit_tag_endpoint', 'edit-tag' ),
				'brands'           => get_option( 'storesuite_myshop_brands_endpoint', 'brands' ),
				'add-new-brand'    => get_option( 'storesuite_myshop_new_brand_endpoint', 'add-new-brand' ),
				'edit-brand'       => get_option( 'storesuite_myshop_edit_brand_endpoint', 'edit-brand' ),
				'attributes'       => get_option( 'storesuite_myshop_attributes_endpoint', 'attributes' ),
				'add-new-attribute' => get_option( 'storesuite_myshop_new_attribute_endpoint', 'add-new-attribute' ),
				'edit-attribute'   => get_option( 'storesuite_myshop_edit_attribute_endpoint', 'edit-attribute' ),
				'attribute-terms'  => get_option( 'storesuite_myshop_attribute_terms_endpoint', 'attribute-terms' ),
				'coupons'          => get_option( 'storesuite_myshop_coupons_endpoint', 'coupons' ),
				'add-new-coupon'   => get_option( 'storesuite_myshop_new_coupon_endpoint', 'add-new-coupon' ),
				'edit-coupon'      => get_option( 'storesuite_myshop_edit_coupon_endpoint', 'edit-coupon' ),
				'edit-account-details' => get_option( 'storesuite_myshop_edit_account_endpoint', 'edit-account-details' ),
				'notifications'    => get_option( 'storesuite_myshop_notifications_endpoint', 'notifications' ),
			)
		);
	}

	/**
	 * Resolve query var conflicts with WooCommerce My Account
	 *
	 * @param array $query_vars
	 *
	 * @return array
	 */
	public function resolve_wocommerce_my_acc_query_conflict( $query_vars ) {
		global $post;

		$dashboard_id = apply_filters( 'storesuite_get_dashboard_page_id', Helper::storesuite_get_page_id( 'myshopdashboard' ) );

		if ( ! empty( $post->ID ) && apply_filters( 'storesuite_get_current_page_id', $post->ID ) === absint( $dashboard_id ) ) {
			unset( $query_vars['orders'] );
		}

		return $query_vars;
	}

	/**
	 * Get dashboard page slug from the actual page post.
	 *
	 * @return string
	 */
	protected function get_dashboard_page_slug() {
		$page_id = (int) storesuite_get_option_by_key( 'storesuite_dashboard_page_id' );
		if ( ! $page_id ) {
			$page_id = (int) Helper::storesuite_get_page_id( 'myshopdashboard' );
		}
		if ( ! $page_id ) {
			return 'storesuite-dashboard';
		}
		$page = get_post( $page_id );
		if ( $page && 'page' === $page->post_type && ! empty( $page->post_name ) ) {
			return $page->post_name;
		}
		return 'storesuite-dashboard';
	}

	/**
	 * Add endpoints for query vars.
	 */
	public function add_endpoints() {
		$mask = EP_PAGES;

		foreach ( $this->query_vars as $key => $var ) {
			if ( ! empty( $var ) ) {
				add_rewrite_endpoint( $var, $mask );
			}
		}

		// Add rewrite rule for product list pagination.
		add_rewrite_rule(
			$this->store_front_base . '/products/page/([^/]+)/?$',
			'index.php?pagename=' . $this->store_front_base . '&products=1&paged=$matches[1]',
			'top'
		);

		// Add rewrite rule for order list pagination.
		add_rewrite_rule(
			$this->store_front_base . '/orders/page/([^/]+)/?$',
			'index.php?pagename=' . $this->store_front_base . '&orders=1&paged=$matches[1]',
			'top'
		);

		// Add rewrite rule for category list pagination.
		add_rewrite_rule(
			$this->store_front_base . '/categories/page/([^/]+)/?$',
			'index.php?pagename=' . $this->store_front_base . '&categories=1&paged=$matches[1]',
			'top'
		);

		// Add rewrite rule for brand list pagination.
		add_rewrite_rule(
			$this->store_front_base . '/brands/page/([^/]+)/?$',
			'index.php?pagename=' . $this->store_front_base . '&brands=1&paged=$matches[1]',
			'top'
		);

		// Add rewrite rule for tag list pagination.
		add_rewrite_rule(
			$this->store_front_base . '/tags/page/([^/]+)/?$',
			'index.php?pagename=' . $this->store_front_base . '&tags=1&paged=$matches[1]',
			'top'
		);

		// Add rewrite rule for attributes list pagination.
		add_rewrite_rule(
			$this->store_front_base . '/attributes/page/([^/]+)/?$',
			'index.php?pagename=' . $this->store_front_base . '&attributes=1&paged=$matches[1]',
			'top'
		);

		// Add rewrite rule for attribute terms list pagination.
		add_rewrite_rule(
			$this->store_front_base . '/attribute-terms/page/([^/]+)/?$',
			'index.php?pagename=' . $this->store_front_base . '&attribute-terms=1&paged=$matches[1]',
			'top'
		);

		// Add rewrite rule for coupon list pagination.
		add_rewrite_rule(
			$this->store_front_base . '/coupons/page/([^/]+)/?$',
			'index.php?pagename=' . $this->store_front_base . '&coupons=1&paged=$matches[1]',
			'top'
		);

		// Add rewrite rule for notification list pagination.
		add_rewrite_rule(
			$this->store_front_base . '/notifications/page/([^/]+)/?$',
			'index.php?pagename=' . $this->store_front_base . '&notifications=1&paged=$matches[1]',
			'top'
		);

		// Add rewrite rule for edit-account endpoint.
		$edit_account_slug = isset( $this->query_vars['edit-account-details'] ) ? $this->query_vars['edit-account-details'] : 'edit-account-details';
		add_rewrite_rule(
			$this->store_front_base . '/' . $edit_account_slug . '/?$',
			'index.php?pagename=' . $this->store_front_base . '&' . $edit_account_slug . '=1',
			'top'
		);
	}

	/**
	 * Add query vars.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		foreach ( $this->query_vars as $key => $var ) {
			$vars[] = $key;
		}
		return $vars;
	}

	/**
	 * Get page title for an endpoint.
	 *
	 * @param string $endpoint Endpoint key.
	 * @param string $action Optional action or variation within the endpoint.
	 * @return string The page title.
	 */
	public function get_endpoint_title( $endpoint, $action = '' ) {
		global $wp;

		switch ( $endpoint ) {
			case 'products':
				$title = __( 'All Products', 'storesuite' );
				break;
			case 'add-new-product':
				$title = __( 'Add New Product', 'storesuite' );
				break;
			case 'edit-product':
				$title = __( 'Edit Product', 'storesuite' );
				break;
			case 'orders':
				$title = __( 'Orders', 'storesuite' );
				break;
			case 'add-new-order':
				$title = __( 'Add New Order', 'storesuite' );
				break;
			case 'edit-order':
				$order = wc_get_order( $wp->query_vars['edit-order'] );
				/* translators: %s: order number */
				$title = $order ? sprintf( __( 'Edit Order #%s', 'storesuite' ), $order->get_order_number() ) : __( 'Edit Order', 'storesuite' );
				break;
			case 'order-details':
				$order = wc_get_order( $wp->query_vars['order-details'] );
				/* translators: %s: order number */
				$title = ( $order ) ? sprintf( __( 'Order #%s', 'storesuite' ), $order->get_order_number() ) : '';
				break;
			case 'categories':
				$title = __( 'Categories', 'storesuite' );
				break;
			case 'add-new-category':
				$title = __( 'Add New Category', 'storesuite' );
				break;
			case 'edit-category':
				$title = __( 'Edit Product Category', 'storesuite' );
				break;
			case 'tags':
				$title = __( 'Tags', 'storesuite' );
				break;
			case 'add-new-tag':
				$title = __( 'Add New Tag', 'storesuite' );
				break;
			case 'edit-tag':
				$title = __( 'Edit Product Tag', 'storesuite' );
				break;
			case 'brands':
				$title = __( 'Brands', 'storesuite' );
				break;
			case 'add-new-brand':
				$title = __( 'Add New Brand', 'storesuite' );
				break;
			case 'edit-brand':
				$title = __( 'Edit Product Brand', 'storesuite' );
				break;
			case 'attributes':
				$title = __( 'Attributes', 'storesuite' );
				break;
			case 'add-new-attribute':
				$title = __( 'Add New Attribute', 'storesuite' );
				break;
			case 'edit-attribute':
				$title = __( 'Edit Attribute', 'storesuite' );
				break;
			case 'attribute-terms':
				$title    = __( 'Attribute Terms', 'storesuite' );
				$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_text_field( wp_unslash( $_GET['taxonomy'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only for title.
				if ( $taxonomy && taxonomy_exists( $taxonomy ) ) {
					$attribute_label = '';
					$taxonomies      = \wc_get_attribute_taxonomies();
					if ( ! empty( $taxonomies ) ) {
						foreach ( $taxonomies as $attr ) {
							$attr_taxonomy = \wc_attribute_taxonomy_name( $attr->attribute_name );
							if ( $attr_taxonomy === $taxonomy ) {
								$attribute_label = $attr->attribute_label;
								break;
							}
						}
					}
					if ( $attribute_label ) {
						/* translators: %s: attribute label */
						$title = sprintf( __( 'Attribute Terms for %s', 'storesuite' ), $attribute_label );
					}
				}
				break;
			case 'coupons':
				$title = __( 'Coupons', 'storesuite' );
				break;
			case 'add-new-coupon':
				$title = __( 'Add New Coupon', 'storesuite' );
				break;
			case 'edit-coupon':
				$coupon_id = absint( $wp->query_vars['edit-coupon'] );
				/* translators: %d: coupon post ID */
				$title = $coupon_id ? sprintf( __( 'Edit Coupon #%d', 'storesuite' ), $coupon_id ) : __( 'Edit Coupon', 'storesuite' );
				break;
			case 'edit-account-details':
				$title = __( 'Account details', 'storesuite' );
				break;
			case 'notifications':
				$title = __( 'Notifications', 'storesuite' );
				break;
			default:
				$title = '';
				break;
		}

		/**
		 * Filters the page title used for my-account endpoints.
		 *
		 * @param string $title Default title.
		 * @param string $endpoint Endpoint key.
		 * @param string $action Optional action or variation within the endpoint.
		 */
		return apply_filters( 'storesuite_endpoint_' . $endpoint . '_title', $title, $endpoint, $action );
	}

	/**
	 * Get query current active query var.
	 *
	 * @return string
	 */
	public function get_current_endpoint() {
		global $wp;

		foreach ( $this->query_vars as $key => $value ) {
			if ( isset( $wp->query_vars[ $key ] ) ) {
				return $key;
			}
		}
		return '';
	}
}
