<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get template part for store suite
 *
 * Looks at the theme directory first
 */
use Automattic\WooCommerce\Enums\OrderStatus;

function storesuite_get_template_part( $slug, $name = '', $args = array() ) {
	$defaults = array(
		'pro' => false,
	);

	$args = wp_parse_args( $args, $defaults );

	$template = '';

	// Look in yourtheme/my-storesuite/slug-name.php and yourtheme/my-storesuite/slug.php
	$template_path = ! empty( $name ) ? "{$slug}-{$name}.php" : "{$slug}.php";
	$template      = locate_template( array( pluginizelab_storesuite()->template_path() . $template_path ) );

	/**
	 * Change template directory path filter
	 */
	$template_path = apply_filters( 'storesuite_set_template_path', STORESUITE_TEMPLATE_DIR, $template, $args );

	// Get default slug-name.php
	if ( ! $template && $name && file_exists( $template_path . "/{$slug}-{$name}.php" ) ) {
		$template = $template_path . "/{$slug}-{$name}.php";
	}

	if ( ! $template && ! $name && file_exists( $template_path . "/{$slug}.php" ) ) {
		$template = $template_path . "/{$slug}.php";
	}

	// Allow 3rd party plugin filter template file from their plugin
	$template = apply_filters( 'storesuite_get_template_part', $template, $slug, $name );

	// Extract only after the template has been resolved, and never overwrite
	// this function's own variables: an arg keyed "name" (or "slug", "template",
	// ...) must not be able to break template resolution or the include below.
	if ( $args && is_array( $args ) ) {
		extract( $args, EXTR_SKIP ); // phpcs:ignore
	}

	if ( $template ) {
		include $template;
	}
}

/**
 * Storesuite_is_endpoint_url - Check if an endpoint is showing.
 *
 * @param string|false $endpoint Whether endpoint.
 * @return bool
 */
if ( ! function_exists( 'storesuite_is_endpoint_url' ) ) {
	function storesuite_is_endpoint_url( $endpoint = false ) {
		global $wp;

		$storesuite_endpoints = pluginizelab_storesuite()->get_storesuite_query()->query_vars;

		if ( false !== $endpoint ) {
			if ( ! isset( $storesuite_endpoints[ $endpoint ] ) ) {
				return false;
			} else {
				$endpoint_var = $storesuite_endpoints[ $endpoint ];
			}

			return isset( $wp->query_vars[ $endpoint_var ] );
		} else {
			foreach ( $storesuite_endpoints as $key => $value ) {
				if ( isset( $wp->query_vars[ $key ] ) ) {
					return true;
				}
			}

			return false;
		}
	}
}

/**
 * Get user-friendly post status based on post
 *
 * @param string $status
 *
 * @return string|array
 */
function storesuite_get_post_status( $status = '' ) {
	$statuses = apply_filters(
		'storesuite_get_post_status',
		array(
			'publish' => __( 'Online', 'storesuite' ),
			'draft'   => __( 'Draft', 'storesuite' ),
			'pending' => __( 'Pending Review', 'storesuite' ),
			'private' => __( 'Private', 'storesuite' ),
			'future'  => __( 'Scheduled', 'storesuite' ),
		)
	);

	if ( $status ) {
		return isset( $statuses[ $status ] ) ? $statuses[ $status ] : '';
	}

	return $statuses;
}

/**
 * Get user-friendly post status class based on post
 *
 * @param string $status
 *
 * @return string|array
 */
function storesuite_get_post_status_class( $status = '' ) {
	$statuses = apply_filters(
		'storesuite_get_post_status_class',
		array(
			'publish' => 'success',
			'draft'   => 'default',
			'pending' => 'warning',
			'private' => 'secondary',
			'future'  => 'info',
		)
	);

	if ( $status ) {
		return isset( $statuses[ $status ] ) ? $statuses[ $status ] : '';
	}

	return $statuses;
}

/**
 * HTML for one product row of a product-based list table.
 *
 * Shared by the first paint, quick edit and inline cell edit AJAX row refreshes.
 *
 * @param int    $product_id Product ID.
 * @param string $context    List context: 'products' (default) or 'inventory'.
 * @return string
 */
