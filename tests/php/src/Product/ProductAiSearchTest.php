<?php
/**
 * Tests for the natural-language AI product search (Tier 2, issue #190).
 *
 * The AI Client itself is never called: the `storesuite_ai_search_pre_response`
 * filter supplies the model's answer, so these tests pin down the allow-list
 * validation that keeps the feature safe whatever the model says.
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test\Product;

use PluginizeLab\StoreSuite\Product\ProductAiSearch;
use PluginizeLab\StoreSuite\Test\StoreSuiteAjaxTestCase;

/**
 * @covers \PluginizeLab\StoreSuite\Product\ProductAiSearch
 * @group storesuite-product
 * @group storesuite-ajax
 */
class ProductAiSearchTest extends StoreSuiteAjaxTestCase {

	const ACTION = 'storesuite_ai_product_search';

	/**
	 * @var ProductAiSearch
	 */
	private $search;

	public function set_up() {
		parent::set_up();
		$this->search = new ProductAiSearch();
		$this->create_dashboard_page();
	}

	/**
	 * Make the AJAX handler believe the AI Client is available and answer with $json.
	 *
	 * @param string $json Model answer.
	 * @return void
	 */
	private function stub_model( $json ) {
		add_filter( 'storesuite_ai_search_available', '__return_true' );
		add_filter(
			'storesuite_ai_search_pre_response',
			function () use ( $json ) {
				return $json;
			}
		);
	}

	public function test_parse_keeps_only_known_keys_and_valid_values() {
		$cat = wp_insert_term( 'Hoodies', 'product_cat' );

		$filters = $this->search->parse_response(
			wp_json_encode(
				array(
					'search_by'    => 'hoodie',
					'product_cat'  => (string) $cat['term_id'],
					'stock_status' => 'outofstock',
					'post_status'  => 'trash',
					'price_max'    => '$10',
					'price_min'    => 'cheap',
					'orderby'      => 'price',
					'order'        => 'DESC',
					'date_from'    => '2024-02-30',
					'sql'          => 'DROP TABLE wp_posts',
				)
			)
		);

		$this->assertEquals(
			array(
				'search_by'    => 'hoodie',
				'product_cat'  => (string) $cat['term_id'],
				'stock_status' => 'outofstock',
				'price_max'    => '10',
				'orderby'      => 'price',
				'order'        => 'desc',
			),
			$filters
		);
		$this->assertArrayNotHasKey( 'post_status', $filters, 'trash is not a listable status.' );
		$this->assertArrayNotHasKey( 'date_from', $filters, 'An impossible date is dropped.' );
		$this->assertArrayNotHasKey( 'sql', $filters );
	}

	public function test_parse_tolerates_code_fences_and_prose() {
		$filters = $this->search->parse_response( "Sure! Here you go:\n```json\n{\"stock_status\":\"instock\",\"price_min\":5,\"price_max\":20}\n```" );

		$this->assertSame(
			array(
				'stock_status' => 'instock',
				'price_min'    => '5',
				'price_max'    => '20',
			),
			$filters
		);
	}

	public function test_parse_returns_nothing_for_garbage() {
		$this->assertSame( array(), $this->search->parse_response( 'I do not understand.' ) );
		$this->assertSame( array(), $this->search->parse_response( '{"bogus":"x","product_cat":"999999"}' ) );
	}

	public function test_ajax_returns_the_filtered_list_url() {
		$this->_setRole( 'shop_manager' );
		$this->stub_model( '{"stock_status":"outofstock","price_max":10,"search_by":"cap"}' );

		$response = $this->do_ajax( self::ACTION, array( 'query' => 'out of stock caps under $10' ), ProductAiSearch::NONCE_ACTION, 'nonce' );

		$this->assertTrue( $response['success'], wp_json_encode( $response ) );
		$this->assertSame( 'outofstock', $response['data']['filters']['stock_status'] );

		wp_parse_str( (string) wp_parse_url( $response['data']['url'], PHP_URL_QUERY ), $query );
		$this->assertStringContainsString( 'products', $response['data']['url'] );
		$this->assertSame( 'outofstock', $query['stock_status'] );
		$this->assertSame( '10', $query['price_max'] );
		$this->assertSame( 'cap', $query['search_by'] );
	}

	public function test_ajax_reports_when_nothing_usable_came_back() {
		$this->_setRole( 'shop_manager' );
		$this->stub_model( '{"nonsense":true}' );

		$response = $this->do_ajax( self::ACTION, array( 'query' => 'purple' ), ProductAiSearch::NONCE_ACTION, 'nonce' );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'no_filters', $response['data']['reason'] );
	}

	public function test_ajax_rejects_empty_queries_and_customers() {
		$this->_setRole( 'shop_manager' );
		$this->stub_model( '{}' );
		$response = $this->do_ajax( self::ACTION, array( 'query' => '   ' ), ProductAiSearch::NONCE_ACTION, 'nonce' );
		$this->assertFalse( $response['success'] );

		$this->_setRole( 'subscriber' );
		$response = $this->do_ajax( self::ACTION, array( 'query' => 'anything' ), ProductAiSearch::NONCE_ACTION, 'nonce' );
		$this->assertFalse( $response['success'] );
	}

	public function test_ajax_is_unavailable_without_an_ai_provider() {
		$this->_setRole( 'shop_manager' );
		add_filter( 'storesuite_ai_search_available', '__return_false' );

		$response = $this->do_ajax( self::ACTION, array( 'query' => 'anything' ), ProductAiSearch::NONCE_ACTION, 'nonce' );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'unavailable', $response['data']['reason'] );
	}
}
