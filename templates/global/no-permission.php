<?php
/**
 * StoreSuite no-permission template.
 *
 * Rendered inside the dashboard content area when the current user lacks the
 * capability required for the requested screen. Referenced from the dashboard
 * shortcode and from module front-end templates.
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="storesuite-no-data-found">
	<h2><?php esc_html_e( 'Permission denied', 'storesuite' ); ?></h2>
	<p><?php esc_html_e( 'You do not have permission to view this page.', 'storesuite' ); ?></p>
</div>
