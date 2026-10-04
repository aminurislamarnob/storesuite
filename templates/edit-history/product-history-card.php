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

$storesuite_history_limit = 10;
$storesuite_history_rows  = array_slice( (array) $rows, 0, $storesuite_history_limit );
?>
<div class="storesuite-card storesuite-card-with-header storesuite-mb-24 storesuite-product-history-card">
	<h3 class="storesuite-card-title"><?php esc_html_e( 'Edit History', 'storesuite' ); ?></h3>
	<div class="storesuite-card-content">
		<?php if ( empty( $storesuite_history_rows ) ) : ?>
			<p class="storesuite-text-muted storesuite-mb-0"><?php esc_html_e( 'No inline or bulk edits have been recorded for this product yet.', 'storesuite' ); ?></p>
		<?php else : ?>
			<ul class="storesuite-history-entries">
				<?php foreach ( $storesuite_history_rows as $storesuite_row ) : ?>
					<?php $storesuite_user = get_userdata( (int) $storesuite_row->user_id ); ?>
					<li class="storesuite-history-entry<?php echo ! empty( $storesuite_row->undone_at ) ? ' is-undone' : ''; ?>">
						<div class="storesuite-history-entry-main">
							<span class="storesuite-history-field"><?php echo esc_html( EditHistoryManager::field_label( $storesuite_row->field ) ); ?></span>
							<span class="storesuite-history-diff">
								<span class="storesuite-history-old"><?php echo esc_html( EditHistoryManager::format_value( 'product', $storesuite_row->field, $storesuite_row->old_value ) ); ?></span>
								<span class="storesuite-history-arrow" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="currentColor" focusable="false"><path d="M23.12,9.91,19.25,6a1,1,0,0,0-1.42,0h0a1,1,0,0,0,0,1.41L21.39,11H1a1,1,0,0,0-1,1H0a1,1,0,0,0,1,1H21.45l-3.62,3.61a1,1,0,0,0,0,1.42h0a1,1,0,0,0,1.42,0l3.87-3.88A3,3,0,0,0,23.12,9.91Z"/></svg></span>
								<span class="storesuite-history-new"><?php echo esc_html( EditHistoryManager::format_value( 'product', $storesuite_row->field, $storesuite_row->new_value ) ); ?></span>
							</span>
							<?php if ( ! empty( $storesuite_row->undone_at ) ) : ?>
								<span class="storesuite-badge"><?php esc_html_e( 'Undone', 'storesuite' ); ?></span>
							<?php endif; ?>
						</div>
						<div class="storesuite-history-entry-meta">
							<span class="storesuite-badge storesuite-badge-<?php echo esc_attr( 'undo' === $storesuite_row->source ? 'warning' : 'info' ); ?>"><?php echo esc_html( EditHistoryManager::source_label( $storesuite_row->source ) ); ?></span>
							<span>
								<?php
								echo esc_html(
									sprintf(
										/* translators: 1: user display name, 2: human time diff. */
										__( '%1$s · %2$s ago', 'storesuite' ),
										$storesuite_user ? $storesuite_user->display_name : __( 'Unknown', 'storesuite' ),
										human_time_diff( strtotime( $storesuite_row->created_at . ' UTC' ) )
									)
								);
								?>
							</span>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
			<a class="storesuite-history-more" href="<?php echo esc_url( storesuite_get_navigation_url( 'edit-history' ) ); ?>">
				<?php esc_html_e( 'Open the full edit history', 'storesuite' ); ?>
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="currentColor" aria-hidden="true" focusable="false"><path d="M15.4,9.88,10.81,5.29a1,1,0,0,0-1.41,0,1,1,0,0,0,0,1.42L14,11.29a1,1,0,0,1,0,1.42L9.4,17.29a1,1,0,0,0,1.41,1.42l4.59-4.59A3,3,0,0,0,15.4,9.88Z"/></svg>
			</a>
		<?php endif; ?>
	</div>
</div>
