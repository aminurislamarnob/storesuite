<?php

namespace PluginizeLab\StoreSuite\Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI-assisted product copy generation.
 *
 * Uses the WordPress 7.0 core AI Client ( wp_ai_client_prompt() ) together with
 * the core Connectors API for provider/API-key configuration. No dependency on
 * the standalone "AI" plugin: if core lacks AI support or no provider is
 * configured, the feature silently disables itself.
 */
class ProductAI {

	use AiRequestTrait;

	/**
	 * Supported fields and how their generated output is sanitized.
	 *
	 * @var array<string, string>
	 */
	private const FIELDS = array(
		'title'             => 'text',
		'description'       => 'html',
		'short_description' => 'textarea',
	);

	/**
	 * The constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_storesuite_generate_product_field', array( $this, 'handle_generate' ) );
		add_action( 'wp_ajax_storesuite_generate_product_bundle', array( $this, 'handle_generate_bundle' ) );
		add_action( 'storesuite_dashboard_title_after', array( $this, 'render_bundle_launcher' ) );
		add_action( 'storesuite_product_form_field_label', array( $this, 'render_field_button' ) );
		add_action( 'storesuite_product_form_after', array( $this, 'render_modals' ) );
	}

	/**
	 * Render the AI suggestion and prompt modals for the product form.
	 *
	 * Hooked on `storesuite_product_form_after`; only outputs when text
	 * generation is available, so the template need not gate it.
	 */
	public function render_modals() {
		if ( ! self::is_text_supported() ) {
			return;
		}

		storesuite_get_template_part( 'products/ai-text-modals' );
	}

	/**
	 * Render the inline "Generate with AI" button inside a product field label.
	 *
	 * Hooked on `storesuite_product_form_field_label`. Only renders for the text
	 * fields this class supports and only when text generation is available, so
	 * the template does not need to know whether AI is configured.
	 *
	 * @param string $field Field key passed by the template.
	 */
	public function render_field_button( $field ) {
		if ( ! isset( self::FIELDS[ $field ] ) || ! self::is_text_supported() || ! storesuite_is_ai_field_enabled( $field ) ) {
			return;
		}
		?>
		<button type="button" class="storesuite-ai-generate" data-field="<?php echo esc_attr( $field ); ?>"><svg class="storesuite-ai-icon" aria-hidden="true" focusable="false"><use href="#storesuite-icon-ai-magic"></use></svg> <span class="storesuite-ai-bundle-prefix"><?php esc_html_e( 'Generate with', 'storesuite' ); ?> </span><?php esc_html_e( 'AI', 'storesuite' ); ?></button>
		<?php
	}

	/**
	 * Whether text generation is available in this environment.
	 *
	 * Requires WordPress 7.0+ (core AI Client), AI support enabled, and at least
	 * one configured provider that supports text generation. Memoized per request.
	 *
	 * @return bool
	 */
	public static function is_text_supported() {
		return self::is_ai_capability_supported( 'is_supported_for_text_generation' );
	}

	/**
	 * Whether the all-in-one "Generate with AI" bundle launcher is available.
	 *
	 * The bundle drafts the title, long description and short description
	 * together. It has its own toggle on the AI settings page so it can be
	 * offered (or hidden) independently of the per-field buttons.
	 *
	 * @return bool
	 */
	public static function is_bundle_enabled() {
		return storesuite_is_ai_field_enabled( 'bundle' );
	}

