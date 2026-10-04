<?php

namespace PluginizeLab\StoreSuite\Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared helpers for product AI AJAX endpoints.
 *
 * Centralizes the core AI Client readiness check and the per-request guard
 * (nonce + capability + availability) used by ProductAI and ProductImageAI, so
 * the gate logic and error copy live in one place.
 */
trait AiRequestTrait {

	/**
	 * Whether the core AI Client supports a given capability in this environment.
	 *
	 * Requires WordPress 7.0+ (core AI Client) and AI support enabled. Memoized
	 * per capability method for the duration of the request.
	 *
	 * @param string $capability_method AI Client readiness method, e.g.
	 *                                   `is_supported_for_text_generation`.
	 * @param bool   $reset             Forget every memoized answer instead of asking.
	 * @return bool
	 */
	protected static function is_ai_capability_supported( $capability_method, $reset = false ) {
		static $supported = array();

		if ( $reset ) {
			$supported = array();
			return false;
		}

		if ( isset( $supported[ $capability_method ] ) ) {
			return $supported[ $capability_method ];
		}

		// A custom text generator (see ProductAI::get_text_generator()) makes text
		// generation available regardless of the core AI Client.
		if ( 'is_supported_for_text_generation' === $capability_method && ProductAI::get_text_generator() ) {
			$supported[ $capability_method ] = true;
			return true;
		}

		$supported[ $capability_method ] = function_exists( 'wp_ai_client_prompt' )
			&& function_exists( 'wp_supports_ai' )
			&& wp_supports_ai()
			&& wp_ai_client_prompt()->{ $capability_method }();

		return $supported[ $capability_method ];
	}

	/**
	 * Forget the memoized capability answers.
	 *
	 * Only needed when the environment changes within one request, e.g. tests
	 * that supply and remove a custom text generator between cases.
	 */
	public static function reset_ai_support_cache() {
		self::is_ai_capability_supported( '', true );
	}

	/**
	 * Enforce the capability and availability gates for a product AI request.
	 *
	 * Call after check_ajax_referer(): sends a JSON error and halts when the
	 * current user lacks permission or the required AI capability is unavailable.
	 * Pass `true` (the default) to skip the availability gate where it does not
	 * apply, e.g. inserting an image that was already generated.
	 *
	 * @param bool   $is_supported        Whether the needed AI capability is available.
	 * @param string $unavailable_message Message shown when AI is not available.
	 */
	protected function guard_ai_request( $is_supported = true, $unavailable_message = '' ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'storesuite' ) ) );
		}

		if ( ! $is_supported ) {
			wp_send_json_error(
				array(
					'reason'  => 'unavailable',
					'message' => $unavailable_message,
				)
			);
		}
	}
}
