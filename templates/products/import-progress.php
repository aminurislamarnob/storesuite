<?php
/**
 * StoreSuite product import — import (progress) step.
 *
 * WooCommerce's `wc-product-import.js` boots on `.woocommerce-importer` and writes the batch percentage
 * into `.woocommerce-importer-progress`, so both must stay on the markup. Everything else is StoreSuite
 * chrome; the percentage is mirrored into a label for readability.
 *
 * @var string $file_name Name of the file being imported.
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="storesuite-card storesuite-import-card storesuite-import-progress-step woocommerce-importer">
	<span class="storesuite-import-progress-step__spinner" aria-hidden="true"></span>

	<h2 class="storesuite-import-progress-step__title"><?php esc_html_e( 'Importing products', 'storesuite' ); ?></h2>
	<p class="storesuite-import-progress-step__lead">
		<?php if ( $file_name ) : ?>
			<?php
			printf(
				/* translators: %s: CSV file name. */
				esc_html__( 'Importing products from %s. Keep this page open until the import finishes.', 'storesuite' ),
				'<strong>' . esc_html( $file_name ) . '</strong>'
			);
			?>
		<?php else : ?>
			<?php esc_html_e( 'Your products are now being imported. Keep this page open until the import finishes.', 'storesuite' ); ?>
		<?php endif; ?>
	</p>

	<div class="storesuite-import-progress-step__bar">
		<progress class="woocommerce-importer-progress" max="100" value="0" aria-label="<?php esc_attr_e( 'Import progress', 'storesuite' ); ?>"></progress>
	</div>
	<div class="storesuite-import-progress-step__value" data-import-percentage aria-live="polite">0%</div>
</div>
<script type="text/javascript">
	( function () {
		var bar   = document.querySelector( '.storesuite-import-progress-step .woocommerce-importer-progress' );
		var label = document.querySelector( '[data-import-percentage]' );
		if ( ! bar || ! label ) {
			return;
		}

		// The importer script sets `progress.value` directly, which fires no event — poll instead.
		var last = -1;
		( function tick() {
			var value = Math.round( bar.value );
			if ( value !== last ) {
				last          = value;
				label.textContent = value + '%';
			}
			window.requestAnimationFrame( tick );
		} )();
	} )();
</script>
