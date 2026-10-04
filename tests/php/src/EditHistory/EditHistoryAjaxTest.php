<?php
/**
 * Tests for edit history recording through the edit endpoints and the undo AJAX action.
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test\EditHistory;

use PluginizeLab\StoreSuite\EditHistory\EditHistoryController;
use PluginizeLab\StoreSuite\EditHistory\EditHistoryManager;
use PluginizeLab\StoreSuite\Test\StoreSuiteAjaxTestCase;

/**
 * @covers \PluginizeLab\StoreSuite\EditHistory\EditHistoryController
 * @covers \PluginizeLab\StoreSuite\Product\ProductInlineEdit
 * @covers \PluginizeLab\StoreSuite\Product\ProductBulkEdit
 * @covers \PluginizeLab\StoreSuite\Order\OrderController
 * @group storesuite-edit-history
 * @group storesuite-ajax
 */
class EditHistoryAjaxTest extends StoreSuiteAjaxTestCase {

	public function set_up() {
		parent::set_up();
		$this->create_storesuite_users();
		$this->create_dashboard_page();
	}

	private function inline_edit( $product_id, $field, $value ) {
		return $this->do_ajax(
			'storesuite_product_inline_cell_edit',
			array(
				'product_id' => $product_id,
				'field'      => $field,
				'value'      => $value,
			)
		);
	}

	public function test_inline_edit_records_a_batch_and_returns_an_undo_payload() {
		$this->_setRole( 'shop_manager' );
		$product = self::factory()->product->create(
			array(
				'name' => 'Blue Hoodie',
				'sku'  => 'HOOD-1',
			)
		);

		$response = $this->inline_edit( $product->get_id(), 'sku', 'HOOD-2' );

		$this->assertTrue( $response['success'], wp_json_encode( $response ) );
		$this->assertIsArray( $response['data']['undo'] );
		$this->assertGreaterThan( 0, $response['data']['undo']['batch_id'] );
		$this->assertNotEmpty( $response['data']['undo']['nonce'] );

		$manager = new EditHistoryManager();
		$batch   = $manager->get_batch( $response['data']['undo']['batch_id'] );
		$this->assertSame( 'inline', $batch->source );
		$this->assertSame( 'SKU changed on "Blue Hoodie"', $batch->summary );

		$items = $manager->get_items( $batch->id );
		$this->assertCount( 1, $items );
		$this->assertSame( 'HOOD-1', $items[0]->old_value );
		$this->assertSame( 'HOOD-2', $items[0]->new_value );
	}

	public function test_inline_edit_returns_no_undo_when_history_is_off() {
		$this->_setRole( 'shop_manager' );
		$this->set_storesuite_option( EditHistoryManager::ENABLED_OPTION, 'no' );
		$product = self::factory()->product->create( array( 'sku' => 'OFF-1' ) );

		$response = $this->inline_edit( $product->get_id(), 'sku', 'OFF-2' );

		$this->assertTrue( $response['success'] );
		$this->assertNull( $response['data']['undo'] );
		$this->assertSame( 'OFF-2', wc_get_product( $product->get_id() )->get_sku(), 'The edit itself still applies.' );
	}

	public function test_undo_ajax_reverts_the_inline_edit_and_returns_the_row() {
		$this->_setRole( 'shop_manager' );
		$product = self::factory()->product->create( array( 'sku' => 'ORIG' ) );

		$edit     = $this->inline_edit( $product->get_id(), 'sku', 'CHANGED' );
		$batch_id = $edit['data']['undo']['batch_id'];

		$response = $this->do_ajax(
			'storesuite_undo_edit_batch',
			array(
				'batch_id' => $batch_id,
				'context'  => 'products',
			),
			EditHistoryController::NONCE_ACTION
		);

		$this->assertTrue( $response['success'], wp_json_encode( $response ) );
		$this->assertSame( 1, $response['data']['reverted'] );
		$this->assertSame( 0, $response['data']['skipped'] );
		$this->assertArrayHasKey( $product->get_id(), $response['data']['rows'] );
		$this->assertStringContainsString( 'ORIG', $response['data']['rows'][ $product->get_id() ] );
		$this->assertSame( 'ORIG', wc_get_product( $product->get_id() )->get_sku() );
	}

