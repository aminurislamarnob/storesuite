<?php
/**
 * StoreSuite product import: done summary.
 *
 * WooCommerce's admin classes (`wc-progress-form-content`, `woocommerce-importer`) are intentionally
 * dropped here — nothing binds to them on this step, and admin.css would restyle the card. The result
 * counts are rendered as stat tiles and the failure log as a standard dashboard table.
 *
 * @package StoreSuite
 *
 * @var int   $imported            Number of products created.
 * @var int   $imported_variations Number of product variations created.
 * @var int   $updated             Number of products updated.
 * @var int   $failed              Number of products that failed.
 * @var int   $skipped             Number of products skipped.
 * @var array $errors              WP_Error objects for failed/skipped rows.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$imported            = isset( $imported ) ? (int) $imported : 0;
$imported_variations = isset( $imported_variations ) ? (int) $imported_variations : 0;
$updated             = isset( $updated ) ? (int) $updated : 0;
$failed              = isset( $failed ) ? (int) $failed : 0;
$skipped             = isset( $skipped ) ? (int) $skipped : 0;
// Renamed from $errors to avoid overriding the WordPress global of the same name.
$storesuite_errors = isset( $errors ) ? (array) $errors : array();

// Only the counts that actually happened get a tile, so a clean import stays uncluttered.
$storesuite_stats = array(
	array(
		'value' => $imported,
		'label' => _n( 'Product imported', 'Products imported', $imported, 'storesuite' ),
		'tone'  => 'success',
	),
	array(
		'value' => $imported_variations,
		'label' => _n( 'Variation imported', 'Variations imported', $imported_variations, 'storesuite' ),
		'tone'  => 'success',
	),
	array(
		'value' => $updated,
		'label' => _n( 'Product updated', 'Products updated', $updated, 'storesuite' ),
		'tone'  => 'info',
	),
	array(
		'value' => $skipped,
		'label' => _n( 'Product skipped', 'Products skipped', $skipped, 'storesuite' ),
		'tone'  => 'warning',
	),
	array(
		'value' => $failed,
		'label' => _n( 'Product failed', 'Products failed', $failed, 'storesuite' ),
		'tone'  => 'danger',
	),
);

$storesuite_stats = array_filter(
	$storesuite_stats,
	static function ( $stat ) {
		return 0 < $stat['value'];
	}
);

$storesuite_has_log = ( 0 < $failed || 0 < $skipped ) && ! empty( $storesuite_errors );
$storesuite_clean   = 0 === $failed && 0 === $skipped;
?>
<div class="storesuite-card storesuite-import-card storesuite-import-done">
	<span class="storesuite-import-done__icon <?php echo $storesuite_clean ? '' : 'is-warning'; ?>" aria-hidden="true">
		<?php if ( $storesuite_clean ) : ?>
			<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" viewBox="0 0 16 16" focusable="false"><path d="M12.736 3.97a.733.733 0 0 1 1.047 0c.286.289.29.756.01 1.05L7.88 12.01a.733.733 0 0 1-1.065.02L3.217 8.384a.757.757 0 0 1 0-1.06.733.733 0 0 1 1.047 0l3.052 3.093 5.4-6.425z"/></svg>
		<?php else : ?>
			<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" viewBox="0 0 16 16" focusable="false"><path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5m.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2"/></svg>
		<?php endif; ?>
	</span>

	<h2 class="storesuite-import-done__title"><?php esc_html_e( 'Import complete!', 'storesuite' ); ?></h2>
	<p class="storesuite-import-done__lead">
		<?php if ( $storesuite_clean ) : ?>
			<?php esc_html_e( 'Your CSV file has been imported. Your products are ready to review.', 'storesuite' ); ?>
		<?php else : ?>
			<?php esc_html_e( 'Your CSV file has been processed, but some rows need your attention.', 'storesuite' ); ?>
		<?php endif; ?>
	</p>

	<?php if ( $storesuite_stats ) : ?>
		<ul class="storesuite-import-done__stats">
			<?php foreach ( $storesuite_stats as $storesuite_stat ) : ?>
				<li class="storesuite-import-done__stat storesuite-import-done__stat--<?php echo esc_attr( $storesuite_stat['tone'] ); ?>">
					<span class="storesuite-import-done__stat-value"><?php echo esc_html( number_format_i18n( $storesuite_stat['value'] ) ); ?></span>
					<span class="storesuite-import-done__stat-label"><?php echo esc_html( $storesuite_stat['label'] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<div class="storesuite-import-done__actions">
		<a class="my-storesuite-button storesuite-import-btn" href="<?php echo esc_url( storesuite_get_navigation_url( 'products' ) ); ?>"><?php esc_html_e( 'View products', 'storesuite' ); ?></a>
		<a class="my-storesuite-button my-storesuite-button-light storesuite-import-btn" href="<?php echo esc_url( storesuite_get_navigation_url( 'import-products' ) ); ?>"><?php esc_html_e( 'Import another file', 'storesuite' ); ?></a>
		<?php if ( $storesuite_has_log ) : ?>
			<button type="button" class="my-storesuite-button my-storesuite-button-light storesuite-import-btn storesuite-import-done__log-toggle" aria-expanded="false" aria-controls="storesuite-import-error-log">
				<?php esc_html_e( 'View import log', 'storesuite' ); ?>
			</button>
		<?php endif; ?>
	</div>

	<?php if ( $storesuite_has_log ) : ?>
		<div class="storesuite-import-done__log" id="storesuite-import-error-log" hidden>
			<div class="storesuite-table-responsive">
				<table class="my-storesuite-tbl">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Row', 'storesuite' ); ?></th>
							<th><?php esc_html_e( 'Reason for failure', 'storesuite' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $storesuite_errors as $storesuite_error ) {
							if ( ! is_wp_error( $storesuite_error ) ) {
								continue;
							}
							$storesuite_error_data = $storesuite_error->get_error_data();
							?>
							<tr>
								<td><code><?php echo esc_html( isset( $storesuite_error_data['row'] ) ? $storesuite_error_data['row'] : '' ); ?></code></td>
								<td><?php echo esc_html( $storesuite_error->get_error_message() ); ?></td>
							</tr>
							<?php
						}
						?>
					</tbody>
				</table>
			</div>
		</div>
	<?php endif; ?>
</div>
<?php if ( $storesuite_has_log ) : ?>
	<script type="text/javascript">
		( function () {
			var toggle = document.querySelector( '.storesuite-import-done__log-toggle' );
			var log    = document.getElementById( 'storesuite-import-error-log' );
			if ( ! toggle || ! log ) {
				return;
			}
			toggle.addEventListener( 'click', function () {
				var expanded = 'true' === toggle.getAttribute( 'aria-expanded' );
				toggle.setAttribute( 'aria-expanded', expanded ? 'false' : 'true' );
				log.hidden = expanded;
			} );
		} )();
	</script>
<?php endif; ?>
