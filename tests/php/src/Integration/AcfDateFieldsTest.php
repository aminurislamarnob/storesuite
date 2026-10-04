<?php
/**
 * Tests for the ACF date / time types on the product form.
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test\Integration;

use PluginizeLab\StoreSuite\Integration\Acf\DateFormats;
use PluginizeLab\StoreSuite\Integration\Acf\FieldSanitizer;
use PluginizeLab\StoreSuite\Test\StoreSuiteAjaxTestCase;

/**
 * @covers \PluginizeLab\StoreSuite\Integration\Acf\DateFormats
 * @covers \PluginizeLab\StoreSuite\Integration\Acf\FieldSanitizer
 * @covers \PluginizeLab\StoreSuite\Integration\Acf\FieldRenderer
 * @group storesuite-acf
 * @group storesuite-ajax
 */
class AcfDateFieldsTest extends StoreSuiteAjaxTestCase {

	use AcfTestHelpers;

	const GROUP_KEY = 'group_storesuite_dates';

	/**
	 * Tear down the test fixture.
	 *
	 * @return void
	 */
	public function tear_down() {
		$this->remove_acf_field_groups();
		parent::tear_down();
	}

	/**
	 * Register a group with the three date / time fields, located on products.
	 *
	 * @param array $extra Extra settings merged into every field.
	 * @return void
	 */
	private function register_date_group( array $extra = array() ) {
		$this->register_acf_field_group(
			array(
				'key'      => self::GROUP_KEY,
				'title'    => 'Dates',
				'fields'   => array(
					array_merge(
						array(
							'key'   => 'field_ss_date',
							'name'  => 'ss_date',
							'label' => 'Release date',
							'type'  => 'date_picker',
						),
						$extra
					),
					array_merge(
						array(
							'key'   => 'field_ss_datetime',
							'name'  => 'ss_datetime',
							'label' => 'Launch',
							'type'  => 'date_time_picker',
						),
						$extra
					),
					array_merge(
						array(
							'key'   => 'field_ss_time',
							'name'  => 'ss_time',
							'label' => 'Cut-off time',
							'type'  => 'time_picker',
						),
						$extra
					),
				),
				'location' => $this->acf_product_location(),
			)
		);
	}

	/**
	 * Dispatch the edit-product AJAX action as a shop manager.
	 *
	 * @param int   $product_id Product to edit.
	 * @param array $acf        storesuite_acf values keyed by field key.
	 * @return array Decoded response.
	 */
	private function edit_with_acf( $product_id, array $acf ) {
		$this->_setRole( 'shop_manager' );

		return $this->do_ajax(
			'storesuite_edit_product_action',
			array(
				'product_id'     => $product_id,
				'product_title'  => get_the_title( $product_id ),
				'storesuite_acf' => $acf,
			),
			'_storesuite_edit_product_',
			'storesuite_edit_product_nonce'
		);
	}

	// -------------------------------------------------------------------------
	// Conversions (no ACF needed).
	// -------------------------------------------------------------------------

	/**
	 * @dataProvider to_storage_provider
	 */
	public function test_input_converts_to_acf_storage_format( $type, $input, $expected ) {
		$this->assertSame( $expected, DateFormats::to_storage( $type, $input ) );
		$this->assertSame( $expected, ( new FieldSanitizer() )->sanitize( array( 'type' => $type ), $input ), 'The sanitiser branch delegates to the converter.' );
	}