	public function test_bulk_edit_records_one_batch_across_products() {
		$this->_setRole( 'administrator' );
		$a = self::factory()->product->create( array( 'name' => 'Bulk A' ) );
		$b = self::factory()->product->create( array( 'name' => 'Bulk B' ) );

		$response = $this->do_ajax(
			'storesuite_bulk_edit_products',
			array(
				'post'      => array( $a->get_id(), $b->get_id() ),
				'post_type' => 'product',
				'_status'   => 'draft',
			)
		);

		$this->assertTrue( $response['success'], wp_json_encode( $response ) );
		$this->assertIsArray( $response['data']['undo'] );

		$manager = new EditHistoryManager();
		$batch   = $manager->get_batch( $response['data']['undo']['batch_id'] );
		$this->assertSame( 'bulk', $batch->source );
		$this->assertSame( 'Bulk edit of 2 products', $batch->summary );

		$fields = wp_list_pluck( $manager->get_items( $batch->id ), 'field' );
		$this->assertSame( array( 'status', 'status' ), $fields );

		$manager->undo( $batch->id );
		$this->assertSame( 'publish', get_post_status( $a->get_id() ) );
		$this->assertSame( 'publish', get_post_status( $b->get_id() ) );
	}

	public function test_order_bulk_status_change_records_a_batch_and_passes_it_to_the_redirect() {
		wp_set_current_user( $this->admin_id );
		$order = self::factory()->order->create( array( 'status' => 'processing' ) );

		$_POST = array(
			'storesuite_bulk_action_nonce' => wp_create_nonce( 'storesuite_order_bulk_action' ),
			'action'                       => 'mark_completed',
			'bulk_order_ids'               => array( $order->get_id() ),
		);

		$controller = \pluginizelab_storesuite()->storesuite_order_controller;
		$redirect   = $this->capture_redirect(
			function () use ( $controller ) {
				$controller->handle_order_bulk_actions();
			}
		);
		$_POST = array();

		wp_parse_str( (string) wp_parse_url( $redirect, PHP_URL_QUERY ), $query );

		$this->assertArrayHasKey( 'history', $query );

		$manager = new EditHistoryManager();
		$batch   = $manager->get_batch( (int) $query['history'] );
		$this->assertSame( 'order_bulk', $batch->source );
		$this->assertSame( 'order', $batch->object_type );
		$this->assertSame( 'Changed status to Completed on 1 order', $batch->summary );

		$manager->undo( $batch->id );
		$this->assertSame( 'processing', wc_get_order( $order->get_id() )->get_status() );
	}

	public function test_undo_requires_manage_woocommerce() {
		$this->_setRole( 'subscriber' );

		$response = $this->do_ajax( 'storesuite_undo_edit_batch', array( 'batch_id' => 1 ), EditHistoryController::NONCE_ACTION );

		$this->assertFalse( $response['success'] );
	}

	public function test_settings_endpoint_saves_the_history_toggle_and_retention() {
		wp_set_current_user( $this->admin_id );

		$response = $this->do_rest_request(
			'POST',
			'/storesuite/v1/settings',
			array(
				'storesuite_edit_history_enabled'        => 'no',
				'storesuite_edit_history_retention_days' => 45,
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'no', storesuite_get_option_by_key( 'storesuite_edit_history_enabled' ) );
		$this->assertSame( 45, storesuite_get_option_by_key( 'storesuite_edit_history_retention_days' ) );
		$this->assertSame( 45, EditHistoryManager::get_retention_days() );
	}
}
