<?php
/**
 * StoreSuite brand list table row
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<tr class="storesuite-list-row" id="brand-row-<?php echo esc_attr( $brand->term_id ); ?>">
	<td class="check-column">
		<?php storesuite_get_template_part( 'shared/list-bulk-checkbox', '', array( 'value' => $brand->term_id ) ); ?>
	</td>
	<td<?php storesuite_list_column_attrs( 'brands', 'image' ); ?> class="brand-image" data-title="<?php esc_attr_e( 'Image', 'storesuite' ); ?>">
		<?php
		$thumbnail_id = absint( get_term_meta( $brand->term_id, 'thumbnail_id', true ) );
		if ( $thumbnail_id ) {
			$image_url = wp_get_attachment_image_url( $thumbnail_id, 'thumbnail' );
			if ( $image_url ) {
				?>
				<img src="<?php echo esc_url( $image_url ); ?>" class="my-storesuite-thumb" alt="<?php echo esc_attr( $brand->name ); ?>">
				<?php
			} else {
				?>
				<img src="<?php echo esc_url( wc_placeholder_img_src( 'thumbnail' ) ); ?>" class="my-storesuite-thumb" alt="<?php esc_attr_e( 'Placeholder', 'storesuite' ); ?>">
				<?php
			}
		} else {
			?>
			<img src="<?php echo esc_url( wc_placeholder_img_src( 'thumbnail' ) ); ?>" class="my-storesuite-thumb" alt="<?php esc_attr_e( 'Placeholder', 'storesuite' ); ?>">
			<?php
		}
		?>
	</td>
	<td<?php storesuite_list_column_attrs( 'brands', 'name' ); ?> class="brand-name" data-title="<?php esc_attr_e( 'Name', 'storesuite' ); ?>">
		<?php echo wp_kses_post( $dash_prefix ); ?><a href="<?php echo esc_url( sprintf( storesuite_get_navigation_url( 'edit-brand' ) . '%s', $brand->term_id ) ); ?>"><?php echo esc_html( $brand->name ); ?></a>
	</td>
	<td<?php storesuite_list_column_attrs( 'brands', 'description' ); ?> class="brand-description" data-title="<?php esc_attr_e( 'Description', 'storesuite' ); ?>">
		<?php echo esc_html( wp_trim_words( $brand->description, 10, '...' ) ); ?>
	</td>
	<td<?php storesuite_list_column_attrs( 'brands', 'parent' ); ?> class="brand-parent" data-title="<?php esc_attr_e( 'Parent', 'storesuite' ); ?>">
		<?php echo esc_html( $parent ? $parent->name : '-' ); ?>
	</td>
	<td<?php storesuite_list_column_attrs( 'brands', 'slug' ); ?> class="brand-slug" data-title="<?php esc_attr_e( 'Slug', 'storesuite' ); ?>">
		<?php echo esc_html( $brand->slug ); ?>
	</td>
	<td<?php storesuite_list_column_attrs( 'brands', 'count' ); ?> class="brand-count" data-title="<?php esc_attr_e( 'Count', 'storesuite' ); ?>">
		<?php echo esc_html( $brand->count ); ?>
	</td>
	<td class="text-right" data-title="<?php esc_attr_e( 'Actions', 'storesuite' ); ?>">
		<div class="storesuite-dropdown">
			<span class="storesuite-dropdown-icon">
				<svg width="20" height="20" fill="currentColor" aria-hidden="true" focusable="false"><use href="#storesuite-icon-three-dots"></use></svg>
			</span>
			<ul class="storesuite-dropdown-menu">
				<li>
					<a href="<?php echo esc_url( get_category_link( $brand->term_id ) ); ?>" class="dropdown-link">
						<?php echo esc_html__( 'View', 'storesuite' ); ?>
					</a>
				</li>
				<li>
					<a href="<?php echo esc_url( sprintf( storesuite_get_navigation_url( 'edit-brand' ) . '%s', $brand->term_id ) ); ?>" class="dropdown-link"><?php echo esc_html__( 'Edit', 'storesuite' ); ?></a>
				</li>
				<li>
					<button type="button" class="inline-button dropdown-link storesuite-item-quick-edit" data-object-type="brand" data-id="<?php echo esc_attr( $brand->term_id ); ?>"><?php echo esc_html__( 'Quick edit', 'storesuite' ); ?></button>
				</li>
				<li>
					<button type="button" class="inline-button dropdown-link storesuite-delete-brand" data-brand-id="<?php echo esc_attr( $brand->term_id ); ?>">
						<?php echo esc_html__( 'Delete', 'storesuite' ); ?>
					</button>
				</li>
			</ul>
		</div>
	</td>
</tr> 