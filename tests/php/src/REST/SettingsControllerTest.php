<?php
/**
 * Authorization and sanitization tests for the admin settings REST API.
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test\REST;

use PluginizeLab\StoreSuite\Test\StoreSuiteTestCase;

/**
 * @covers \PluginizeLab\StoreSuite\REST\SettingsController
 * @group storesuite-rest
 * @group storesuite-authorization
 */
class SettingsControllerTest extends StoreSuiteTestCase {

	const ROUTE = '/storesuite/v1/settings';

	public function test_settings_routes_are_registered() {
		$this->set_up_rest_server();

		$this->assertArrayHasKey( self::ROUTE, rest_get_server()->get_routes() );
	}

	public function test_admin_can_read_settings() {
		wp_set_current_user( $this->admin_id );
		$this->set_storesuite_option( 'storesuite_product_per_page', '25' );

		$response = $this->do_rest_request( 'GET', self::ROUTE );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( '25', $data['storesuite_product_per_page'] );
	}

	public function test_admin_can_update_settings() {
		wp_set_current_user( $this->admin_id );

		$response = $this->do_rest_request( 'POST', self::ROUTE, array( 'storesuite_product_per_page' => '15' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( '15', storesuite_get_option_by_key( 'storesuite_product_per_page' ) );

		$data = $response->get_data();
		$this->assertSame( '15', $data['storesuite_product_per_page'], 'The update response must echo the saved settings.' );
	}

	public function test_shop_manager_is_denied() {
		wp_set_current_user( $this->shop_manager_id );

		$read  = $this->do_rest_request( 'GET', self::ROUTE );
		$write = $this->do_rest_request( 'POST', self::ROUTE, array( 'storesuite_product_per_page' => '5' ) );

		// The settings API is admin-only: it requires manage_options, which
		// the shop_manager role does not have.
		$this->assertSame( 403, $read->get_status() );
		$this->assertSame( 403, $write->get_status() );
		$this->assertSame( '', storesuite_get_option_by_key( 'storesuite_product_per_page' ), 'A denied write must not persist anything.' );
	}

	public function test_customer_and_anonymous_are_denied() {
		wp_set_current_user( $this->customer_id );
		$this->assertSame( 403, $this->do_rest_request( 'GET', self::ROUTE )->get_status() );

		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->do_rest_request( 'GET', self::ROUTE )->get_status() );
	}

	public function test_prevent_admin_access_setting_round_trips() {
		wp_set_current_user( $this->admin_id );

		$response = $this->do_rest_request( 'POST', self::ROUTE, array( 'storesuite_prevent_admin_access' => 'yes' ) );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'yes', $data['storesuite_prevent_admin_access'] );
		$this->assertSame( 'yes', storesuite_get_option_by_key( 'storesuite_prevent_admin_access' ) );
	}

	public function test_schema_enum_rejects_unknown_values() {
		wp_set_current_user( $this->admin_id );

		// The route args come from the item schema, so the REST layer itself
		// rejects values outside an enum before the callback runs.
		$response = $this->do_rest_request( 'POST', self::ROUTE, array( 'storesuite_color_palette_mode' => 'neon' ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( '', storesuite_get_option_by_key( 'storesuite_color_palette_mode' ) );

		$response = $this->do_rest_request( 'POST', self::ROUTE, array( 'storesuite_color_palette_mode' => 'custom' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'custom', storesuite_get_option_by_key( 'storesuite_color_palette_mode' ) );
	}

	public function test_text_settings_are_stripped_of_markup() {
		wp_set_current_user( $this->admin_id );

		$this->do_rest_request(
			'POST',
			self::ROUTE,
			array( 'storesuite_ai_instruction_title' => '<script>alert(1)</script>Write catchy titles' )
		);

		$stored = storesuite_get_option_by_key( 'storesuite_ai_instruction_title' );
		$this->assertStringNotContainsString( '<script', $stored );
		$this->assertStringContainsString( 'Write catchy titles', $stored );
	}

	public function test_color_settings_keep_valid_hex_and_strip_markup() {
		wp_set_current_user( $this->admin_id );

		$this->do_rest_request( 'POST', self::ROUTE, array( 'storesuite_color_button_background' => '#ff0000' ) );
		$this->assertSame( '#ff0000', storesuite_get_option_by_key( 'storesuite_color_button_background' ) );

		$this->do_rest_request( 'POST', self::ROUTE, array( 'storesuite_color_button_background' => '<b>#123456</b>' ) );
		$this->assertStringNotContainsString( '<', storesuite_get_option_by_key( 'storesuite_color_button_background' ) );
	}

	public function test_branding_logo_requires_a_real_image_attachment() {
		wp_set_current_user( $this->admin_id );

		// A bogus attachment ID is discarded.
		$this->do_rest_request( 'POST', self::ROUTE, array( 'storesuite_dashboard_sidebar_logo_id' => 999999 ) );
		$this->assertSame( '', storesuite_get_option_by_key( 'storesuite_dashboard_sidebar_logo_id' ) );

		// A real image attachment is stored.
		$attachment_id = self::factory()->attachment->create( array( 'post_mime_type' => 'image/jpeg' ) );
		update_post_meta( $attachment_id, '_wp_attached_file', '2026/01/logo.jpg' );

		$this->do_rest_request( 'POST', self::ROUTE, array( 'storesuite_dashboard_sidebar_logo_id' => $attachment_id ) );
		$this->assertSame( $attachment_id, storesuite_get_option_by_key( 'storesuite_dashboard_sidebar_logo_id' ) );

		// Sending an invalid value clears the stored key again.
		$this->do_rest_request( 'POST', self::ROUTE, array( 'storesuite_dashboard_sidebar_logo_id' => 0 ) );
		$this->assertSame( '', storesuite_get_option_by_key( 'storesuite_dashboard_sidebar_logo_id' ) );
	}
}
