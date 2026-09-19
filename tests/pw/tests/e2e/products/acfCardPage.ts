import type { Locator, Page } from '@playwright/test';

/**
 * Page object for an ACF field group card on the product add/edit form.
 *
 * Fields are addressed by ACF field key: every wrapper carries
 * `data-key`, and inputs are named `storesuite_acf[<key>]`.
 */
export class AcfCardPage {
	readonly page: Page;
	readonly form: Locator;
	readonly card: Locator;

	constructor( page: Page, groupKey: string ) {
		this.page = page;
		this.form = page.locator( 'form#storesuite-add-product' );
		this.card = this.form.locator( `.storesuite-acf-field-group[data-group-key="${ groupKey }"]` );
	}

	field( key: string ): Locator {
		return this.card.locator( `.storesuite-acf-field[data-key="${ key }"]` );
	}

	/** The single named control of a field (text, number, select, hidden id, …). */
	input( key: string ): Locator {
		return this.field( key ).locator( `[name="storesuite_acf[${ key }]"]:not([type="hidden"])` );
	}

	choice( key: string, value: string ): Locator {
		return this.field( key ).locator( `input[value="${ value }"]` );
	}

	async selectMulti( key: string, values: string[] ): Promise< void > {
		await this.field( key ).locator( 'select' ).selectOption( values );
	}

	async setSwitch( key: string, on: boolean ): Promise< void > {
		const box = this.field( key ).locator( 'input[type="checkbox"]' );
		if ( ( await box.isChecked() ) !== on ) {
			// The visual switch is the label; the checkbox itself is hidden.
			await this.field( key ).locator( 'label[for]' ).last().click();
		}
	}

	/** Type into the TinyMCE editor of a WYSIWYG field once it has initialised. */
	async fillEditor( key: string, text: string ): Promise< void > {
		const wrap = this.field( key ).locator( '.wp-editor-wrap' );
		await wrap.locator( 'iframe' ).waitFor();
		const body = wrap.frameLocator( 'iframe' ).locator( 'body#tinymce' );
		await body.click();
		await body.fill( text );
	}

	editorText( key: string ): Locator {
		return this.field( key ).locator( '.wp-editor-wrap' ).frameLocator( 'iframe' ).locator( 'body#tinymce' );
	}

	/** Pick a media-library image by title through the WordPress media frame. */
	async pickImage( key: string, title: string ): Promise< void > {
		await this.field( key ).locator( '.storesuite-media-picker-drop' ).click();

		const modal = this.page.locator( '.media-modal' );
		await modal.waitFor();
		await modal.getByRole( 'tab', { name: 'Media Library' } ).click();
		await modal.locator( '.attachments-browser' ).waitFor();
		await modal.locator( '#media-search-input' ).fill( title );
		await modal.locator( `.attachment[aria-label="${ title }"]` ).first().click();
		await modal.locator( '.media-button-select' ).click();
		await modal.waitFor( { state: 'hidden' } );
	}

	imageId( key: string ): Locator {
		return this.field( key ).locator( `input[type="hidden"][name="storesuite_acf[${ key }]"]` );
	}

	async save(): Promise< void > {
		await this.form.locator( '#storesuite-product-actions button[name="save_product"]' ).click();
	}

	dialog(): Locator {
		return this.page.locator( '.swal2-popup' );
	}
}
