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
	 * Definitions of the three built-in fields; see get_fields() for the shape.
	 *
	 * @return array<string, array>
	 */
	private static function builtin_fields() {
		$instructions = self::default_system_instructions();

		return array(
			'title'             => array(
				'label'               => __( 'Title suggestion', 'storesuite' ),
				'output'              => 'text',
				'instruction'         => $instructions['title'],
				'instruction_setting' => 'storesuite_ai_instruction_title',
				'enabled_setting'     => 'storesuite_ai_field_title',
				'intro'               => 'Generate a product title for the following item.',
				'needs_context'       => false,
				'target'              => '#product_title',
				'rows'                => 3,
			),
			'description'       => array(
				'label'               => __( 'Description suggestion', 'storesuite' ),
				'output'              => 'html',
				'instruction'         => $instructions['description'],
				'instruction_setting' => 'storesuite_ai_instruction_description',
				'enabled_setting'     => 'storesuite_ai_field_description',
				'intro'               => 'Write a product description for the following item.',
				'needs_context'       => true,
				'target'              => '#product_description',
				'rows'                => 8,
			),
			'short_description' => array(
				'label'               => __( 'Short description suggestion', 'storesuite' ),
				'output'              => 'textarea',
				'instruction'         => $instructions['short_description'],
				'instruction_setting' => 'storesuite_ai_instruction_short_description',
				'enabled_setting'     => 'storesuite_ai_field_short_description',
				'intro'               => 'Write a short product summary for the following item.',
				'needs_context'       => true,
				'target'              => '#product_short_description',
				'rows'                => 3,
			),
		);
	}

	/**
	 * Every field AI can generate text for, keyed by field key.
	 *
	 * Each definition holds what the generic machinery needs:
	 *
	 * - `label`               Modal title shown for a suggestion.
	 * - `output`              How the result is sanitized: `text`, `textarea` or `html`.
	 * - `instruction`         Default system instruction.
	 * - `instruction_setting` Settings key holding a merchant's custom instruction (optional).
	 * - `enabled_setting`     Settings key switching the field off when set to "no" (optional).
	 * - `intro`               First line of the prompt.
	 * - `needs_context`       Whether a product title or short description must exist first.
	 * - `target`              CSS selector of the form field the result is inserted into.
	 * - `rows`                Height of the suggestion textarea (optional, default 3).
	 * - `length`              Target length in characters, shown as a counter (optional).
	 * - `prompt_lines`        Callable ( array $context, array $request ): string[] adding
	 *                         prompt lines from the request (optional).
	 *
	 * Integrations add fields with the `storesuite_ai_text_fields` filter. The
	 * built-in fields cannot be replaced, and definitions without a `target`
	 * and `label` are ignored.
	 *
	 * @return array<string, array>
	 */
	public static function get_fields() {
		$builtin = self::builtin_fields();

		/**
		 * Filters the fields AI can generate text for on the product form.
		 *
		 * @param array<string, array> $fields Field definitions keyed by field key; see ProductAI::get_fields().
		 */
		$fields = apply_filters( 'storesuite_ai_text_fields', $builtin );

		$result = $builtin;

		foreach ( (array) $fields as $key => $definition ) {
			if ( isset( $builtin[ $key ] ) || ! is_array( $definition ) || empty( $definition['target'] ) || empty( $definition['label'] ) ) {
				continue;
			}

			$result[ sanitize_key( $key ) ] = array_merge(
				array(
					'output'              => 'text',
					'instruction'         => '',
					'instruction_setting' => '',
					'enabled_setting'     => '',
					'intro'               => 'Write the following item.',
					'needs_context'       => true,
					'rows'                => 3,
				),
				$definition
			);
		}

		return $result;
	}

	/**
	 * AI settings groups contributed by registered (non built-in) fields.
	 *
	 * Fields that share a `group` key are presented on the AI settings page as
	 * one block: a single on/off toggle (their shared `enabled_setting`) and one
	 * textarea per distinct `instruction_setting`, prefilled with the default
	 * instruction. The block's label comes from the
	 * `storesuite_ai_settings_group_labels` filter, falling back to the group key.
	 *
	 * @return array<int, array{key: string, label: string, enabled_setting: string, instructions: array<int, array{setting: string, label: string, default: string}>}>
	 */
	public static function get_settings_groups() {
		$builtin = self::builtin_fields();
		$groups  = array();

		foreach ( self::get_fields() as $key => $definition ) {
			if ( isset( $builtin[ $key ] ) || empty( $definition['group'] ) ) {
				continue;
			}

			$group = sanitize_key( $definition['group'] );

			if ( ! isset( $groups[ $group ] ) ) {
				$groups[ $group ] = array(
					'key'             => $group,
					'label'           => $group,
					'enabled_setting' => '',
					'instructions'    => array(),
				);
			}

			if ( ! empty( $definition['enabled_setting'] ) && '' === $groups[ $group ]['enabled_setting'] ) {
				$groups[ $group ]['enabled_setting'] = $definition['enabled_setting'];
			}

			$setting = isset( $definition['instruction_setting'] ) ? $definition['instruction_setting'] : '';
			if ( '' !== $setting && ! isset( $groups[ $group ]['instructions'][ $setting ] ) ) {
				$groups[ $group ]['instructions'][ $setting ] = array(
					'setting' => $setting,
					'label'   => isset( $definition['instruction_label'] ) ? $definition['instruction_label'] : $definition['label'],
					'default' => (string) $definition['instruction'],
				);
			}
		}

		/**
		 * Filters the labels of AI settings groups, keyed by group key.
		 *
		 * @param array<string, string> $labels Group labels.
		 */
		$labels = apply_filters( 'storesuite_ai_settings_group_labels', array() );

		foreach ( $groups as $group => &$data ) {
			if ( isset( $labels[ $group ] ) ) {
				$data['label'] = $labels[ $group ];
			}
			$data['instructions'] = array_values( $data['instructions'] );
		}
		unset( $data );

		return array_values( $groups );
	}

	/**
	 * Definition of one field, or null when unknown.
	 *
	 * @param string $field Field key.
	 * @return array|null
	 */
	private static function get_field( $field ) {
		$fields = self::get_fields();

		return isset( $fields[ $field ] ) ? $fields[ $field ] : null;
	}

	/**
	 * Whether a field's toggle on the AI settings page is on.
	 *
	 * Built-in fields keep the existing storesuite_is_ai_field_enabled() logic;
	 * registered fields use their own `enabled_setting`, and are on when they
	 * have none.
	 *
	 * @param string $field      Field key.
	 * @param array  $definition Field definition.
	 * @return bool
	 */
	private static function is_field_enabled( $field, array $definition ) {
		if ( isset( self::builtin_fields()[ $field ] ) ) {
			return storesuite_is_ai_field_enabled( $field );
		}

		if ( empty( $definition['enabled_setting'] ) ) {
			return true;
		}

		return 'no' !== storesuite_get_option_by_key( $definition['enabled_setting'] );
	}

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
		$definition = self::get_field( $field );

		if ( ! $definition || ! self::is_text_supported() || ! self::is_field_enabled( $field, $definition ) ) {
			return;
		}
		?>
		<button type="button" class="storesuite-ai-generate" data-field="<?php echo esc_attr( $field ); ?>" data-target="<?php echo esc_attr( $definition['target'] ); ?>"><svg class="storesuite-ai-icon" aria-hidden="true" focusable="false"><use href="#storesuite-icon-ai-magic"></use></svg> <span class="storesuite-ai-bundle-prefix"><?php esc_html_e( 'Generate with', 'storesuite' ); ?> </span><?php esc_html_e( 'AI', 'storesuite' ); ?></button>
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

		$field      = isset( $_POST['field'] ) ? sanitize_key( wp_unslash( $_POST['field'] ) ) : '';
		$definition = self::get_field( $field );
		if ( ! $definition ) {
			wp_send_json_error( array( 'message' => __( 'Invalid field.', 'storesuite' ) ) );
		}

		if ( ! self::is_field_enabled( $field, $definition ) ) {
			wp_send_json_error( array( 'message' => __( 'AI generation is disabled for this field.', 'storesuite' ) ) );
		}

		$context = $this->get_context_from_request( $_POST );

		// Most fields need at least a title or some keywords to work from.
		if ( $definition['needs_context'] && '' === $context['title'] && '' === $context['short_description'] ) {
			wp_send_json_error(
				array(
					'reason'  => 'no_context',
					'message' => __( 'Add a product title or a few keywords first.', 'storesuite' ),
				)
			);
		}

		$result = $this->generate_one( $field, $context, $_POST );

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
	 * @param array  $request Raw request data, for fields that add their own prompt lines.
	 * @return string|\WP_Error
	 */
	private function generate_one( $field, array $context, array $request = array() ) {
		$prompt      = $this->build_prompt( $field, $context, $request );
		$instruction = $this->get_system_instruction( $field );
		$generator   = self::get_text_generator();

		if ( $generator ) {
			return call_user_func( $generator, $prompt, $instruction, $field );
		}

		return wp_ai_client_prompt( $prompt )
			->using_system_instruction( $instruction )
			->generate_text();
	}

	/**
	 * A custom text generator, if one has been supplied.
	 *
	 * When present it replaces the WordPress core AI Client for every text
	 * generation and makes text AI count as available. It is the seam the test
	 * suites use (no provider on CI), and lets a store plug in its own model.
	 *
	 * @return callable|null Callable taking ( string $prompt, string $system_instruction, string $field )
	 *                       and returning the generated text or a WP_Error.
	 */
	public static function get_text_generator() {
		/**
		 * Filters the text generator used for AI product copy.
		 *
		 * @param callable|null $generator Callable ( $prompt, $system_instruction, $field ): string|WP_Error, or null
		 *                                 to use the WordPress core AI Client.
		 */
		$generator = apply_filters( 'storesuite_ai_text_generator', null );

		return is_callable( $generator ) ? $generator : null;
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
	 * Per-field system instruction.
	 *
	 * Returns the merchant's custom instruction when one has been saved on the AI
	 * settings page, otherwise falls back to the built-in default.
	 *
	 * @param string $field Field key.
	 * @return string
	 */
	private function get_system_instruction( $field ) {
		$definition = self::get_field( $field );
		$default    = $definition ? (string) $definition['instruction'] : self::default_system_instructions()['short_description'];
		$custom     = '';

		if ( $definition && ! empty( $definition['instruction_setting'] ) ) {
			$custom = trim( (string) storesuite_get_option_by_key( $definition['instruction_setting'] ) );
		}

		return '' !== $custom ? $custom : $default;
	}

	/**
	 * Build the prompt body from whatever context is available.
	 *
	 * @param string $field   Field key.
	 * @param array  $context Sanitized form context.
	 * @return string
	 */
	private function build_prompt( $field, array $context, array $request = array() ) {
		$definition = self::get_field( $field );
		$parts      = array();

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

		// Fields may contribute their own lines from the request (e.g. an SEO keyphrase).
		if ( $definition && ! empty( $definition['prompt_lines'] ) && is_callable( $definition['prompt_lines'] ) ) {
			$extra = call_user_func( $definition['prompt_lines'], $context, $request );
			foreach ( (array) $extra as $line ) {
				if ( is_string( $line ) && '' !== trim( $line ) ) {
					$parts[] = $line;
				}
			}
		}

		$intro  = $definition ? $definition['intro'] : 'Write the following item.';
		$prompt = $intro . "\n\n<context>\n" . implode( "\n", $parts ) . "\n</context>";

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

		$definition = self::get_field( $field );

		switch ( $definition ? $definition['output'] : 'text' ) {
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
