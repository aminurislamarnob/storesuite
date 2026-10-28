<?php

namespace PluginizeLab\StoreSuite\ProductTag;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin product tag controller class
 */
class TagController {
	/**
	 * The constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_storesuite_add_product_tag', array( $this, 'handle_add_tag' ) );
		add_action( 'wp_ajax_storesuite_edit_product_tag', array( $this, 'handle_edit_tag' ) );
		add_action( 'wp_ajax_storesuite_delete_product_tag', array( $this, 'handle_delete_tag' ) );
		add_action( 'storesuite_tags_toolbar_add_button', array( $this, 'render_toolbar_add_button' ) );
		add_action( 'storesuite_dashboard_title_after', array( $this, 'render_title_add_button' ) );
	}

	public function render_toolbar_add_button() {
		$this->render_add_button(
			storesuite_get_navigation_url( 'add-new-tag' ),
			__( 'Add Tag', 'storesuite' )
		);
	}

	public function render_title_add_button() {
		$query = pluginizelab_storesuite()->get_storesuite_query();
		if ( ! $query || 'tags' !== $query->get_current_endpoint() ) {
			return;
		}
		$this->render_add_button(
			storesuite_get_navigation_url( 'add-new-tag' ),
			__( 'Add Tag', 'storesuite' ),
			'storesuite-title-action'
		);
	}

	private function render_add_button( $url, $label, $extra_class = '' ) {
		$class = trim( 'my-storesuite-button ' . $extra_class );
		?>
		<a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $class ); ?>">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">
				<path d="M23,11H13V1a1,1,0,0,0-1-1h0a1,1,0,0,0-1,1V11H1a1,1,0,0,0-1,1H0a1,1,0,0,0,1,1H11V23a1,1,0,0,0,1,1h0a1,1,0,0,0,1-1V13H23a1,1,0,0,0,1-1h0A1,1,0,0,0,23,11Z"/>
			</svg>
			<?php echo esc_html( $label ); ?>
		</a>
		<?php
	}

	/**
	 * Handle the AJAX request for adding a new product tag.
	 */
	public function handle_add_tag() {

		// Verify the nonce.
		if ( ! isset( $_POST['storesuite_add_product_tag_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['storesuite_add_product_tag_nonce'] ) ), '_storesuite_add_product_tag_' ) ) {
			wp_send_json_error( array( 'error' => __( 'Nonce verification failed', 'storesuite' ) ) );
		}

		// Check user permissions.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'error' => __( 'You do not have permission to perform this action.', 'storesuite' ) ) );
		}

		// Validate inputs.
		$name        = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$slug        = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';

		if ( empty( $name ) ) {
			wp_send_json_error( array( 'error' => __( 'Tag Name is required', 'storesuite' ) ) );
		}

		// Generate slug from name if not provided.
		if ( empty( $slug ) ) {
			$slug = sanitize_title( $name );
		}

		// Check if slug already exists.
		$existing_term = get_term_by( 'slug', $slug, 'product_tag' );
		if ( $existing_term ) {
			wp_send_json_error( array( 'error' => __( 'Tag slug already exists. Please choose a different slug.', 'storesuite' ) ) );
		}

		// Create a new tag.
		$new_category = wp_insert_term(
			$name,
			'product_tag',
			array(
				'slug'        => $slug,
				'description' => $description,
			)
		);

		if ( is_wp_error( $new_category ) ) {
			wp_send_json_error( array( 'error' => $new_category->get_error_message() ) );
		}

		/**
		 * Fires after a product tag is created from the dashboard.
		 *
		 * @param array $new_category The wp_insert_term() result (term_id, term_taxonomy_id).
		 */
		do_action( 'storesuite_product_tag_created', $new_category );

		wp_send_json_success( array( 'message' => __( 'Tag successfully created', 'storesuite' ) ) );
	}

	/**
	 * Handle the AJAX request for editing an existing product category.
	 */
	public function handle_edit_tag() {

		// Verify the nonce.
		if ( ! isset( $_POST['storesuite_edit_product_tag_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['storesuite_edit_product_tag_nonce'] ) ), '_storesuite_edit_product_tag_' ) ) {
			wp_send_json_error( array( 'error' => __( 'Nonce verification failed', 'storesuite' ) ) );
		}

		// Check user permissions.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'error' => __( 'You do not have permission to perform this action.', 'storesuite' ) ) );
		}

		// Validate inputs.
		$tag_id      = isset( $_POST['tag_id'] ) ? absint( $_POST['tag_id'] ) : 0;
		$name        = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$slug        = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';

		if ( empty( $tag_id ) ) {
			wp_send_json_error( array( 'error' => __( 'Tag ID is required', 'storesuite' ) ) );
		}

		if ( empty( $name ) ) {
			wp_send_json_error( array( 'error' => __( 'Tag Name is required', 'storesuite' ) ) );
		}

		// Get current tag.
		$current_tag = get_term( $tag_id, 'product_tag' );
		if ( ! $current_tag || is_wp_error( $current_tag ) ) {
			wp_send_json_error( array( 'error' => __( 'Tag not found', 'storesuite' ) ) );
		}

		// Generate slug from name if not provided.
		if ( empty( $slug ) ) {
			$slug = sanitize_title( $name );
		}

		// Check if slug already exists (but allow it if it's the current tag's slug).
		if ( $slug !== $current_tag->slug ) {
			$existing_term = get_term_by( 'slug', $slug, 'product_tag' );
			if ( $existing_term && $existing_term->term_id !== $tag_id ) {
				wp_send_json_error( array( 'error' => __( 'Tag slug already exists. Please choose a different slug.', 'storesuite' ) ) );
			}
		}

		// Update the tag.
		$updated_tag = wp_update_term(
			$tag_id,
			'product_tag',
			array(
				'name'        => $name,
				'slug'        => $slug,
				'description' => $description,
			)
		);

		if ( is_wp_error( $updated_tag ) ) {
			wp_send_json_error( array( 'error' => $updated_tag->get_error_message() ) );
		}

		/**
		 * Fires after a product tag is updated from the dashboard.
		 *
		 * @param array $updated_tag The wp_update_term() result (term_id, term_taxonomy_id).
		 */
		do_action( 'storesuite_product_tag_updated', $updated_tag );

		wp_send_json_success( array( 'message' => __( 'Tag successfully updated', 'storesuite' ) ) );
	}

	/**
	 * Handle the AJAX request for deleting an existing product category.
	 */
	public function handle_delete_tag() {
		// Verify the nonce.
		if ( ! isset( $_POST['storesuite_delete_product_tag_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['storesuite_delete_product_tag_nonce'] ) ), '_storesuite_delete_nonce_' ) ) {
			wp_send_json_error( array( 'error' => __( 'Nonce verification failed', 'storesuite' ) ) );
		}

		// Check user permissions.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'error' => __( 'You do not have permission to perform this action.', 'storesuite' ) ) );
		}

		// Validate category ID.
		$tag_id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! $tag_id || ! term_exists( $tag_id, 'product_tag' ) ) {
			wp_send_json_error( array( 'error' => __( 'Invalid tag ID', 'storesuite' ) ) );
		}

		// Attempt to delete the category.
		$result = wp_delete_term( $tag_id, 'product_tag' );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'error' => $result->get_error_message() ) );
		} elseif ( $result === false ) {
			wp_send_json_error( array( 'error' => __( 'Failed to delete tag', 'storesuite' ) ) );
		}

		/**
		 * Fires after a product tag is deleted from the dashboard.
		 *
		 * @param int $tag_id The deleted term ID.
		 */
		do_action( 'storesuite_product_tag_deleted', $tag_id );

		wp_send_json_success( array( 'message' => __( 'Tag successfully deleted', 'storesuite' ) ) );
	}
}
