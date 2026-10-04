<?php
/**
 * StoreSuite product form: one card per ACF field group.
 *
 * Rendered after the "Others" card via `storesuite_product_form_after_others`.
 *
 * @var array                                                 $group        ACF field group array.
 * @var array                                                 $fields       ACF field arrays.
 * @var int                                                   $product_id   Product ID, 0 on the add form.
 * @var bool                                                  $is_edit_mode Whether the edit form is rendered.
 * @var \PluginizeLab\StoreSuite\Integration\Acf\FieldRenderer $renderer     Field renderer.
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="storesuite-card storesuite-card-with-header storesuite-mb-24 storesuite-acf-field-group" data-group-key="<?php echo esc_attr( $group['key'] ); ?>">
	<h3 class="storesuite-card-title"><?php echo esc_html( $group['title'] ); ?></h3>
	<div class="storesuite-card-content">
		<div class="row">
			<?php
			foreach ( $fields as $field ) {
				$renderer->render_field( $field, $product_id, $is_edit_mode );
			}
			?>
		</div>
	</div>
</div>
