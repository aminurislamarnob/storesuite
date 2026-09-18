<?php
/**
 * StoreSuite product import — column mapping step.
 *
 * Redesigned mapping screen: progress card, search + status filter, and columns grouped by field
 * category. Reuses WooCommerce's form field names (map_from, map_to, file, delimiter, update_existing,
 * character_encoding) so the inherited import step keeps working.
 *
 * @var array  $groups             Category groups, each { label, columns[] }. A column is
 *                                 { index, name, sample, mapped_value, options }.
 * @var int    $total_columns      Total number of CSV columns.
 * @var int    $mapped_count       Columns auto-mapped to a field (initial mapped count).
 * @var string $next_step_url      Form action (import step).
 * @var string $back_url           Upload step URL.
 * @var string $file               Uploaded file path.
 * @var string $delimiter          CSV delimiter.
 * @var bool   $update_existing    Whether existing products are updated.
 * @var string $character_encoding File character encoding.
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ignored_count = max( 0, (int) $total_columns - (int) $mapped_count );
$progress_pct  = $total_columns > 0 ? round( ( $mapped_count / $total_columns ) * 100 ) : 0;

/**
 * Render the option list for a mapping select.
 *
 * @param array  $options      Mapping options (flat values or optgroup arrays).
 * @param string $mapped_value Currently selected value.
 */