	/**
	 * Handle the AJAX request to generate a product field with AI.
	 */
	public function handle_generate() {
		check_ajax_referer( '_storesuite_ai_', 'nonce' );
		$this->guard_ai_request(
			self::is_text_supported(),
			__( 'AI generation is not available. Connect an AI provider to use this feature.', 'storesuite' )
		);

		$field = isset( $_POST['field'] ) ? sanitize_key( wp_unslash( $_POST['field'] ) ) : '';
		if ( ! isset( self::FIELDS[ $field ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid field.', 'storesuite' ) ) );
		}

		if ( ! storesuite_is_ai_field_enabled( $field ) ) {
			wp_send_json_error( array( 'message' => __( 'AI generation is disabled for this field.', 'storesuite' ) ) );
		}

		$context = $this->get_context_from_request( $_POST );

		// Descriptions need at least a title or some keywords to work from.
		if ( 'title' !== $field && '' === $context['title'] && '' === $context['short_description'] ) {
			wp_send_json_error(
				array(
					'reason'  => 'no_context',
					'message' => __( 'Add a product title or a few keywords first.', 'storesuite' ),
				)
			);
		}

		$result = $this->generate_one( $field, $context );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		if ( ! is_string( $result ) || '' === trim( $result ) ) {
			wp_send_json_error( array( 'message' => __( 'No content was generated. Please try again.', 'storesuite' ) ) );
		}

		wp_send_json_success(
			array(
				'field'   => $field,
				'content' => $this->sanitize_output( $field, $result ),
			)
		);
	}

	/**
	 * Generate title, long description and short description in one request.
	 *
	 * Used by the global "Generate with AI" launcher on the Add New Product
	 * page: the merchant types a short hint and gets all three fields drafted at
	 * once. Each field is generated in turn, feeding the previous output forward
	 * as context (hint -> title -> long description -> short description).
	 */
	public function handle_generate_bundle() {
		check_ajax_referer( '_storesuite_ai_', 'nonce' );
		$this->guard_ai_request(
			self::is_text_supported() && self::is_bundle_enabled(),
			__( 'AI generation is not available. Connect an AI provider to use this feature.', 'storesuite' )
		);

		$hint = isset( $_POST['hint'] ) ? sanitize_textarea_field( wp_unslash( $_POST['hint'] ) ) : '';
		if ( '' === $hint ) {
			wp_send_json_error( array( 'message' => __( 'Please describe your product first.', 'storesuite' ) ) );
		}

		// When regenerating, steer the title away from the previous attempt.
		$previous_title = isset( $_POST['previous_title'] ) ? sanitize_text_field( wp_unslash( $_POST['previous_title'] ) ) : '';

		$title = $this->generate_one(
			'title',
			array(
				'title'             => $hint,
				'short_description' => '',
				'description'       => '',
				'categories'        => array(),
				'previous'          => $previous_title,
			)
		);
		if ( is_wp_error( $title ) ) {
			wp_send_json_error( array( 'message' => $title->get_error_message() ) );
		}
		$title = $this->sanitize_output( 'title', $title );

		$description = $this->generate_one(
			'description',
			array(
				'title'             => $title,
				'short_description' => $hint,
				'description'       => '',
				'categories'        => array(),
				'previous'          => '',
			)
		);
		if ( is_wp_error( $description ) ) {
			wp_send_json_error( array( 'message' => $description->get_error_message() ) );
		}
		$description = $this->sanitize_output( 'description', $description );

		$short_description = $this->generate_one(
			'short_description',
			array(
				'title'             => $title,
				'short_description' => '',
				'description'       => wp_strip_all_tags( $description ),
				'categories'        => array(),
				'previous'          => '',
			)
		);
		if ( is_wp_error( $short_description ) ) {
			wp_send_json_error( array( 'message' => $short_description->get_error_message() ) );
		}
		$short_description = $this->sanitize_output( 'short_description', $short_description );

		wp_send_json_success(
			array(
				'title'             => $title,
				'description'       => $description,
				'short_description' => $short_description,
			)
		);
	}

	/**
	 * Run a single field generation and return the raw model output.
	 *
	 * Note: temperature is intentionally not set. Some newer models (e.g. OpenAI
	 * reasoning models) reject a custom `temperature` with a 400 error, so we
	 * rely on the model default for broad compatibility.
	 *
	 * @param string $field   Field key.
	 * @param array  $context Sanitized prompt context.
	 * @return string|\WP_Error
	 */
	private function generate_one( $field, array $context ) {
		// The core AI Client ships with WordPress 7.0; callers already gate on
		// is_text_supported(), but keep the guard inline so the call is never
		// reached on the 6.9 minimum this plugin still supports.
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return new \WP_Error(
				'storesuite_ai_unavailable',
				__( 'AI text generation requires WordPress 7.0 or newer.', 'storesuite' )
			);
		}

		return wp_ai_client_prompt( $this->build_prompt( $field, $context ) )
			->using_system_instruction( $this->get_system_instruction( $field ) )
			->generate_text();
	}

	/**
	 * Render the global "Generate with AI" launcher and bundle modal.
	 *
	 * Hooked on `storesuite_dashboard_title_after`; only renders on the Add New
	 * Product and Edit Product pages when text generation is available.
	 */
	public function render_bundle_launcher() {
		if ( ! self::is_text_supported() || ! self::is_bundle_enabled() ) {
			return;
		}

		$query = pluginizelab_storesuite()->get_storesuite_query();
		if ( ! $query ) {
			return;
		}

		$endpoint = $query->get_current_endpoint();
		if ( 'add-new-product' !== $endpoint && 'edit-product' !== $endpoint ) {
			return;
		}

		storesuite_get_template_part( 'products/ai-bundle-modal' );
	}

	/**
	 * Collect and sanitize the form context posted by the browser.
	 *
	 * The nonce is verified by the caller ( handle_generate() ) via
	 * check_ajax_referer() before this runs.
	 *
	 * @param array $post Raw request data (typically $_POST).
	 * @return array{title:string, short_description:string, description:string, categories:string[]}
	 */
	private function get_context_from_request( $post ) {
		$categories = array();
		if ( isset( $post['categories'] ) && is_array( $post['categories'] ) ) {
			$categories = array_filter( array_map( 'sanitize_text_field', wp_unslash( $post['categories'] ) ) );
		}

		return array(
			'title'             => isset( $post['product_title'] ) ? sanitize_text_field( wp_unslash( $post['product_title'] ) ) : '',
			'short_description' => isset( $post['product_short_description'] ) ? sanitize_textarea_field( wp_unslash( $post['product_short_description'] ) ) : '',
			// Existing long description is used only as prompt context, so strip markup.
			'description'       => isset( $post['product_description'] ) ? wp_strip_all_tags( wp_unslash( $post['product_description'] ) ) : '',
			'categories'        => array_values( $categories ),
			// The current suggestion the user is regenerating away from, if any.
			'previous'          => isset( $post['previous'] ) ? wp_strip_all_tags( wp_unslash( $post['previous'] ) ) : '',
		);
	}

	/**
	 * Default per-field system instructions.
	 *
	 * The single source of truth for the built-in prompts. These are used as the
	 * fallback whenever a merchant has not saved a custom instruction on the AI
	 * settings page, and are also exposed there to prefill the textareas.
	 *
	 * @return array<string, string> Map of field key to default instruction.
	 */
	public static function default_system_instructions() {
		return array(
			'title'             => __( 'You are an expert e-commerce copywriter. Write ONE concise, compelling product title of at most 70 characters. Return only the title text with no quotation marks, labels, or extra commentary.', 'storesuite' ),
			'description'       => __( 'You are an expert e-commerce copywriter. Write an engaging product description as 4 to 5 short paragraphs using only <p> HTML tags. Do not include headings, lists, or a title. Focus on benefits and key features. Return only the HTML.', 'storesuite' ),
			'short_description' => __( 'You are an expert e-commerce copywriter. Write a punchy product summary of 4 to 5 sentences (at most 160 characters) as plain text. Return only the summary with no labels or quotation marks.', 'storesuite' ),
		);
	}

	/**
	 * Option key holding the custom system instruction for a field.
	 *
	 * @param string $field Field key.
	 * @return string
	 */
	private static function instruction_option_key( $field ) {
		return 'storesuite_ai_instruction_' . $field;
	}

	/**
	 * Per-field system instruction.
	 *
	 * Returns the merchant's custom instruction when one has been saved on the AI
	 * settings page, otherwise falls back to the built-in default.
	 *
	 * @param string $field Field key.
	 * @return string
	 */
	private function get_system_instruction( $field ) {
		$defaults = self::default_system_instructions();
		$default  = isset( $defaults[ $field ] ) ? $defaults[ $field ] : $defaults['short_description'];

		$custom = trim( (string) storesuite_get_option_by_key( self::instruction_option_key( $field ) ) );

		return '' !== $custom ? $custom : $default;
	}

	/**
	 * Build the prompt body from whatever context is available.
	 *
	 * @param string $field   Field key.
	 * @param array  $context Sanitized form context.
	 * @return string
	 */
	private function build_prompt( $field, array $context ) {
		$parts = array();

		if ( '' !== $context['title'] ) {
			$parts[] = 'Product name / keywords: ' . $context['title'];
		}
		if ( ! empty( $context['categories'] ) ) {
			$parts[] = 'Categories: ' . implode( ', ', $context['categories'] );
		}
		// Give the description generators any existing short summary for tone.
		if ( 'short_description' !== $field && '' !== $context['short_description'] ) {
			$parts[] = 'Existing summary: ' . $context['short_description'];
		}
		// Let the short-description generator lean on the long description if present.
		if ( 'short_description' === $field && '' !== $context['description'] ) {
			$parts[] = 'Full description: ' . $context['description'];
		}

		$intro = array(
			'title'             => 'Generate a product title for the following item.',
			'description'       => 'Write a product description for the following item.',
			'short_description' => 'Write a short product summary for the following item.',
		);

		$prompt = $intro[ $field ] . "\n\n<context>\n" . implode( "\n", $parts ) . "\n</context>";

		// Encourage variety between requests. Without this, deterministic models
		// return the same text for an identical prompt every time, which makes
		// "Regenerate" appear broken. A random seed varies the input, and when
		// regenerating we explicitly ask for something different from the
		// previous suggestion.
		if ( '' !== $context['previous'] ) {
			$prompt .= "\n\n<avoid>\nDo not repeat or lightly reword this previous attempt. Produce a clearly different alternative with a fresh angle:\n" . $context['previous'] . "\n</avoid>";
		}
		$prompt .= "\n\nWrite a fresh, original variation. (seed: " . wp_rand( 100000, 999999 ) . ')';

		return $prompt;
	}

	/**
	 * Sanitize generated output for its target field.
	 *
	 * @param string $field  Field key.
	 * @param string $result Raw model output.
	 * @return string
	 */
	private function sanitize_output( $field, $result ) {
		$result = trim( $result );

		switch ( self::FIELDS[ $field ] ) {
			case 'html':
				return wp_kses_post( $result );

			case 'textarea':
				return sanitize_textarea_field( $result );

			case 'text':
			default:
				return sanitize_text_field( trim( $result, " \t\n\r\0\x0B\"'" ) );
		}
	}
}
