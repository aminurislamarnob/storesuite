<?php

namespace PluginizeLab\StoreSuite\Integration\Acf;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Surfaces Advanced Custom Fields field groups on the frontend product add/edit form.
 *
 * StoreSuite renders the fields in its own design system; ACF is only the data
 * layer (field group / location resolution, stored values, `update_field()`).
 *
 * ACF boots on `init` priority 5, after StoreSuite has created its services on
 * priority 4, so the constructor must not call any ACF API. Everything that
 * touches ACF runs at render or save time.
 */
class AcfIntegration {

	/**
	 * Field types StoreSuite can render and save. Every other type gets a
	 * read-only placeholder and is never written back.
	 *
	 * @var string[]
	 */
	const SUPPORTED_TYPES = array(
		'text',
		'textarea',
		'number',
		'range',
		'email',
		'url',
		'password',
		'color_picker',
		'select',
		'checkbox',
		'radio',
		'button_group',
		'true_false',
		'date_picker',
		'date_time_picker',
		'time_picker',
		'wysiwyg',
		'image',
	);

	/**
	 * Layout-only field types: rendered in place but they carry no value, so
	 * they have no input and are never saved.
	 *
	 * @var string[]
	 */
	const LAYOUT_TYPES = array( 'message', 'separator' );

	/**
	 * Post type whose field groups are surfaced.
	 *
	 * @var string
	 */
	const POST_TYPE = 'product';

	/**
	 * Script handle for the frontend ACF behaviour.
	 *
	 * @var string
	 */
	const SCRIPT_HANDLE = 'storesuite_product_acf_script';

	/**
	 * Renderer.
	 *
	 * @var FieldRenderer
	 */
	protected $renderer;

	/**
	 * Sanitiser.
	 *
	 * @var FieldSanitizer
	 */
	protected $sanitizer;

	/**
	 * Posted values the sanitiser refused during this request, keyed by field
	 * key. They are validated so ACF's own message (e.g. "Value must be a
	 * number") reaches the user instead of the field silently not saving.
	 *
	 * @var array<string, mixed>
	 */
	protected $rejected = array();

	/**
	 * Constructor. Bails unless ACF is active.
	 */
	public function __construct() {
		if ( ! self::is_acf_active() ) {
			return;
		}

		$this->renderer  = new FieldRenderer( $this );
		$this->sanitizer = new FieldSanitizer();

		add_action( 'storesuite_product_form_after_others', array( $this, 'render_field_groups' ), 10, 2 );
		add_filter( 'storesuite_sanitize_acf_fields', array( $this, 'sanitize_fields' ), 10, 3 );
		add_filter( 'storesuite_product_pre_save_validation', array( $this, 'validate_fields' ), 10, 3 );
		add_action( 'storesuite_new_product_added', array( $this, 'save_fields' ), 10, 2 );
		add_action( 'storesuite_product_updated', array( $this, 'save_fields' ), 10, 2 );

		// Assets registers its scripts on init 10; register after so the product script handle exists.
		add_action( 'init', array( $this, 'register_script' ), 11 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_script' ), 20 );
	}

	/**
	 * Whether Advanced Custom Fields (free or Pro) is active.
	 *
	 * Only checks that the plugin's main class has been defined; ACF has not
	 * necessarily booted yet when this runs.
	 *
	 * @return bool
	 */
	public static function is_acf_active(): bool {
		return class_exists( 'ACF' );
	}

	/**
	 * Whether a field type can be rendered and saved by StoreSuite.
	 *
	 * @param string $type ACF field type.
	 *
	 * @return bool
	 */
	public static function is_supported_type( string $type ): bool {
		return in_array( $type, self::SUPPORTED_TYPES, true );
	}

	/**
	 * Whether a field type is layout-only (rendered, never saved).
	 *
	 * @param string $type ACF field type.
	 *
	 * @return bool
	 */
	public static function is_layout_type( string $type ): bool {
		return in_array( $type, self::LAYOUT_TYPES, true );
	}

	/**
	 * Resolve the field groups shown for a product using ACF's full location rules.
	 *
	 * In edit mode the product ID is part of the screen so taxonomy / status
	 * rules are evaluated; in add mode only the post type is known.
	 *
	 * @param int $product_id Product ID, 0 on the add form.
	 *
	 * @return array<int, array> ACF field group arrays.
	 */
	public function get_field_groups( int $product_id = 0 ): array {
		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			return array();
		}

		$screen = array( 'post_type' => self::POST_TYPE );

		if ( $product_id > 0 ) {
			$screen['post_id'] = $product_id;
		}

		$groups = acf_get_field_groups( $screen );

		return is_array( $groups ) ? $groups : array();
	}

