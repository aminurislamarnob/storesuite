<?php
/**
 * Natural-language AI search box on the Products list toolbar.
 *
 * Only rendered when the WordPress AI Client is available (see ProductAiSearch::is_available()).
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<form class="storesuite-ai-search" id="storesuite-ai-search" role="search" aria-label="<?php esc_attr_e( 'AI search', 'storesuite' ); ?>">
	<div class="storesuite-table-search-input storesuite-ai-search-input">
		<div class="storesuite-table-search-icon storesuite-ai-search-icon" aria-hidden="true">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="currentColor" focusable="false">
				<path d="M9.5,3l1.6,4.4L15.5,9l-4.4,1.6L9.5,15l-1.6-4.4L3.5,9l4.4-1.6L9.5,3Zm8,9l1,2.75L21.25,15.75l-2.75,1L17.5,19.5l-1-2.75L13.75,15.75l2.75-1L17.5,12ZM5.5,15l.75,2L8.25,17.75l-2,.75L5.5,20.5l-.75-2L2.75,17.75l2-.75L5.5,15Z"/>
			</svg>
		</div>
		<input type="text" name="ai_query" id="storesuite-ai-search-query" placeholder="<?php esc_attr_e( 'Ask AI: e.g. out-of-stock hoodies under $10', 'storesuite' ); ?>" autocomplete="off" maxlength="300" />
		<button type="submit" class="my-storesuite-button storesuite-ai-search-submit" aria-label="<?php esc_attr_e( 'Search with AI', 'storesuite' ); ?>">
			<span class="storesuite-ai-search-submit-label"><?php esc_html_e( 'Ask', 'storesuite' ); ?></span>
			<span class="storesuite-ai-search-spinner" hidden aria-hidden="true"></span>
		</button>
	</div>
	<p class="storesuite-ai-search-hint" hidden aria-live="polite"></p>
</form>
