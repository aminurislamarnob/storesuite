import type { Page } from '@playwright/test';
import { test, expect } from '../../../utils/test';
import { ADMIN_STATE, MANAGER_STATE } from '../../../utils/authStates';
import { dashboardPath } from '../../../utils/testData';

/**
 * Yoast SEO card on the product add/edit form.
 *
 * The card only exists while Yoast SEO is active, so every test skips itself
 * on a site without it. Provision with E2E_WITH_YOAST=true to exercise them.
 */
const card = ( page: Page ) => page.locator( '#storesuite-yoast-seo' );

async function openAddForm( page: Page ) {
	await page.goto( `${ dashboardPath }/add-new-product/` );
	test.skip( ( await card( page ).count() ) === 0, 'Yoast SEO is not active on this site.' );
}

async function openTab( page: Page, tab: 'seo' | 'social' | 'advanced' ) {
	// Retried: the form's "Unsaved Changes?" bar shifts the page when it first
	// appears, and a click issued during that shift can miss the tab.
	await expect( async () => {
		await card( page ).locator( `.storesuite-seo-tab[data-seo-tab="${ tab }"]` ).click();
		await expect( card( page ).locator( `[data-seo-panel="${ tab }"]` ) ).toBeVisible( { timeout: 1_000 } );
	} ).toPass();
}

async function submitAndExpectSuccess( page: Page ) {
	await page.locator( 'form#storesuite-add-product button[name="save_product"]' ).first().click();

	// The form submits over AJAX and reports success via SweetAlert.
	await expect( page.locator( '.swal2-popup' ) ).toBeVisible();
	await expect( page.locator( '.swal2-popup' ) ).toContainText( /success/i );
}

/** Opens the edit form of the product with the given (unique) name. */
async function openEditForm( page: Page, name: string ) {
	await page.goto( `${ dashboardPath }/products/` );
	const row = page.locator( 'tr' ).filter( { hasText: name } );
	const editUrl = await row.locator( 'a[href*="/edit-product/"]' ).first().getAttribute( 'href' );
	expect( editUrl ).toBeTruthy();
	await page.goto( editUrl as string );
}

/** Fetches the public product page of the edit form currently open and returns its HTML. */
async function fetchProductHtml( page: Page ) {
	const base = ( await page.locator( '.storesuite-permalink-prefix' ).first().innerText() ).trim();
	const slug = await page.locator( '#product_slug' ).inputValue();
	const response = await page.request.get( `${ base }${ slug }/` );
	expect( response.ok() ).toBe( true );
	return response.text();
}

test.describe( 'product SEO (Yoast) as a shop manager', () => {
	test.use( { storageState: MANAGER_STATE } );

	test( 'SEO and social values persist and reach the product page', async ( { page } ) => {
		// Idempotent across runs: the name is unique per run.
		const name = `E2E SEO Product ${ Date.now() }`;

		await openAddForm( page );
		await page.locator( '#product_title' ).fill( name );

		await page.locator( '#storesuite_yoast_focuskw' ).fill( 'e2e widget' );
		await page.locator( '#storesuite_yoast_title' ).fill( '%%title%% %%sep%% Handmade e2e widget' );
		await page.locator( '#storesuite_yoast_metadesc' ).fill( 'An e2e widget described for search engines.' );

		await openTab( page, 'social' );
		await page.locator( '#storesuite_yoast_opengraph-title' ).fill( 'Share the e2e widget' );
		await page.locator( '#storesuite_yoast_opengraph-description' ).fill( 'An e2e widget described for sharing.' );

		await submitAndExpectSuccess( page );

		await openEditForm( page, name );
		await expect( page.locator( '#storesuite_yoast_focuskw' ) ).toHaveValue( 'e2e widget' );
		await expect( page.locator( '#storesuite_yoast_title' ) ).toHaveValue( '%%title%% %%sep%% Handmade e2e widget' );
		await expect( page.locator( '#storesuite_yoast_metadesc' ) ).toHaveValue( 'An e2e widget described for search engines.' );
		await expect( page.locator( '#storesuite_yoast_opengraph-title' ) ).toHaveValue( 'Share the e2e widget' );

		const html = await fetchProductHtml( page );
		expect( html ).toMatch( new RegExp( `<title>${ name } .{1,3} Handmade e2e widget</title>` ) );
		expect( html ).toContain( 'content="An e2e widget described for search engines."' );
		expect( html ).toContain( '<meta property="og:title" content="Share the e2e widget"' );
		expect( html ).toContain( '<meta property="og:description" content="An e2e widget described for sharing."' );
	} );

	test( 'the Google preview and Insert variable follow the form', async ( { page } ) => {
		await openAddForm( page );

		await page.locator( '#product_title' ).fill( 'Previewed Widget' );
		await expect( card( page ).locator( '.storesuite-seo-snippet-title' ) ).toContainText( 'Previewed Widget' );

		await page.locator( '#storesuite_yoast_title' ).fill( 'Buy' );
		await card( page )
			.locator( '[data-seo-field="title"] .storesuite-seo-insert-variable' )
			.click();
		await card( page ).locator( '.storesuite-seo-variable-menu [data-variable="title"]' ).click();

		await expect( page.locator( '#storesuite_yoast_title' ) ).toHaveValue( /^Buy %%title%%/ );
		await expect( card( page ).locator( '.storesuite-seo-snippet-title' ) ).toHaveText( 'Buy Previewed Widget' );

		// Typing % opens the same menu, filtered by what follows.
		await page.locator( '#storesuite_yoast_metadesc' ).pressSequentially( 'From %site' );
		await expect( card( page ).locator( '.storesuite-seo-variable-menu [role="option"]' ) ).toHaveCount( 2 );
		await page.keyboard.press( 'Enter' );
		await expect( page.locator( '#storesuite_yoast_metadesc' ) ).toHaveValue( /^From %%sitename%%/ );

		await card( page ).locator( '.storesuite-seo-mode-switch' ).click();
		await expect( card( page ).locator( '.storesuite-seo-snippet-url' ) ).toContainText( '›' );
	} );

	test( 'the Advanced tab is not offered under Yoast’s default security setting', async ( { page } ) => {
		await openAddForm( page );

		await expect( card( page ).locator( '.storesuite-seo-tab[data-seo-tab="advanced"]' ) ).toHaveCount( 0 );
		await expect( page.locator( '#storesuite_yoast_canonical' ) ).toHaveCount( 0 );
	} );
} );

