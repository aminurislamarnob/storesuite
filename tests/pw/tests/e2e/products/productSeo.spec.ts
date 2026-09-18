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
	await card( page ).locator( `.storesuite-seo-tab[data-seo-tab="${ tab }"]` ).click();
	await expect( card( page ).locator( `[data-seo-panel="${ tab }"]` ) ).toBeVisible();
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