	/**
	 * Get the fields of a field group.
	 *
	 * @param array $group ACF field group array.
	 *
	 * @return array<int, array> ACF field arrays.
	 */
	public function get_fields( array $group ): array {
		if ( ! function_exists( 'acf_get_fields' ) ) {
			return array();
		}

		$fields = acf_get_fields( $group );

		return is_array( $fields ) ? $fields : array();
	}

	/**
	 * Every supported field that is rendered for a product, keyed by field key.
	 *
	 * This is the whitelist for saving: a key that was not rendered is never
	 * written, whatever the request contains.
	 *
	 * @param int $product_id Product ID, 0 on the add form.
	 *
	 * @return array<string, array> ACF field arrays keyed by field key.
	 */
	public function get_writable_fields( int $product_id = 0 ): array {
		$writable = array();

		foreach ( $this->get_field_groups( $product_id ) as $group ) {
			foreach ( $this->get_fields( $group ) as $field ) {
				if ( empty( $field['key'] ) || empty( $field['type'] ) || ! self::is_supported_type( $field['type'] ) ) {
					continue;
				}

				$writable[ $field['key'] ] = $field;
			}
		}

		return $writable;
	}

	/**
	 * Render one card per field group after the "Others" card.
	 *
	 * @param int  $product_id   Product ID, 0 on the add form.
	 * @param bool $is_edit_mode Whether the edit form is rendered.
	 */
	public function render_field_groups( $product_id, $is_edit_mode ) {
		$product_id = absint( $product_id );

		foreach ( $this->get_field_groups( $product_id ) as $group ) {
			$this->renderer->render_group( $group, $this->get_fields( $group ), $product_id, (bool) $is_edit_mode );
		}
	}

	/**
	 * Sanitise the posted ACF values, keeping only fields that were rendered.
	 *
	 * @param array $sanitized  Values sanitised so far.
	 * @param array $raw        Raw posted values keyed by field key.
	 * @param int   $product_id Product being edited, 0 when adding.
	 *
	 * @return array<string, mixed> Sanitised values keyed by field key.
	 */
	public function sanitize_fields( $sanitized, $raw, $product_id ) {
		$sanitized = is_array( $sanitized ) ? $sanitized : array();

		if ( ! is_array( $raw ) ) {
			return $sanitized;
		}

		$this->rejected = array();

		foreach ( $this->get_writable_fields( absint( $product_id ) ) as $key => $field ) {
			if ( ! array_key_exists( $key, $raw ) ) {
				continue;
			}

			$value = $this->sanitizer->sanitize( $field, $raw[ $key ] );

			if ( null === $value ) {
				// A blank password means "keep the stored value", not a rejection.
				if ( 'password' !== $field['type'] || '' !== $raw[ $key ] ) {
					$this->rejected[ $key ] = $raw[ $key ];
				}
				continue;
			}

			$sanitized[ $key ] = $value;
		}

		return $sanitized;
	}

