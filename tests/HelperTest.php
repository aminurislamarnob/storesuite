<?php
/**
 * Helper tests: the storesuite_get_page_id() page-option resolver.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Tests;

use PluginizeLab\StoreSuite\Helper;
use WP_UnitTestCase;

/**
 * Tests for PluginizeLab\StoreSuite\Helper.
 */
class HelperTest extends WP_UnitTestCase {

	public function test_unset_page_option_resolves_to_minus_one() {
		delete_option( 'storesuite_dashboard_page_id' );

		$this->assertSame( -1, Helper::storesuite_get_page_id( 'dashboard' ) );
	}

	public function test_page_id_comes_from_the_option_as_a_positive_int() {
		update_option( 'storesuite_dashboard_page_id', '42' );

		$this->assertSame( 42, Helper::storesuite_get_page_id( 'dashboard' ) );
	}

	public function test_page_id_is_overridable_via_its_dynamic_filter() {
		update_option( 'storesuite_dashboard_page_id', '42' );

		add_filter(
			'storesuite_get_dashboard_page_id',
			function () {
				return 99;
			}
		);

		$this->assertSame( 99, Helper::storesuite_get_page_id( 'dashboard' ) );
	}
}