	public function to_storage_provider() {
		return array(
			'date'                        => array( 'date_picker', '2026-09-19', '20260919' ),
			'date trimmed'                => array( 'date_picker', ' 2026-01-05 ', '20260105' ),
			'date empty clears'           => array( 'date_picker', '', '' ),
			'date impossible day'         => array( 'date_picker', '2026-02-30', '' ),
			'date wrong format'           => array( 'date_picker', '19/09/2026', '' ),
			'date garbage'                => array( 'date_picker', 'next tuesday', '' ),
			'date array'                  => array( 'date_picker', array( '2026-09-19' ), '' ),
			'datetime local'              => array( 'date_time_picker', '2026-09-19T14:30', '2026-09-19 14:30:00' ),
			'datetime local with seconds' => array( 'date_time_picker', '2026-09-19T14:30:15', '2026-09-19 14:30:15' ),
			'datetime space separator'    => array( 'date_time_picker', '2026-09-19 14:30', '2026-09-19 14:30:00' ),
			'datetime empty clears'       => array( 'date_time_picker', '', '' ),
			'datetime date only rejected' => array( 'date_time_picker', '2026-09-19', '' ),
			'datetime bad hour'           => array( 'date_time_picker', '2026-09-19T25:00', '' ),
			'time'                        => array( 'time_picker', '09:05', '09:05:00' ),
			'time with seconds'           => array( 'time_picker', '23:59:59', '23:59:59' ),
			'time empty clears'           => array( 'time_picker', '', '' ),
			'time 12h rejected'           => array( 'time_picker', '9:05 pm', '' ),
			'time bad minute'             => array( 'time_picker', '09:65', '' ),
		);
	}

	/**
	 * @dataProvider to_input_provider
	 */
	public function test_storage_converts_to_input_format( $type, $stored, $expected ) {
		$this->assertSame( $expected, DateFormats::to_input( $type, $stored ) );
	}

	public function to_input_provider() {
		return array(
			'date'                    => array( 'date_picker', '20260919', '2026-09-19' ),
			'date legacy iso'         => array( 'date_picker', '2026-09-19', '2026-09-19' ),
			'date empty'              => array( 'date_picker', '', '' ),
			'date null'               => array( 'date_picker', null, '' ),
			'date garbage'            => array( 'date_picker', '2026', '' ),
			'datetime'                => array( 'date_time_picker', '2026-09-19 14:30:00', '2026-09-19T14:30' ),
			'datetime without secs'   => array( 'date_time_picker', '2026-09-19 14:30', '2026-09-19T14:30' ),
			'datetime garbage'        => array( 'date_time_picker', '20260919', '' ),
			'time'                    => array( 'time_picker', '09:05:00', '09:05' ),
			'time without secs'       => array( 'time_picker', '09:05', '09:05' ),
			'time garbage'            => array( 'time_picker', '9am', '' ),
		);
	}

	public function test_conversions_round_trip() {
		foreach ( array( 'date_picker' => '2026-09-19', 'date_time_picker' => '2026-09-19T14:30', 'time_picker' => '14:30' ) as $type => $input ) {
			$this->assertSame( $input, DateFormats::to_input( $type, DateFormats::to_storage( $type, $input ) ), $type );
		}
	}

	// -------------------------------------------------------------------------
	// Rendering.
	// -------------------------------------------------------------------------

	public function test_add_form_renders_the_three_inputs_empty() {
		$this->require_acf();
		$this->register_date_group();

		$html = $this->render_acf_cards( 0 );

		$this->assertMatchesRegularExpression( '/<input[^>]*type="text"[^>]*class="storesuite-form-control storesuite-acf-datepicker"[^>]*name="storesuite_acf\[field_ss_date\]"[^>]*value=""/s', $html );
		$this->assertMatchesRegularExpression( '/<input[^>]*type="datetime-local"[^>]*name="storesuite_acf\[field_ss_datetime\]"[^>]*value=""/s', $html );
		$this->assertMatchesRegularExpression( '/<input[^>]*type="time"[^>]*name="storesuite_acf\[field_ss_time\]"[^>]*value=""/s', $html );
	}

