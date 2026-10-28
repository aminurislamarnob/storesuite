<?php
/**
 * StoreSuite category add page
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
						<form id="storesuite-add-category">
						<div class="storesuite-form-group">
							<label for="product_category_name"><?php esc_html_e( 'Category Name', 'storesuite' ); ?> <span class="req"><?php esc_html_e( '*', 'storesuite' ); ?></span></label>
						<input type="text" class="storesuite-form-control" id="product_category_name" name="product_category_name" placeholder="<?php echo esc_attr__( 'Product category name', 'storesuite' ); ?>">
						</div>
						<div class="storesuite-form-group">
							<label for="product_category_slug"><?php esc_html_e( 'Slug', 'storesuite' ); ?></label>
							<input type="text" class="storesuite-form-control" id="product_category_slug" name="product_category_slug" placeholder="<?php echo esc_attr__( 'Category slug', 'storesuite' ); ?>">
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
										?>
										<option value="<?php echo esc_attr( $product_category->slug ); ?>"><?php echo esc_html( $product_category->name ); ?></option>
									<?php } ?>
								</select>
							</div>
						<div class="storesuite-form-group">
							<label for="product_category_description"><?php esc_html_e( 'Category Description', 'storesuite' ); ?></label>
							<textarea class="storesuite-form-control" id="product_category_description" name="product_category_description" placeholder="<?php echo esc_attr__( 'Product category description', 'storesuite' ); ?>" rows="3"></textarea>
						</div>
						<div class="storesuite-form-group">
							<label for="display_type"><?php esc_html_e( 'Display Type', 'storesuite' ); ?></label>
							<select class="storesuite-form-control" id="display_type" name="display_type">
								<option value=""><?php esc_html_e( 'Default', 'storesuite' ); ?></option>
								<option value="products"><?php esc_html_e( 'Products', 'storesuite' ); ?></option>
								<option value="subcategories"><?php esc_html_e( 'Subcategories', 'storesuite' ); ?></option>
								<option value="both"><?php esc_html_e( 'Both', 'storesuite' ); ?></option>
							</select>
						</div>
						<div class="storesuite-form-group">
							<label for="product_category_thumbnail_id"><?php esc_html_e( 'Category Image', 'storesuite' ); ?></label>
							<input type="hidden" id="product_category_thumbnail_id" name="product_category_thumbnail_id" value="">
							<input type="hidden" id="product_category_thumbnail_url" name="product_category_thumbnail_url" value="">
							<div id="category-single-image" class="image-drop-container">
								<div id="category_thumb_img" class="preview-image"></div>
								<div class="image-drop-text">
									<i class="las la-image"></i>
									<span><?php esc_html_e( 'Upload Image', 'storesuite' ); ?></span>
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
						do_action( 'storesuite_term_form_after_fields', 'product_cat', null );
						?>
						<div class="storesuite-form-submission-group">
								<?php wp_nonce_field( '_storesuite_add_product_category_', 'storesuite_add_product_category_nonce' ); ?>
								<input type="hidden" name="action" value="storesuite_add_product_category">
								<div class="storesuite-button-group">
									<button class="my-storesuite-button" name="save_product_category" type="submit"><?php esc_html_e( 'Add Category', 'storesuite' ); ?></button>
									<a href="<?php echo esc_url( storesuite_get_navigation_url( 'categories' ) ); ?>" class="my-storesuite-button my-storesuite-button-light"><?php esc_html_e( 'Back', 'storesuite' ); ?></a>
								</div>
							</div>
						</form>
					</div>
				</div>
			</div>
		</main>
	</div>
</div>
<?php do_action( 'storesuite_dashboard_wrapper_end' ); ?>