test.describe( 'product SEO (Yoast) as an administrator', () => {
	test.use( { storageState: ADMIN_STATE } );

	test( 'advanced settings persist and reach the product page', async ( { page } ) => {
		const name = `E2E SEO Advanced ${ Date.now() }`;

		await openAddForm( page );
		await page.locator( '#product_title' ).fill( name );

		await openTab( page, 'advanced' );
		await page.locator( '#storesuite_yoast_meta-robots-noindex' ).selectOption( '1' );
		await page.locator( '#storesuite_yoast_bctitle' ).fill( 'E2E crumb' );

		await submitAndExpectSuccess( page );

		await openEditForm( page, name );
		await expect( page.locator( '#storesuite_yoast_meta-robots-noindex' ) ).toHaveValue( '1' );
		await expect( page.locator( '#storesuite_yoast_bctitle' ) ).toHaveValue( 'E2E crumb' );

		const html = await fetchProductHtml( page );
		expect( html ).toMatch( /<meta name=['"]robots['"] content=['"]noindex/ );
	} );
} );

/**
 * AI Generate on the SEO card. Needs Yoast SEO plus a text generator; the
 * provisioning script's fake generator (E2E_WITH_FAKE_AI=true) is enough.
 */
test.describe( 'product SEO (Yoast) AI Generate as a shop manager', () => {
	test.use( { storageState: MANAGER_STATE } );

	async function openAddFormWithAi( page: Page ) {
		await openAddForm( page );
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
				field: 'title',
				product_title: 'probe',
			},
		} );
		const body = await probe.json();
		test.skip(
			! ( body?.data?.content || '' ).startsWith( 'E2E generated' ),
			'The fake AI generator is not installed (E2E_WITH_FAKE_AI=true).'
		);
	}

	test( 'generate, regenerate and insert an SEO title', async ( { page } ) => {
		await openAddFormWithAi( page );
		await page.locator( '#product_title' ).fill( 'AI Widget' );
		await page.locator( '#storesuite_yoast_focuskw' ).fill( 'ai widget' );

		await card( page )
			.locator( '.storesuite-ai-generate[data-field="yoast_title"]' )
			.click();

		const modal = page.locator( '#storesuite-ai-modal' );
		await expect( modal ).toBeVisible();
		await expect( modal.locator( '#storesuite-ai-modal-title' ) ).toHaveText( 'SEO title suggestion' );
		await expect( modal.locator( '#storesuite-ai-modal-text' ) ).toHaveValue( 'E2E generated title #1' );

		// The counter shows the field's target and updates as the text is edited.
		const counter = modal.locator( '.storesuite-ai-modal-counter' );
		await expect( counter ).toHaveText( '22 / 60' );
		await expect( counter ).toHaveAttribute( 'data-state', 'ok' );
		await modal.locator( '#storesuite-ai-modal-text' ).fill( 'x'.repeat( 61 ) );
		await expect( counter ).toHaveText( '61 / 60' );
		await expect( counter ).toHaveAttribute( 'data-state', 'over' );

		await modal.locator( '.storesuite-ai-regenerate' ).click();
		await expect( modal.locator( '.storesuite-ai-pager-status' ) ).toHaveText( '2/2' );
		await expect( modal.locator( '#storesuite-ai-modal-text' ) ).toHaveValue( 'E2E generated title #2' );

		await modal.locator( '.storesuite-ai-insert' ).click();
		await expect( modal ).toBeHidden();
		await expect( page.locator( '#storesuite_yoast_title' ) ).toHaveValue( 'E2E generated title #2' );
		// The Google preview and length bar follow the inserted title.
		await expect( card( page ).locator( '.storesuite-seo-snippet-title' ) ).toHaveText( 'E2E generated title #2' );
		await expect( card( page ).locator( '[data-seo-field="title"] .storesuite-seo-progress' ) ).toHaveAttribute( 'data-state', 'good' );
	} );

	test( 'insert a generated social description', async ( { page } ) => {
		await openAddFormWithAi( page );
		await page.locator( '#product_title' ).fill( 'AI Widget' );
		await openTab( page, 'social' );

		await card( page )
			.locator( '.storesuite-ai-generate[data-field="yoast_opengraph-description"]' )
			.click();

		const modal = page.locator( '#storesuite-ai-modal' );
		await expect( modal.locator( '#storesuite-ai-modal-title' ) ).toHaveText( 'Social description suggestion' );
		await expect( modal.locator( '.storesuite-ai-modal-counter' ) ).toContainText( '/ 200' );
		await modal.locator( '.storesuite-ai-insert' ).click();

		await expect( page.locator( '#storesuite_yoast_opengraph-description' ) ).toHaveValue( 'E2E generated opengraph description #1' );
	} );

	test( 'a description needs a product title first', async ( { page } ) => {
		await openAddFormWithAi( page );

		await card( page )
			.locator( '.storesuite-ai-generate[data-field="yoast_metadesc"]' )
			.click();

		await expect( page.locator( '.swal2-popup' ) ).toContainText( /add a product title/i );
	} );
} );
