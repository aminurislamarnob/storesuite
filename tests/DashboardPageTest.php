<?php
/**
 * Dashboard-page context tests: Common (page template + body classes),
 * Assets (theme/foreign-plugin asset stripping), and Main's CSS variables.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Tests;

use PluginizeLab\StoreSuite\Assets;
use PluginizeLab\StoreSuite\Common;
use PluginizeLab\StoreSuite\Main;
use WP_UnitTestCase;

/**
 * Tests for behaviour that only runs on the StoreSuite dashboard page.
 *
 * ⚠️ storesuite_is_dashboard_page() memoizes its answer in a function-static
 * that CANNOT be reset for the rest of the PHP process. Every test in this
 * class therefore sets up the dashboard context BEFORE anything calls the
 * helper, and no test here (or anywhere in the suite) may assert the
 * negative ("not a dashboard page") through that helper — once any test
 * evaluates it, the answer is locked in for the whole run.
 */
class DashboardPageTest extends WP_UnitTestCase {

	/**
	 * Shared dashboard page ID.
	 *
	 * @var int
	 */
	private static $page_id;

	/**
	 * Create the dashboard page once for the class.
	 *
	 * @param \WP_UnitTest_Factory $factory Fixture factory.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$page_id = $factory->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'StoreSuite Dashboard',
				'post_content' => '<!-- wp:shortcode -->[storesuite_dashboard]<!-- /wp:shortcode -->',
			)
		);
	}

	/**
	 * Point the settings at the dashboard page and visit it.
	 */
	public function set_up() {
		parent::set_up();

		update_option( 'storesuite_settings', array( 'storesuite_dashboard_page_id' => self::$page_id ) );
		$this->go_to( get_permalink( self::$page_id ) );
	}

	/*
	|-----------------------------------------------------------------------
	| Common
	|-----------------------------------------------------------------------
	*/

	public function test_dashboard_page_uses_the_plugin_page_template() {
		$common = new Common();

		$this->assertSame(
			STORESUITE_TEMPLATE_DIR . '/page-template.php',
			$common->storesuite_register_page_template( 'theme-default.php' )
		);
	}

	public function test_dashboard_body_class_is_added() {
		$classes = ( new Common() )->storesuite_add_body_class( array( 'existing' ) );

		$this->assertContains( 'existing', $classes );
		$this->assertContains( 'storesuite-main-dashboard', $classes );
		$this->assertNotContains( 'storesuite-palette-predefined', $classes, 'No palette class without predefined mode.' );
	}

	public function test_predefined_palette_adds_palette_body_classes() {
		update_option(
			'storesuite_settings',
			array(
				'storesuite_dashboard_page_id'   => self::$page_id,
				'storesuite_color_palette_mode'  => 'predefined',
				'storesuite_color_palette_name'  => 'midnight',
			)
		);

		$classes = ( new Common() )->storesuite_add_body_class( array() );

		$this->assertContains( 'storesuite-palette-predefined', $classes );
		$this->assertContains( 'storesuite-palette-midnight', $classes );
	}

	/*
	|-----------------------------------------------------------------------
	| Assets — theme/foreign-plugin stripping on the dashboard
	|-----------------------------------------------------------------------
	*/

	public function test_theme_and_foreign_plugin_assets_are_stripped_but_allowed_ones_survive() {
		$theme_src   = get_stylesheet_directory_uri() . '/style.css';
		$foreign_src = plugins_url( 'some-random-plugin/assets/app.css' );
		$woo_src     = plugins_url( 'woocommerce/assets/css/woocommerce.css' );
		$core_src    = includes_url( 'css/dashicons.css' );

		wp_register_style( 'test-theme-style', $theme_src );
		wp_register_style( 'test-foreign-style', $foreign_src );
		wp_register_style( 'test-woo-style', $woo_src );
		wp_register_style( 'test-core-style', $core_src );
		wp_register_script( 'test-foreign-script', plugins_url( 'some-random-plugin/app.js' ), array(), '1.0', true );

		( new Assets() )->remove_all_theme_assets();

		$this->assertFalse( wp_style_is( 'test-theme-style', 'registered' ), 'Theme assets are stripped.' );
		$this->assertFalse( wp_style_is( 'test-foreign-style', 'registered' ), 'Foreign plugin assets are stripped.' );
		$this->assertFalse( wp_script_is( 'test-foreign-script', 'registered' ) );
		$this->assertTrue( wp_style_is( 'test-woo-style', 'registered' ), 'WooCommerce is on the default allow-list.' );
		$this->assertTrue( wp_style_is( 'test-core-style', 'registered' ), 'WordPress core assets are never touched.' );
	}

	public function test_allow_list_filters_can_keep_a_plugin_or_a_single_handle() {
		add_filter(
			'storesuite_allowed_plugin_slugs',
			function ( $slugs ) {
				$slugs[] = 'my-companion-plugin';
				return $slugs;
			}
		);
		add_filter(
			'storesuite_allowed_asset_handles',
			function ( $handles ) {
				$handles[] = 'keep-this-handle';
				return $handles;
			}
		);

		wp_register_style( 'companion-style', plugins_url( 'my-companion-plugin/style.css' ) );
		wp_register_style( 'keep-this-handle', plugins_url( 'some-random-plugin/style.css' ) );
		wp_register_style( 'still-stripped', plugins_url( 'some-random-plugin/other.css' ) );

		( new Assets() )->remove_all_theme_assets();

		$this->assertTrue( wp_style_is( 'companion-style', 'registered' ) );
		$this->assertTrue( wp_style_is( 'keep-this-handle', 'registered' ), 'An allow-listed handle survives even from a disallowed plugin.' );
		$this->assertFalse( wp_style_is( 'still-stripped', 'registered' ) );
	}

	/*
	|-----------------------------------------------------------------------
	| Main — CSS variables from appearance settings
	|-----------------------------------------------------------------------
	*/

	public function test_css_variables_are_injected_from_the_appearance_settings() {
		update_option(
			'storesuite_settings',
			array(
				'storesuite_dashboard_page_id'       => self::$page_id,
				'storesuite_color_button_background' => '#123456',
				'storesuite_color_button_text'       => '#ffffff',
			)
		);

		( new Main() )->add_storesuite_css_variables();

		$this->assertTrue( wp_style_is( 'storesuite-css-variables', 'enqueued' ) );

		$inline = wp_styles()->get_data( 'storesuite-css-variables', 'after' );
		$css    = implode( '', (array) $inline );
		$this->assertStringContainsString( '--storesuite-primary-bg: #123456', $css );
		$this->assertStringContainsString( '--storesuite-button-text-color: #ffffff', $css );
	}

	public function test_no_css_variable_style_is_registered_without_color_settings() {
		// $wp_styles is NOT reset between tests: drop the handle the previous
		// test enqueued before asserting on it.
		wp_dequeue_style( 'storesuite-css-variables' );
		wp_deregister_style( 'storesuite-css-variables' );

		( new Main() )->add_storesuite_css_variables();

		$this->assertFalse( wp_style_is( 'storesuite-css-variables', 'enqueued' ), 'No colors configured means no injected style.' );
	}
}
