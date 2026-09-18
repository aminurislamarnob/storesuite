<?php
/**
 * StoreSuite product import — upload step form.
 *
 * Redesigned upload screen for the CSV import wizard, built on the StoreSuite dashboard design system
 * (cards, form controls, switches, buttons). Reuses WooCommerce's form field names (import,
 * update_existing, file_url, delimiter, character_encoding, map_preferences) so the inherited upload
 * handler keeps working.
 *
 * @var int    $bytes      Maximum upload size in bytes.
 * @var string $size       Human-readable maximum upload size.
 * @var array  $upload_dir Result of wp_upload_dir(); may carry an 'error'.
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$upload_error = ! empty( $upload_dir['error'] ) ? $upload_dir['error'] : '';
?>
<form class="storesuite-import-upload" enctype="multipart/form-data" method="post">
	<div class="row">
		<div class="col-md-8">
			<div class="storesuite-card storesuite-import-card storesuite-mb-24">
				<h2 class="storesuite-import-card__title"><?php esc_html_e( 'Import products from a CSV file', 'storesuite' ); ?></h2>
				<p class="storesuite-import-card__lead">
					<?php
					echo wp_kses_post(
						__( 'This tool imports or merges product data into your store from a <strong>CSV</strong> or <strong>TXT</strong> file. Drop a file below to get started.', 'storesuite' )
					);
					?>
				</p>

				<?php if ( $upload_error ) : ?>
					<div class="storesuite-notice storesuite-notice-error" role="alert">
						<p><?php esc_html_e( 'Before you can upload your import file, you will need to fix the following error:', 'storesuite' ); ?></p>
						<p><strong><?php echo esc_html( $upload_error ); ?></strong></p>
					</div>
				<?php else : ?>
					<label class="storesuite-import-dropzone" for="storesuite-import-file">
						<input type="file" id="storesuite-import-file" name="import" accept=".csv,.txt" class="storesuite-import-dropzone__input" />
						<input type="hidden" name="action" value="save" />
						<input type="hidden" name="max_file_size" value="<?php echo esc_attr( $bytes ); ?>" />
						<span class="storesuite-import-dropzone__icon" aria-hidden="true" focusable="false">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"></path><polyline points="8 8 12 4 16 8"></polyline><line x1="12" y1="4" x2="12" y2="16"></line></svg>
						</span>
						<span class="storesuite-import-dropzone__title"><?php esc_html_e( 'Choose a CSV file to upload', 'storesuite' ); ?></span>
						<span class="storesuite-import-dropzone__hint">
							<?php esc_html_e( 'Drag & drop your file here, or', 'storesuite' ); ?>
							<span class="storesuite-import-dropzone__browse"><?php esc_html_e( 'browse', 'storesuite' ); ?></span>
						</span>
						<span class="storesuite-import-dropzone__meta">
							<span class="storesuite-badge"><?php esc_html_e( 'CSV · TXT', 'storesuite' ); ?></span>
							<span class="storesuite-badge">
								<?php
								/* translators: %s: maximum upload size. */
								printf( esc_html__( 'Max %s', 'storesuite' ), esc_html( $size ) );
								?>
							</span>
						</span>
					</label>
				<?php endif; ?>

				<div class="storesuite-form-group storesuite-form-switch storesuite-import-switch-row">
					<input type="hidden" name="update_existing" value="0" />
					<input type="checkbox" id="woocommerce-importer-update-existing" name="update_existing" value="1" />
					<label for="woocommerce-importer-update-existing">
						<span class="storesuite-import-switch-row__title"><?php esc_html_e( 'Update existing products', 'storesuite' ); ?></span>
						<span class="storesuite-import-switch-row__desc"><?php esc_html_e( 'Products matching by ID or SKU are updated. Products that don’t exist are skipped.', 'storesuite' ); ?></span>
					</label>
				</div>
			</div>
		</div>

		<div class="col-md-4">
			<div class="storesuite-card storesuite-card-with-header storesuite-import-card storesuite-mb-24">
				<h3 class="storesuite-card-title"><?php esc_html_e( 'Advanced options', 'storesuite' ); ?></h3>
				<div class="storesuite-card-content">
					<div class="storesuite-form-group">
						<label for="woocommerce-importer-file-url"><?php esc_html_e( 'CSV file on your server', 'storesuite' ); ?></label>
						<input type="text" class="storesuite-form-control" id="woocommerce-importer-file-url" name="file_url" placeholder="<?php echo esc_attr( trailingslashit( ABSPATH ) ); ?>" />
					</div>

					<div class="row">
						<div class="col-md-6">
							<div class="storesuite-form-group">
								<label for="storesuite-import-delimiter"><?php esc_html_e( 'Delimiter', 'storesuite' ); ?></label>
								<input type="text" class="storesuite-form-control" id="storesuite-import-delimiter" name="delimiter" placeholder="," maxlength="1" />
							</div>
						</div>
						<div class="col-md-6">
							<div class="storesuite-form-group">
								<label for="woocommerce-importer-character-encoding"><?php esc_html_e( 'Encoding', 'storesuite' ); ?></label>
								<select class="storesuite-form-control" id="woocommerce-importer-character-encoding" name="character_encoding">
									<option value="" selected><?php esc_html_e( 'Autodetect', 'storesuite' ); ?></option>
									<?php
									$encodings = mb_list_encodings();
									sort( $encodings, SORT_NATURAL );
									foreach ( $encodings as $encoding ) {
										echo '<option>' . esc_html( $encoding ) . '</option>';
									}
									?>
								</select>
							</div>
						</div>
					</div>

					<div class="storesuite-form-group storesuite-form-switch storesuite-import-switch-map">
						<input type="checkbox" id="woocommerce-importer-map-preferences" name="map_preferences" value="1" />
						<label for="woocommerce-importer-map-preferences"><?php esc_html_e( 'Use previous column mapping', 'storesuite' ); ?></label>
					</div>
				</div>
			</div>

			<button type="submit" class="my-storesuite-button storesuite-import-submit" name="save_step" value="<?php esc_attr_e( 'Continue', 'storesuite' ); ?>">
				<?php esc_html_e( 'Continue to mapping', 'storesuite' ); ?>
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true" focusable="false"><path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8"/></svg>
			</button>
		</div>
	</div>
	<?php wp_nonce_field( 'woocommerce-csv-importer' ); ?>
