<?php
/**
 * Tests for the batched order CSV export (Tier 2, issue #190).
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test\Order;

use PluginizeLab\StoreSuite\Order\OrderExporter;
use PluginizeLab\StoreSuite\Test\StoreSuiteAjaxTestCase;

/**
 * @covers \PluginizeLab\StoreSuite\Order\OrderExporter
 * @covers \PluginizeLab\StoreSuite\Order\OrderExportController
 * @group storesuite-order
 * @group storesuite-ajax
 */
class OrderExportTest extends StoreSuiteAjaxTestCase {

	const ACTION = 'storesuite_order_export';
	const NONCE  = 'storesuite_order_export';

	/**
	 * Files written by the exporter during a test, removed in tear_down().
	 *
	 * @var string[]
	 */
	private $files = array();

	public function tear_down() {
		foreach ( $this->files as $file ) {
			if ( file_exists( $file ) ) {
				wp_delete_file( $file );
			}
			if ( file_exists( $file . '.headers' ) ) {
				wp_delete_file( $file . '.headers' );
			}
		}
		parent::tear_down();
	}

	/**
	 * Track a temp file for cleanup and return its full path.
	 *
	 * @param string $filename File name inside the uploads dir.
	 * @return string
	 */
	private function track( $filename ) {
		$upload_dir    = wp_upload_dir();
		$this->files[] = trailingslashit( $upload_dir['basedir'] ) . $filename;
		return end( $this->files );
	}

	/**
	 * Read a generated export the way the download endpoint serves it
	 * (headers row + batch file) and parse it into rows keyed by header.
	 *
	 * @param OrderExporter $exporter Exporter that generated the file.
	 * @param string        $path     Batch file path.
	 * @return array<int, array<string, string>>
	 */
	private function read_csv( OrderExporter $exporter, $path ) {
		$csv = $exporter->get_headers_row_file() . file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$csv = preg_replace( '/^\xEF\xBB\xBF/', '', $csv );

		$lines  = array_values( array_filter( explode( "\n", trim( $csv ) ), 'strlen' ) );
		$header = str_getcsv( array_shift( $lines ), ',', '"', '\\' );
		$rows   = array();

		foreach ( $lines as $line ) {
			$rows[] = array_combine( $header, str_getcsv( $line, ',', '"', '\\' ) );
		}

		return $rows;
	}

	public function test_one_row_per_order_with_flattened_items() {
		$product = self::factory()->product->create(
			array(
				'name' => 'Blue Hoodie',
				'sku'  => 'HOOD-1',
			)
		);
		$order   = self::factory()->order->create(
			array(
				'status'   => 'completed',
				'product'  => $product,
				'quantity' => 2,
			)
		);
		$order->set_billing_email( 'alice@example.org' );
		$order->set_billing_first_name( 'Alice' );
		$order->save();

		$exporter = new OrderExporter();
		$exporter->set_filename( 'storesuite-test-orders.csv' );
		$path = $this->track( 'storesuite-test-orders.csv' );
		$exporter->set_page( 1 );
		$exporter->generate_file();

		$this->assertSame( 100, $exporter->get_percent_complete() );

		$rows = $this->read_csv( $exporter, $path );
		$this->assertCount( 1, $rows, 'One row per order.' );

		$row = $rows[0];
		$this->assertSame( (string) $order->get_id(), $row['Order ID'] );
		$this->assertSame( 'completed', $row['Status'] );
		$this->assertSame( 'alice@example.org', $row['Customer email'] );
		$this->assertSame( 'Alice', $row['Billing first name'] );
		$this->assertSame( 'Blue Hoodie [HOOD-1] × 2', $row['Items'] );
		$this->assertSame( '2', $row['Item count'] );
		$this->assertSame( wc_format_decimal( $order->get_total(), wc_get_price_decimals() ), $row['Order total'] );
	}

	public function test_status_and_date_filters_narrow_the_rows() {
		$done = self::factory()->order->create( array( 'status' => 'completed' ) );
		$open = self::factory()->order->create( array( 'status' => 'processing' ) );

		$old = self::factory()->order->create( array( 'status' => 'completed' ) );
		$old->set_date_created( '2020-01-15 10:00:00' );
		$old->save();

		$exporter = new OrderExporter();
		$exporter->set_filename( 'storesuite-test-filtered.csv' );
		$path = $this->track( 'storesuite-test-filtered.csv' );
		$exporter->set_statuses_to_export( array( 'completed' ) );
		$exporter->set_date_range( '2021-01-01', '' );
		$exporter->set_page( 1 );
		$exporter->generate_file();

		$ids = wp_list_pluck( $this->read_csv( $exporter, $path ), 'Order ID' );

		$this->assertSame( array( (string) $done->get_id() ), $ids );
		$this->assertNotContains( (string) $open->get_id(), $ids );
		$this->assertNotContains( (string) $old->get_id(), $ids );
	}

	public function test_selected_ids_and_columns_override_filters() {
		$a = self::factory()->order->create( array( 'status' => 'processing' ) );
		$b = self::factory()->order->create( array( 'status' => 'processing' ) );
		self::factory()->order->create( array( 'status' => 'processing' ) );

		$exporter = new OrderExporter();
		$exporter->set_filename( 'storesuite-test-selected.csv' );
		$path = $this->track( 'storesuite-test-selected.csv' );
		$exporter->set_order_ids_to_export( array( $a->get_id(), $b->get_id() ) );
		$exporter->set_columns_to_export( array( 'order_id', 'status' ) );
		$exporter->set_page( 1 );
		$exporter->generate_file();

		$rows = $this->read_csv( $exporter, $path );

		$this->assertCount( 2, $rows );
		$this->assertSame( array( 'Order ID', 'Status' ), array_keys( $rows[0] ) );
	}

	public function test_ajax_step_reports_done_with_a_download_url() {
		$this->_setRole( 'shop_manager' );
		self::factory()->order->create();

		$this->track( 'storesuite-ajax.csv' );
		$response = $this->do_ajax(
			self::ACTION,
			array(
				'step'     => 1,
				'filename' => 'storesuite-ajax.csv',
			),
			self::NONCE
		);

		$this->assertTrue( $response['success'], wp_json_encode( $response ) );
		$this->assertSame( 'done', $response['data']['step'] );
		$this->assertStringContainsString( 'action=storesuite_download_order_csv', $response['data']['url'] );
		$this->assertStringContainsString( 'filename=storesuite-ajax.csv', $response['data']['url'] );
	}

	public function test_customer_is_denied() {
		$this->_setRole( 'subscriber' );

		$response = $this->do_ajax( self::ACTION, array( 'step' => 1 ), self::NONCE );

		$this->assertFalse( $response['success'] );
	}
}
