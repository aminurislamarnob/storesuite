<?php
/**
 * Tests for the order note AJAX endpoints.
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test\Order;

use PluginizeLab\StoreSuite\Test\StoreSuiteAjaxTestCase;

/**
 * @covers \PluginizeLab\StoreSuite\Order\OrderController
 * @group storesuite-order
 * @group storesuite-ajax
 */
class OrderNotesTest extends StoreSuiteAjaxTestCase {

	/**
	 * Add a note through the AJAX endpoint.
	 *
	 * @param array $fields Request fields.
	 * @return array Decoded response.
	 */
	private function add_note_ajax( $fields ) {
		return $this->do_ajax(
			'storesuite_add_order_note',
			$fields,
			'_storesuite_add_order_note_',
			'storesuite_add_order_note_nonce'
		);
	}

	public function test_private_note_is_added_and_rendered() {
		$this->_setRole( 'administrator' );
		$order = self::factory()->order->create();

		$response = $this->add_note_ajax(
			array(
				'order_id'        => $order->get_id(),
				'order_note'      => 'Packed and ready.',
				'order_note_type' => '',
			)
		);

		$this->assertTrue( $response['success'] );
		$this->assertStringContainsString( 'Packed and ready.', $response['data']['note_html'] );

		$note = wc_get_order_note( $response['data']['note_id'] );
		$this->assertSame( 'Packed and ready.', $note->content );
		$this->assertFalse( $note->customer_note );
	}

	public function test_customer_note_is_flagged_as_customer_note() {
		$this->_setRole( 'administrator' );
		$order = self::factory()->order->create();

		$response = $this->add_note_ajax(
			array(
				'order_id'        => $order->get_id(),
				'order_note'      => 'Your parcel ships tomorrow.',
				'order_note_type' => 'customer',
			)
		);

		$this->assertTrue( $response['success'] );

		$note = wc_get_order_note( $response['data']['note_id'] );
		$this->assertTrue( $note->customer_note );
	}

	public function test_empty_note_is_rejected() {
		$this->_setRole( 'administrator' );
		$order = self::factory()->order->create();

		$response = $this->add_note_ajax(
			array(
				'order_id'   => $order->get_id(),
				'order_note' => '   ',
			)
		);

		$this->assertFalse( $response['success'] );
	}

	public function test_note_requires_manage_woocommerce() {
		$this->_setRole( 'administrator' );
		$order = self::factory()->order->create();

		// WooCommerce itself may have written system notes (status changes);
		// only assert that the rejected request adds nothing on top.
		$notes_before = count( wc_get_order_notes( array( 'order_id' => $order->get_id() ) ) );

		$this->_setRole( 'subscriber' );
		$response = $this->add_note_ajax(
			array(
				'order_id'   => $order->get_id(),
				'order_note' => 'Sneaky note',
			)
		);

		$this->assertFalse( $response['success'] );
		$this->assertCount( $notes_before, wc_get_order_notes( array( 'order_id' => $order->get_id() ) ) );
	}

	public function test_note_can_be_deleted() {
		$this->_setRole( 'administrator' );
		$order   = self::factory()->order->create();
		$note_id = $order->add_order_note( 'Delete me' );

		$response = $this->do_ajax(
			'storesuite_delete_order_note',
			array( 'note_id' => $note_id ),
			'_storesuite_delete_nonce_',
			'storesuite_delete_order_note_nonce'
		);

		$this->assertTrue( $response['success'] );
		$this->assertNull( wc_get_order_note( $note_id ) );
	}

	public function test_only_order_notes_can_be_deleted() {
		$this->_setRole( 'administrator' );
		$comment_id = self::factory()->comment->create();

		$response = $this->do_ajax(
			'storesuite_delete_order_note',
			array( 'note_id' => $comment_id ),
			'_storesuite_delete_nonce_',
			'storesuite_delete_order_note_nonce'
		);

		$this->assertFalse( $response['success'], 'A regular comment must not be deletable through the order note endpoint.' );
		$this->assertNotNull( get_comment( $comment_id ) );
	}
}
