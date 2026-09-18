<?php
/**
 * Dashboard Logo Template
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Resolve an image URL from a settings key holding an attachment ID.
 *
 * @param string $option_key Settings key.
 * @param string $size       Image size.
 * @return string Image URL, or an empty string when unset.
 */
$storesuite_branding_image_url = function ( $option_key, $size ) {
	$attachment_id = absint( storesuite_get_option_by_key( $option_key ) );

	if ( ! $attachment_id || ! wp_attachment_is_image( $attachment_id ) ) {
		return '';
	}

	return (string) wp_get_attachment_image_url( $attachment_id, $size );
};

$logo_url      = $storesuite_branding_image_url( 'storesuite_dashboard_sidebar_logo_id', 'medium' );
$icon_url      = $storesuite_branding_image_url( 'storesuite_dashboard_sidebar_icon_id', 'thumbnail' );
$logo_dark_url = $storesuite_branding_image_url( 'storesuite_dashboard_sidebar_logo_dark_id', 'medium' );
$icon_dark_url = $storesuite_branding_image_url( 'storesuite_dashboard_sidebar_icon_dark_id', 'thumbnail' );

// Either mode falls back to the other's image when it has none of its own.
$logo_url      = $logo_url ? $logo_url : $logo_dark_url;
$icon_url      = $icon_url ? $icon_url : $icon_dark_url;
$logo_dark_url = $logo_dark_url ? $logo_dark_url : $logo_url;
$icon_dark_url = $icon_dark_url ? $icon_dark_url : $icon_url;

$site_name = get_bloginfo( 'name' );
?>
<div class="storesuite-sidebar-logo">
	<?php if ( $logo_url ) : ?>
		<img class="storesuite-sidebar-logo-image" src="<?php echo esc_url( $logo_url ); ?>" data-storesuite-light-src="<?php echo esc_url( $logo_url ); ?>" data-storesuite-dark-src="<?php echo esc_url( $logo_dark_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" />
	<?php endif; ?>
	<?php if ( $icon_url ) : ?>
		<img class="storesuite-sidebar-icon-image" src="<?php echo esc_url( $icon_url ); ?>" data-storesuite-light-src="<?php echo esc_url( $icon_url ); ?>" data-storesuite-dark-src="<?php echo esc_url( $icon_dark_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" />
	<?php endif; ?>
	<h1 class="storesuite-sidebar-site-title<?php echo $logo_url ? ' screen-reader-text' : ''; ?>"><?php bloginfo( 'title' ); ?></h1>
</div>
<?php if ( $logo_url || $icon_url ) : ?>
	<script>
		/*
		 * Pick the branding image for the active theme before first paint. The
		 * dashboard script runs in the footer, so leaving this to it would show
		 * the light logo on a dark sidebar for a beat on every dark-mode load.
		 */
		( function () {
			var isDark = document.documentElement.getAttribute( 'data-theme' ) === 'dark';
			var images = document.querySelectorAll( '.storesuite-sidebar-logo [data-storesuite-dark-src]' );

			for ( var i = 0; i < images.length; i++ ) {
				images[ i ].src = isDark
					? images[ i ].getAttribute( 'data-storesuite-dark-src' )
					: images[ i ].getAttribute( 'data-storesuite-light-src' );
			}
		} )();
	</script>
<?php endif; ?>
