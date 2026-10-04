import { test, expect } from '../../../utils/test';
import { MANAGER_STATE } from '../../../utils/authStates';
import { dashboardPath } from '../../../utils/testData';

test.use( { storageState: MANAGER_STATE } );

test.describe( 'order export', () => {
	test( 'the toolbar Export button generates and downloads a CSV', async ( { page } ) => {
		await page.goto( `${ dashboardPath }/orders/` );

		await page.locator( '#storesuite-order-export-toggle' ).click();
		const modal = page.locator( '#storesuite-order-export-modal' );
		await expect( modal ).toBeVisible();

		const [ download ] = await Promise.all( [
			page.waitForEvent( 'download' ),
			modal.locator( '.storesuite-export-submit' ).click(),
		] );

		expect( download.suggestedFilename() ).toMatch( /^storesuite-order-export-.*\.csv$/ );

		const path = await download.path();
		expect( path ).toBeTruthy();
		const fs = await import( 'node:fs/promises' );
		const csv = await fs.readFile( path as string, 'utf8' );
		const [ header, ...rows ] = csv.trim().split( '\n' );
		expect( header ).toContain( 'Order ID' );
		expect( header ).toContain( 'Items' );
		expect( rows.length ).toBeGreaterThanOrEqual( 3 );
	} );

	test( 'the bulk Export action limits the export to checked orders', async ( { page } ) => {
		await page.goto( `${ dashboardPath }/orders/` );

		const rows = page.locator( '.my-storesuite-tbl tbody tr.storesuite-list-row' );
		await rows.first().locator( '.storesuite-bulk-cb' ).check();

		await page.locator( '#bulk-action-selector-top' ).selectOption( 'export' );
		await page.locator( '#doaction' ).click();

		const modal = page.locator( '#storesuite-order-export-modal' );
		await expect( modal ).toBeVisible();
		await expect( modal.locator( '.storesuite-export-bulk-notice' ) ).toContainText( 'export 1 orders' );

		const [ download ] = await Promise.all( [
			page.waitForEvent( 'download' ),
			modal.locator( '.storesuite-export-submit' ).click(),
		] );

		const fs = await import( 'node:fs/promises' );
		const csv = await fs.readFile( ( await download.path() ) as string, 'utf8' );
		expect( csv.trim().split( '\n' ) ).toHaveLength( 2 );
	} );
} );