function storesuite_get_product_list_row_html( $product_id, $context = 'products' ) {
	$product_id = absint( $product_id );
	$product    = wc_get_product( $product_id );
	if ( ! $product_id || ! $product ) {
		return '';
	}

	$row_templates = array(
		'products'  => 'products/product-list-table-row',
		'inventory' => 'inventory/inventory-list-table-row',
	);
	$template      = isset( $row_templates[ $context ] ) ? $row_templates[ $context ] : $row_templates['products'];

	ob_start();
	storesuite_get_template_part(
		$template,
		'',
		array(
			'product_id' => $product_id,
			'product'    => $product,
		)
	);
	return ob_get_clean();
}

/**
 * Get user-friendly order status class based on order status
 *
 * @param string $status
 *
 * @return string|array
 */
function storesuite_get_order_status_class( $status = '' ) {
	$statuses = apply_filters(
		'storesuite_get_order_status_class',
		array(
			OrderStatus::PENDING    => 'warning',
			OrderStatus::DRAFT      => 'default',
			OrderStatus::FAILED     => 'danger',
			OrderStatus::ON_HOLD    => 'info',
			OrderStatus::COMPLETED  => 'success',
			OrderStatus::PROCESSING => 'success',
			OrderStatus::REFUNDED   => 'info',
			OrderStatus::CANCELLED  => 'danger',
			OrderStatus::TRASH      => 'danger',
			OrderStatus::NEW        => 'default',
			OrderStatus::AUTO_DRAFT => 'default',
		)
	);

	if ( $status ) {
		return isset( $statuses[ $status ] ) ? $statuses[ $status ] : '';
	}

	return $statuses;
}

/**
 * Get user friendly post status label based class
 *
 * @param string $status
 *
 * @return string|array
 */
function storesuite_get_post_status_label_class( $status = '' ) {
	$labels = apply_filters(
		'storesuite_get_post_status_label_class',
		array(
			'publish' => 'storesuite-label-success',
			'draft'   => 'storesuite-label-default',
			'pending' => 'storesuite-label-danger',
			'future'  => 'storesuite-label-warning',
		)
	);

	if ( $status ) {
		return isset( $labels[ $status ] ) ? $labels[ $status ] : '';
	}

	return $labels;
}


/**
 * Get product type
 *
 * @param object $product
 *
 * @return string
 */
function storesuite_get_product_type( $product ) {
	$product_type = $product->get_type();
	if ( $product_type === 'grouped' ) {
		echo '<span class="product-type grouped">' . esc_html__( 'Grouped', 'storesuite' ) . '</span>';
	} elseif ( $product_type === 'external' ) {
		echo '<span class="product-type external">' . esc_html__( 'External', 'storesuite' ) . '</span>';
	} elseif ( $product_type === 'simple' ) {
		if ( $product->is_virtual() ) {
			echo '<span class="product-type virtual">' . esc_html__( 'Virtual', 'storesuite' ) . '</span>';
		} elseif ( $product->is_downloadable() ) {
			echo '<span class="product-type downloadable">' . esc_html__( 'Downloadable', 'storesuite' ) . '</span>';
		} else {
			echo '<span class="product-type simple">' . esc_html__( 'Simple', 'storesuite' ) . '</span>';
		}
	} elseif ( $product_type === 'variable' ) {
		echo '<span class="product-type variable">' . esc_html__( 'Variable', 'storesuite' ) . '</span>';
	}
}

function storesuite_get_option_by_key( $key ) {
	$storesuite_settings = get_option( 'storesuite_settings', array() );
	if ( ! empty( $storesuite_settings ) && array_key_exists( $key, $storesuite_settings ) ) {
		return $storesuite_settings[ $key ];
	}
	return '';
}

/**
 * Whether AI generation is enabled for a given product form field.
 *
 * Controlled from the admin "AI" settings page. Each field defaults to enabled
 * until a merchant explicitly turns it off, so existing installs keep their
 * current behaviour.
 *
 * @param string $field Field key: title, description, short_description,
 *                      featured, or gallery.
 * @return bool
 */
function storesuite_is_ai_field_enabled( $field ) {
	$option_map = array(
		'title'             => 'storesuite_ai_field_title',
		'description'       => 'storesuite_ai_field_description',
		'short_description' => 'storesuite_ai_field_short_description',
		'featured'          => 'storesuite_ai_field_featured_image',
		'gallery'           => 'storesuite_ai_field_gallery_image',
		'bundle'            => 'storesuite_ai_field_bundle',
	);

	if ( ! isset( $option_map[ $field ] ) ) {
		return false;
	}

	return 'no' !== storesuite_get_option_by_key( $option_map[ $field ] );
}