	/**
	 * Run ACF's validator over every rendered field before the product is saved.
	 *
	 * Uses the sanitised values (or the refused raw value, so ACF can explain
	 * why). Fields that were not rendered are never validated, so a required
	 * unsupported field cannot block a frontend save.
	 *
	 * @param \WP_Error|null $error      Error collected so far.
	 * @param array          $data       Sanitised product data.
	 * @param string         $context    'add' or 'edit'.
	 *
	 * @return \WP_Error|null
	 */
	public function validate_fields( $error, $data, $context ) {
		if ( ! function_exists( 'acf_validate_value' ) || ! function_exists( 'acf_get_validation_errors' ) ) {
			return $error;
		}

		$values     = isset( $data['storesuite_acf'] ) && is_array( $data['storesuite_acf'] ) ? $data['storesuite_acf'] : array();
		$product_id = 'edit' === $context && ! empty( $data['product_id'] ) ? absint( $data['product_id'] ) : 0;
		$messages   = array();

		foreach ( $this->get_writable_fields( $product_id ) as $key => $field ) {
			if ( array_key_exists( $key, $values ) ) {
				$value = $values[ $key ];
			} elseif ( array_key_exists( $key, $this->rejected ) ) {
				$value = $this->rejected[ $key ];
			} else {
				// Not in the request (e.g. blank password): nothing is written, nothing to validate.
				continue;
			}

			acf_reset_validation_errors();
			acf_validate_value( $value, $field, 'storesuite_acf[' . $key . ']' );

			foreach ( (array) acf_get_validation_errors() as $acf_error ) {
				$message = isset( $acf_error['message'] ) ? wp_strip_all_tags( (string) $acf_error['message'] ) : '';

				if ( '' === $message ) {
					continue;
				}

				// ACF's required message already names the field; prefix everything else.
				/* translators: %s: field label (ACF's own string, reproduced to detect it). */
				$required_message = sprintf( __( '%s value is required', 'acf' ), $field['label'] ); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Must match ACF's string.

				$messages[] = $message === $required_message
					? $message
					/* translators: 1: field label, 2: validation message */
					: sprintf( __( '%1$s: %2$s', 'storesuite' ), $field['label'], $message );
			}
		}

		acf_reset_validation_errors();

		if ( empty( $messages ) ) {
			return $error;
		}

		if ( ! is_wp_error( $error ) ) {
			$error = new \WP_Error();
		}

		foreach ( $messages as $message ) {
			$error->add( 'storesuite_acf_validation', $message );
		}

		return $error;
	}

	/**
	 * Persist the sanitised values through ACF once the product exists.
	 *
	 * Writing by field key makes ACF store its `_<name>` reference meta, so
	 * wp-admin shows the value straight away.
	 *
	 * @param int   $product_id Product ID.
	 * @param array $data       Sanitised product data from the controller.
	 */
	public function save_fields( $product_id, $data ) {
		if ( ! function_exists( 'update_field' ) || empty( $data['storesuite_acf'] ) || ! is_array( $data['storesuite_acf'] ) ) {
			return;
		}

		foreach ( $data['storesuite_acf'] as $key => $value ) {
			update_field( $key, $value, absint( $product_id ) );
		}
	}

	/**
	 * Register the frontend script.
	 */
	public function register_script() {
		wp_register_script(
			self::SCRIPT_HANDLE,
			STORESUITE_PLUGIN_ASSET . '/frontend/product-acf.js',
			array( 'jquery', 'storesuite_product_script' ),
			STORESUITE_PLUGIN_VERSION,
			true
		);
	}

	/**
	 * Enqueue the frontend script on the product add / edit pages only.
	 */
	public function enqueue_script() {
		if ( ! storesuite_is_dashboard_page() ) {
			return;
		}

		if ( ! storesuite_is_endpoint_url( 'add-new-product' ) && ! storesuite_is_endpoint_url( 'edit-product' ) ) {
			return;
		}

		wp_enqueue_script( self::SCRIPT_HANDLE );
	}
}