$storesuite_render_map_options = static function ( $options, $mapped_value ) {
	foreach ( $options as $key => $value ) {
		if ( is_array( $value ) ) {
			echo '<optgroup label="' . esc_attr( $value['name'] ) . '">';
			foreach ( $value['options'] as $sub_key => $sub_value ) {
				echo '<option value="' . esc_attr( $sub_key ) . '" ' . selected( $mapped_value, $sub_key, false ) . '>' . esc_html( $sub_value ) . '</option>';
			}
			echo '</optgroup>';
		} else {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $mapped_value, $key, false ) . '>' . esc_html( $value ) . '</option>';
		}
	}
};
?>
<form class="storesuite-import-mapping" method="post" action="<?php echo esc_url( $next_step_url ); ?>">
	<div class="storesuite-card storesuite-import-card storesuite-mb-24">
		<div class="storesuite-import-mapping__head">
			<div class="storesuite-import-mapping__intro">
				<h2 class="storesuite-import-card__title"><?php esc_html_e( 'Map CSV fields to products', 'storesuite' ); ?></h2>
				<p class="storesuite-import-card__lead"><?php esc_html_e( 'Match each column in your file to a product field. Unrecognized columns can be imported as meta data or ignored.', 'storesuite' ); ?></p>
			</div>
			<div class="storesuite-import-progress" data-import-progress>
				<div class="storesuite-import-progress__top">
					<span class="storesuite-import-progress__label"><?php esc_html_e( 'Mapping progress', 'storesuite' ); ?></span>
					<span class="storesuite-import-progress__count">
						<strong data-mapped-count><?php echo esc_html( $mapped_count ); ?></strong>
						/ <span data-total-count><?php echo esc_html( $total_columns ); ?></span> <?php esc_html_e( 'mapped', 'storesuite' ); ?>
					</span>
				</div>
				<div class="storesuite-import-progress__bar">
					<span class="storesuite-import-progress__fill" data-progress-fill style="width: <?php echo esc_attr( $progress_pct ); ?>%"></span>
				</div>
				<div class="storesuite-import-progress__legend">
					<span class="storesuite-import-progress__legend-item storesuite-import-progress__legend-item--mapped">
						<i aria-hidden="true"></i> <strong data-mapped-count><?php echo esc_html( $mapped_count ); ?></strong> <?php esc_html_e( 'mapped', 'storesuite' ); ?>
					</span>
					<span class="storesuite-import-progress__legend-item storesuite-import-progress__legend-item--ignored">
						<i aria-hidden="true"></i> <strong data-ignored-count><?php echo esc_html( $ignored_count ); ?></strong> <?php esc_html_e( 'ignored', 'storesuite' ); ?>
					</span>
				</div>
			</div>
		</div>

		<div class="storesuite-import-mapping__toolbar">
			<div class="storesuite-table-search-input storesuite-import-mapping__search">
				<div class="storesuite-table-search-icon" aria-hidden="true">
					<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" focusable="false"><path d="M23.707,22.293l-5.969-5.969a10.016,10.016,0,1,0-1.414,1.414l5.969,5.969a1,1,0,0,0,1.414-1.414ZM10,18a8,8,0,1,1,8-8A8.009,8.009,0,0,1,10,18Z"></path></svg>
				</div>
				<input type="text" data-map-search placeholder="<?php esc_attr_e( 'Search columns…', 'storesuite' ); ?>" aria-label="<?php esc_attr_e( 'Search columns', 'storesuite' ); ?>" />
			</div>
			<div class="storesuite-import-segmented" role="group" aria-label="<?php esc_attr_e( 'Filter columns by status', 'storesuite' ); ?>">
				<button type="button" class="is-active" data-map-filter="all"><?php esc_html_e( 'All', 'storesuite' ); ?></button>
				<button type="button" data-map-filter="mapped"><?php esc_html_e( 'Mapped', 'storesuite' ); ?></button>
				<button type="button" data-map-filter="ignored"><?php esc_html_e( 'Ignored', 'storesuite' ); ?></button>
			</div>
		</div>

		<div class="storesuite-import-mapping__table">
			<div class="storesuite-import-mapping__thead">
				<span><?php esc_html_e( 'Column in your file', 'storesuite' ); ?></span>
				<span><?php esc_html_e( 'Maps to product field', 'storesuite' ); ?></span>
			</div>

			<?php foreach ( $groups as $group ) : ?>
				<div class="storesuite-import-mapping__group" data-map-group>
					<div class="storesuite-import-mapping__group-head">
						<span class="storesuite-import-mapping__group-title"><?php echo esc_html( $group['label'] ); ?></span>
						<span class="storesuite-import-mapping__group-count" data-group-count><?php echo esc_html( count( $group['columns'] ) ); ?></span>
					</div>

					<?php foreach ( $group['columns'] as $column ) : ?>
						<?php $is_mapped = '' !== (string) $column['mapped_value']; ?>
						<div class="storesuite-import-mapping__row" data-map-row data-name="<?php echo esc_attr( strtolower( $column['name'] ) ); ?>" data-mapped="<?php echo $is_mapped ? '1' : '0'; ?>">
							<div class="storesuite-import-mapping__col">
								<span class="storesuite-import-mapping__dot<?php echo $is_mapped ? ' is-mapped' : ''; ?>" data-map-dot aria-hidden="true"></span>
								<span class="storesuite-import-mapping__col-text">
									<span class="storesuite-import-mapping__col-name"><?php echo esc_html( $column['name'] ); ?></span>
									<?php if ( '' !== (string) $column['sample'] ) : ?>
										<span class="storesuite-import-mapping__sample"><?php esc_html_e( 'Sample:', 'storesuite' ); ?> <code><?php echo esc_html( $column['sample'] ); ?></code></span>
									<?php endif; ?>
								</span>
							</div>
							<div class="storesuite-import-mapping__arrow" aria-hidden="true">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
							</div>
							<div class="storesuite-import-mapping__field">
								<input type="hidden" name="map_from[<?php echo esc_attr( $column['index'] ); ?>]" value="<?php echo esc_attr( $column['name'] ); ?>" />
								<label class="screen-reader-text" for="storesuite-map-to-<?php echo esc_attr( $column['index'] ); ?>"><?php echo esc_html( $column['name'] ); ?></label>
								<select class="storesuite-form-control" id="storesuite-map-to-<?php echo esc_attr( $column['index'] ); ?>" name="map_to[<?php echo esc_attr( $column['index'] ); ?>]" data-map-select>
									<option value=""><?php esc_html_e( 'Do not import', 'storesuite' ); ?></option>
									<?php $storesuite_render_map_options( $column['options'], $column['mapped_value'] ); ?>
								</select>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>

			<div class="storesuite-import-mapping__empty" data-map-empty hidden><?php esc_html_e( 'No columns match your search.', 'storesuite' ); ?></div>
		</div>
	</div>

	<div class="storesuite-import-mapping__actions">
		<a href="<?php echo esc_url( $back_url ); ?>" class="my-storesuite-button my-storesuite-button-light storesuite-import-btn storesuite-import-btn--back">
			<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8"/></svg>
			<?php esc_html_e( 'Back', 'storesuite' ); ?>
		</a>
		<button type="submit" class="my-storesuite-button storesuite-import-btn storesuite-import-btn--run" name="save_step" value="<?php esc_attr_e( 'Run the importer', 'storesuite' ); ?>">
			<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M10.804 8 5 4.633v6.734zm.792-.696a.802.802 0 0 1 0 1.392l-6.363 3.692C4.713 12.69 4 12.345 4 11.692V4.308c0-.653.713-.998 1.233-.696z"/></svg>
			<?php esc_html_e( 'Run the importer', 'storesuite' ); ?>
		</button>
		<input type="hidden" name="file" value="<?php echo esc_attr( $file ); ?>" />
		<input type="hidden" name="delimiter" value="<?php echo esc_attr( $delimiter ); ?>" />
		<input type="hidden" name="update_existing" value="<?php echo (int) $update_existing; ?>" />
		<?php if ( $character_encoding ) : ?>
			<input type="hidden" name="character_encoding" value="<?php echo esc_attr( $character_encoding ); ?>" />
		<?php endif; ?>
		<?php wp_nonce_field( 'woocommerce-csv-importer' ); ?>
	</div>
