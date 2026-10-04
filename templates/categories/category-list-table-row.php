<?php
/**
 * StoreSuite category list table row
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<tr class="storesuite-list-row" id="category-row-<?php echo esc_attr( $category->term_id ); ?>">
	<td class="check-column">
		<?php storesuite_get_template_part( 'shared/list-bulk-checkbox', '', array( 'value' => $category->term_id ) ); ?>
	</td>
	<td<?php storesuite_list_column_attrs( 'categories', 'image' ); ?> data-title="<?php esc_attr_e( 'Image', 'storesuite' ); ?>">
		<?php
		$thumbnail_id = absint( get_term_meta( $category->term_id, 'thumbnail_id', true ) );
		if ( $thumbnail_id ) {
			$image_url = wp_get_attachment_image_url( $thumbnail_id, 'thumbnail' );
			if ( $image_url ) {
				?>
				<img src="<?php echo esc_url( $image_url ); ?>" class="my-storesuite-thumb" alt="<?php echo esc_attr( $category->name ); ?>">
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
	<td<?php storesuite_list_column_attrs( 'categories', 'name' ); ?> data-title="<?php esc_attr_e( 'Name', 'storesuite' ); ?>">
		<a href="<?php echo esc_url( sprintf( storesuite_get_navigation_url( 'edit-category' ) . '%s', $category->term_id ) ); ?>">
			<?php echo esc_html( $dash_prefix . $category->name ); ?>
		</a>
	</td>
	<td<?php storesuite_list_column_attrs( 'categories', 'description' ); ?> data-title="<?php esc_attr_e( 'Description', 'storesuite' ); ?>"><?php echo esc_html( wp_trim_words( $category->description, '9', '...' ) ); ?></td>
	<td<?php storesuite_list_column_attrs( 'categories', 'parent' ); ?> data-title="<?php esc_attr_e( 'Parent', 'storesuite' ); ?>"><?php echo esc_html( $parent ? $parent->name : '-' ); ?></td>
	<td<?php storesuite_list_column_attrs( 'categories', 'slug' ); ?> data-title="<?php esc_attr_e( 'Slug', 'storesuite' ); ?>"><?php echo esc_html( $category->slug ); ?></td>
	<td<?php storesuite_list_column_attrs( 'categories', 'count' ); ?> data-title="<?php esc_attr_e( 'Count', 'storesuite' ); ?>"><?php echo esc_html( $category->count ); ?></td>
	<td class="text-right" data-title="<?php esc_attr_e( 'Actions', 'storesuite' ); ?>">
		<div class="storesuite-dropdown">
			<span class="storesuite-dropdown-icon">
				<svg width="20" height="20" fill="currentColor" aria-hidden="true" focusable="false"><use href="#storesuite-icon-three-dots"></use></svg>
			</span>
			<ul class="storesuite-dropdown-menu">
				<li>
					<a href="<?php echo esc_url( get_category_link( $category->term_id ) ); ?>" class="dropdown-link">
						<?php echo esc_html__( 'View', 'storesuite' ); ?>
					</a>
				</li>
				<li>
					<a href="<?php echo esc_url( sprintf( storesuite_get_navigation_url( 'edit-category' ) . '%s', $category->term_id ) ); ?>" class="dropdown-link"><?php echo esc_html__( 'Edit', 'storesuite' ); ?></a>
				</li>
				<li>
					<button type="button" class="inline-button dropdown-link storesuite-item-quick-edit" data-object-type="category" data-id="<?php echo esc_attr( $category->term_id ); ?>"><?php echo esc_html__( 'Quick edit', 'storesuite' ); ?></button>
				</li>
				<li>
					<button type="button" class="inline-button dropdown-link storesuite-delete-category" data-category-id="<?php echo esc_attr( $category->term_id ); ?>">
						<?php echo esc_html__( 'Delete', 'storesuite' ); ?>
					</button>
				</li>
			</ul>
		</div>
	</td>
</tr>