<?php
/**
 * Tests for the edit history recorder and undo (Tier 2, issue #190).
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test\EditHistory;

use PluginizeLab\StoreSuite\EditHistory\EditHistoryInstaller;
use PluginizeLab\StoreSuite\EditHistory\EditHistoryManager;
use PluginizeLab\StoreSuite\Test\StoreSuiteTestCase;

/**
 * @covers \PluginizeLab\StoreSuite\EditHistory\EditHistoryManager
 * @covers \PluginizeLab\StoreSuite\EditHistory\EditHistoryInstaller
 * @group storesuite-edit-history
 */
class EditHistoryManagerTest extends StoreSuiteTestCase {

	/**
	 * @var EditHistoryManager
	 */
	private $manager;

	public function set_up() {
		parent::set_up();
		wp_set_current_user( $this->shop_manager_id );
		$this->manager = new EditHistoryManager();
	}

	public function test_tables_exist() {
		global $wpdb;

		$this->assertSame( EditHistoryInstaller::get_batches_table(), $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', EditHistoryInstaller::get_batches_table() ) ) );
		$this->assertSame( EditHistoryInstaller::get_items_table(), $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', EditHistoryInstaller::get_items_table() ) ) );
	}

	public function test_diff_reports_only_changed_fields() {
		$before = array(
			'status' => 'publish',
			'sku'    => 'A',
		);
		$after  = array(
			'status' => 'draft',
			'sku'    => 'A',
		);

		$this->assertSame( array( 'status' => array( 'publish', 'draft' ) ), $this->manager->diff( $before, $after ) );
	}

	public function test_record_stores_a_batch_with_one_item_per_changed_field() {
		$product = self::factory()->product->create( array( 'regular_price' => '10' ) );
		$before  = $this->manager->snapshot_product( $product );

		$product->set_regular_price( '12' );
		$product->set_status( 'draft' );
		$product->save();

		$batch_id = $this->manager->record_from_snapshots(
			'inline',
			'product',
			'Test change',
			array( $product->get_id() => $before ),
			array( $product->get_id() => $this->manager->snapshot_product_fresh( $product->get_id() ) )
		);

		$this->assertGreaterThan( 0, $batch_id );

		$batch = $this->manager->get_batch( $batch_id );
		$this->assertSame( (string) $this->shop_manager_id, $batch->user_id );
		$this->assertSame( 'inline', $batch->source );
		$this->assertSame( '2', $batch->item_count );

		$items = wp_list_pluck( $this->manager->get_items( $batch_id ), 'new_value', 'field' );
		$this->assertSame(
			array(
				'status'        => 'draft',
				'regular_price' => '12',
			),
			$items
		);
	}

	public function test_no_batch_when_nothing_changed() {
		$product  = self::factory()->product->create();
		$snapshot = $this->manager->snapshot_product( $product );

		$this->assertSame( 0, $this->manager->record_from_snapshots( 'inline', 'product', 'Noop', array( $product->get_id() => $snapshot ), array( $product->get_id() => $snapshot ) ) );
		$this->assertSame( 0, $this->manager->count_batches() );
	}

	public function test_recording_respects_the_settings_toggle() {
		$this->set_storesuite_option( EditHistoryManager::ENABLED_OPTION, 'no' );

		$this->assertFalse( EditHistoryManager::is_enabled() );
		$this->assertSame( 0, $this->manager->record( 'inline', 'product', 'Off', array( array( 'object_id' => 1, 'field' => 'sku', 'old_value' => 'a', 'new_value' => 'b' ) ) ) );
	}

	public function test_undo_restores_old_values_and_logs_an_undo_batch() {
		$product = self::factory()->product->create(
			array(
				'regular_price' => '10',
				'sku'           => 'BEFORE',
			)
		);
		$before  = $this->manager->snapshot_product( $product );
		$product->set_regular_price( '99' );
		$product->set_sku( 'AFTER' );
		$product->save();

		$batch_id = $this->manager->record_from_snapshots( 'bulk', 'product', 'Change', array( $product->get_id() => $before ), array( $product->get_id() => $this->manager->snapshot_product_fresh( $product->get_id() ) ) );

		$result = $this->manager->undo( $batch_id );

		$this->assertSame( 2, $result['reverted'] );
		$this->assertSame( 0, $result['skipped'] );
		$this->assertSame( array( $product->get_id() ), $result['object_ids'] );

		$restored = wc_get_product( $product->get_id() );
		$this->assertSame( '10', $restored->get_regular_price() );
		$this->assertSame( 'BEFORE', $restored->get_sku() );

		$this->assertNotEmpty( $this->manager->get_batch( $batch_id )->undone_at, 'The original batch is flagged as undone.' );

		$undo_batch = $this->manager->get_batch( $result['batch_id'] );
		$this->assertSame( 'undo', $undo_batch->source );
		$this->assertSame( (string) $batch_id, $undo_batch->undo_of );
		$this->assertSame( 'Undo: Change', $undo_batch->summary );
	}

	public function test_undo_skips_fields_changed_since_the_batch() {
		$product = self::factory()->product->create( array( 'sku' => 'ONE' ) );
		$before  = $this->manager->snapshot_product( $product );
		$product->set_sku( 'TWO' );
		$product->set_status( 'draft' );
		$product->save();

		$batch_id = $this->manager->record_from_snapshots( 'inline', 'product', 'Change', array( $product->get_id() => $before ), array( $product->get_id() => $this->manager->snapshot_product_fresh( $product->get_id() ) ) );

		// Someone edits the SKU again before the undo.
		$product = wc_get_product( $product->get_id() );
		$product->set_sku( 'THREE' );
		$product->save();

		$result = $this->manager->undo( $batch_id );

		$this->assertSame( 1, $result['reverted'] );
		$this->assertSame( 1, $result['skipped'] );

		$restored = wc_get_product( $product->get_id() );
		$this->assertSame( 'THREE', $restored->get_sku(), 'The newer SKU must be left alone.' );
		$this->assertSame( 'publish', $restored->get_status(), 'The status still matched and was reverted.' );
	}

	public function test_undo_cannot_run_twice() {
		$product = self::factory()->product->create( array( 'sku' => 'X' ) );
		$before  = $this->manager->snapshot_product( $product );
		$product->set_sku( 'Y' );
		$product->save();
		$batch_id = $this->manager->record_from_snapshots( 'inline', 'product', 'Change', array( $product->get_id() => $before ), array( $product->get_id() => $this->manager->snapshot_product_fresh( $product->get_id() ) ) );

		$this->assertIsArray( $this->manager->undo( $batch_id ) );
		$this->assertWPError( $this->manager->undo( $batch_id ) );
	}

	public function test_undo_restores_taxonomy_terms() {
		$product = self::factory()->product->create();
		$term    = wp_insert_term( 'History Cat', 'product_cat' );
		$before  = $this->manager->snapshot_product( $product );

		wp_set_object_terms( $product->get_id(), array( $term['term_id'] ), 'product_cat', true );

		$batch_id = $this->manager->record_from_snapshots( 'bulk', 'product', 'Add cat', array( $product->get_id() => $before ), array( $product->get_id() => $this->manager->snapshot_product_fresh( $product->get_id() ) ) );
		$this->assertTrue( has_term( $term['term_id'], 'product_cat', $product->get_id() ) );

		$this->manager->undo( $batch_id );

		$this->assertFalse( has_term( $term['term_id'], 'product_cat', $product->get_id() ) );
	}

	public function test_undo_restores_order_status() {
		$order  = self::factory()->order->create( array( 'status' => 'processing' ) );
		$before = $this->manager->snapshot_order( $order );
		$order->update_status( 'completed' );

		$batch_id = $this->manager->record_from_snapshots( 'order_bulk', 'order', 'Complete', array( $order->get_id() => $before ), array( $order->get_id() => $this->manager->snapshot_order( wc_get_order( $order->get_id() ) ) ) );

		$result = $this->manager->undo( $batch_id );

		$this->assertSame( 1, $result['reverted'] );
		$this->assertSame( 'processing', wc_get_order( $order->get_id() )->get_status() );
	}

	public function test_history_for_one_product_includes_batch_info() {
		$product = self::factory()->product->create( array( 'sku' => 'P1' ) );
		$other   = self::factory()->product->create( array( 'sku' => 'P2' ) );
		$before  = array(
			$product->get_id() => $this->manager->snapshot_product( $product ),
			$other->get_id()   => $this->manager->snapshot_product( $other ),
		);
		$product->set_sku( 'P1-EDIT' );
		$product->save();
		$other->set_sku( 'P2-EDIT' );
		$other->save();

		$this->manager->record_from_snapshots(
			'bulk',
			'product',
			'SKU sweep',
			$before,
			array(
				$product->get_id() => $this->manager->snapshot_product_fresh( $product->get_id() ),
				$other->get_id()   => $this->manager->snapshot_product_fresh( $other->get_id() ),
			)
		);

		$rows = $this->manager->get_for_object( 'product', $product->get_id() );

		$this->assertCount( 1, $rows );
		$this->assertSame( 'sku', $rows[0]->field );
		$this->assertSame( 'SKU sweep', $rows[0]->summary );
		$this->assertSame( (string) $this->shop_manager_id, $rows[0]->user_id );
	}

	public function test_retention_cleanup_removes_old_batches_and_their_items() {
		global $wpdb;

		$this->set_storesuite_option( EditHistoryManager::RETENTION_OPTION, 30 );

		$old_id = $this->manager->record( 'inline', 'product', 'Old', array( array( 'object_id' => 1, 'field' => 'sku', 'old_value' => 'a', 'new_value' => 'b' ) ) );
		$new_id = $this->manager->record( 'inline', 'product', 'New', array( array( 'object_id' => 2, 'field' => 'sku', 'old_value' => 'a', 'new_value' => 'b' ) ) );

		$wpdb->update( EditHistoryInstaller::get_batches_table(), array( 'created_at' => gmdate( 'Y-m-d H:i:s', time() - 40 * DAY_IN_SECONDS ) ), array( 'id' => $old_id ) );

		$this->assertSame( 1, $this->manager->delete_older_than() );
		$this->assertNull( $this->manager->get_batch( $old_id ) );
		$this->assertEmpty( $this->manager->get_items( $old_id ) );
		$this->assertNotNull( $this->manager->get_batch( $new_id ) );
	}

	public function test_format_value_renders_terms_prices_and_statuses() {
		$term = wp_insert_term( 'Shown Cat', 'product_cat' );

		$this->assertSame( 'Shown Cat', EditHistoryManager::format_value( 'product', 'product_cat', wp_json_encode( array( $term['term_id'] ) ) ) );
		$this->assertSame( '—', EditHistoryManager::format_value( 'product', 'product_tag', '[]' ) );
		$this->assertSame( '—', EditHistoryManager::format_value( 'product', 'sale_price', '' ) );
		$this->assertSame( 'Draft', EditHistoryManager::format_value( 'product', 'status', 'draft' ) );
		$this->assertSame( 'Completed', EditHistoryManager::format_value( 'order', 'status', 'completed' ) );
		$this->assertStringContainsString( '12', EditHistoryManager::format_value( 'product', 'regular_price', '12' ) );
	}
}