</form>
<script type="text/javascript">
	( function () {
		var form = document.currentScript.previousElementSibling;
		if ( ! form || ! form.classList.contains( 'storesuite-import-mapping' ) ) {
			return;
		}

		var rows      = Array.prototype.slice.call( form.querySelectorAll( '[data-map-row]' ) );
		var groups    = Array.prototype.slice.call( form.querySelectorAll( '[data-map-group]' ) );
		var searchEl  = form.querySelector( '[data-map-search]' );
		var filterBtns = Array.prototype.slice.call( form.querySelectorAll( '[data-map-filter]' ) );
		var emptyEl   = form.querySelector( '[data-map-empty]' );
		var fillEl    = form.querySelector( '[data-progress-fill]' );
		var mappedEls = Array.prototype.slice.call( form.querySelectorAll( '[data-mapped-count]' ) );
		var ignoredEls = Array.prototype.slice.call( form.querySelectorAll( '[data-ignored-count]' ) );

		var activeFilter = 'all';

		function recomputeProgress() {
			var total = rows.length;
			var mapped = rows.filter( function ( r ) { return r.getAttribute( 'data-mapped' ) === '1'; } ).length;
			var ignored = total - mapped;
			mappedEls.forEach( function ( el ) { el.textContent = mapped; } );
			ignoredEls.forEach( function ( el ) { el.textContent = ignored; } );
			if ( fillEl ) {
				fillEl.style.width = ( total > 0 ? Math.round( ( mapped / total ) * 100 ) : 0 ) + '%';
			}
		}

		function applyFilters() {
			var term = ( searchEl && searchEl.value ? searchEl.value : '' ).trim().toLowerCase();
			var anyVisible = false;

			rows.forEach( function ( row ) {
				var isMapped = row.getAttribute( 'data-mapped' ) === '1';
				var matchesFilter = 'all' === activeFilter || ( 'mapped' === activeFilter && isMapped ) || ( 'ignored' === activeFilter && ! isMapped );
				var matchesSearch = '' === term || ( row.getAttribute( 'data-name' ) || '' ).indexOf( term ) !== -1;
				var visible = matchesFilter && matchesSearch;
				row.hidden = ! visible;
				if ( visible ) {
					anyVisible = true;
				}
			} );

			groups.forEach( function ( group ) {
				var visibleRows = Array.prototype.slice.call( group.querySelectorAll( '[data-map-row]' ) ).filter( function ( r ) { return ! r.hidden; } );
				group.hidden = 0 === visibleRows.length;
				var countEl = group.querySelector( '[data-group-count]' );
				if ( countEl ) {
					countEl.textContent = visibleRows.length;
				}
			} );

			if ( emptyEl ) {
				emptyEl.hidden = anyVisible;
			}
		}

		form.addEventListener( 'change', function ( e ) {
			if ( e.target && e.target.matches( '[data-map-select]' ) ) {
				var row = e.target.closest( '[data-map-row]' );
				if ( row ) {
					var mapped = '' !== e.target.value;
					row.setAttribute( 'data-mapped', mapped ? '1' : '0' );
					var dot = row.querySelector( '[data-map-dot]' );
					if ( dot ) {
						dot.classList.toggle( 'is-mapped', mapped );
					}
				}
				recomputeProgress();
				applyFilters();
			}
		} );

		if ( searchEl ) {
			searchEl.addEventListener( 'input', applyFilters );
		}

		filterBtns.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				activeFilter = btn.getAttribute( 'data-map-filter' );
				filterBtns.forEach( function ( b ) { b.classList.toggle( 'is-active', b === btn ); } );
				applyFilters();
			} );
		} );

		recomputeProgress();
		applyFilters();
	} )();
</script>
