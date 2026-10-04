<?php

namespace PluginizeLab\StoreSuite\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin admin page settings class
 */
class Settings {
	/**
	 * The constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_settings_menu' ), 100 );
	}

	/**
	 * Register settings main menu
	 *
	 * @return void
	 */
	public function add_admin_settings_menu() {
		add_submenu_page(
			'woocommerce',
			'StoreSuite Settings',
			'StoreSuite',
			'manage_options',
			'storesuite',
			array( $this, 'settings_page_content' ),
		);
	}

	/**
	 * Plugin settings page
	 *
	 * @return void
	 */
	public function settings_page_content() {
		echo '<div id="storesuite-settings"></div>';
	}
}
