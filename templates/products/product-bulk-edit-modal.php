<?php
/**
 * Bulk edit products: modal form (field names align with bulk_edit_posts / WooCommerce bulk edit).
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$storesuite_post_type_object = get_post_type_object( 'product' );
if ( ! $storesuite_post_type_object ) {
	return;
}

$can_publish = current_user_can( $storesuite_post_type_object->cap->publish_posts );

$inline_edit_statuses = array(
	'-1' => __( '— No change —', 'storesuite' ),
);
if ( $can_publish ) {
	$inline_edit_statuses['publish'] = __( 'Published', 'storesuite' );
	$inline_edit_statuses['future']  = __( 'Scheduled', 'storesuite' );
	$inline_edit_statuses['private'] = __( 'Private', 'storesuite' );
}
$inline_edit_statuses['pending'] = __( 'Pending Review', 'storesuite' );
$inline_edit_statuses['draft']   = __( 'Draft', 'storesuite' );

$inline_edit_statuses = apply_filters( 'quick_edit_statuses', $inline_edit_statuses, 'product', true, $can_publish );

$shipping_class = get_terms(
	array(
		'taxonomy'   => 'product_shipping_class',
		'hide_empty' => false,
	)
);
if ( is_wp_error( $shipping_class ) ) {
	$shipping_class = array();
}

$storesuite_bulk_categories = get_terms(
	array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => false,
	)
);
if ( is_wp_error( $storesuite_bulk_categories ) ) {
	$storesuite_bulk_categories = array();
}

$storesuite_bulk_tags = get_terms(
	array(
		'taxonomy'   => 'product_tag',
		'hide_empty' => false,
	)
);
if ( is_wp_error( $storesuite_bulk_tags ) ) {
	$storesuite_bulk_tags = array();
}

$storesuite_bulk_tax_ops = array(
	''       => __( '— No change —', 'storesuite' ),
	'add'    => __( 'Add', 'storesuite' ),
	'remove' => __( 'Remove', 'storesuite' ),
);
?>
<div id="storesuite-product-bulk-edit-modal" class="storesuite-product-bulk-modal-overlay" hidden aria-hidden="true">
	<div
		class="storesuite-product-bulk-modal-dialog"
		role="dialog"
		aria-modal="true"
		aria-labelledby="storesuite-product-bulk-edit-title"
		tabindex="-1"
	>
		<div class="storesuite-product-bulk-modal-header">
			<h2 id="storesuite-product-bulk-edit-title" class="storesuite-product-bulk-modal-title">
				<?php esc_html_e( 'Bulk edit products', 'storesuite' ); ?>
			</h2>
			<button type="button" class="storesuite-product-bulk-modal-close my-storesuite-button storesuite-button-ghost" aria-label="<?php esc_attr_e( 'Close', 'storesuite' ); ?>">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">
					<path d="M18,6h0a1,1,0,0,0-1.414,0L12,10.586,7.414,6A1,1,0,0,0,6,6H6a1,1,0,0,0,0,1.414L10.586,12,6,16.586A1,1,0,0,0,6,18h0a1,1,0,0,0,1.414,0L12,13.414,16.586,18A1,1,0,0,0,18,18h0a1,1,0,0,0,0-1.414L13.414,12,18,7.414A1,1,0,0,0,18,6Z"/>
				</svg>
			</button>
		</div>
		<form id="storesuite-product-bulk-edit-form" method="post" class="storesuite-product-bulk-edit-form">
			<input type="hidden" name="post_type" value="product" />
			<div id="storesuite-bulk-edit-post-ids" class="storesuite-bulk-edit-post-ids" aria-hidden="true"></div>

			<div class="storesuite-product-bulk-edit-scroll">
				<div class="storesuite-product-bulk-edit-core">
					<legend class="screen-reader-text"><?php esc_html_e( 'Post fields', 'storesuite' ); ?></legend>
					<div class="row">
						<div class="<?php echo post_type_supports( 'product', 'comments' ) ? 'col-md-6' : 'col-md-12'; ?>">
							<div class="storesuite-form-group">
								<label for="storesuite-bulk-_status" class="storesuite-form-label"><?php esc_html_e( 'Status', 'storesuite' ); ?></label>
								<select name="_status" id="storesuite-bulk-_status" class="storesuite-form-control">
									<?php foreach ( $inline_edit_statuses as $inline_status_value => $inline_status_text ) : ?>
										<option value="<?php echo esc_attr( (string) $inline_status_value ); ?>"><?php echo esc_html( $inline_status_text ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>
						<?php if ( post_type_supports( 'product', 'comments' ) ) : ?>
							<div class="col-md-6">
								<div class="storesuite-form-group">
									<label for="storesuite-bulk-comment_status" class="storesuite-form-label"><?php esc_html_e( 'Reviews / comments', 'storesuite' ); ?></label>
									<select name="comment_status" id="storesuite-bulk-comment_status" class="storesuite-form-control">
										<option value=""><?php esc_html_e( '— No change —', 'storesuite' ); ?></option>
										<option value="open"><?php esc_html_e( 'Allow', 'storesuite' ); ?></option>
										<option value="closed"><?php esc_html_e( 'Do not allow', 'storesuite' ); ?></option>
									</select>
								</div>
							</div>
						<?php endif; ?>
					</div>

					<?php if ( post_type_supports( 'product', 'trackbacks' ) ) : ?>
						<div class="row">
							<div class="col-md-6">
								<div class="storesuite-form-group">
									<label for="storesuite-bulk-ping_status" class="storesuite-form-label"><?php esc_html_e( 'Pings', 'storesuite' ); ?></label>
									<select name="ping_status" id="storesuite-bulk-ping_status" class="storesuite-form-control">
										<option value=""><?php esc_html_e( '— No change —', 'storesuite' ); ?></option>
										<option value="open"><?php esc_html_e( 'Allow', 'storesuite' ); ?></option>
										<option value="closed"><?php esc_html_e( 'Do not allow', 'storesuite' ); ?></option>
									</select>
								</div>
							</div>
						</div>
					<?php endif; ?>

						<div class="row">
							<div class="col-md-6">
								<div class="storesuite-form-group">
									<label for="storesuite-bulk-cat-op" class="storesuite-form-label"><?php esc_html_e( 'Categories', 'storesuite' ); ?></label>
									<select name="storesuite_bulk_cat_op" id="storesuite-bulk-cat-op" class="storesuite-form-control">
										<?php foreach ( $storesuite_bulk_tax_ops as $storesuite_tax_op_value => $storesuite_tax_op_label ) : ?>
											<option value="<?php echo esc_attr( $storesuite_tax_op_value ); ?>"><?php echo esc_html( $storesuite_tax_op_label ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="storesuite-form-group">
									<select name="storesuite_bulk_cats[]" id="storesuite-bulk-cats" class="storesuite-form-control storesuite-select2" multiple data-placeholder="<?php esc_attr_e( 'Select categories…', 'storesuite' ); ?>" aria-label="<?php esc_attr_e( 'Categories to add or remove', 'storesuite' ); ?>">
										<?php foreach ( $storesuite_bulk_categories as $storesuite_bulk_category ) : ?>
											<option value="<?php echo esc_attr( (string) $storesuite_bulk_category->term_id ); ?>"><?php echo esc_html( $storesuite_bulk_category->name ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
							</div>
							<div class="col-md-6">
								<div class="storesuite-form-group">
									<label for="storesuite-bulk-tag-op" class="storesuite-form-label"><?php esc_html_e( 'Tags', 'storesuite' ); ?></label>
									<select name="storesuite_bulk_tag_op" id="storesuite-bulk-tag-op" class="storesuite-form-control">
										<?php foreach ( $storesuite_bulk_tax_ops as $storesuite_tax_op_value => $storesuite_tax_op_label ) : ?>
											<option value="<?php echo esc_attr( $storesuite_tax_op_value ); ?>"><?php echo esc_html( $storesuite_tax_op_label ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="storesuite-form-group">
									<select name="storesuite_bulk_tags[]" id="storesuite-bulk-tags" class="storesuite-form-control storesuite-select2" multiple data-placeholder="<?php esc_attr_e( 'Select tags…', 'storesuite' ); ?>" aria-label="<?php esc_attr_e( 'Tags to add or remove', 'storesuite' ); ?>">
										<?php foreach ( $storesuite_bulk_tags as $storesuite_bulk_tag ) : ?>
											<option value="<?php echo esc_attr( (string) $storesuite_bulk_tag->term_id ); ?>"><?php echo esc_html( $storesuite_bulk_tag->name ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
							</div>
						</div>
					</div>

				<?php
				storesuite_get_template_part(
					'products/html-bulk-edit-product',
					'',
					array(
						'shipping_class' => $shipping_class,
					)
				);
				?>
			</div>

			<div class="storesuite-product-bulk-modal-footer">
				<button type="button" class="my-storesuite-button storesuite-button-neutral-panel storesuite-product-bulk-modal-cancel">
					<?php esc_html_e( 'Cancel', 'storesuite' ); ?>
				</button>
				<input type="submit" name="bulk_edit" class="my-storesuite-button storesuite-bulk-edit-submit" value="<?php esc_attr_e( 'Update', 'storesuite' ); ?>" />
			</div>
		</form>
	</div>
</div>
