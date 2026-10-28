<?php
/**
 * StoreSuite brand add page
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
						<form id="storesuite-add-brand">
						<div class="storesuite-form-group">
							<label for="product_brand_name"><?php esc_html_e( 'Brand Name', 'storesuite' ); ?> <span class="req"><?php esc_html_e( '*', 'storesuite' ); ?></span></label>
							<input type="text" class="storesuite-form-control" id="product_brand_name" name="product_brand_name" data-ai-context placeholder="<?php echo esc_attr__( 'Product brand name', 'storesuite' ); ?>">
						</div>
						<div class="storesuite-form-group">
							<label for="product_brand_slug"><?php esc_html_e( 'Slug', 'storesuite' ); ?></label>
							<input type="text" class="storesuite-form-control" id="product_brand_slug" name="product_brand_slug" placeholder="<?php echo esc_attr__( 'Brand slug', 'storesuite' ); ?>">
							<small class="storesuite-form-text"><?php esc_html_e( 'The "slug" is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.', 'storesuite' ); ?></small>
						</div>
							<div class="storesuite-form-group">
								<label for="product_parent_brand"><?php esc_html_e( 'Parent Brand', 'storesuite' ); ?></label>
								<select class="storesuite-form-control" id="product_parent_brand" name="product_parent_brand">
									<option value=""><?php esc_html_e( 'Select parent brand', 'storesuite' ); ?></option>
									<?php
									$product_brands = get_terms(
										array(
											'taxonomy'   => 'product_brand',
											'hide_empty' => false,
											'orderby'    => 'name',
											'order'      => 'ASC',
										)
									);
									if ( ! empty( $product_brands ) && ! is_wp_error( $product_brands ) ) {
										foreach ( $product_brands as $product_brand ) {
											?>
											<option value="<?php echo esc_attr( $product_brand->slug ); ?>"><?php echo esc_html( $product_brand->name ); ?></option>
											<?php
										}
									}
									?>
								</select>
							</div>
						<div class="storesuite-form-group">
							<label for="product_brand_description"><?php esc_html_e( 'Brand Description', 'storesuite' ); ?></label>
							<textarea class="storesuite-form-control" id="product_brand_description" name="product_brand_description" data-ai-context placeholder="<?php echo esc_attr__( 'Product brand description', 'storesuite' ); ?>" rows="3"></textarea>
						</div>
						<div class="storesuite-form-group">
							<label for="product_brand_thumbnail_id"><?php esc_html_e( 'Brand Image', 'storesuite' ); ?></label>
							<input type="hidden" id="product_brand_thumbnail_id" name="product_brand_thumbnail_id" value="">
							<input type="hidden" id="product_brand_thumbnail_url" name="product_brand_thumbnail_url" value="">
							<div id="brand-single-image" class="image-drop-container">
								<div id="brand_thumb_img" class="preview-image"></div>
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
						do_action( 'storesuite_term_form_after_fields', 'product_brand', null );
						?>
						<div class="storesuite-form-submission-group">
								<?php wp_nonce_field( '_storesuite_add_product_brand_', 'storesuite_add_product_brand_nonce' ); ?>
								<input type="hidden" name="action" value="storesuite_add_product_brand">
								<div class="storesuite-button-group">
									<button class="my-storesuite-button" name="save_product_brand" type="submit"><?php esc_html_e( 'Add Brand', 'storesuite' ); ?></button>
									<a href="<?php echo esc_url( storesuite_get_navigation_url( 'brands' ) ); ?>" class="my-storesuite-button my-storesuite-button-light"><?php esc_html_e( 'Back', 'storesuite' ); ?></a>
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