import type { Page } from '@playwright/test';
import { test, expect } from '../../../utils/test';
import { ADMIN_STATE, MANAGER_STATE } from '../../../utils/authStates';
import { dashboardPath } from '../../../utils/testData';

/**
 * Yoast SEO card on the category, tag and brand add/edit forms.
 *
 * The card only exists while Yoast SEO is active, so every test skips itself
 * on a site without it. Provision with E2E_WITH_YOAST=true to exercise them.
 * Run with BASE_URL=https://… — http drops POST bodies on the https redirect.
 */
const card = ( page: Page ) => page.locator( '#storesuite-yoast-term-seo' );

type Kind = {
	label: string;
	addEndpoint: string;
	listEndpoint: string;
	addForm: string;
	editForm: string;
	name: string;
	slug: string;
};

const category: Kind = {
	label: 'category',
	addEndpoint: 'add-new-category',
	listEndpoint: 'categories',
	addForm: 'form#storesuite-add-category',
	editForm: 'form#storesuite-edit-category',
	name: '#product_category_name',
	slug: '#product_category_slug',
};
const tag: Kind = {
	label: 'tag',
	addEndpoint: 'add-new-tag',
	listEndpoint: 'tags',
	addForm: 'form#storesuite-add-tag',
	editForm: 'form#storesuite-edit-tag',
	name: '#name',
	slug: '#slug',
};
const brand: Kind = {
	label: 'brand',
	addEndpoint: 'add-new-brand',
	listEndpoint: 'brands',
	addForm: 'form#storesuite-add-brand',
	editForm: 'form#storesuite-edit-brand',
	name: '#product_brand_name',
	slug: '#product_brand_slug',
};

async function openAddForm( page: Page, kind: Kind ) {
	await page.goto( `${ dashboardPath }/${ kind.addEndpoint }/` );
	test.skip( ( await card( page ).count() ) === 0, 'Yoast SEO is not active on this site. Provision with E2E_WITH_YOAST=true.' );
}

async function openTab( page: Page, tab: 'seo' | 'social' | 'advanced' ) {
	// Retried: the form's "Unsaved Changes?" bar shifts the page when it first
	// appears, and a click issued during that shift can miss the tab.
	await expect( async () => {
		await card( page ).locator( `.storesuite-seo-tab[data-seo-tab="${ tab }"]` ).click();
		await expect( card( page ).locator( `[data-seo-panel="${ tab }"]` ) ).toBeVisible( { timeout: 1_000 } );
	} ).toPass();
}

async function submitAndExpectSuccess( page: Page, form: string ) {
	await page.locator( `${ form } button[type="submit"]` ).first().click();

	// The form submits over AJAX and reports success via SweetAlert.
	await expect( page.locator( '.swal2-popup' ) ).toBeVisible();
	await expect( page.locator( '.swal2-popup' ) ).toContainText( /success/i );
}

/** Opens the edit form of the term with the given (unique) name. */
async function openEditForm( page: Page, kind: Kind, name: string ) {
	await page.goto( `${ dashboardPath }/${ kind.listEndpoint }/?search_by=${ encodeURIComponent( name ) }` );
	const editUrl = await page
		.locator( 'tr' )
		.filter( { hasText: name } )
		.locator( 'a[href*="/edit-"]' )
		.first()
		.getAttribute( 'href' );
	expect( editUrl ).toBeTruthy();
	await page.goto( editUrl as string );
}

/** Fetches the public archive page of the term whose edit form is open. */
async function fetchArchiveHtml( page: Page ) {
	const base: string = await page.evaluate( () => ( window as any ).StoreSuite_TermSeo.urlBase );
	const slug = await page.locator( '#product_category_slug, #product_brand_slug, #slug' ).first().inputValue();
	const response = await page.request.get( `${ base }${ slug }/` );
	expect( response.ok() ).toBe( true );
	return response.text();
}

/** Creates a term from its add form, optionally filling the SEO card first. */
async function createTerm( page: Page, kind: Kind, name: string, fillSeo?: () => Promise<void> ) {
	await openAddForm( page, kind );
	await page.locator( kind.name ).fill( name );
	await page.locator( kind.slug ).fill( name.toLowerCase().replace( /[^a-z0-9]+/g, '-' ) );
	if ( fillSeo ) {
		await fillSeo();
	}
	await submitAndExpectSuccess( page, kind.addForm );
}

