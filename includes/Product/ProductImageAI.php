<?php

namespace PluginizeLab\StoreSuite\Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI-assisted product image generation.
 *
 * Uses the WordPress 7.0 core AI Client ( wp_ai_client_prompt()->generate_image() )
 * together with the core Connectors API for provider/API-key configuration. No
 * dependency on the standalone "AI" plugin: if core lacks AI support or no
 * image-capable provider is configured, the feature silently disables itself.
 *
 * Flow: generate a preview held in a short-lived transient ( handle_generate() ),
 * then side-load it into the media library only when the merchant inserts it
 * ( handle_insert() ).
 */
class ProductImageAI {

	use AiRequestTrait;

	/**
	 * Transient key prefix for a pending generated image.
	 *
	 * @var string
	 */
	private const TRANSIENT_PREFIX = 'storesuite_ai_img_';

	/**
	 * The constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_storesuite_generate_product_image', array( $this, 'handle_generate' ) );
		add_action( 'wp_ajax_storesuite_insert_product_image', array( $this, 'handle_insert' ) );
		add_action( 'storesuite_product_form_field_label', array( $this, 'render_field_button' ) );
		add_action( 'storesuite_product_form_after', array( $this, 'render_modal' ) );
	}

	/**
	 * Render the AI image generation modal for the product form.
	 *
	 * Hooked on `storesuite_product_form_after`; only outputs when image
	 * generation is available, so the template need not gate it.
	 */
	public function render_modal() {
		if ( ! self::is_supported() ) {
			return;
		}

		storesuite_get_template_part( 'products/ai-image-modal' );
	}

	/**
	 * Render the inline "AI" image button inside a product image field label.
	 *
	 * Hooked on `storesuite_product_form_field_label`. Only renders for the image
	 * targets this class supports and only when image generation is available, so
	 * the template does not need to know whether AI is configured.
	 *
	 * @param string $field Field key passed by the template.
	 */
	public function render_field_button( $field ) {
		$labels = array(
			'featured' => __( 'Generate image with AI', 'storesuite' ),
			'gallery'  => __( 'Generate gallery image with AI', 'storesuite' ),
		);

		if ( ! isset( $labels[ $field ] ) || ! self::is_supported() || ! storesuite_is_ai_field_enabled( $field ) ) {
			return;
		}
		?>
		<button type="button" class="storesuite-ai-image-generate" data-target="<?php echo esc_attr( $field ); ?>" title="<?php echo esc_attr( $labels[ $field ] ); ?>" aria-label="<?php echo esc_attr( $labels[ $field ] ); ?>"><svg class="storesuite-ai-icon" aria-hidden="true" focusable="false"><use href="#storesuite-icon-ai-magic"></use></svg> <?php esc_html_e( 'AI', 'storesuite' ); ?></button>
		<?php
	}

	/**
	 * Whether image generation is available in this environment.
	 *
	 * Requires WordPress 7.0+ (core AI Client), AI support enabled, and at least
	 * one configured provider that supports image generation. Memoized per request.
	 *
	 * @return bool
	 */
	public static function is_supported() {
		return self::is_ai_capability_supported( 'is_supported_for_image_generation' );
	}

	/**
	 * Default styling guidance appended to every image prompt.
	 *
	 * Single source of truth for the built-in image instruction. Used as the
	 * fallback when a merchant has not saved a custom instruction on the AI
	 * settings page, and exposed there to prefill the textarea.
	 *
	 * @return string
	 */
	public static function default_image_instruction() {
		return __( 'professional e-commerce product photograph, clean uncluttered background, soft studio lighting, high detail', 'storesuite' );
	}

	/**
	 * Handle the AJAX request to generate a product image with AI.
	 *
	 * The generated image is held server-side in a short-lived transient keyed
	 * by a one-time token and a preview data URI is returned. Nothing touches
	 * the media library until the merchant clicks Insert ( handle_insert() ).
	 */
	public function handle_generate() {
		check_ajax_referer( '_storesuite_ai_', 'nonce' );
		$image_enabled = storesuite_is_ai_field_enabled( 'featured' ) || storesuite_is_ai_field_enabled( 'gallery' );
		$this->guard_ai_request(
			self::is_supported() && $image_enabled,
			__( 'AI image generation is not available. Connect an AI provider that supports images to use this feature.', 'storesuite' )
		);

		// The core AI Client ships with WordPress 7.0; is_supported() already
		// covers this, but keep the guard inline so the call below is never
		// reached on the 6.9 minimum this plugin still supports.
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			wp_send_json_error(
				array(
					'reason'  => 'unavailable',
					'message' => __( 'AI image generation requires WordPress 7.0 or newer.', 'storesuite' ),
				)
			);
		}

		$prompt = isset( $_POST['prompt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['prompt'] ) ) : '';
		if ( '' === $prompt ) {
			wp_send_json_error( array( 'message' => __( 'Please describe the image you want to generate.', 'storesuite' ) ) );
		}

		// Nudge the model toward clean, usable e-commerce imagery. Merchants can
		// override the styling guidance from the AI settings page.
		$instruction = trim( (string) storesuite_get_option_by_key( 'storesuite_ai_image_instruction' ) );
		if ( '' === $instruction ) {
			$instruction = self::default_image_instruction();
		}
		if ( '' !== $instruction ) {
			$prompt .= ', ' . $instruction;
		}

		// Image generation is slower than text; raise the 30s default request
		// timeout (and the PHP limit) just for this call.
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 180 );
		}
		$bump_timeout = static function () {
			return 120.0;
		};
		add_filter( 'wp_ai_client_default_request_timeout', $bump_timeout );
		$file = wp_ai_client_prompt( $prompt )->generate_image();
		remove_filter( 'wp_ai_client_default_request_timeout', $bump_timeout );

