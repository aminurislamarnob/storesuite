<?php
/**
 * Base test case for StoreSuite admin-ajax endpoint tests.
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test;

/**
 * Base class for tests that dispatch wp_ajax_storesuite_* actions.
 *
 * Centralizes the request/response plumbing every AJAX test needs: nonce
 * creation, the WPAjaxDieContinueException control flow, response-buffer
 * resets between dispatches and JSON extraction from a noisy buffer.
 */
abstract class StoreSuiteAjaxTestCase extends \WP_Ajax_UnitTestCase {

	use StoreSuiteFixtures;

	/**
	 * Set up the test fixture.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		// Main::block_admin_access() redirects and exits on admin_init when the
		// prevent-admin-access setting is on; it must never end a test process.
		$main = \pluginizelab_storesuite()->storesuite_main;
		if ( $main ) {
			remove_action( 'admin_init', array( $main, 'block_admin_access' ) );
		}
	}

	/**
	 * Tear down the test fixture.
	 *
	 * @return void
	 */
	public function tear_down() {
		parent::tear_down();

		$this->reset_role_singleton();
		$this->reset_rest_server();
	}

	/**
	 * Dispatch an admin-ajax action and return the decoded JSON response.
	 *
	 * @param string      $action       AJAX action name.
	 * @param array       $post_fields  Request fields.
	 * @param string|null $nonce_action Nonce action when it differs from $action.
	 * @param string      $nonce_field  POST field carrying the nonce. Defaults to
	 *                                  'security' (the check_ajax_referer convention);
	 *                                  form-handler endpoints use per-form field names.
	 * @return array Decoded wp_send_json_* payload.
	 */
	protected function do_ajax( $action, array $post_fields = array(), $nonce_action = null, $nonce_field = 'security' ) {
		// _last_response accumulates across _handleAjax calls; start fresh.
		$this->_last_response = '';

		$_POST = array_merge(
			array(
				$nonce_field => wp_create_nonce( $nonce_action ? $nonce_action : $action ),
			),
			$post_fields
		);

		try {
			$this->_handleAjax( $action );
		} catch ( \WPAjaxDieContinueException $e ) {
			// wp_send_json_* ends with an empty wp_die(); this is the expected control flow.
			unset( $e );
		}

		// Strip any debug output printed before or after the JSON payload.
		$raw   = $this->_last_response;
		$start = strpos( $raw, '{' );
		$end   = strrpos( $raw, '}' );
		if ( false !== $start && false !== $end && $end >= $start ) {
			$raw = substr( $raw, $start, $end - $start + 1 );
		}

		$decoded = json_decode( $raw, true );
		if ( null === $decoded ) {
			$this->fail( 'Non-JSON AJAX response (' . strlen( $this->_last_response ) . ' bytes), tail: ' . substr( $this->_last_response, -600 ) );
		}

		return $decoded;
	}
}
