<?php
/**
 * StoreSuite tag add page
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
						<form id="storesuite-add-tag">
						<div class="storesuite-form-group">
							<label for="name"><?php esc_html_e( 'Tag Name', 'storesuite' ); ?> <span class="req"><?php esc_html_e( '*', 'storesuite' ); ?></span></label>
							<input type="text" class="storesuite-form-control" id="name" name="name" data-ai-context placeholder="<?php echo esc_attr__( 'Product tag name', 'storesuite' ); ?>">
						</div>
						<div class="storesuite-form-group">
							<label for="slug"><?php esc_html_e( 'Slug', 'storesuite' ); ?></label>
							<input type="text" class="storesuite-form-control" id="slug" name="slug" placeholder="<?php echo esc_attr__( 'Tag slug', 'storesuite' ); ?>">
							<small class="storesuite-form-text"><?php esc_html_e( 'The "slug" is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.', 'storesuite' ); ?></small>
						</div>
						<div class="storesuite-form-group">
							<label for="description"><?php esc_html_e( 'Tag Description', 'storesuite' ); ?></label>
							<textarea class="storesuite-form-control" id="description" name="description" data-ai-context placeholder="<?php echo esc_attr__( 'Product tag description', 'storesuite' ); ?>" rows="3"></textarea>
						</div>
							<?php
							/**
							 * Fires inside a term form, right before the submission group.
							 *
							 * @param string        $taxonomy Taxonomy of the form's term.
							 * @param \WP_Term|null $term     The term being edited, or null on add forms.
							 */
							do_action( 'storesuite_term_form_after_fields', 'product_tag', null );
							?>
							<div class="storesuite-form-submission-group">
								<?php wp_nonce_field( '_storesuite_add_product_tag_', 'storesuite_add_product_tag_nonce' ); ?>
								<input type="hidden" name="action" value="storesuite_add_product_tag">
								<div class="storesuite-button-group">
									<button class="my-storesuite-button" name="save_product_tag" type="submit"><?php esc_html_e( 'Add Tag', 'storesuite' ); ?></button>
									<a href="<?php echo esc_url( storesuite_get_navigation_url( 'tags' ) ); ?>" class="my-storesuite-button my-storesuite-button-light"><?php esc_html_e( 'Back', 'storesuite' ); ?></a>
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