test.describe( 'term SEO (Yoast) as an administrator', () => {
	test.use( { storageState: ADMIN_STATE } );

	test( 'a category’s SEO values reach its archive page', async ( { page } ) => {
		const name = `E2E SEO Category ${ Date.now() }`;
		await createTerm( page, category, name );

		await openEditForm( page, category, name );
		await page.locator( '#storesuite_yoast_term_title' ).fill( '%%term_title%% %%sep%% Handmade e2e shelf' );
		await page.locator( '#storesuite_yoast_term_metadesc' ).fill( 'An e2e shelf described for search engines.' );
		await openTab( page, 'advanced' );
		await page.locator( '#storesuite_yoast_term_noindex' ).selectOption( 'noindex' );
		await submitAndExpectSuccess( page, category.editForm );

		// Proves the meta lands before Yoast's indexable watcher rebuilds on edited_term.
		const html = await fetchArchiveHtml( page );
		expect( html ).toMatch( new RegExp( `<title>${ name } .{1,3} Handmade e2e shelf</title>` ) );
		expect( html ).toContain( 'content="An e2e shelf described for search engines."' );
		expect( html ).toMatch( /<meta name=['"]robots['"] content=['"][^'"]*noindex/ );
	} );
} );

test.describe( 'term SEO (Yoast) as a shop manager', () => {
	test.use( { storageState: MANAGER_STATE } );

	test( 'saved category values prefill the edit form', async ( { page } ) => {
		const name = `E2E SEO Roundtrip ${ Date.now() }`;
		await createTerm( page, category, name );

		await openEditForm( page, category, name );
		await page.locator( '#storesuite_yoast_term_focuskw' ).fill( 'e2e shelf' );
		await page.locator( '#storesuite_yoast_term_title' ).fill( 'Round trip title' );
		await page.locator( '#storesuite_yoast_term_metadesc' ).fill( 'Round trip description.' );
		await submitAndExpectSuccess( page, category.editForm );

		await page.reload();
		await expect( page.locator( '#storesuite_yoast_term_focuskw' ) ).toHaveValue( 'e2e shelf' );
		await expect( page.locator( '#storesuite_yoast_term_title' ) ).toHaveValue( 'Round trip title' );
		await expect( page.locator( '#storesuite_yoast_term_metadesc' ) ).toHaveValue( 'Round trip description.' );
	} );

	test( 'the Google preview follows the name field and the title bar reacts to length', async ( { page } ) => {
		await openAddForm( page, category );

		await page.locator( category.name ).fill( 'Previewed Shelf' );
		await expect( card( page ).locator( '.storesuite-seo-snippet-title' ) ).toContainText( 'Previewed Shelf' );

		await page.locator( category.slug ).fill( 'previewed-shelf' );
		await card( page ).locator( '.storesuite-seo-mode-switch' ).click();
		await expect( card( page ).locator( '.storesuite-seo-snippet-url' ) ).toContainText( 'previewed-shelf' );

		// A short custom title sits under the 400px floor: the orange "ok" state.
		await page.locator( '#storesuite_yoast_term_title' ).fill( 'Shelf' );
		await expect( card( page ).locator( '.storesuite-seo-snippet-title' ) ).toHaveText( 'Shelf' );
		await expect( card( page ).locator( '[data-seo-field="title"] .storesuite-seo-progress' ) ).toHaveAttribute( 'data-state', 'ok' );
	} );

	for ( const kind of [ tag, brand ] ) {
		test( `a ${ kind.label }’s SEO title reaches its archive page`, async ( { page } ) => {
			const name = `E2E SEO ${ kind.label } ${ Date.now() }`;
			await createTerm( page, kind, name );

			await openEditForm( page, kind, name );
			await page.locator( '#storesuite_yoast_term_title' ).fill( `%%term_title%% %%sep%% E2E ${ kind.label } title` );
			await submitAndExpectSuccess( page, kind.editForm );

			const html = await fetchArchiveHtml( page );
			expect( html ).toMatch( new RegExp( `<title>${ name } .{1,3} E2E ${ kind.label } title</title>` ) );
		} );
	}

	test( 'SEO values filled on the add form persist', async ( { page } ) => {
		const name = `E2E SEO Added ${ Date.now() }`;
		await createTerm( page, category, name, async () => {
			await page.locator( '#storesuite_yoast_term_focuskw' ).fill( 'added shelf' );
			await page.locator( '#storesuite_yoast_term_metadesc' ).fill( 'Filled before the first save.' );
		} );

		await openEditForm( page, category, name );
		await expect( page.locator( '#storesuite_yoast_term_focuskw' ) ).toHaveValue( 'added shelf' );
		await expect( page.locator( '#storesuite_yoast_term_metadesc' ) ).toHaveValue( 'Filled before the first save.' );
	} );

	test( 'the Advanced tab is not offered under Yoast’s default security setting', async ( { page } ) => {
		await openAddForm( page, category );

		await expect( card( page ).locator( '.storesuite-seo-tab[data-seo-tab="advanced"]' ) ).toHaveCount( 0 );
		await expect( page.locator( '#storesuite_yoast_term_noindex' ) ).toHaveCount( 0 );
	} );
} );

/**
 * AI Generate on the term SEO card. Needs Yoast SEO plus a text generator; the
 * provisioning script's fake generator (E2E_WITH_FAKE_AI=true) is enough.
 */
test.describe( 'term SEO (Yoast) AI Generate as a shop manager', () => {
	test.use( { storageState: MANAGER_STATE } );

	async function openAddFormWithAi( page: Page, kind: Kind ) {
		await openAddForm( page, kind );
		// The AI script binds on load; a click before that opens nothing.
		await page.waitForLoadState( 'networkidle' );
		test.skip(
			( await card( page ).locator( '.storesuite-ai-generate' ).count() ) === 0,
			'AI text generation is not available on this site.'
		);
		// Only run against the provisioning script's fake generator: a real
		// provider would spend credits and return unpredictable text.
		const probe = await page.request.post( '/wp-admin/admin-ajax.php', {
			form: {
				action: 'storesuite_generate_product_field',
				nonce: await page.evaluate( () => ( window as any ).StoreSuite_Product.ai.nonce ),
				field: 'yoast_term_title',
			},
		} );
		const body = await probe.json();
		test.skip(
			! ( body?.data?.content || '' ).startsWith( 'E2E generated' ),
			'The fake AI generator is not installed (E2E_WITH_FAKE_AI=true).'
		);
	}

	for ( const kind of [ category, tag, brand ] ) {
		test( `generate and insert a ${ kind.label } SEO title`, async ( { page } ) => {
			await openAddFormWithAi( page, kind );
			await page.locator( kind.name ).fill( 'AI Shelf' );

			await card( page )
				.locator( '.storesuite-ai-generate[data-field="yoast_term_title"]' )
				.click();

			const modal = page.locator( '#storesuite-ai-modal' );
			await expect( modal ).toBeVisible();
			await expect( modal.locator( '#storesuite-ai-modal-title' ) ).toHaveText( 'SEO title suggestion' );
			await expect( modal.locator( '#storesuite-ai-modal-text' ) ).toHaveValue( 'E2E generated term title #1' );

			await modal.locator( '.storesuite-ai-insert' ).click();
			await expect( modal ).toBeHidden();
			await expect( page.locator( '#storesuite_yoast_term_title' ) ).toHaveValue( 'E2E generated term title #1' );
			await expect( card( page ).locator( '.storesuite-seo-snippet-title' ) ).toHaveText( 'E2E generated term title #1' );
		} );
	}

	test( 'insert a generated social description', async ( { page } ) => {
		await openAddFormWithAi( page, category );
		await page.locator( category.name ).fill( 'AI Shelf' );
		await openTab( page, 'social' );

		await card( page )
			.locator( '.storesuite-ai-generate[data-field="yoast_term_opengraph-description"]' )
			.click();

		const modal = page.locator( '#storesuite-ai-modal' );
		await expect( modal.locator( '#storesuite-ai-modal-title' ) ).toHaveText( 'Social description suggestion' );
		await modal.locator( '.storesuite-ai-insert' ).click();

		await expect( page.locator( '#storesuite_yoast_term_opengraph-description' ) ).toHaveValue( 'E2E generated term opengraph description #1' );
	} );
} );