/**
 * Whether dashboard notifications are enabled for a given event type.
 *
 * Controlled from the admin "Notifications" settings page. Each event
 * defaults to enabled until a merchant explicitly turns it off. Checked at
 * insert time, so disabled events are never recorded.
 *
 * @param string $type Event type: new_order, new_customer, or product_review.
 * @return bool
 */
function storesuite_is_notification_enabled( $type ) {
	$option_map = array(
		'new_order'      => 'storesuite_notification_new_order',
		'new_customer'   => 'storesuite_notification_new_customer',
		'product_review' => 'storesuite_notification_product_review',
	);

	if ( ! isset( $option_map[ $type ] ) ) {
		return false;
	}

	return 'no' !== storesuite_get_option_by_key( $option_map[ $type ] );
}


/**
 * Get navigation URL for the store suite dashboard.
 *
 * @param string $name Endpoint name.
 *
 * @return string URL
 */
function storesuite_get_navigation_url( $name = '' ) {
	// Get the page ID from options.
	$page_id = (int) storesuite_get_option_by_key( 'storesuite_dashboard_page_id' );

	// If page ID is not found, return an empty string.
	if ( ! $page_id ) {
		return '';
	}

	// Get the permalink for the page.
	$url = get_permalink( $page_id );

	// If the permalink retrieval fails, return an empty string.
	if ( ! $url ) {
		return '';
	}

	// Trim the trailing slash and append the endpoint name if provided.
	$url = rtrim( $url, '/' );

	if ( ! empty( $name ) ) {
		$url .= '/' . trailingslashit( $name );
	}

	// Apply a filter to the URL before returning it.
	return apply_filters( 'storesuite_get_navigation_url', esc_url( $url ), $name );
}


/**
 * Check if it's a store suite dashboard page
 *
 * @return bool
 */
function storesuite_is_dashboard_page() {
	static $cached = null;
	if ( null !== $cached ) {
		return $cached;
	}

	$page_id = (int) storesuite_get_option_by_key( 'storesuite_dashboard_page_id' );
	$cached  = ( $page_id && is_page( $page_id ) ) || wc_post_content_has_shortcode( 'storesuite_dashboard' );

	return $cached;
}


/**
 * Get id query var
 *
 * @return bool
 */
function storesuite_get_id_from_query_vars( $query_var ) {
	global $wp;
	$query_var_parts = explode( '/', $wp->query_vars[ $query_var ] );

	if ( isset( $query_var_parts[1] ) ) {
		return $query_var_parts[1];
	}

	return null;
}

/**
 * Check if the current page is a store suite page by endpoint
 *
 * @param string $endpoint
 *
 * @return bool
 */
function storesuite_is_page( $endpoint ) {
	if ( empty( $endpoint ) ) {
		return false;
	}

	if ( ! storesuite_is_dashboard_page() ) {
		return false;
	}

	global $wp;

	$query_var = $wp->query_vars;
	return array_key_exists( $endpoint, $query_var );
}


/**
 * Custom WooCommerce logger
 *
 * @param string $message
 * @param string $level
 * @return void
 */
function storesuite_log( $message, $level = 'debug' ) {
	if ( ! function_exists( 'wc_get_logger' ) ) {
		return;
	}

	$logger  = wc_get_logger();
	$context = array( 'source' => 'storesuite' );
	$logger->log( $level, $message, $context );
}

/**
 * Redirect to login page if user not logged in
 *
 * @return void
 */
function storesuite_redirect_if_not_logged_in() {
	if ( ! is_user_logged_in() ) {
		$redirect_url = wc_get_page_permalink( 'myaccount' );
		wp_safe_redirect( $redirect_url );
		exit();
	}
}

/**
 * Redirect to homepage, If the current user is not manager.
 *
 * @param string $redirect
 */
function storesuite_redirect_if_not_manager( $redirect = '' ) {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		$redirect = empty( $redirect ) ? home_url( '/' ) : $redirect;

		wp_safe_redirect( $redirect );
		exit();
	}
}
