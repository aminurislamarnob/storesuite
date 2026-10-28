<?php
/**
 * StoreSuite brand edit page
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PluginizeLab\StoreSuite\ProductBrand\Brands;

$brand_id = get_query_var( 'edit-brand' );
$brand_id = absint( $brand_id );

if ( ! $brand_id ) {
	wp_die( esc_html__( 'Invalid brand ID', 'storesuite' ) );
}

$brands_obj = new Brands();
$brand      = $brands_obj->get_brand_by_id( $brand_id );

if ( ! $brand || is_wp_error( $brand ) ) {
	wp_die( esc_html__( 'Brand not found', 'storesuite' ) );
}

// Get brand thumbnail.
$thumbnail_id  = absint( get_term_meta( $brand_id, 'thumbnail_id', true ) );
$thumbnail_url = '';
if ( $thumbnail_id ) {
	$thumbnail_url = wp_get_attachment_image_url( $thumbnail_id, 'thumbnail' );
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
						<form id="storesuite-edit-brand">
							<div class="storesuite-form-group">
								<label for="product_brand_name"><?php esc_html_e( 'Brand Name', 'storesuite' ); ?> <span class="req"><?php esc_html_e( '*', 'storesuite' ); ?></span></label>
								<input type="text" class="storesuite-form-control" id="product_brand_name" name="product_brand_name" value="<?php echo esc_attr( $brand->name ); ?>" placeholder="<?php echo esc_attr__( 'Product brand name', 'storesuite' ); ?>">
							</div>
							<div class="storesuite-form-group">
								<label for="product_brand_slug"><?php esc_html_e( 'Slug', 'storesuite' ); ?></label>
								<input type="text" class="storesuite-form-control" id="product_brand_slug" name="product_brand_slug" placeholder="<?php echo esc_attr__( 'Brand slug', 'storesuite' ); ?>" value="<?php echo esc_attr( $brand->slug ); ?>">
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
											// Don't allow selecting itself as parent.
											if ( $product_brand->term_id === $brand->term_id ) {
												continue;
											}
											$selected = ( $brand->parent === $product_brand->term_id ) ? 'selected' : '';
											?>
											<option value="<?php echo esc_attr( $product_brand->slug ); ?>" <?php echo esc_attr( $selected ); ?>><?php echo esc_html( $product_brand->name ); ?></option>
											<?php
										}
									}
									?>
								</select>
							</div>
						<div class="storesuite-form-group">
							<label for="product_brand_description"><?php esc_html_e( 'Brand Description', 'storesuite' ); ?></label>
							<textarea class="storesuite-form-control" id="product_brand_description" name="product_brand_description" placeholder="<?php echo esc_attr__( 'Product brand description', 'storesuite' ); ?>" rows="3"><?php echo esc_textarea( $brand->description ); ?></textarea>
						</div>
						<div class="storesuite-form-group">
							<label for="product_brand_thumbnail_id"><?php esc_html_e( 'Brand Image', 'storesuite' ); ?></label>
							<input type="hidden" id="product_brand_thumbnail_id" name="product_brand_thumbnail_id" value="<?php echo esc_attr( $thumbnail_id ); ?>">
							<input type="hidden" id="product_brand_thumbnail_url" name="product_brand_thumbnail_url" value="<?php echo esc_url( $thumbnail_url ); ?>">
							<div id="brand-single-image" class="image-drop-container<?php echo esc_attr( $thumbnail_id ? ' image-drop-bg' : '' ); ?>">
								<div id="brand_thumb_img" class="preview-image">
									<?php if ( $thumbnail_url ) : ?>
										<img src="<?php echo esc_url( $thumbnail_url ); ?>" alt="<?php esc_attr_e( 'Brand thumbnail', 'storesuite' ); ?>">
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
						do_action( 'storesuite_term_form_after_fields', 'product_brand', $brand );
						?>
						<div class="storesuite-form-submission-group">
								<?php wp_nonce_field( '_storesuite_edit_product_brand_', 'storesuite_edit_product_brand_nonce' ); ?>
								<input type="hidden" name="action" value="storesuite_edit_product_brand">
								<input type="hidden" name="brand_id" value="<?php echo esc_attr( $brand->term_id ); ?>">
								<div class="storesuite-button-group">
									<button class="my-storesuite-button" name="save_product_brand" type="submit"><?php esc_html_e( 'Save Changes', 'storesuite' ); ?></button>
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