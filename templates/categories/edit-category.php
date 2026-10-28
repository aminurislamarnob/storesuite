<?php
/**
 * StoreSuite category edit page
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$category_id = get_query_var( 'edit-category' );
$category_id = absint( $category_id );

do_action( 'storesuite_dashboard_wrapper_start' );
?>
<div class="my-storesuite-container">
	<aside id="storesuite-dashboard-sidebar" class="my-storesuite-sidebar" role="navigation" aria-label="<?php esc_attr_e( 'Store dashboard navigation', 'storesuite' ); ?>">
		<?php do_action( 'storesuite_dashboard_navigation' ); ?>
	</aside>
	<div class="my-storesuite-wrapper">
		<?php do_action( 'storesuite_dashboard_content_before' ); ?>
		<main class="my-storesuite-page-content">
			<?php do_action( 'storesuite_dashboard_before_main_content' ); ?>
			<div class="row">
				<div class="col-md-6">
					<div class="storesuite-card">
						<?php
						if ( $category_id ) {
							$category = get_term( $category_id, 'product_cat' );

							if ( ! $category || is_wp_error( $category ) ) {
								echo '<div class="alert alert-danger">' . esc_html__( 'Category not found.', 'storesuite' ) . '</div>';
								return;
							}

							// Get category thumbnail.
							$thumbnail_id  = absint( get_term_meta( $category_id, 'thumbnail_id', true ) );
							$thumbnail_url = '';
							if ( $thumbnail_id ) {
								$thumbnail_url = wp_get_attachment_image_url( $thumbnail_id, 'thumbnail' );
							}

							// Get display type.
							$display_type = get_term_meta( $category_id, 'display_type', true );
							?>
						<form id="storesuite-edit-category">
							<div class="storesuite-form-group">
								<label for="product_category_name"><?php esc_html_e( 'Category Name', 'storesuite' ); ?> <span class="req"><?php esc_html_e( '*', 'storesuite' ); ?></span></label>
								<input type="text" class="storesuite-form-control" id="product_category_name" name="product_category_name" data-ai-context placeholder="<?php echo esc_attr__( 'Product category name', 'storesuite' ); ?>" value="<?php echo esc_attr( $category->name ); ?>">
							</div>
							<div class="storesuite-form-group">
								<label for="product_category_slug"><?php esc_html_e( 'Slug', 'storesuite' ); ?></label>
								<input type="text" class="storesuite-form-control" id="product_category_slug" name="product_category_slug" placeholder="<?php echo esc_attr__( 'Category slug', 'storesuite' ); ?>" value="<?php echo esc_attr( $category->slug ); ?>">
								<small class="storesuite-form-text"><?php esc_html_e( 'The "slug" is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.', 'storesuite' ); ?></small>
							</div>
							<div class="storesuite-form-group">
								<label for="product_parent_category"><?php esc_html_e( 'Select Parent Category', 'storesuite' ); ?></label>
								<select class="storesuite-form-control" id="product_parent_category" name="product_parent_category">
									<option value=""><?php esc_html_e( '-- Select parent category --', 'storesuite' ); ?></option>
									<?php
									$product_categories = get_categories(
										array(
											'orderby'    => 'name',
											'order'      => 'ASC',
											'taxonomy'   => 'product_cat',
											'hide_empty' => false,
										)
									);
									foreach ( $product_categories as $product_category ) {
										if ( $product_category->term_id !== $category->term_id ) { // Prevent setting itself as parent.
											?>
											<option value="<?php echo esc_attr( $product_category->slug ); ?>" <?php selected( $category->parent, $product_category->term_id ); ?>>
												<?php echo esc_html( $product_category->name ); ?>
											</option>
											<?php
										}
									}
									?>
								</select>
							</div>
							<div class="storesuite-form-group">
								<label for="product_category_description"><?php esc_html_e( 'Category Description', 'storesuite' ); ?></label>
								<textarea class="storesuite-form-control" id="product_category_description" name="product_category_description" data-ai-context placeholder="<?php echo esc_attr__( 'Product category description', 'storesuite' ); ?>" rows="3"><?php echo esc_textarea( $category->description ); ?></textarea>
							</div>
							<div class="storesuite-form-group">
								<label for="display_type"><?php esc_html_e( 'Display Type', 'storesuite' ); ?></label>
								<select class="storesuite-form-control" id="display_type" name="display_type">
									<option value="" <?php selected( $display_type, '' ); ?>><?php esc_html_e( 'Default', 'storesuite' ); ?></option>
									<option value="products" <?php selected( $display_type, 'products' ); ?>><?php esc_html_e( 'Products', 'storesuite' ); ?></option>
									<option value="subcategories" <?php selected( $display_type, 'subcategories' ); ?>><?php esc_html_e( 'Subcategories', 'storesuite' ); ?></option>
									<option value="both" <?php selected( $display_type, 'both' ); ?>><?php esc_html_e( 'Both', 'storesuite' ); ?></option>
								</select>
							</div>
							<div class="storesuite-form-group">
								<label for="product_category_thumbnail_id"><?php esc_html_e( 'Category Image', 'storesuite' ); ?></label>
								<input type="hidden" id="product_category_thumbnail_id" name="product_category_thumbnail_id" value="<?php echo esc_attr( $thumbnail_id ); ?>">
								<input type="hidden" id="product_category_thumbnail_url" name="product_category_thumbnail_url" value="<?php echo esc_url( $thumbnail_url ); ?>">
								<div id="category-single-image" class="image-drop-container<?php echo esc_attr( $thumbnail_id ? ' image-drop-bg' : '' ); ?>">
									<div id="category_thumb_img" class="preview-image">
										<?php if ( $thumbnail_url ) : ?>
											<img src="<?php echo esc_url( $thumbnail_url ); ?>" alt="<?php esc_attr_e( 'Category thumbnail', 'storesuite' ); ?>">
										<?php endif; ?>
									</div>
									<div class="image-drop-text">
										<i class="las la-image"></i>
										<?php if ( $thumbnail_id ) : ?>
											<span><?php esc_html_e( 'Remove Image', 'storesuite' ); ?></span>
										<?php else : ?>
											<span><?php esc_html_e( 'Upload Image', 'storesuite' ); ?></span>
										<?php endif; ?>
									</div>
								</div>
							</div>
							<?php
							/**
							 * Fires inside a term form, right before the submission group.
							 *
							 * @param string        $taxonomy Taxonomy of the form's term.
							 * @param \WP_Term|null $term     The term being edited, or null on add forms.
							 */
							do_action( 'storesuite_term_form_after_fields', 'product_cat', $category );
							?>
							<div class="storesuite-form-submission-group">
								<?php wp_nonce_field( '_storesuite_edit_product_category_', 'storesuite_edit_product_category_nonce' ); ?>
								<input type="hidden" name="action" value="storesuite_edit_product_category">
								<input type="hidden" name="category_id" value="<?php echo esc_attr( $category->term_id ); ?>">
								<div class="storesuite-button-group">
									<button class="my-storesuite-button" name="save_product_category" type="submit"><?php esc_html_e( 'Save Changes', 'storesuite' ); ?></button>
									<a href="<?php echo esc_url( storesuite_get_navigation_url( 'categories' ) ); ?>" class="my-storesuite-button my-storesuite-button-light"><?php esc_html_e( 'Back', 'storesuite' ); ?></a>
								</div>
							</div>
						</form>
						<?php } ?>
					</div>
				</div>
			</div>
		</main>
	</div>
</div>
<?php do_action( 'storesuite_dashboard_wrapper_end' ); ?>