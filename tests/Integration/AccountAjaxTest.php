<?php
/**
 * Account AJAX integration tests: the edit-account-details form flow through
 * wp_ajax_storesuite_save_account_details — validation chain, WP user update,
 * and the WooCommerce customer billing sync.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Tests\Integration;

use WC_Customer;

/**
 * Exercises AccountController end to end for a logged-in shop manager.
 */
class AccountAjaxTest extends StoreSuiteAjaxTestCase {

	/**
	 * The logged-in user under test.
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * Log in as a shop manager with known account details.
	 */
	public function set_up() {
		parent::set_up();

		$this->user_id = self::factory()->user->create(
			array(
				'role'       => 'shop_manager',
				'user_email' => 'before@example.com',
				'first_name' => 'Before',
				'last_name'  => 'Change',
				'user_pass'  => 'original-pass-123',
			)
		);
		wp_set_current_user( $this->user_id );
	}

	/**
	 * A complete, valid form payload; tests override single fields.
	 *
	 * @param array $overrides Field overrides.
	 * @return array
	 */
	private function valid_payload( array $overrides = array() ) {
		return array_merge(
			$this->nonce_field( 'save_account_details' ),
			array(
				'account_first_name'   => 'After',
				'account_last_name'    => 'Update',
				'account_display_name' => 'After U.',
				'account_email'        => 'after@example.com',
			),
			$overrides
		);
	}

	public function test_valid_submission_updates_user_and_syncs_billing_details() {
		$response = $this->dispatch( 'storesuite_save_account_details', $this->valid_payload() );

		$this->assertTrue( $response['success'], 'Save must succeed: ' . wp_json_encode( $response ) );

		$user = get_user_by( 'id', $this->user_id );
		$this->assertSame( 'After', $user->first_name );
		$this->assertSame( 'Update', $user->last_name );
		$this->assertSame( 'After U.', $user->display_name );
		$this->assertSame( 'after@example.com', $user->user_email );

		$customer = new WC_Customer( $this->user_id );
		$this->assertSame( 'after@example.com', $customer->get_billing_email(), 'Changed email propagates to WooCommerce billing.' );
		$this->assertSame( 'After', $customer->get_billing_first_name() );
		$this->assertSame( 'Update', $customer->get_billing_last_name() );
	}

	public function test_logged_out_request_is_rejected() {
		// Create the nonce while logged in (as the form would), then log out.
		$payload = $this->valid_payload();
		wp_set_current_user( 0 );

		$response = $this->dispatch( 'storesuite_save_account_details', $payload );

		$this->assertFalse( $response['success'] );
	}

	public function test_each_required_field_is_enforced() {
		foreach ( array( 'account_first_name', 'account_last_name', 'account_display_name', 'account_email' ) as $field ) {
			$response = $this->dispatch(
				'storesuite_save_account_details',
				$this->valid_payload( array( $field => '' ) )
			);

			$this->assertFalse( $response['success'], "{$field} must be required." );
			$this->assertStringContainsString( 'required field', $response['data']['error'] );
		}
	}

	public function test_display_name_may_not_be_an_email_address() {
		$response = $this->dispatch(
			'storesuite_save_account_details',
			$this->valid_payload( array( 'account_display_name' => 'leak@example.com' ) )
		);

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'privacy', $response['data']['error'] );
	}

	public function test_email_already_registered_to_another_user_is_rejected() {
		self::factory()->user->create( array( 'user_email' => 'taken@example.com' ) );

		$response = $this->dispatch(
			'storesuite_save_account_details',
			$this->valid_payload( array( 'account_email' => 'taken@example.com' ) )
		);

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'already registered', $response['data']['error'] );
	}

	public function test_password_change_requires_correct_current_password() {
		$response = $this->dispatch(
			'storesuite_save_account_details',
			$this->valid_payload(
				array(
					'password_current' => 'wrong-guess',
					'password_1'       => 'new-pass-456',
					'password_2'       => 'new-pass-456',
				)
			)
		);

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'current password is incorrect', $response['data']['error'] );
	}

	public function test_mismatched_new_passwords_are_rejected() {
		$response = $this->dispatch(
			'storesuite_save_account_details',
			$this->valid_payload(
				array(
					'password_current' => 'original-pass-123',
					'password_1'       => 'new-pass-456',
					'password_2'       => 'different-789',
				)
			)
		);

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'do not match', $response['data']['error'] );
	}

	public function test_valid_password_change_lets_the_user_authenticate_with_the_new_password() {
		$response = $this->dispatch(
			'storesuite_save_account_details',
			$this->valid_payload(
				array(
					'password_current' => 'original-pass-123',
					'password_1'       => 'new-pass-456',
					'password_2'       => 'new-pass-456',
				)
			)
		);

		$this->assertTrue( $response['success'], 'Password change must succeed: ' . wp_json_encode( $response ) );

		$user = get_user_by( 'id', $this->user_id );
		$this->assertTrue( wp_check_password( 'new-pass-456', $user->user_pass, $this->user_id ) );
	}
}
