import fs from 'fs';
import type { Page } from '@playwright/test';
import { test as setup, expect } from '../../utils/test';
import { AUTH_DIR, ADMIN_STATE, MANAGER_STATE, CUSTOMER_STATE } from '../../utils/authStates';
import { users } from '../../utils/testData';

/**
 * Logs each role in through the WooCommerce My Account form once and saves
 * its storage state, so every spec starts already authenticated. (The
 * WooCommerce form is used because it works regardless of any plugin that
 * moves or hides wp-login.php.)
 *
 * StoreSuite's login_redirect then sends admins to wp-admin, managers to
 * the dashboard and customers back to My Account; the logged-in cookie is
 * what matters here, not the landing page.
 */
async function logIn( page: Page, username: string, password: string, statePath: string ) {
	await page.goto( '/my-account/' );
	await page.locator( 'form.woocommerce-form-login #username' ).fill( username );
	await page.locator( 'form.woocommerce-form-login #password' ).fill( password );
	await page.locator( 'form.woocommerce-form-login button[name="login"]' ).click();
	await page.waitForLoadState();

	await expect
		.poll( async () => {
			const cookies = await page.context().cookies();
			return cookies.some( ( cookie ) => cookie.name.startsWith( 'wordpress_logged_in_' ) );
		}, { message: 'Expected a wordpress_logged_in_ cookie after login.' } )
		.toBe( true );

	await page.context().storageState( { path: statePath } );
}

setup.beforeAll( () => {
	fs.mkdirSync( AUTH_DIR, { recursive: true } );
} );

setup( 'authenticate as admin', async ( { page } ) => {
	await logIn( page, users.admin.username, users.admin.password, ADMIN_STATE );
} );

setup( 'authenticate as shop manager', async ( { page } ) => {
	await logIn( page, users.manager.username, users.manager.password, MANAGER_STATE );
} );

setup( 'authenticate as customer', async ( { page } ) => {
	await logIn( page, users.customer.username, users.customer.password, CUSTOMER_STATE );
} );
