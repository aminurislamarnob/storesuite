<?php

namespace PluginizeLab\StoreSuite\Integration\Acf;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders ACF field groups and fields with StoreSuite's theme-overridable templates.
 *
 * Templates live under `templates/products/acf/`: one for the group card
 * (`group.php`), one base wrapper shared by every type
 * (`field-wrapper.php`) and one per field type (`field-<type>.php`), with
 * `field-unsupported.php` standing in for types StoreSuite cannot edit.
 */
class FieldRenderer {

	/**
	 * Owning integration (field type support lookup).
	 *
	 * @var AcfIntegration
	 */
	protected $integration;

	/**
	 * Constructor.
	 *
	 * @param AcfIntegration $integration Owning integration.
	 */
	public function __construct( AcfIntegration $integration ) {
		$this->integration = $integration;
	}

	/**
	 * Render a field group card.
	 *
	 * @param array $group        ACF field group array.
	 * @param array $fields       ACF field arrays belonging to the group.
	 * @param int   $product_id   Product ID, 0 on the add form.
	 * @param bool  $is_edit_mode Whether the edit form is rendered.
	 */
	public function render_group( array $group, array $fields, int $product_id, bool $is_edit_mode ) {
		if ( empty( $fields ) ) {
			return;
		}

		storesuite_get_template_part(
			'products/acf/group',
			'',
			array(
				'group'        => $group,
				'fields'       => $fields,
				'product_id'   => $product_id,
				'is_edit_mode' => $is_edit_mode,
				'renderer'     => $this,
			)
		);
	}

	/**
	 * Render a single field: the shared wrapper around the type template.
	 *
	 * @param array $field        ACF field array.
	 * @param int   $product_id   Product ID, 0 on the add form.
	 * @param bool  $is_edit_mode Whether the edit form is rendered.
	 */
	public function render_field( array $field, int $product_id, bool $is_edit_mode ) {
		if ( empty( $field['key'] ) || empty( $field['type'] ) ) {
			return;
		}

		$type      = (string) $field['type'];
		$supported = AcfIntegration::is_supported_type( $type );
		$layout    = AcfIntegration::is_layout_type( $type );

		storesuite_get_template_part(
			'products/acf/field-wrapper',
			'',
			array(
				'field'        => $field,
				'type'         => $type,
				'supported'    => $supported,
				'layout'       => $layout,
				'input_name'   => $this->get_input_name( $field ),
				'input_id'     => $this->get_input_id( $field ),
				'value'        => $supported ? $this->get_value( $field, $product_id, $is_edit_mode ) : null,
				'columns'      => $this->get_columns( $field ),
				'product_id'   => $product_id,
				'is_edit_mode' => $is_edit_mode,
				'renderer'     => $this,
			)
		);
	}

	/**
	 * Render the type-specific input for a field (called from the wrapper template).
	 *
	 * @param array $args Wrapper template args (field, input_name, input_id, value, ...).
	 */
	public function render_input( array $args ) {
		$name = ( ! empty( $args['supported'] ) || ! empty( $args['layout'] ) ) ? (string) $args['type'] : 'unsupported';

		storesuite_get_template_part( 'products/acf/field', $name, $args );
	}

	/**
	 * Open an input group when the field has a prepend / append adornment.
	 *
	 * Type templates call this before and close_input_group() after the input.
	 *
	 * @param array $field ACF field array.
	 */
	public function open_input_group( array $field ) {
		if ( ! $this->has_adornments( $field ) ) {
			return;
		}

		echo '<div class="storesuite-acf-input-group">';

		if ( '' !== (string) $field['prepend'] ) {
			echo '<span class="storesuite-acf-input-addon">' . esc_html( $field['prepend'] ) . '</span>';
		}
	}

	/**
	 * Close the input group opened by open_input_group().
	 *
	 * @param array $field ACF field array.
	 */
	public function close_input_group( array $field ) {
		if ( ! $this->has_adornments( $field ) ) {
			return;
		}

		if ( '' !== (string) $field['append'] ) {
			echo '<span class="storesuite-acf-input-addon">' . esc_html( $field['append'] ) . '</span>';
		}

		echo '</div>';
	}

	/**
	 * Whether a field carries a prepend or append adornment.
	 *
	 * @param array $field ACF field array.
	 *
	 * @return bool
	 */
	protected function has_adornments( array $field ): bool {
		$field = wp_parse_args(
			$field,
			array(
				'prepend' => '',
				'append'  => '',
			)
		);

		return '' !== (string) $field['prepend'] || '' !== (string) $field['append'];
	}

