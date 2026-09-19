import type { Page, Locator } from '@playwright/test';
import { dashboardPath } from '../../../utils/testData';

/**
 * Page object for the Products list of the frontend dashboard.
 */
export class ProductsPage {
	readonly page: Page;
	readonly table: Locator;
	readonly rows: Locator;
	readonly selectAll: Locator;
	readonly rowCheckboxes: Locator;
	readonly filterToggle: Locator;
	readonly filterCount: Locator;
	readonly filterDrawer: Locator;
	readonly filterForm: Locator;

	constructor( page: Page ) {
		this.page = page;
		this.table = page.locator( '.my-storesuite-product-list-table' );
		this.rows = this.table.locator( 'tbody tr.single-product-item' );
		this.selectAll = this.table.locator( '.storesuite-bulk-select-all' );
		this.rowCheckboxes = this.table.locator( '.storesuite-bulk-cb' );
		this.filterToggle = page.locator( '#storesuite-filter-toggle' );
		this.filterCount = this.filterToggle.locator( '.storesuite-filter-count' );
		this.filterDrawer = page.locator( '#storesuite-filter-offcanvas' );
		this.filterForm = this.filterDrawer.locator( 'form.storesuite-filters-form-offcanvas' );
	}

	async goto( query = '' ) {
		await this.page.goto( `${ dashboardPath }/products/${ query }` );
	}

	row( name: string ): Locator {
		return this.rows.filter( { has: this.page.getByRole( 'link', { name, exact: true } ) } );
	}

	/** Names of the products currently listed, in render order. */
	async rowNames(): Promise< string[] > {
		// :not(.dropdown-link) excludes the row-actions "Edit" link, which
		// points at the same URL as the product-name link.
		return this.rows
			.locator( 'td a[href*="/edit-product/"]:not(.dropdown-link)' )
			.allInnerTexts();
	}

	async searchFor( term: string ) {
		await this.page.locator( '#search_by' ).fill( term );
		await this.page.locator( '#search_by' ).press( 'Enter' );
	}

	/**
	 * Cell carrying an inline editor for the given field inside a row.
	 */
	inlineCell( row: Locator, field: string ): Locator {
		return row.locator( `td.storesuite-inline-cell[data-inline-field="${ field }"]` );
	}

	/** Open a cell's inline editor and return the editor element. */
	async openInlineEditor( row: Locator, field: string ): Promise< Locator > {
		const cell = this.inlineCell( row, field );
		await cell.click();
		const editor = cell.locator( '.storesuite-inline-editor' );
		await editor.waitFor();
		return editor;
	}

	/** Open the filter off-canvas and wait for it to be usable. */
	async openFilters() {
		await this.filterToggle.click();
		await this.filterForm.waitFor( { state: 'visible' } );
	}

	/** Submit the filter drawer and wait for the filtered list to render. */
	async applyFilters() {
		await Promise.all( [
			this.page.waitForLoadState(),
			this.filterForm.getByRole( 'button', { name: 'Filter Products' } ).click(),
		] );
	}
}
