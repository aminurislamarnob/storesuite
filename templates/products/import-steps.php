<?php
/**
 * StoreSuite product import wizard step indicator.
 *
 * Renders the four-step progress bar (Upload → Column mapping → Import → Done) for the CSV import wizard.
 *
 * @var array  $steps        Wizard steps keyed by step slug, each with a 'name'.
 * @var string $current_step Slug of the active step.
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$step_keys    = array_keys( $steps );
$current_index = array_search( $current_step, $step_keys, true );
?>
<ol class="storesuite-import-steps storesuite-card storesuite-import-card storesuite-mb-24">
	<?php foreach ( $step_keys as $index => $step_key ) : ?>
		<?php
		$state = 'upcoming';
		if ( $step_key === $current_step ) {
			$state = 'active';
		} elseif ( false !== $current_index && $index < $current_index ) {
			$state = 'done';
		}
		?>
		<li class="storesuite-import-step is-<?php echo esc_attr( $state ); ?>">
			<span class="storesuite-import-step__badge">
				<?php if ( 'done' === $state ) : ?>
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>
				<?php else : ?>
					<?php echo esc_html( $index + 1 ); ?>
				<?php endif; ?>
			</span>
			<span class="storesuite-import-step__text">
				<span class="storesuite-import-step__label">
					<?php
					/* translators: %d: step number. */
					printf( esc_html__( 'STEP %d', 'storesuite' ), (int) ( $index + 1 ) );
					?>
				</span>
				<span class="storesuite-import-step__name"><?php echo esc_html( $steps[ $step_key ]['name'] ); ?></span>
			</span>
		</li>
	<?php endforeach; ?>
</ol>
