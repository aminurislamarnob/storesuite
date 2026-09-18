<?php
/**
 * StoreSuite product import — error notice.
 *
 * Replaces WooCommerce's `.error.inline` admin notice with the dashboard's notice component.
 *
 * @var array $errors List of errors, each with a 'message' and an optional 'actions' list of
 *                    { url, label } pairs.
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<?php /* Prefixed loop vars: $error and $action are WordPress globals. */ ?>
<?php foreach ( $errors as $storesuite_error ) : ?>
	<div class="storesuite-notice storesuite-notice-error storesuite-mb-24" role="alert">
		<p><?php echo esc_html( $storesuite_error['message'] ); ?></p>

		<?php if ( ! empty( $storesuite_error['actions'] ) ) : ?>
			<p class="storesuite-notice-actions">
				<?php foreach ( $storesuite_error['actions'] as $storesuite_action ) : ?>
					<a class="my-storesuite-button" href="<?php echo esc_url( $storesuite_action['url'] ); ?>"><?php echo esc_html( $storesuite_action['label'] ); ?></a>
				<?php endforeach; ?>
			</p>
		<?php endif; ?>
	</div>
<?php endforeach; ?>