	/**
	 * Name attribute for a field's input.
	 *
	 * @param array $field ACF field array.
	 *
	 * @return string
	 */
	public function get_input_name( array $field ): string {
		return 'storesuite_acf[' . $field['key'] . ']';
	}

	/**
	 * ID attribute for a field's input.
	 *
	 * @param array $field ACF field array.
	 *
	 * @return string
	 */
	public function get_input_id( array $field ): string {
		return 'storesuite_acf_' . $field['key'];
	}

	/**
	 * Value to prefill the input with.
	 *
	 * Add mode: the field's `default_value`. Edit mode: the stored value only —
	 * ACF's default is deliberately not applied to a product that has no value.
	 *
	 * @param array $field        ACF field array.
	 * @param int   $product_id   Product ID, 0 on the add form.
	 * @param bool  $is_edit_mode Whether the edit form is rendered.
	 *
	 * @return mixed Raw (unformatted) value, or null when there is none.
	 */
	public function get_value( array $field, int $product_id, bool $is_edit_mode ) {
		// A stored secret is never sent back to the browser; an empty submit keeps it.
		if ( isset( $field['type'] ) && 'password' === $field['type'] ) {
			return null;
		}

		if ( ! $is_edit_mode || $product_id <= 0 ) {
			return isset( $field['default_value'] ) ? $field['default_value'] : null;
		}

		if ( ! function_exists( 'acf_get_value' ) ) {
			return null;
		}

		// acf_get_value() falls back to default_value when no meta exists; strip
		// it so an unset field renders empty.
		unset( $field['default_value'] );

		return acf_get_value( $product_id, $field );
	}

	/**
	 * The configured choices of a choice field, keyed by choice value (as strings).
	 *
	 * @param array $field ACF field array.
	 *
	 * @return array<string, string> Choice value => label.
	 */
	public function get_choices( array $field ): array {
		$choices = isset( $field['choices'] ) && is_array( $field['choices'] ) ? $field['choices'] : array();
		$result  = array();

		foreach ( $choices as $choice_value => $choice_label ) {
			$result[ (string) $choice_value ] = is_scalar( $choice_label ) ? (string) $choice_label : (string) $choice_value;
		}

		return $result;
	}

	/**
	 * Normalise a stored / default choice value to a list of strings.
	 *
	 * ACF stores single choices as a string and multi choices as an array
	 * (or '' when empty); this lets templates compare uniformly.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return string[]
	 */
	public function get_selected_values( $value ): array {
		if ( null === $value || '' === $value || false === $value ) {
			return array();
		}

		$values = array();

		foreach ( (array) $value as $item ) {
			if ( is_scalar( $item ) ) {
				$values[] = (string) $item;
			}
		}

		return $values;
	}

	/**
	 * Resolve ACF's comma-separated file extensions (`mime_types`) to MIME types
	 * for the media frame's library filter.
	 *
	 * @param string $extensions Comma-separated extensions, e.g. "jpg, png".
	 *
	 * @return string[] MIME types; empty when no restriction applies.
	 */
	public function get_mime_types( string $extensions ): array {
		$mimes = array();
		$known = wp_get_mime_types();

		foreach ( array_filter( array_map( 'trim', explode( ',', strtolower( $extensions ) ) ) ) as $extension ) {
			$extension = ltrim( $extension, '.' );

			foreach ( $known as $pattern => $mime ) {
				if ( in_array( $extension, explode( '|', $pattern ), true ) ) {
					$mimes[] = $mime;
					break;
				}
			}
		}

		return array_values( array_unique( $mimes ) );
	}

	/**
	 * Map ACF's percentage `wrapper.width` onto the 12-column grid.
	 *
	 * @param array $field ACF field array.
	 *
	 * @return int Column span, 1–12.
	 */
	public function get_columns( array $field ): int {
		$width = isset( $field['wrapper']['width'] ) ? (int) $field['wrapper']['width'] : 0;

		if ( $width <= 0 ) {
			return 12;
		}
		if ( $width <= 25 ) {
			return 3;
		}
		if ( $width <= 33 ) {
			return 4;
		}
		if ( $width <= 50 ) {
			return 6;
		}
		if ( $width <= 66 ) {
			return 8;
		}
		if ( $width <= 75 ) {
			return 9;
		}

		return 12;
	}
}
