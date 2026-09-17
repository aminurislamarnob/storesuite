<?php
/**
 * Tests for the per-user list table column manager (Tier 2, issue #190).
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test\ListTable;

use PluginizeLab\StoreSuite\ListTable\ColumnManager;
use PluginizeLab\StoreSuite\Test\StoreSuiteAjaxTestCase;

/**
 * @covers \PluginizeLab\StoreSuite\ListTable\ColumnManager
 * @group storesuite-list-table
 * @group storesuite-ajax
 */
class ColumnManagerTest extends StoreSuiteAjaxTestCase {

	const ACTION = 'storesuite_save_hidden_columns';

	public function test_every_list_table_has_a_locked_identity_column() {
		foreach ( ColumnManager::get_all_columns() as $table => $columns ) {
			$locked = array_filter(
				$columns,
				function ( $column ) {
					return ! empty( $column['locked'] );
				}
			);
			$this->assertNotEmpty( $locked, "Table '{$table}' must keep at least one column locked." );
		}
	}

	public function test_nothing_is_hidden_by_default() {
		$this->_setRole( 'shop_manager' );

		$this->assertSame( array(), ColumnManager::get_hidden( 'products' ) );
		$this->assertTrue( ColumnManager::is_visible( 'products', 'sku' ) );
	}

	public function test_save_stores_only_known_unlocked_keys_per_user() {
		$this->_setRole( 'shop_manager' );
		$user_id = get_current_user_id();

		$stored = ColumnManager::save_hidden( 'products', array( 'sku', 'name', 'bogus', 'type', 'sku' ) );

		$this->assertSame( array( 'sku', 'type' ), $stored, 'Locked and unknown keys are dropped, duplicates collapsed.' );
		$this->assertFalse( ColumnManager::is_visible( 'products', 'sku' ) );
		$this->assertTrue( ColumnManager::is_visible( 'products', 'name' ) );
		$this->assertTrue( ColumnManager::is_visible( 'orders', 'status' ), 'Other tables are unaffected.' );

		$other = self::factory()->user->create( array( 'role' => 'shop_manager' ) );
		$this->assertSame( array(), ColumnManager::get_hidden( 'products', $other ), 'Visibility is per user.' );

		$this->assertSame( array( 'products' => array( 'sku', 'type' ) ), get_user_meta( $user_id, ColumnManager::USER_META_KEY, true ) );
	}

	public function test_saving_an_empty_set_removes_the_table_and_the_meta() {
		$this->_setRole( 'shop_manager' );
		$user_id = get_current_user_id();

		ColumnManager::save_hidden( 'orders', array( 'phone' ) );
		ColumnManager::save_hidden( 'orders', array() );

		$this->assertSame( '', get_user_meta( $user_id, ColumnManager::USER_META_KEY, true ) );
		$this->assertTrue( ColumnManager::is_visible( 'orders', 'phone' ) );
	}

	public function test_helper_tags_headers_and_hides_hidden_columns() {
		$this->_setRole( 'shop_manager' );
		ColumnManager::save_hidden( 'coupons', array( 'description' ) );

		ob_start();
		storesuite_list_column_attrs( 'coupons', 'description' );
		$hidden = ob_get_clean();

		ob_start();
		storesuite_list_column_attrs( 'coupons', 'amount' );
		$visible = ob_get_clean();

		$this->assertSame( ' data-col="description" hidden', $hidden );
		$this->assertSame( ' data-col="amount"', $visible );
	}

	public function test_ajax_save_round_trip() {
		$this->_setRole( 'shop_manager' );

		$response = $this->do_ajax(
			self::ACTION,
			array(
				'table'  => 'inventory',
				'hidden' => array( 'backorders', 'name' ),
			),
			ColumnManager::NONCE_ACTION
		);

		$this->assertTrue( $response['success'], wp_json_encode( $response ) );
		$this->assertSame( array( 'backorders' ), $response['data']['hidden'] );
		$this->assertSame( array( 'backorders' ), ColumnManager::get_hidden( 'inventory' ) );
	}

	public function test_ajax_rejects_unknown_tables_and_customers() {
		$this->_setRole( 'shop_manager' );
		$response = $this->do_ajax( self::ACTION, array( 'table' => 'nope', 'hidden' => array() ), ColumnManager::NONCE_ACTION );
		$this->assertFalse( $response['success'] );

		$this->_setRole( 'subscriber' );
		$response = $this->do_ajax( self::ACTION, array( 'table' => 'products', 'hidden' => array( 'sku' ) ), ColumnManager::NONCE_ACTION );
		$this->assertFalse( $response['success'] );
	}
}
