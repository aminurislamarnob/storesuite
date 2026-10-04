<?php
/**
 * Helpers for tests that exercise the Advanced Custom Fields integration.
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test\Integration;

/**
 * Skips tests when ACF is not loaded and registers / removes local field
 * groups around each test.
 *
 * ACF is loaded by tests/php/bootstrap.php when ACF_DIR points at a checkout.
 */
trait AcfTestHelpers {

	/**
	 * Keys of the local field groups registered by the current test.
	 *
	 * @var string[]
	 */
	protected $acf_group_keys = array();

	/**
	 * Keys of the local fields registered by the current test.
	 *
	 * @var string[]
	 */
	protected $acf_field_keys = array();

	/**
	 * Skip the test unless ACF is loaded.
	 *
	 * @return void
	 */
	protected function require_acf() {
		if ( ! class_exists( 'ACF' ) || ! function_exists( 'acf_add_local_field_group' ) ) {
			$this->markTestSkipped( 'Advanced Custom Fields is not installed (set ACF_DIR).' );
		}
	}

	/**
	 * Register a local ACF field group for the duration of the test.
	 *
	 * @param array $group Field group definition as accepted by acf_add_local_field_group().
	 * @return array The registered group.
	 */
	protected function register_acf_field_group( array $group ) {
		$group = wp_parse_args(
			$group,
			array(
				'active' => true,
				'fields' => array(),
			)
		);

		acf_add_local_field_group( $group );

		$this->acf_group_keys[] = $group['key'];
		foreach ( $group['fields'] as $field ) {
			$this->acf_field_keys[] = $field['key'];
		}

		return $group;
	}

	/**
	 * Remove every local field group registered by the test.
	 *
	 * @return void
	 */
	protected function remove_acf_field_groups() {
		if ( ! function_exists( 'acf_remove_local_field_group' ) ) {
			return;
		}

		foreach ( $this->acf_field_keys as $key ) {
			acf_remove_local_field( $key );
		}
		foreach ( $this->acf_group_keys as $key ) {
			acf_remove_local_field_group( $key );
		}

		$this->acf_field_keys = array();
		$this->acf_group_keys = array();

		// Field / value lookups are memoised per request; forget them between tests.
		foreach ( array( 'fields', 'values', 'field-groups' ) as $store ) {
			if ( acf_get_store( $store ) ) {
				acf_get_store( $store )->reset();
			}
		}
	}

	/**
	 * Render the product form's after-Others slot and return the markup.
	 *
	 * @param int $product_id Product ID, 0 for the add form.
	 * @return string
	 */
	protected function render_acf_cards( $product_id = 0 ) {
		ob_start();
		do_action( 'storesuite_product_form_after_others', $product_id, $product_id > 0 );
		return (string) ob_get_clean();
	}

	/**
	 * A location rule set restricting a group to the product post type.
	 *
	 * @return array
	 */
	protected function acf_product_location() {
		return array(
			array(
				array(
					'param'    => 'post_type',
					'operator' => '==',
					'value'    => 'product',
				),
			),
		);
	}
}
