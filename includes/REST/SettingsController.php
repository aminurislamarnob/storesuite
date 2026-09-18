<?php

namespace PluginizeLab\StoreSuite\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_REST_Controller;
use WP_REST_Server;

/**
 * Admin settings REST API controller.
 */
class SettingsController extends WP_REST_Controller {

	/**
	 * The namespace of this controller's route.
	 *
	 * @var string
	 */
	protected $namespace;

	/**
	 * The base of this controller's route.
	 *
	 * @var string
	 */
	protected $rest_base;

	/**
	 * Constructor.
	 *
	 * Sets the namespace and rest base for the controller.
	 */
	public function __construct() {
		$this->namespace = 'storesuite/v1';
		$this->rest_base = 'settings';
	}

	/**
	 * Register the routes for the objects of the controller.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'get_settings_permissions_check' ),
					'args'                => array(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'update_settings_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
				),
			)
		);
	}

	/**
	 * Get the settings.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error The response or error object.
	 */
	public function get_settings( $request ) {
		$settings = get_option( 'storesuite_settings', array() );

		return rest_ensure_response( $settings );
	}

	/**
	 * Update the settings.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error The response or error object.
	 */
	public function update_settings( $request ) {
		$storesuite_settings = get_option( 'storesuite_settings', array() );

		if ( $request->has_param( 'storesuite_dashboard_page_id' ) ) {
			$storesuite_settings['storesuite_dashboard_page_id'] = sanitize_text_field( $request->get_param( 'storesuite_dashboard_page_id' ) );
		}

		if ( $request->has_param( 'storesuite_prevent_admin_access' ) ) {
			$val = $request->get_param( 'storesuite_prevent_admin_access' );
			$storesuite_settings['storesuite_prevent_admin_access'] = sanitize_text_field( $val );
		}

		$branding_image_keys = array(
			'storesuite_dashboard_sidebar_logo_id',
			'storesuite_dashboard_sidebar_icon_id',
			'storesuite_dashboard_sidebar_logo_dark_id',
			'storesuite_dashboard_sidebar_icon_dark_id',
		);

		foreach ( $branding_image_keys as $key ) {
			if ( ! $request->has_param( $key ) ) {
				continue;
			}

			$attachment_id = absint( $request->get_param( $key ) );

			if ( $attachment_id > 0 && wp_attachment_is_image( $attachment_id ) ) {
				$storesuite_settings[ $key ] = $attachment_id;
			} else {
				unset( $storesuite_settings[ $key ] );
			}
		}

		if ( $request->has_param( 'storesuite_product_per_page' ) ) {
			$storesuite_settings['storesuite_product_per_page'] = sanitize_text_field( $request->get_param( 'storesuite_product_per_page' ) );
		}

		if ( $request->has_param( 'storesuite_order_per_page' ) ) {
			$storesuite_settings['storesuite_order_per_page'] = sanitize_text_field( $request->get_param( 'storesuite_order_per_page' ) );
		}

		if ( $request->has_param( 'storesuite_category_per_page' ) ) {
			$storesuite_settings['storesuite_category_per_page'] = sanitize_text_field( $request->get_param( 'storesuite_category_per_page' ) );
		}

		if ( $request->has_param( 'storesuite_tag_per_page' ) ) {
			$storesuite_settings['storesuite_tag_per_page'] = sanitize_text_field( $request->get_param( 'storesuite_tag_per_page' ) );
		}

		if ( $request->has_param( 'storesuite_brand_per_page' ) ) {
			$storesuite_settings['storesuite_brand_per_page'] = sanitize_text_field( $request->get_param( 'storesuite_brand_per_page' ) );
		}

		if ( $request->has_param( 'storesuite_coupon_per_page' ) ) {
			$storesuite_settings['storesuite_coupon_per_page'] = sanitize_text_field( $request->get_param( 'storesuite_coupon_per_page' ) );
		}

		$ai_field_keys = array(
			'storesuite_ai_field_title',
			'storesuite_ai_field_description',
			'storesuite_ai_field_short_description',
			'storesuite_ai_field_featured_image',
			'storesuite_ai_field_gallery_image',
			'storesuite_ai_field_bundle',
		);
		foreach ( $ai_field_keys as $key ) {
			if ( $request->has_param( $key ) ) {
				$storesuite_settings[ $key ] = 'no' === $request->get_param( $key ) ? 'no' : 'yes';
			}
		}

		$notification_keys = array(
			'storesuite_notification_new_order',
			'storesuite_notification_new_customer',
			'storesuite_notification_product_review',
		);
		foreach ( $notification_keys as $key ) {
			if ( $request->has_param( $key ) ) {
				$storesuite_settings[ $key ] = 'no' === $request->get_param( $key ) ? 'no' : 'yes';
			}
		}

		$ai_instruction_keys = array(
			'storesuite_ai_instruction_title',
			'storesuite_ai_instruction_description',
			'storesuite_ai_instruction_short_description',
			'storesuite_ai_image_instruction',
		);
		foreach ( $ai_instruction_keys as $key ) {
			if ( $request->has_param( $key ) ) {
				$storesuite_settings[ $key ] = sanitize_textarea_field( $request->get_param( $key ) );
			}
		}

		$color_keys = array(
			'storesuite_color_button_text',
			'storesuite_color_button_background',
			'storesuite_color_button_hover_text',
			'storesuite_color_button_hover_background',
			'storesuite_text_color',
			'storesuite_title_text_color',
			'storesuite_lite_text_color',
			'storesuite_icon_color',
			'storesuite_color_sidebar_menu_text',
			'storesuite_color_sidebar_background',
			'storesuite_color_sidebar_active_text',
			'storesuite_color_sidebar_active_background',
			'storesuite_color_sidebar_border',
			'storesuite_color_border',
			'storesuite_color_lite_bg',
		);
		/*
		 * Dark mode only overrides the neutrals (surfaces, text, borders). Button and
		 * active-menu accents are deliberately absent so the light palette's primary
		 * colors carry over into dark mode.
		 */
		$dark_color_keys = array(
			'storesuite_dark_text_color',
			'storesuite_dark_title_text_color',
			'storesuite_dark_lite_text_color',
			'storesuite_dark_icon_color',
			'storesuite_dark_color_sidebar_menu_text',
			'storesuite_dark_color_sidebar_background',
			'storesuite_dark_color_sidebar_active_text',
			'storesuite_dark_color_sidebar_border',
			'storesuite_dark_color_border',
			'storesuite_dark_color_lite_bg',
			'storesuite_dark_color_page_bg',
			'storesuite_dark_color_surface_bg',
		);

		foreach ( array_merge( $color_keys, $dark_color_keys ) as $key ) {
			if ( $request->has_param( $key ) ) {
				$sanitized_hex               = sanitize_hex_color( $request->get_param( $key ) );
				$storesuite_settings[ $key ] = $sanitized_hex ? $sanitized_hex : sanitize_text_field( $request->get_param( $key ) );
			}
		}

		if ( $request->has_param( 'storesuite_color_palette_mode' ) ) {
			$mode = $request->get_param( 'storesuite_color_palette_mode' );
			if ( in_array( $mode, array( 'predefined', 'custom' ), true ) ) {
				$storesuite_settings['storesuite_color_palette_mode'] = $mode;
			}
		}

		if ( $request->has_param( 'storesuite_color_palette_name' ) ) {
			$storesuite_settings['storesuite_color_palette_name'] = sanitize_text_field( $request->get_param( 'storesuite_color_palette_name' ) );
		}

		if ( $request->has_param( 'storesuite_dark_theme' ) ) {
			$storesuite_settings['storesuite_dark_theme'] = sanitize_text_field( $request->get_param( 'storesuite_dark_theme' ) );
		}

		if ( $request->has_param( 'storesuite_attribution_logo_variant' ) ) {
			$variant = $request->get_param( 'storesuite_attribution_logo_variant' );
			if ( in_array( $variant, array( 'dark', 'light' ), true ) ) {
				$storesuite_settings['storesuite_attribution_logo_variant'] = $variant;
			}
		}

		update_option( 'storesuite_settings', $storesuite_settings );

		return $this->get_settings( $request );
	}

