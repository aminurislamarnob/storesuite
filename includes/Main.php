<?php

/**
 * Main class.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main class.
 *
 * Handles main functionality of the plugin.
 */
class Main {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'login_redirect', array( $this, 'filter_login_redirect' ), 1, 3 );
		add_filter( 'woocommerce_login_redirect', array( $this, 'redirect_after_login' ), 1, 2 );
		add_action( 'admin_init', array( $this, 'block_admin_access' ) );
		add_action( 'template_redirect', array( $this, 'redirect_if_not_logged_in_manager' ), 11 );
		add_filter( 'show_admin_bar', array( $this, 'hide_admin_bar' ) );
		add_action( 'woocommerce_account_dashboard', array( $this, 'add_storesuite_dashboard_btn' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'add_storesuite_dashboard_btn_css' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'add_storesuite_css_variables' ), 20 );
		add_filter( 'user_has_cap', array( $this, 'grant_storesuite_caps_to_managers' ), 10, 2 );
		add_action( 'wp_head', array( $this, 'add_storesuite_theme_mode_script' ), 1 );
	}

	/**
	 * Treat `manage_woocommerce` as implicitly holding every granular
	 * `storesuite_{area}` capability.
	 *
	 * The granular caps back the per-area permission model
	 * (see storesuite_current_user_can()). Most call sites go through that
	 * helper, which already accepts manage_woocommerce — but a few check a
	 * granular cap with the raw current_user_can() (e.g. DashboardMenu's per-item
	 * `permission` key). Without this, a shop manager or admin would lose menu
	 * items whose permission was set to a granular cap. Granting the caps on
	 * demand keeps managers all-powerful while letting granular-permission
	 * modules restrict individual staff roles.
	 *
	 * @param array $allcaps The user's current capabilities.
	 * @param array $caps    The primitive caps being checked this call.
	 * @return array
	 */
	public function grant_storesuite_caps_to_managers( $allcaps, $caps ) {
		if ( empty( $allcaps['manage_woocommerce'] ) ) {
			return $allcaps;
		}

		foreach ( (array) $caps as $cap ) {
			if ( is_string( $cap ) && 0 === strpos( $cap, 'storesuite_' ) ) {
				$allcaps[ $cap ] = true;
			}
		}

		return $allcaps;
	}

	/**
	 * Print an inline head script that resolves the dashboard color scheme
	 * (light/dark) before first paint to avoid a flash of the wrong theme.
	 *
	 * The user's explicit choice is read from localStorage; when unset we fall
	 * back to the operating system's `prefers-color-scheme` preference.
	 */
	public function add_storesuite_theme_mode_script() {
		if ( ! storesuite_is_dashboard_page() ) {
			return;
		}
		?>
		<script id="storesuite-theme-mode">
			( function () {
				try {
					var storedMode = localStorage.getItem( 'storesuite_theme_mode' );
					var prefersDark = window.matchMedia && window.matchMedia( '(prefers-color-scheme: dark)' ).matches;
					var mode = ( storedMode === 'dark' || storedMode === 'light' ) ? storedMode : ( prefersDark ? 'dark' : 'light' );
					document.documentElement.setAttribute( 'data-theme', mode );
				} catch ( error ) {
					document.documentElement.setAttribute( 'data-theme', 'light' );
				}
			} )();
		</script>
		<?php
	}

	/**
	 * Block user access to admin panel for specific roles
	 *
	 * @global string $pagenow
	 */
	public function block_admin_access() {
		global $pagenow, $current_user;

		if ( defined( 'WP_CLI' ) ) {
			return;
		}

		$is_prevent_admin_access = storesuite_get_option_by_key( 'storesuite_prevent_admin_access' );
		$valid_pages = array( 'admin-ajax.php', 'admin-post.php', 'async-upload.php', 'media-upload.php' );
		$user_role   = reset( $current_user->roles );

		/**
		 * Filter the roles blocked from wp-admin when "prevent admin access" is
		 * on. A granular-permission module registers its custom staff roles
		 * here so they are kept out of wp-admin like shop managers.
		 *
		 * @param string[] $roles Role slugs to block.
		 */
		$blocked_roles = apply_filters( 'storesuite_blocked_admin_roles', array( 'shop_manager', 'customer' ) );

		if ( ( 'yes' === $is_prevent_admin_access ) && in_array( $user_role, $blocked_roles, true ) && ( ! in_array( $pagenow, $valid_pages, true ) ) ) {
			// Managers have somewhere better to be than the shop homepage.
			$redirect = ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'storesuite_access_dashboard' ) ) ? $this->get_storesuite_dashboard_url() : '';

			wp_safe_redirect( $redirect ? $redirect : home_url() );
			exit;
		}
	}

	/**
	 * Hide admin bar for logged-in users if prevent admin access is enabled.
	 *
	 * @param bool $show Whether to show the admin bar.
	 * @return bool
	 */
	public function hide_admin_bar( $show ) {
		if ( ! is_user_logged_in() ) {
			return $show;
		}

		$is_prevent_admin_access = storesuite_get_option_by_key( 'storesuite_prevent_admin_access' );

		if ( 'yes' === $is_prevent_admin_access ) {
			return false;
		}

		return $show;
	}

	/**
	 * Redirect if not logged in and not manager.
	 *
	 * @return void
	 */
	public function redirect_if_not_logged_in_manager() {
		if ( is_page() && storesuite_is_dashboard_page() ) {
			storesuite_redirect_if_not_logged_in();
			storesuite_redirect_if_not_manager();
		}
	}

	/**
	 * Adapter for the core `login_redirect` filter, which passes the requested
	 * redirect between the destination and the user.
	 *
	 * @param string             $redirect_to           The redirect destination URL.
	 * @param string             $requested_redirect_to The requested redirect destination URL.
	 * @param \WP_User|\WP_Error $user                  WP_User on a successful login, WP_Error otherwise.
	 * @return string
	 */
	public function filter_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
		return $this->redirect_after_login( $redirect_to, $user );
	}

	/**
	 * Pick the post-login destination by role.
	 *
	 * Core applies `login_redirect` every time the wp-login.php form is
	 * rendered, not only after a successful login, so anything that is not a
	 * real user (a WP_Error, an empty string) must leave the URL untouched or
	 * the login screen itself gets redirected away.
	 *
	 * @param string             $redirect_to The redirect destination URL.
	 * @param \WP_User|\WP_Error $user        WP_User on a successful login, WP_Error otherwise.
	 * @return string
	 */
	public function redirect_after_login( $redirect_to, $user ) {
		if ( ! $user instanceof \WP_User || ! $user->exists() ) {
			return $redirect_to;
		}

		// 1) Admins → WP admin dashboard.
		if ( in_array( 'administrator', (array) $user->roles, true ) ) {
			return admin_url();
		}

		// 2) Non-admins who can manage WooCommerce, or hold a granular
		// StoreSuite dashboard capability → StoreSuite dashboard.
		if ( user_can( $user, 'manage_woocommerce' ) || user_can( $user, 'storesuite_access_dashboard' ) ) {
			$dashboard_url = $this->get_storesuite_dashboard_url();

			if ( $dashboard_url ) {
				return $dashboard_url;
			}
		}

		// 3) Everyone else → normal My Account page.
		return wc_get_page_permalink( 'myaccount' );
	}

	/**
	 * URL of the StoreSuite dashboard page, or an empty string when no page is set.
	 *
	 * @return string
	 */
	public function get_storesuite_dashboard_url() {
		$page_id = (int) storesuite_get_option_by_key( 'storesuite_dashboard_page_id' );

		return $page_id ? storesuite_get_navigation_url() : '';
	}

	/**
	 * Redirect to the storesuite dashboard page
	 *
	 * @return void
	 */
	public function redirect_to_storesuite_dashboard() {
		$dashboard_url = $this->get_storesuite_dashboard_url();

		if ( $dashboard_url ) {
			wp_safe_redirect( $dashboard_url );
			exit();
		}
	}

	/**
	 * Add go to storesuite dashboard button to the woocommerce my account page
	 *
	 * @return string
	 */
	public function add_storesuite_dashboard_btn() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		printf(
			'<div><a href="%s" class="storesuite-dashboard-btn my-storesuite-button">%s</a></div>',
			esc_url( storesuite_get_navigation_url() ),
			esc_html__( 'StoreSuite Dashboard', 'storesuite' )
		);
	}

	/**
	 * Add CSS for the storesuite dashboard button
	 *
	 * @return void
	 */
	public function add_storesuite_dashboard_btn_css() {

		if ( ! is_user_logged_in() || ! is_account_page() || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$css = 'a.storesuite-dashboard-btn.my-storesuite-button {
			background: #2d5bdb;
			display: inline-flex;
			align-items: center;
			padding: 12px 20px;
			font-size: 14px;
			color: #fff;
			border-radius: 6px;
			cursor: pointer;
			line-height: 1.15;
			border: 1px solid #2d5bdb;
			justify-content: center;
			font-weight: 600;
		}
		a.storesuite-dashboard-btn.my-storesuite-button:hover {
			background: #213fd4;
			border: 1px solid #213fd4;
		}';

		wp_register_style( 'storesuite-dashboard-btn', false );
		wp_enqueue_style( 'storesuite-dashboard-btn' );
		wp_add_inline_style( 'storesuite-dashboard-btn', $css );
	}

	public function add_storesuite_css_variables() {
		if ( ! storesuite_is_dashboard_page() ) {
			return;
		}

		$css_vars = array(
			'--storesuite-primary-bg'             => 'storesuite_color_button_background',
			'--storesuite-primary-bg-hover'      => 'storesuite_color_button_hover_background',
			'--storesuite-button-text-color'     => 'storesuite_color_button_text',
			'--storesuite-button-text-hover-color' => 'storesuite_color_button_hover_text',
			'--storesuite-text-black'             => 'storesuite_title_text_color',
			'--storesuite-text-color'             => 'storesuite_text_color',
			'--storesuite-text-color-light'       => 'storesuite_lite_text_color',
			'--storesuite-icon-color'             => 'storesuite_icon_color',
			'--storesuite-sidebar-bg-color'       => 'storesuite_color_sidebar_background',
			'--storesuite-sidebar-menu-text'     => 'storesuite_color_sidebar_menu_text',
			'--storesuite-sidebar-active-text'    => 'storesuite_color_sidebar_active_text',
			'--storesuite-sidebar-active-background' => 'storesuite_color_sidebar_active_background',
			'--storesuite-sidebar-border-color'   => 'storesuite_color_sidebar_border',
			'--storesuite-bg-color-light'         => 'storesuite_color_lite_bg',
			'--storesuite-border-color'           => 'storesuite_color_border',
		);

		/*
		 * Dark mode overrides only the neutrals — surfaces, text and borders. The button
		 * and active-menu accents are intentionally left out so the light palette's
		 * primary colors carry over from the `:root{}` block above.
		 *
		 * These are written to a `html[data-theme="dark"]:root` block so they outrank the
		 * built-in `:root[data-theme="dark"]` palette in style.css, which stays the
		 * fallback when a store has never saved a dark theme.
		 */
		$dark_css_vars = array(
			'--storesuite-text-black'           => 'storesuite_dark_title_text_color',
			'--storesuite-text-color'           => 'storesuite_dark_text_color',
			'--storesuite-text-color-light'     => 'storesuite_dark_lite_text_color',
			'--storesuite-icon-color'           => 'storesuite_dark_icon_color',
			'--storesuite-sidebar-bg-color'     => 'storesuite_dark_color_sidebar_background',
			'--storesuite-sidebar-menu-text'    => 'storesuite_dark_color_sidebar_menu_text',
			'--storesuite-sidebar-active-text'  => 'storesuite_dark_color_sidebar_active_text',
			'--storesuite-sidebar-border-color' => 'storesuite_dark_color_sidebar_border',
			'--storesuite-bg-color-light'       => 'storesuite_dark_color_lite_bg',
			'--storesuite-border-color'         => 'storesuite_dark_color_border',
			'--storesuite-page-bg'              => 'storesuite_dark_color_page_bg',
			'--storesuite-surface-bg'           => 'storesuite_dark_color_surface_bg',
			'--storesuite-surface-elevated'     => 'storesuite_dark_color_surface_bg',
		);

		$rules      = $this->build_css_variable_rules( $css_vars );
		$dark_rules = $this->build_css_variable_rules( $dark_css_vars );

		if ( empty( $rules ) && empty( $dark_rules ) ) {
			return;
		}

		$css = '';

		if ( ! empty( $rules ) ) {
			$css .= ':root{ ' . esc_attr( implode( ';', $rules ) ) . ' }';
		}

		if ( ! empty( $dark_rules ) ) {
			$css .= 'html[data-theme="dark"]:root{ ' . esc_attr( implode( ';', $dark_rules ) ) . ' }';
		}

		if ( storesuite_get_option_by_key( 'storesuite_color_palette_mode' ) === 'predefined' ) {
			$css .= '.storesuite-table-search-icon svg,'
				. '.storesuite-dashboard-braedcrumb ul li svg,'
				. '.storesuite-dropdown svg{fill:#94a3b8}';
		}

		wp_register_style( 'storesuite-css-variables', false );
		wp_enqueue_style( 'storesuite-css-variables' );
		wp_add_inline_style( 'storesuite-css-variables', $css );
	}

	/**
	 * Turn a CSS variable => option key map into `--var: value` declarations.
	 *
	 * Options that were never saved are skipped so the stylesheet defaults apply.
	 *
	 * @param array $css_vars Map of CSS variable name to settings option key.
	 * @return array List of declarations.
	 */
	private function build_css_variable_rules( $css_vars ) {
		$rules = array();

		foreach ( $css_vars as $var_name => $option_key ) {
			$value = storesuite_get_option_by_key( $option_key );
			if ( $value !== '' && $value !== null ) {
				$rules[] = $var_name . ': ' . esc_attr( $value );
			}
		}

		return $rules;
	}
}
