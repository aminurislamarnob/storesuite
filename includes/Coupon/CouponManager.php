<?php

namespace PluginizeLab\StoreSuite\Coupon;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Error;
use WC_Coupon;

/**
 * Plugin coupon manager class
 */
class CouponManager {
	/**
	 * Update coupon post status/visibility after saving WC_Coupon props.
	 *
	 * @param int   $coupon_id Coupon ID.
	 * @param array $data Sanitized coupon request data.
	 *
	 * @return true|WP_Error
	 */
	private function update_coupon_post_state( $coupon_id, $data ) {
		$status     = isset( $data['coupon_status'] ) ? $data['coupon_status'] : 'publish';
		$visibility = isset( $data['coupon_visibility'] ) ? $data['coupon_visibility'] : 'public';

		$allowed_statuses     = array( 'publish', 'pending', 'draft' );
		$allowed_visibilities = array( 'public', 'private' );

		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			$status = 'publish';
		}

		if ( ! in_array( $visibility, $allowed_visibilities, true ) ) {
			$visibility = 'public';
		}

		$post_status = $status;

		if ( 'private' === $visibility ) {
			$post_status = 'private';
		}

		$post_arr = array(
			'ID'          => $coupon_id,
			'post_status' => $post_status,
		);

		$updated = wp_update_post( $post_arr, true );
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		return true;
	}

	/**
	 * Create a new coupon
	 *
	 * @param array $data Sanitized coupon data.
	 *
	 * @return int|WP_Error Coupon ID on success, WP_Error on failure.
	 */
	public function create_coupon( $data ) {
		try {
			// Create new coupon object.
			$coupon = new WC_Coupon();

			// Set coupon properties - data is already sanitized by controller.
			$errors = $coupon->set_props(
				array(
					'code'                        => isset( $data['coupon_code'] ) ? wc_format_coupon_code( $data['coupon_code'] ) : '',
					'discount_type'               => isset( $data['discount_type'] ) ? $data['discount_type'] : 'fixed_cart',
					'amount'                      => isset( $data['coupon_amount'] ) ? $data['coupon_amount'] : 0,
					'description'                 => isset( $data['description'] ) ? $data['description'] : '',
					'date_expires'                => isset( $data['expiry_date'] ) ? $data['expiry_date'] : null,
					'individual_use'              => isset( $data['individual_use'] ) && $data['individual_use'],
					'product_ids'                 => isset( $data['product_ids'] ) ? array_filter( array_map( 'intval', (array) $data['product_ids'] ) ) : array(),
					'excluded_product_ids'        => isset( $data['exclude_product_ids'] ) ? array_filter( array_map( 'intval', (array) $data['exclude_product_ids'] ) ) : array(),
					'usage_limit'                 => isset( $data['usage_limit'] ) ? absint( $data['usage_limit'] ) : 0,
					'usage_limit_per_user'        => isset( $data['usage_limit_per_user'] ) ? absint( $data['usage_limit_per_user'] ) : 0,
					'limit_usage_to_x_items'      => isset( $data['limit_usage_to_x_items'] ) ? absint( $data['limit_usage_to_x_items'] ) : null,
					'free_shipping'               => isset( $data['free_shipping'] ) && $data['free_shipping'],
					'product_categories'          => isset( $data['product_categories'] ) ? array_filter( array_map( 'intval', (array) $data['product_categories'] ) ) : array(),
					'excluded_product_categories' => isset( $data['exclude_product_categories'] ) ? array_filter( array_map( 'intval', (array) $data['exclude_product_categories'] ) ) : array(),
					'exclude_sale_items'          => isset( $data['exclude_sale_items'] ) && $data['exclude_sale_items'],
					'minimum_amount'              => isset( $data['minimum_amount'] ) ? $data['minimum_amount'] : '',
					'maximum_amount'              => isset( $data['maximum_amount'] ) ? $data['maximum_amount'] : '',
					'email_restrictions'          => isset( $data['customer_email'] ) ? array_filter( array_map( 'trim', explode( ',', $data['customer_email'] ) ) ) : array(),
				)
			);

			if ( is_wp_error( $errors ) ) {
				return $errors;
			}

			// Save coupon.
			$coupon_id = $coupon->save();

			$post_state = $this->update_coupon_post_state( $coupon_id, $data );
			if ( is_wp_error( $post_state ) ) {
				return $post_state;
			}

			do_action( 'storesuite_new_coupon_created', $coupon_id, $data );

			return $coupon_id;
		} catch ( \Exception $e ) {
			return new WP_Error( 'coupon_error', $e->getMessage() );
		}
	}

	/**
	 * Update an existing coupon
	 *
	 * @param int   $coupon_id Coupon ID.
	 * @param array $data Sanitized coupon data.
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function update_coupon( $coupon_id, $data ) {
		try {
			$coupon = new WC_Coupon( $coupon_id );

			if ( ! $coupon->get_id() ) {
				return new WP_Error( 'invalid_coupon', __( 'Coupon not found', 'storesuite' ) );
			}

			// Set coupon properties - data is already sanitized by controller.
			$errors = $coupon->set_props(
				array(
					'code'                        => isset( $data['coupon_code'] ) ? wc_format_coupon_code( $data['coupon_code'] ) : $coupon->get_code(),
					'discount_type'               => isset( $data['discount_type'] ) ? $data['discount_type'] : $coupon->get_discount_type(),
					'amount'                      => isset( $data['coupon_amount'] ) ? $data['coupon_amount'] : $coupon->get_amount(),
					'description'                 => isset( $data['description'] ) ? $data['description'] : $coupon->get_description(),
					'date_expires'                => isset( $data['expiry_date'] ) ? $data['expiry_date'] : $coupon->get_date_expires(),
					'individual_use'              => isset( $data['individual_use'] ) && $data['individual_use'],
					'product_ids'                 => isset( $data['product_ids'] ) ? array_filter( array_map( 'intval', (array) $data['product_ids'] ) ) : array(),
					'excluded_product_ids'        => isset( $data['exclude_product_ids'] ) ? array_filter( array_map( 'intval', (array) $data['exclude_product_ids'] ) ) : array(),
					'usage_limit'                 => isset( $data['usage_limit'] ) ? absint( $data['usage_limit'] ) : $coupon->get_usage_limit(),
					'usage_limit_per_user'        => isset( $data['usage_limit_per_user'] ) ? absint( $data['usage_limit_per_user'] ) : $coupon->get_usage_limit_per_user(),
					'limit_usage_to_x_items'      => isset( $data['limit_usage_to_x_items'] ) ? absint( $data['limit_usage_to_x_items'] ) : $coupon->get_limit_usage_to_x_items(),
					'free_shipping'               => isset( $data['free_shipping'] ) && $data['free_shipping'],
					'product_categories'          => isset( $data['product_categories'] ) ? array_filter( array_map( 'intval', (array) $data['product_categories'] ) ) : array(),
					'excluded_product_categories' => isset( $data['exclude_product_categories'] ) ? array_filter( array_map( 'intval', (array) $data['exclude_product_categories'] ) ) : array(),
					'exclude_sale_items'          => isset( $data['exclude_sale_items'] ) && $data['exclude_sale_items'],
					'minimum_amount'              => isset( $data['minimum_amount'] ) ? $data['minimum_amount'] : $coupon->get_minimum_amount(),
					'maximum_amount'              => isset( $data['maximum_amount'] ) ? $data['maximum_amount'] : $coupon->get_maximum_amount(),
					'email_restrictions'          => isset( $data['customer_email'] ) ? array_filter( array_map( 'trim', explode( ',', $data['customer_email'] ) ) ) : array(),
				)
			);

			if ( is_wp_error( $errors ) ) {
				return $errors;
			}

			$coupon->save();

			$post_state = $this->update_coupon_post_state( $coupon_id, $data );
			if ( is_wp_error( $post_state ) ) {
				return $post_state;
			}

			do_action( 'storesuite_coupon_updated', $coupon_id, $data );

			return true;
		} catch ( \Exception $e ) {
			return new WP_Error( 'coupon_error', $e->getMessage() );
		}
	}

	/**
	 * Delete a coupon
	 *
	 * @param int  $coupon_id Coupon ID.
	 * @param bool $force_delete Whether to permanently delete or move to trash.
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function delete_coupon( $coupon_id, $force_delete = false ) {
		try {
			$coupon = new WC_Coupon( $coupon_id );

			if ( ! $coupon->get_id() ) {
				return new WP_Error( 'invalid_coupon', __( 'Coupon not found', 'storesuite' ) );
			}

			$coupon->delete( $force_delete );

			do_action( 'storesuite_coupon_deleted', $coupon_id, $force_delete );

			return true;
		} catch ( \Exception $e ) {
			return new WP_Error( 'coupon_error', $e->getMessage() );
		}
	}

	/**
	 * Duplicate a coupon as a draft copy.
	 *
	 * Copies every coupon property except the usage counters, suffixes the
	 * code with "-copy" (numbered when that code is already taken) and saves
	 * the copy as a draft so it is inactive until reviewed.
	 *
	 * @param int $coupon_id Source coupon ID.
	 * @return int|WP_Error New coupon ID, or WP_Error when the source is missing.
	 */
	public function duplicate_coupon( $coupon_id ) {
		$source = new WC_Coupon( $coupon_id );

		if ( ! $source->get_id() ) {
			return new WP_Error( 'invalid_coupon', __( 'Coupon not found', 'storesuite' ) );
		}

		try {
			$duplicate = clone $source;
			$duplicate->set_id( 0 );
			$duplicate->set_code( $this->generate_unique_coupon_code( $source->get_code() ) );
			$duplicate->set_usage_count( 0 );
			$duplicate->set_used_by( array() );
			$duplicate->set_date_created( null );
			$duplicate->set_date_modified( null );
			$duplicate->set_status( 'draft' );

			$new_id = $duplicate->save();

			if ( ! $new_id ) {
				return new WP_Error( 'storesuite_duplicate_failed', __( 'The coupon could not be duplicated.', 'storesuite' ) );
			}

			do_action( 'storesuite_coupon_duplicated', $new_id, $coupon_id );

			return $new_id;
		} catch ( \Exception $e ) {
			return new WP_Error( 'coupon_error', $e->getMessage() );
		}
	}

	/**
	 * Build a coupon code that is not yet in use, based on a source code.
	 *
	 * @param string $code Source coupon code.
	 * @return string "<code>-copy", or "<code>-copy-N" when the plain suffix is taken.
	 */
	private function generate_unique_coupon_code( $code ) {
		$base      = wc_format_coupon_code( $code . '-copy' );
		$candidate = $base;
		$n         = 2;

		while ( $this->coupon_code_exists( $candidate ) ) {
			$candidate = $base . '-' . $n;
			++$n;
		}

		return $candidate;
	}

	/**
	 * Whether any coupon (of any status, including drafts) already uses a code.
	 *
	 * `wc_get_coupon_id_by_code()` only sees published coupons, which would let
	 * two draft copies collide on the same code.
	 *
	 * @param string $code Normalized coupon code.
	 * @return bool
	 */
	private function coupon_code_exists( $code ) {
		global $wpdb;

		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'shop_coupon' AND post_status <> 'trash' AND post_title = %s LIMIT 1",
				$code
			)
		);

		return ! empty( $id );
	}
}