	/**
	 * Check if a given request has access to get the settings.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool True if the request has access, false otherwise.
	 */
	public function get_settings_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check if a given request has access to update the settings.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool True if the request has access, false otherwise.
	 */
	public function update_settings_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get the schema for a single item, if any.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'settings',
			'type'       => 'object',
			'properties' => array(
				'storesuite_dashboard_page_id'             => array(
					'description' => __( 'Dashboard Page.', 'storesuite' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
				),
				'storesuite_prevent_admin_access'          => array(
					'description' => __( 'Prevent vendors from accessing wp-admin. If HPOS is enabled, admin access is blocked regardless.', 'storesuite' ),
					'type'        => 'string',
					'enum'        => array( 'yes', 'no' ),
					'context'     => array( 'view', 'edit' ),
				),
				'storesuite_dashboard_sidebar_logo_id'     => array(
					'description' => __( 'Attachment ID for the dashboard sidebar logo image.', 'storesuite' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
				),
				'storesuite_dashboard_sidebar_icon_id'     => array(
					'description' => __( 'Attachment ID for the dashboard sidebar icon (shown when the sidebar is collapsed).', 'storesuite' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
				),
				'storesuite_dashboard_sidebar_logo_dark_id' => array(
					'description' => __( 'Attachment ID for the dashboard sidebar logo used in dark mode. Falls back to the light logo when empty.', 'storesuite' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
				),
				'storesuite_dashboard_sidebar_icon_dark_id' => array(
					'description' => __( 'Attachment ID for the dashboard sidebar icon used in dark mode. Falls back to the light icon when empty.', 'storesuite' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
				),
				'storesuite_product_per_page'              => array(
					'description' => __( 'Products Per Page.', 'storesuite' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'storesuite_order_per_page'                => array(
					'description' => __( 'Orders Per Page.', 'storesuite' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'storesuite_category_per_page'             => array(
					'description' => __( 'Categories Per Page.', 'storesuite' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'storesuite_tag_per_page'                  => array(
					'description' => __( 'Tags Per Page.', 'storesuite' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'storesuite_brand_per_page'                => array(
					'description' => __( 'Brands Per Page.', 'storesuite' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'storesuite_coupon_per_page'               => array(
					'description' => __( 'Coupons Per Page.', 'storesuite' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'storesuite_color_button_text'             => array(
					'description' => __( 'Button text color.', 'storesuite' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'storesuite_color_button_background'       => array(
					'description' => __( 'Button background color.', 'storesuite' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'storesuite_color_button_border'           => array(
					'description' => __( 'Button border color.', 'storesuite' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'storesuite_color_button_hover_text'       => array(
					'description' => __( 'Button hover text color.', 'storesuite' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'storesuite_color_button_hover_background' => array(
					'description' => __( 'Button hover background color.', 'storesuite' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'storesuite_color_button_hover_border'     => array(
					'description' => __( 'Button hover border color.', 'storesuite' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'storesuite_color_sidebar_menu_text'       => array(
					'description' => __( 'Dashboard sidebar menu text color.', 'storesuite' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'storesuite_color_sidebar_background'      => array(
					'description' => __( 'Dashboard sidebar background color.', 'storesuite' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'storesuite_color_sidebar_active_text'     => array(
					'description' => __( 'Dashboard sidebar active/hover menu text color.', 'storesuite' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'storesuite_color_sidebar_active_background' => array(
					'description' => __( 'Dashboard sidebar active menu background color.', 'storesuite' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'storesuite_color_sidebar_border' => array(
					'description' => __( 'Dashboard sidebar border color.', 'storesuite' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'storesuite_color_palette_mode' => array(
					'description' => __( 'Color palette mode: predefined or custom.', 'storesuite' ),
					'type'        => 'string',
					'enum'        => array( 'predefined', 'custom' ),
					'context'     => array( 'view', 'edit' ),
				),
				'storesuite_dark_theme' => array(
					'description' => __( 'Active dark mode theme slug. Only the neutrals change; the light palette supplies the accent colors.', 'storesuite' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'storesuite_color_palette_name' => array(
					'description' => __( 'Active predefined color palette slug.', 'storesuite' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'storesuite_attribution_logo_variant' => array(
					'description' => __( 'Attribution logo variant for the custom palette: dark or light.', 'storesuite' ),
					'type'        => 'string',
					'enum'        => array( 'dark', 'light' ),
					'context'     => array( 'view', 'edit' ),
				),
				'storesuite_ai_field_title'           => array(
					'description' => __( 'Enable AI generation for the product title.', 'storesuite' ),
					'type'        => 'string',
					'enum'        => array( 'yes', 'no' ),
					'context'     => array( 'view', 'edit' ),
				),
				'storesuite_ai_field_description'     => array(
					'description' => __( 'Enable AI generation for the product long description.', 'storesuite' ),
					'type'        => 'string',
					'enum'        => array( 'yes', 'no' ),
					'context'     => array( 'view', 'edit' ),
				),
				'storesuite_ai_field_short_description' => array(
					'description' => __( 'Enable AI generation for the product short description.', 'storesuite' ),
					'type'        => 'string',
					'enum'        => array( 'yes', 'no' ),
					'context'     => array( 'view', 'edit' ),
				),
				'storesuite_ai_field_featured_image'  => array(
					'description' => __( 'Enable AI generation for the product featured image.', 'storesuite' ),
					'type'        => 'string',
					'enum'        => array( 'yes', 'no' ),
					'context'     => array( 'view', 'edit' ),
				),
				'storesuite_ai_field_gallery_image'   => array(
					'description' => __( 'Enable AI generation for the product gallery images.', 'storesuite' ),
					'type'        => 'string',
					'enum'        => array( 'yes', 'no' ),
					'context'     => array( 'view', 'edit' ),
				),
				'storesuite_ai_field_bundle'          => array(
					'description' => __( 'Enable the global "Generate with AI" button that drafts all product copy at once.', 'storesuite' ),
					'type'        => 'string',
					'enum'        => array( 'yes', 'no' ),
					'context'     => array( 'view', 'edit' ),
				),
				'storesuite_notification_new_order'   => array(
					'description' => __( 'Record a dashboard notification when a new order is placed.', 'storesuite' ),
					'type'        => 'string',
					'enum'        => array( 'yes', 'no' ),
					'context'     => array( 'view', 'edit' ),
				),
				'storesuite_notification_new_customer' => array(
					'description' => __( 'Record a dashboard notification when a new customer registers.', 'storesuite' ),
					'type'        => 'string',
					'enum'        => array( 'yes', 'no' ),
					'context'     => array( 'view', 'edit' ),
				),
				'storesuite_notification_product_review' => array(
					'description' => __( 'Record a dashboard notification when a product review is submitted.', 'storesuite' ),
					'type'        => 'string',
					'enum'        => array( 'yes', 'no' ),
					'context'     => array( 'view', 'edit' ),
				),
				'storesuite_ai_instruction_title'     => array(
					'description' => __( 'Custom system instruction for the product title. Falls back to the built-in default when empty.', 'storesuite' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'storesuite_ai_instruction_description' => array(
					'description' => __( 'Custom system instruction for the product long description. Falls back to the built-in default when empty.', 'storesuite' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'storesuite_ai_instruction_short_description' => array(
					'description' => __( 'Custom system instruction for the product short description. Falls back to the built-in default when empty.', 'storesuite' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'storesuite_ai_image_instruction'     => array(
					'description' => __( 'Custom styling guidance appended to product image prompts. Falls back to the built-in default when empty.', 'storesuite' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
			),
		);
	}
}
