<?php
/**
 * StoreSuite tag edit page.
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tag_id = get_query_var( 'edit-tag' );
$tag_id = absint( $tag_id );

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
					if ( $tag_id ) {
						$product_tag = get_term( $tag_id, 'product_tag' );

						if ( ! $product_tag || is_wp_error( $product_tag ) ) {
							echo '<div class="alert alert-danger">' . esc_html__( 'Tag not found.', 'storesuite' ) . '</div>';
							return;
						}
						?>
						<form id="storesuite-edit-tag">
							<div class="storesuite-form-group">
								<label for="name"><?php esc_html_e( 'Tag Name', 'storesuite' ); ?> <span class="req"><?php esc_html_e( '*', 'storesuite' ); ?></span></label>
								<input type="text" class="storesuite-form-control" id="name" name="name" placeholder="<?php echo esc_attr__( 'Product tag name', 'storesuite' ); ?>" value="<?php echo esc_attr( $product_tag->name ); ?>">
							</div>
							<div class="storesuite-form-group">
								<label for="slug"><?php esc_html_e( 'Slug', 'storesuite' ); ?></label>
								<input type="text" class="storesuite-form-control" id="slug" name="slug" placeholder="<?php echo esc_attr__( 'Tag slug', 'storesuite' ); ?>" value="<?php echo esc_attr( $product_tag->slug ); ?>">
								<small class="storesuite-form-text"><?php esc_html_e( 'The "slug" is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.', 'storesuite' ); ?></small>
							</div>
							<div class="storesuite-form-group">
								<label for="description"><?php esc_html_e( 'Tag Description', 'storesuite' ); ?></label>
								<textarea class="storesuite-form-control" id="description" name="description" placeholder="<?php echo esc_attr__( 'Product tag description', 'storesuite' ); ?>" rows="3"><?php echo esc_textarea( $product_tag->description ); ?></textarea>
							</div>
							<div class="storesuite-form-submission-group">
								<?php wp_nonce_field( '_storesuite_edit_product_tag_', 'storesuite_edit_product_tag_nonce' ); ?>
								<input type="hidden" name="action" value="storesuite_edit_product_tag">
								<input type="hidden" name="tag_id" value="<?php echo esc_attr( $product_tag->term_id ); ?>">
								<div class="storesuite-button-group">
									<button class="my-storesuite-button" name="save_product_tag" type="submit"><?php esc_html_e( 'Save Changes', 'storesuite' ); ?></button>
									<a href="<?php echo esc_url( storesuite_get_navigation_url( 'tags' ) ); ?>" class="my-storesuite-button my-storesuite-button-light"><?php esc_html_e( 'Back', 'storesuite' ); ?></a>
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