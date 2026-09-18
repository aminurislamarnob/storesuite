<?php
/**
 * Yoast SEO card for the product add/edit form.
 *
 * Rendered by YoastSeoIntegration on `storesuite_product_form_after_main_cards`
 * and only when Yoast SEO is active.
 *
 * @var WC_Product|null $product             Product being edited, null on the add form.
 * @var bool            $is_edit_mode        Whether this is the edit form.
 * @var string          $form_marker         Name of the hidden field marking the card as submitted.
 * @var string          $field_prefix        Prefix of the SEO field names.
 * @var array           $seo_tabs            Tab labels keyed by tab slug; the tab strip shows only with more than one.
 * @var array           $seo_values          Stored Yoast values keyed by Yoast meta key (without prefix).
 * @var string          $title_template      Yoast's site-wide SEO title template for products.
 * @var string          $desc_template       Yoast's site-wide meta description template for products.
 * @var bool            $cornerstone_enabled Whether Yoast's cornerstone content feature is on.
 * @var array           $social_networks     Enabled social networks: Yoast meta key prefix => label.
 * @var array           $image_previews      Preview image URL per social network, empty when none is set.
 * @var bool            $can_edit_advanced   Whether the current user may edit the advanced settings.
 * @var array           $robots_adv_choices  "Meta robots advanced" directives: value => label.
 * @var bool            $noindex_by_default  Whether Yoast keeps products out of search results site-wide.
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="storesuite-card storesuite-card-with-header storesuite-mb-24 storesuite-seo-card" id="storesuite-yoast-seo">
	<h3 class="storesuite-card-title"><?php esc_html_e( 'SEO (Yoast)', 'storesuite' ); ?></h3>
	<div class="storesuite-card-content">
		<input type="hidden" name="<?php echo esc_attr( $form_marker ); ?>" value="1">

		<?php if ( count( $seo_tabs ) > 1 ) : ?>
			<div class="storesuite-seo-tabs" role="tablist">
				<?php foreach ( $seo_tabs as $storesuite_seo_tab_key => $storesuite_seo_tab_label ) : ?>
					<button type="button" class="storesuite-seo-tab<?php echo 'seo' === $storesuite_seo_tab_key ? ' is-active' : ''; ?>" role="tab" data-seo-tab="<?php echo esc_attr( $storesuite_seo_tab_key ); ?>" aria-selected="<?php echo 'seo' === $storesuite_seo_tab_key ? 'true' : 'false'; ?>"><?php echo esc_html( $storesuite_seo_tab_label ); ?></button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<div class="storesuite-seo-panel is-active" role="tabpanel" data-seo-panel="seo">
			<div class="row">
				<div class="col-md-12">
					<div class="storesuite-form-group">
						<label for="<?php echo esc_attr( $field_prefix . 'focuskw' ); ?>"><?php esc_html_e( 'Focus keyphrase', 'storesuite' ); ?></label>
						<input type="text" class="storesuite-form-control" id="<?php echo esc_attr( $field_prefix . 'focuskw' ); ?>" name="<?php echo esc_attr( $field_prefix . 'focuskw' ); ?>" value="<?php echo esc_attr( $seo_values['focuskw'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'The search term you want this product to rank for', 'storesuite' ); ?>">
					</div>
				</div>
				<div class="col-md-12">
					<div class="storesuite-seo-preview" data-mode="mobile">
						<div class="storesuite-seo-preview-header">
							<span class="storesuite-seo-preview-label"><?php esc_html_e( 'Google preview', 'storesuite' ); ?></span>
							<div class="storesuite-seo-preview-modes">
								<span class="storesuite-seo-preview-mode-label" data-seo-mode="mobile"><?php esc_html_e( 'Mobile', 'storesuite' ); ?></span>
								<button type="button" class="storesuite-seo-mode-switch" role="switch" aria-checked="false" aria-label="<?php esc_attr_e( 'Switch to desktop preview', 'storesuite' ); ?>"><span></span></button>
								<span class="storesuite-seo-preview-mode-label" data-seo-mode="desktop"><?php esc_html_e( 'Desktop', 'storesuite' ); ?></span>
							</div>
						</div>
						<div class="storesuite-seo-snippet" aria-live="polite">
							<div class="storesuite-seo-snippet-site">
								<span class="storesuite-seo-snippet-icon" aria-hidden="true"></span>
								<span class="storesuite-seo-snippet-site-text">
									<span class="storesuite-seo-snippet-sitename"></span>
									<span class="storesuite-seo-snippet-url"></span>
								</span>
							</div>
							<div class="storesuite-seo-snippet-title"></div>
							<div class="storesuite-seo-snippet-desc"></div>
						</div>
					</div>
				</div>
				<div class="col-md-12">
					<div class="storesuite-form-group storesuite-seo-field" data-seo-field="title">
						<div class="storesuite-seo-field-header">
							<label for="<?php echo esc_attr( $field_prefix . 'title' ); ?>"><?php esc_html_e( 'SEO title', 'storesuite' ); ?></label>
							<button type="button" class="my-storesuite-button storesuite-seo-insert-variable" aria-haspopup="listbox" aria-expanded="false"><?php esc_html_e( 'Insert variable', 'storesuite' ); ?></button>
						</div>
						<input type="text" class="storesuite-form-control" id="<?php echo esc_attr( $field_prefix . 'title' ); ?>" name="<?php echo esc_attr( $field_prefix . 'title' ); ?>" value="<?php echo esc_attr( $seo_values['title'] ?? '' ); ?>" placeholder="<?php echo esc_attr( $title_template ); ?>" autocomplete="off">
						<div class="storesuite-seo-progress" role="progressbar" aria-label="<?php esc_attr_e( 'SEO title width', 'storesuite' ); ?>" aria-valuemin="0"><span></span></div>
						<small class="storesuite-form-text"><?php esc_html_e( 'Leave blank to use the site-wide SEO title template for products. Type % to insert a variable.', 'storesuite' ); ?></small>
					</div>
				</div>
				<div class="col-md-12">
					<div class="storesuite-form-group storesuite-seo-field" data-seo-field="metadesc">
						<div class="storesuite-seo-field-header">
							<label for="<?php echo esc_attr( $field_prefix . 'metadesc' ); ?>"><?php esc_html_e( 'Meta description', 'storesuite' ); ?></label>
							<button type="button" class="my-storesuite-button storesuite-seo-insert-variable" aria-haspopup="listbox" aria-expanded="false"><?php esc_html_e( 'Insert variable', 'storesuite' ); ?></button>
						</div>
						<textarea class="storesuite-form-control" id="<?php echo esc_attr( $field_prefix . 'metadesc' ); ?>" name="<?php echo esc_attr( $field_prefix . 'metadesc' ); ?>" rows="3" placeholder="<?php echo esc_attr( $desc_template ); ?>"><?php echo esc_textarea( $seo_values['metadesc'] ?? '' ); ?></textarea>
						<div class="storesuite-seo-progress" role="progressbar" aria-label="<?php esc_attr_e( 'Meta description length', 'storesuite' ); ?>" aria-valuemin="0"><span></span></div>
						<small class="storesuite-form-text"><?php esc_html_e( 'Leave blank to use the site-wide meta description template for products.', 'storesuite' ); ?></small>
					</div>
				</div>
				<?php if ( $cornerstone_enabled ) : ?>
					<div class="col-md-12">
						<div class="storesuite-form-group storesuite-form-switch">
							<input type="checkbox" class="storesuite-form-control" id="<?php echo esc_attr( $field_prefix . 'is_cornerstone' ); ?>" name="<?php echo esc_attr( $field_prefix . 'is_cornerstone' ); ?>" value="yes" <?php checked( $seo_values['is_cornerstone'] ?? '', '1' ); ?>>
							<label for="<?php echo esc_attr( $field_prefix . 'is_cornerstone' ); ?>"><?php esc_html_e( 'Mark as cornerstone content', 'storesuite' ); ?></label>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( $social_networks ) : ?>
			<div class="storesuite-seo-panel" role="tabpanel" data-seo-panel="social" hidden>
				<?php foreach ( $social_networks as $storesuite_seo_network => $storesuite_seo_network_label ) : ?>
					<?php
					$storesuite_seo_image_field = $field_prefix . $storesuite_seo_network . '-image-id';
					$storesuite_seo_title_field = $field_prefix . $storesuite_seo_network . '-title';
					$storesuite_seo_desc_field  = $field_prefix . $storesuite_seo_network . '-description';
					$storesuite_seo_image_url   = $image_previews[ $storesuite_seo_network ] ?? '';
					?>
					<?php
					$storesuite_seo_is_x     = 'twitter' === $storesuite_seo_network;
					$storesuite_seo_group_id = 'storesuite-seo-network-' . $storesuite_seo_network;
					?>
					<div class="storesuite-seo-network" role="group" aria-labelledby="<?php echo esc_attr( $storesuite_seo_group_id ); ?>">
						<h4 class="storesuite-seo-network-title" id="<?php echo esc_attr( $storesuite_seo_group_id ); ?>"><?php echo esc_html( $storesuite_seo_network_label ); ?></h4>
						<?php if ( $storesuite_seo_is_x && isset( $social_networks['opengraph'] ) ) : ?>
							<p class="storesuite-seo-network-note"><?php esc_html_e( 'Only fill these in to make the product look different on X. Left untouched, the social media appearance settings above are used for X as well.', 'storesuite' ); ?></p>
						<?php endif; ?>
						<div class="row">
							<div class="col-md-4">
								<div class="storesuite-form-group">
									<span class="storesuite-seo-image-label"><?php echo esc_html( $storesuite_seo_is_x ? __( 'X image', 'storesuite' ) : __( 'Social image', 'storesuite' ) ); ?></span>
									<div class="storesuite-seo-image<?php echo $storesuite_seo_image_url ? ' has-image' : ''; ?>">
										<input type="hidden" name="<?php echo esc_attr( $storesuite_seo_image_field ); ?>" value="<?php echo esc_attr( $seo_values[ $storesuite_seo_network . '-image-id' ] ?? '' ); ?>">
										<div class="storesuite-seo-image-preview">
											<?php if ( $storesuite_seo_image_url ) : ?>
												<img src="<?php echo esc_url( $storesuite_seo_image_url ); ?>" alt="">
											<?php endif; ?>
										</div>
										<div class="storesuite-seo-image-actions">
											<button type="button" class="my-storesuite-button storesuite-seo-image-select" data-select-label="<?php esc_attr_e( 'Select image', 'storesuite' ); ?>" data-replace-label="<?php esc_attr_e( 'Replace image', 'storesuite' ); ?>"><?php echo esc_html( $storesuite_seo_image_url ? __( 'Replace image', 'storesuite' ) : __( 'Select image', 'storesuite' ) ); ?></button>
											<button type="button" class="my-storesuite-button storesuite-seo-image-remove"><?php esc_html_e( 'Remove image', 'storesuite' ); ?></button>
										</div>
									</div>
									<small class="storesuite-form-text"><?php esc_html_e( 'Leave empty to share the product image.', 'storesuite' ); ?></small>
								</div>
							</div>
							<div class="col-md-8">
								<div class="storesuite-form-group storesuite-seo-field">
									<div class="storesuite-seo-field-header">
										<label for="<?php echo esc_attr( $storesuite_seo_title_field ); ?>"><?php echo esc_html( $storesuite_seo_is_x ? __( 'X title', 'storesuite' ) : __( 'Social title', 'storesuite' ) ); ?></label>
										<button type="button" class="my-storesuite-button storesuite-seo-insert-variable" aria-haspopup="listbox" aria-expanded="false"><?php esc_html_e( 'Insert variable', 'storesuite' ); ?></button>
									</div>
									<input type="text" class="storesuite-form-control" id="<?php echo esc_attr( $storesuite_seo_title_field ); ?>" name="<?php echo esc_attr( $storesuite_seo_title_field ); ?>" value="<?php echo esc_attr( $seo_values[ $storesuite_seo_network . '-title' ] ?? '' ); ?>" autocomplete="off">
								</div>
								<div class="storesuite-form-group storesuite-seo-field">
									<div class="storesuite-seo-field-header">
										<label for="<?php echo esc_attr( $storesuite_seo_desc_field ); ?>"><?php echo esc_html( $storesuite_seo_is_x ? __( 'X description', 'storesuite' ) : __( 'Social description', 'storesuite' ) ); ?></label>
										<button type="button" class="my-storesuite-button storesuite-seo-insert-variable" aria-haspopup="listbox" aria-expanded="false"><?php esc_html_e( 'Insert variable', 'storesuite' ); ?></button>
									</div>
									<textarea class="storesuite-form-control" id="<?php echo esc_attr( $storesuite_seo_desc_field ); ?>" name="<?php echo esc_attr( $storesuite_seo_desc_field ); ?>" rows="3"><?php echo esc_textarea( $seo_values[ $storesuite_seo_network . '-description' ] ?? '' ); ?></textarea>
									<small class="storesuite-form-text">
										<?php
										echo esc_html(
											$storesuite_seo_is_x && isset( $social_networks['opengraph'] )
												? __( 'Leave the title and description blank to reuse the social title and description above.', 'storesuite' )
												: __( 'Leave the title and description blank to reuse the SEO title and meta description.', 'storesuite' )
										);
										?>
									</small>
								</div>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( $can_edit_advanced ) : ?>
			<div class="storesuite-seo-panel" role="tabpanel" data-seo-panel="advanced" hidden>
				<div class="row">
					<div class="col-md-12">
						<div class="storesuite-form-group">
							<label for="<?php echo esc_attr( $field_prefix . 'meta-robots-noindex' ); ?>"><?php esc_html_e( 'Allow search engines to show this product in search results?', 'storesuite' ); ?></label>
							<select class="storesuite-form-control storesuite-seo-narrow" id="<?php echo esc_attr( $field_prefix . 'meta-robots-noindex' ); ?>" name="<?php echo esc_attr( $field_prefix . 'meta-robots-noindex' ); ?>">
								<option value="0" <?php selected( $seo_values['meta-robots-noindex'] ?? '', '' ); ?>>
									<?php
									echo esc_html(
										$noindex_by_default
											? __( 'No (current default for Products)', 'storesuite' )
											: __( 'Yes (current default for Products)', 'storesuite' )
									);
									?>
								</option>
								<option value="1" <?php selected( $seo_values['meta-robots-noindex'] ?? '', '1' ); ?>><?php esc_html_e( 'No', 'storesuite' ); ?></option>
								<option value="2" <?php selected( $seo_values['meta-robots-noindex'] ?? '', '2' ); ?>><?php esc_html_e( 'Yes', 'storesuite' ); ?></option>
							</select>
						</div>
					</div>
					<div class="col-md-12">
						<div class="storesuite-form-group" role="radiogroup" aria-labelledby="storesuite-seo-nofollow-label">
							<span class="storesuite-seo-image-label" id="storesuite-seo-nofollow-label"><?php esc_html_e( 'Should search engines follow links on this product?', 'storesuite' ); ?></span>
							<div class="storesuite-seo-radios">
								<label><input type="radio" name="<?php echo esc_attr( $field_prefix . 'meta-robots-nofollow' ); ?>" value="0" <?php checked( $seo_values['meta-robots-nofollow'] ?? '', '' ); ?>> <?php esc_html_e( 'Yes', 'storesuite' ); ?></label>
								<label><input type="radio" name="<?php echo esc_attr( $field_prefix . 'meta-robots-nofollow' ); ?>" value="1" <?php checked( $seo_values['meta-robots-nofollow'] ?? '', '1' ); ?>> <?php esc_html_e( 'No', 'storesuite' ); ?></label>
							</div>
						</div>
					</div>
					<div class="col-md-12">
						<div class="storesuite-form-group">
							<label for="<?php echo esc_attr( $field_prefix . 'meta-robots-adv' ); ?>"><?php esc_html_e( 'Meta robots advanced', 'storesuite' ); ?></label>
							<?php $storesuite_seo_robots_adv = array_filter( explode( ',', $seo_values['meta-robots-adv'] ?? '' ) ); ?>
							<select class="storesuite-form-control storesuite-select2" id="<?php echo esc_attr( $field_prefix . 'meta-robots-adv' ); ?>" name="<?php echo esc_attr( $field_prefix . 'meta-robots-adv' ); ?>[]" data-placeholder="<?php esc_attr_e( 'None', 'storesuite' ); ?>" multiple>
								<?php foreach ( $robots_adv_choices as $storesuite_seo_directive => $storesuite_seo_directive_label ) : ?>
									<option value="<?php echo esc_attr( $storesuite_seo_directive ); ?>" <?php selected( in_array( $storesuite_seo_directive, $storesuite_seo_robots_adv, true ) ); ?>><?php echo esc_html( $storesuite_seo_directive_label ); ?></option>
								<?php endforeach; ?>
							</select>
							<small class="storesuite-form-text"><?php esc_html_e( 'Extra instructions for search engines: keep this product’s images out of image search, stop cached copies, or hide the text snippet.', 'storesuite' ); ?></small>
						</div>
					</div>
					<div class="col-md-12">
						<div class="storesuite-form-group">
							<label for="<?php echo esc_attr( $field_prefix . 'bctitle' ); ?>"><?php esc_html_e( 'Breadcrumbs Title', 'storesuite' ); ?></label>
							<input type="text" class="storesuite-form-control" id="<?php echo esc_attr( $field_prefix . 'bctitle' ); ?>" name="<?php echo esc_attr( $field_prefix . 'bctitle' ); ?>" value="<?php echo esc_attr( $seo_values['bctitle'] ?? '' ); ?>">
							<small class="storesuite-form-text"><?php esc_html_e( 'Title to use for this product in breadcrumb paths. Leave blank to use the product title.', 'storesuite' ); ?></small>
						</div>
					</div>
					<div class="col-md-12">
						<div class="storesuite-form-group">
							<label for="<?php echo esc_attr( $field_prefix . 'canonical' ); ?>"><?php esc_html_e( 'Canonical URL', 'storesuite' ); ?></label>
							<input type="text" inputmode="url" class="storesuite-form-control" id="<?php echo esc_attr( $field_prefix . 'canonical' ); ?>" name="<?php echo esc_attr( $field_prefix . 'canonical' ); ?>" value="<?php echo esc_attr( $seo_values['canonical'] ?? '' ); ?>" placeholder="https://">
							<small class="storesuite-form-text"><?php esc_html_e( 'Only set this when the same product lives at another URL that search engines should treat as the original. Leave blank to use this product’s own permalink.', 'storesuite' ); ?></small>
						</div>
					</div>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>