	public function test_default_to_current_date_prefills_the_add_form_only() {
		$this->require_acf();
		$this->register_date_group( array( 'default_to_current_date' => 1 ) );

		$add_html = $this->render_acf_cards( 0 );
		$this->assertStringContainsString( 'value="' . wp_date( 'Y-m-d' ) . '"', $add_html );
		$this->assertMatchesRegularExpression( '/type="datetime-local"[^>]*value="' . wp_date( 'Y-m-d' ) . 'T\d{2}:\d{2}"/s', $add_html );

		$product_id = self::factory()->product->create()->get_id();
		$edit_html  = $this->render_acf_cards( $product_id );
		$this->assertStringNotContainsString( 'value="' . wp_date( 'Y-m-d' ) . '"', $edit_html );
	}

	public function test_edit_form_shows_stored_values_in_input_formats() {
		$this->require_acf();
		$this->register_date_group();

		$product_id = self::factory()->product->create()->get_id();
		// Written the way wp-admin's ACF box writes them.
		update_field( 'field_ss_date', '20261224', $product_id );
		update_field( 'field_ss_datetime', '2026-12-24 18:45:00', $product_id );
		update_field( 'field_ss_time', '07:30:00', $product_id );

		$html = $this->render_acf_cards( $product_id );

		$this->assertMatchesRegularExpression( '/name="storesuite_acf\[field_ss_date\]"[^>]*value="2026-12-24"/s', $html );
		$this->assertMatchesRegularExpression( '/name="storesuite_acf\[field_ss_datetime\]"[^>]*value="2026-12-24T18:45"/s', $html );
		$this->assertMatchesRegularExpression( '/name="storesuite_acf\[field_ss_time\]"[^>]*value="07:30"/s', $html );
	}

	// -------------------------------------------------------------------------
	// Saving.
	// -------------------------------------------------------------------------

	public function test_values_are_stored_in_acf_formats() {
		$this->require_acf();
		$this->register_date_group();

		$product_id = self::factory()->product->create()->get_id();

		$response = $this->edit_with_acf(
			$product_id,
			array(
				'field_ss_date'     => '2026-09-19',
				'field_ss_datetime' => '2026-09-19T14:30',
				'field_ss_time'     => '14:30',
			)
		);

		$this->assertTrue( $response['success'], wp_json_encode( $response ) );
		$this->assertSame( '20260919', get_post_meta( $product_id, 'ss_date', true ) );
		$this->assertSame( '2026-09-19 14:30:00', get_post_meta( $product_id, 'ss_datetime', true ) );
		$this->assertSame( '14:30:00', get_post_meta( $product_id, 'ss_time', true ) );
		$this->assertSame( 'field_ss_date', get_post_meta( $product_id, '_ss_date', true ) );

		// ACF's own formatter reads them back, proving the storage format is right.
		$this->assertSame( '19/09/2026', get_field( 'field_ss_date', $product_id ) );
		$this->assertSame( '19/09/2026 2:30 pm', get_field( 'field_ss_datetime', $product_id ) );
		$this->assertSame( '2:30 pm', get_field( 'field_ss_time', $product_id ) );
	}

	public function test_malformed_input_clears_and_empty_input_clears() {
		$this->require_acf();
		$this->register_date_group();

		$product_id = self::factory()->product->create()->get_id();
		update_field( 'field_ss_date', '20260919', $product_id );
		update_field( 'field_ss_datetime', '2026-09-19 14:30:00', $product_id );
		update_field( 'field_ss_time', '14:30:00', $product_id );

		$response = $this->edit_with_acf(
			$product_id,
			array(
				'field_ss_date'     => '19/09/2026',
				'field_ss_datetime' => '',
				'field_ss_time'     => 'noon',
			)
		);

		$this->assertTrue( $response['success'], wp_json_encode( $response ) );
		$this->assertSame( '', get_post_meta( $product_id, 'ss_date', true ), 'Malformed date clears instead of storing garbage.' );
		$this->assertSame( '', get_post_meta( $product_id, 'ss_datetime', true ), 'Empty input clears.' );
		$this->assertSame( '', get_post_meta( $product_id, 'ss_time', true ), 'Malformed time clears.' );
	}
}
