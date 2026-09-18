<?php
/**
 * Base class for StoreSuite admin-ajax integration tests.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Tests\Integration;

use WP_Ajax_UnitTestCase;
use WPAjaxDieContinueException;
use WPAjaxDieStopException;

/**
 * Drives the real `wp_ajax_storesuite_*` handlers the way admin-ajax.php
 * does: `$_POST` is populated, the hook fires, and the JSON the handler
 * printed before `wp_die()` is captured and decoded.
 */
abstract class StoreSuiteAjaxTestCase extends WP_Ajax_UnitTestCase {

	/**
	 * Dispatch an admin-ajax action and return the decoded JSON response.
	 *
	 * @param string $action Action name without the `wp_ajax_` prefix.
	 * @param array  $post   `$_POST` payload (the nonce field included).
	 * @return array{success:bool,data:array} Decoded response.
	 */
	protected function dispatch( $action, array $post ) {
		$_POST    = $post;
		$_REQUEST = $post;

		// A fresh buffer per dispatch — several tests fire more than one action.
		$this->_last_response = '';

		try {
			$this->_handleAjax( $action );
		} catch ( WPAjaxDieContinueException $e ) {
			// wp_send_json_*() finishes with an empty wp_die() — expected.
			unset( $e );
		} catch ( WPAjaxDieStopException $e ) {
			unset( $e );
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertIsArray( $response, "Handler for '{$action}' must emit JSON." );

		return $response;
	}

	/**
	 * Build the nonce field pair StoreSuite forms post.
	 *
	 * The plugin's convention: field `storesuite_{action}_nonce` carrying a
	 * nonce for `_storesuite_{action}_`.
	 *
	 * @param string $action Nonce action stem, e.g. 'add_product_category'.
	 * @return array Single-entry field => nonce array.
	 */
	protected function nonce_field( $action ) {
		return array(
			"storesuite_{$action}_nonce" => wp_create_nonce( "_storesuite_{$action}_" ),
		);
	}
}
