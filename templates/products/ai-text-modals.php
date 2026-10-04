<?php
/**
 * AI text generation modals for the product form.
 *
 * Two dialogs: the suggestion modal (review / regenerate / insert generated copy
 * for a single field) and the prompt modal (describe a product to draft a title).
 * Loaded by ProductAI on the add/edit product page when text generation is
 * available.
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<div id="storesuite-ai-modal" class="storesuite-product-bulk-modal-overlay" hidden aria-hidden="true">
	<div
		class="storesuite-product-bulk-modal-dialog"
		role="dialog"
		aria-modal="true"
		aria-labelledby="storesuite-ai-modal-title"
		tabindex="-1"
	>
		<div class="storesuite-product-bulk-modal-header">
			<h2 id="storesuite-ai-modal-title" class="storesuite-product-bulk-modal-title">
				<?php esc_html_e( 'AI suggestion', 'storesuite' ); ?>
			</h2>
			<button type="button" class="storesuite-product-bulk-modal-close my-storesuite-button storesuite-button-ghost" aria-label="<?php esc_attr_e( 'Close', 'storesuite' ); ?>">
				<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><use href="#storesuite-icon-close"></use></svg>
			</button>
		</div>
		<div class="storesuite-product-bulk-edit-scroll">
			<div class="storesuite-ai-modal-subhead">
				<p class="storesuite-ai-modal-subtitle"><?php esc_html_e( 'Review, edit and insert the suggestion or regenerate a new one.', 'storesuite' ); ?></p>
				<div class="storesuite-ai-modal-pager" hidden>
					<button type="button" class="storesuite-ai-prev" aria-label="<?php esc_attr_e( 'Previous suggestion', 'storesuite' ); ?>"><svg class="storesuite-ai-pager-icon" aria-hidden="true" focusable="false"><use href="#storesuite-icon-chevron-left"></use></svg></button>
					<span class="storesuite-ai-pager-status" aria-live="polite">1/1</span>
					<button type="button" class="storesuite-ai-next" aria-label="<?php esc_attr_e( 'Next suggestion', 'storesuite' ); ?>"><svg class="storesuite-ai-pager-icon" aria-hidden="true" focusable="false"><use href="#storesuite-icon-chevron-right"></use></svg></button>
				</div>
			</div>
			<div class="storesuite-form-group">
				<textarea id="storesuite-ai-modal-text" class="storesuite-form-control" rows="3"></textarea>
				<div class="storesuite-ai-modal-counter" aria-live="polite" hidden></div>
				<div class="storesuite-ai-skeleton storesuite-skeleton-lines" hidden aria-hidden="true">
					<div class="storesuite-skeleton"></div>
					<div class="storesuite-skeleton"></div>
					<div class="storesuite-skeleton"></div>
				</div>
			</div>
		</div>
		<div class="storesuite-product-bulk-modal-footer">
			<button type="button" class="my-storesuite-button storesuite-button-neutral-panel storesuite-ai-regenerate">
				<?php esc_html_e( 'Regenerate', 'storesuite' ); ?>
			</button>
			<button type="button" class="my-storesuite-button storesuite-ai-insert">
				<?php esc_html_e( 'Insert', 'storesuite' ); ?>
			</button>
		</div>
	</div>
</div>

<div id="storesuite-ai-prompt-modal" class="storesuite-product-bulk-modal-overlay" hidden aria-hidden="true">
	<div
		class="storesuite-product-bulk-modal-dialog"
		role="dialog"
		aria-modal="true"
		aria-labelledby="storesuite-ai-prompt-title"
		tabindex="-1"
	>
		<div class="storesuite-product-bulk-modal-header">
			<h2 id="storesuite-ai-prompt-title" class="storesuite-product-bulk-modal-title">
				<?php esc_html_e( 'Generate a product title', 'storesuite' ); ?>
			</h2>
			<button type="button" class="storesuite-product-bulk-modal-close my-storesuite-button storesuite-button-ghost" aria-label="<?php esc_attr_e( 'Close', 'storesuite' ); ?>">
				<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><use href="#storesuite-icon-close"></use></svg>
			</button>
		</div>
		<div class="storesuite-product-bulk-edit-scroll">
			<div class="storesuite-ai-modal-subhead">
				<p class="storesuite-ai-modal-subtitle"><?php esc_html_e( 'Describe your product with a few keywords to generate a title.', 'storesuite' ); ?></p>
			</div>
			<div class="storesuite-form-group">
				<textarea id="storesuite-ai-prompt-text" class="storesuite-form-control" rows="3" placeholder="<?php esc_attr_e( 'e.g. handmade ceramic coffee mug, 350ml, matte black', 'storesuite' ); ?>"></textarea>
			</div>
		</div>
		<div class="storesuite-product-bulk-modal-footer">
			<button type="button" class="my-storesuite-button storesuite-button-neutral-panel storesuite-product-bulk-modal-cancel">
				<?php esc_html_e( 'Cancel', 'storesuite' ); ?>
			</button>
			<button type="button" class="my-storesuite-button storesuite-ai-prompt-generate">
				<?php esc_html_e( 'Generate', 'storesuite' ); ?>
			</button>
		</div>
	</div>
</div>