</form>
<script type="text/javascript">
	( function () {
		var form = document.currentScript.previousElementSibling;
		if ( ! form || ! form.classList.contains( 'storesuite-import-upload' ) ) {
			return;
		}
		var dropzone = form.querySelector( '.storesuite-import-dropzone' );
		var input    = form.querySelector( '.storesuite-import-dropzone__input' );
		if ( ! dropzone || ! input ) {
			return;
		}
		var title = dropzone.querySelector( '.storesuite-import-dropzone__title' );
		var defaultTitle = title ? title.textContent : '';

		function setFileName() {
			if ( ! title ) {
				return;
			}
			if ( input.files && input.files.length ) {
				title.textContent = input.files[0].name;
				dropzone.classList.add( 'has-file' );
			} else {
				title.textContent = defaultTitle;
				dropzone.classList.remove( 'has-file' );
			}
		}

		input.addEventListener( 'change', setFileName );

		[ 'dragenter', 'dragover' ].forEach( function ( evt ) {
			dropzone.addEventListener( evt, function ( e ) {
				e.preventDefault();
				e.stopPropagation();
				dropzone.classList.add( 'is-dragging' );
			} );
		} );

		[ 'dragleave', 'drop' ].forEach( function ( evt ) {
			dropzone.addEventListener( evt, function ( e ) {
				e.preventDefault();
				e.stopPropagation();
				dropzone.classList.remove( 'is-dragging' );
			} );
		} );

		dropzone.addEventListener( 'drop', function ( e ) {
			if ( e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length ) {
				input.files = e.dataTransfer.files;
				setFileName();
			}
		} );
	} )();
</script>