		if ( is_wp_error( $file ) ) {
			wp_send_json_error( array( 'message' => $file->get_error_message() ) );
		}

		$bytes = $this->get_image_bytes( $file );
		if ( is_wp_error( $bytes ) ) {
			wp_send_json_error( array( 'message' => $bytes->get_error_message() ) );
		}
		if ( '' === $bytes ) {
			wp_send_json_error( array( 'message' => __( 'No image was generated. Please try again.', 'storesuite' ) ) );
		}

		$mime   = $file->getMimeType();
		$token  = wp_generate_password( 20, false );
		$base64 = base64_encode( $bytes ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- encoding generated image bytes for transient storage and a preview data URI, not obfuscation.
		set_transient(
			self::TRANSIENT_PREFIX . $token,
			array(
				'data' => $base64,
				'mime' => $mime,
			),
			15 * MINUTE_IN_SECONDS
		);

		wp_send_json_success(
			array(
				'token'   => $token,
				'preview' => 'data:' . $mime . ';base64,' . $base64,
			)
		);
	}

	/**
	 * Handle the AJAX request to store a generated image in the media library.
	 *
	 * Reads the image bytes generated by handle_generate() from the transient,
	 * side-loads them into the media library, and returns the new attachment id
	 * and thumbnail URL so the form can use it as the product image.
	 */
	public function handle_insert() {
		check_ajax_referer( '_storesuite_ai_', 'nonce' );
		$this->guard_ai_request();

		$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		if ( '' === $token ) {
			wp_send_json_error( array( 'message' => __( 'The generated image has expired. Please generate it again.', 'storesuite' ) ) );
		}

		$stored = get_transient( self::TRANSIENT_PREFIX . $token );
		if ( ! is_array( $stored ) || empty( $stored['data'] ) ) {
			wp_send_json_error( array( 'message' => __( 'The generated image has expired. Please generate it again.', 'storesuite' ) ) );
		}

		$bytes = base64_decode( $stored['data'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- decoding our own generated image, not obfuscation.
		$mime  = isset( $stored['mime'] ) ? $stored['mime'] : 'image/png';

		$attachment_id = $this->sideload_image( $bytes, $mime );
		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
		}

		delete_transient( self::TRANSIENT_PREFIX . $token );

		$url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );
		if ( ! $url ) {
			$url = wp_get_attachment_url( $attachment_id );
		}

		wp_send_json_success(
			array(
				'id'  => $attachment_id,
				'url' => $url,
			)
		);
	}

	/**
	 * Read the raw bytes of a generated image File ( inline or remote ).
	 *
	 * @param object $file AI Client File object.
	 * @return string|\WP_Error Raw image bytes, or WP_Error on failure.
	 */
	private function get_image_bytes( $file ) {
		if ( method_exists( $file, 'isRemote' ) && $file->isRemote() ) {
			$url = $file->getUrl();

			// Guard against SSRF: only fetch validated http(s) URLs that don't resolve to internal hosts.
			if ( ! wp_http_validate_url( $url ) ) {
				return new \WP_Error( 'storesuite_ai_invalid_image_url', __( 'The generated image URL is not valid.', 'storesuite' ) );
			}

			$response = wp_remote_get( $url, array( 'reject_unsafe_urls' => true ) );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			return (string) wp_remote_retrieve_body( $response );
		}

		$base64 = $file->getBase64Data();
		return $base64 ? (string) base64_decode( $base64 ) : ''; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- decoding our own generated image, not obfuscation.
	}

	/**
	 * Side-load raw image bytes into the WordPress media library.
	 *
	 * @param string $bytes Raw image bytes.
	 * @param string $mime  Image MIME type.
	 * @return int|\WP_Error Attachment ID, or WP_Error on failure.
	 */
	private function sideload_image( $bytes, $mime ) {
		$extensions = array(
			'image/png'  => 'png',
			'image/jpeg' => 'jpg',
			'image/webp' => 'webp',
			'image/gif'  => 'gif',
		);
		$extension  = isset( $extensions[ $mime ] ) ? $extensions[ $mime ] : 'png';
		$filename   = 'ai-product-image-' . gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 6, false ) . '.' . $extension;

		$upload = wp_upload_bits( $filename, null, $bytes );
		if ( ! empty( $upload['error'] ) ) {
			return new \WP_Error( 'storesuite_ai_upload_failed', $upload['error'] );
		}

		$filetype   = wp_check_filetype( $upload['file'], null );
		$attachment = array(
			'post_mime_type' => $filetype['type'] ? $filetype['type'] : $mime,
			'post_title'     => __( 'AI generated product image', 'storesuite' ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment, $upload['file'] );
		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			return new \WP_Error( 'storesuite_ai_attach_failed', __( 'Could not add the image to the media library.', 'storesuite' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		$metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		return $attachment_id;
	}
}
