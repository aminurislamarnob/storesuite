/**
 * Auth setup: log each E2E persona in once and persist the session as a
 * storage state that the spec projects reuse.
 */
const { test: setup, expect } = require( '@playwright/test' );
const { USERS, STORAGE_STATE, dashboardUrl, login } = require( './helpers' );

setup( 'authenticate admin', async ( { page } ) => {
	await login( page, USERS.admin );
	await page.goto( '/wp-admin/' );
	await expect( page.locator( '#wpadminbar' ) ).toBeVisible();
	await page.context().storageState( { path: STORAGE_STATE.admin } );
} );

setup( 'authenticate shop manager', async ( { page } ) => {
	await login( page, USERS.manager );
	await page.goto( dashboardUrl() );
	await expect( page.locator( '#storesuite-dashboard-sidebar' ) ).toBeVisible();
	await page.context().storageState( { path: STORAGE_STATE.manager } );
} );

setup( 'authenticate customer', async ( { page } ) => {
	await login( page, USERS.customer );
	await page.context().storageState( { path: STORAGE_STATE.customer } );
} );
