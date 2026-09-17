<?php
/**
 * Edit history card on the edit-product form.
 *
 * Args from EditHistoryController: $product_id, $rows, $manager.
 *
 * @package StoreSuite
 */

use PluginizeLab\StoreSuite\EditHistory\EditHistoryManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="storesuite-card storesuite-card-with-header storesuite-mb-24 storesuite-product-history-card">
	<h3 class="storesuite-card-title"><?php esc_html_e( 'Edit History', 'storesuite' ); ?></h3>
	<div class="storesuite-card-content">
		<?php if ( empty( $rows ) ) : ?>
			<p class="storesuite-text-muted"><?php esc_html_e( 'No inline or bulk edits have been recorded for this product yet.', 'storesuite' ); ?></p>
		<?php else : ?>
			<ul class="storesuite-history-items storesuite-product-history-items">
				<?php foreach ( $rows as $storesuite_row ) : ?>
					<?php $storesuite_user = get_userdata( (int) $storesuite_row->user_id ); ?>
					<li class="<?php echo ! empty( $storesuite_row->undone_at ) ? 'is-undone' : ''; ?>">
						<span class="storesuite-history-field"><?php echo esc_html( EditHistoryManager::field_label( $storesuite_row->field ) ); ?>:</span>
						<span class="storesuite-history-old"><?php echo esc_html( EditHistoryManager::format_value( 'product', $storesuite_row->field, $storesuite_row->old_value ) ); ?></span>
						<span class="storesuite-history-arrow" aria-hidden="true">→</span>
						<span class="storesuite-history-new"><?php echo esc_html( EditHistoryManager::format_value( 'product', $storesuite_row->field, $storesuite_row->new_value ) ); ?></span>
						<span class="storesuite-history-meta">
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: user display name, 2: human time diff, 3: source label. */
									__( '%1$s · %2$s ago · %3$s', 'storesuite' ),
									$storesuite_user ? $storesuite_user->display_name : __( 'Unknown', 'storesuite' ),
									human_time_diff( strtotime( $storesuite_row->created_at . ' UTC' ) ),
									EditHistoryManager::source_label( $storesuite_row->source )
								)
							);
							?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="storesuite-text-muted storesuite-product-history-link">
				<a href="<?php echo esc_url( storesuite_get_navigation_url( 'edit-history' ) ); ?>"><?php esc_html_e( 'Open the full edit history', 'storesuite' ); ?></a>
			</p>
		<?php endif; ?>
	</div>
</div>
