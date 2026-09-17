<?php
/**
 * "Columns" toolbar dropdown: show/hide list table columns for the current user.
 *
 * @var string $table List table key registered in ColumnManager.
 *
 * @package StoreSuite
 */

use PluginizeLab\StoreSuite\ListTable\ColumnManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$storesuite_cm_table   = isset( $table ) ? sanitize_key( $table ) : '';
$storesuite_cm_columns = $storesuite_cm_table ? ColumnManager::get_columns( $storesuite_cm_table ) : array();

if ( empty( $storesuite_cm_columns ) ) {
	return;
}

$storesuite_cm_hidden = ColumnManager::get_hidden( $storesuite_cm_table );
?>
<div class="storesuite-dropdown storesuite-column-manager" data-table="<?php echo esc_attr( $storesuite_cm_table ); ?>">
	<button type="button" class="my-storesuite-button storesuite-column-manager-toggle storesuite-dropdown-icon" aria-haspopup="true" aria-expanded="false" aria-label="<?php esc_attr_e( 'Columns', 'storesuite' ); ?>" title="<?php esc_attr_e( 'Choose which columns to show', 'storesuite' ); ?>">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="currentColor" aria-hidden="true" focusable="false">
			<path d="M19,0H5C2.243,0,0,2.243,0,5v14c0,2.757,2.243,5,5,5h14c2.757,0,5-2.243,5-5V5c0-2.757-2.243-5-5-5Zm3,19c0,1.654-1.346,3-3,3h-3V2h3c1.654,0,3,1.346,3,3v14ZM2,19V5c0-1.654,1.346-3,3-3h3V22h-3c-1.654,0-3-1.346-3-3Zm8,3V2h4V22h-4Z"/>
		</svg>
		<span class="storesuite-button-label"><?php esc_html_e( 'Columns', 'storesuite' ); ?></span>
		<?php if ( ! empty( $storesuite_cm_hidden ) ) : ?>
			<span class="storesuite-filter-count storesuite-column-manager-count"><?php echo esc_html( (string) count( $storesuite_cm_hidden ) ); ?></span>
		<?php endif; ?>
	</button>
	<div class="storesuite-dropdown-menu storesuite-column-manager-menu" role="group" aria-label="<?php esc_attr_e( 'Visible columns', 'storesuite' ); ?>">
		<span class="storesuite-column-manager-heading"><?php esc_html_e( 'Show columns', 'storesuite' ); ?></span>
		<?php foreach ( $storesuite_cm_columns as $storesuite_cm_key => $storesuite_cm_column ) : ?>
			<?php
			$storesuite_cm_locked  = ! empty( $storesuite_cm_column['locked'] );
			$storesuite_cm_checked = ! in_array( $storesuite_cm_key, $storesuite_cm_hidden, true );
			?>
			<label class="storesuite-column-manager-option<?php echo $storesuite_cm_locked ? ' is-locked' : ''; ?>">
				<span class="my-storesuite-checkbox">
					<input type="checkbox" class="my-storesuite-checkbox-input" name="storesuite_columns[]" value="<?php echo esc_attr( $storesuite_cm_key ); ?>" <?php checked( $storesuite_cm_checked ); ?> <?php disabled( $storesuite_cm_locked ); ?> />
					<span class="my-storesuite-checkbox-back"></span>
					<span class="my-storesuite-tick">
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true" focusable="false">
							<path d="M10.97 4.97a.75.75 0 0 1 1.07 1.05l-3.99 4.99a.75.75 0 0 1-1.08.02L4.324 8.384a.75.75 0 1 1 1.06-1.06l2.094 2.093 3.473-4.425z"/>
						</svg>
					</span>
				</span>
				<span class="storesuite-column-manager-label"><?php echo esc_html( $storesuite_cm_column['label'] ); ?></span>
			</label>
		<?php endforeach; ?>
		<button type="button" class="inline-button storesuite-column-manager-reset"><?php esc_html_e( 'Show all columns', 'storesuite' ); ?></button>
	</div>
</div>
