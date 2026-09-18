import { test, expect } from '../../../utils/test';
import { MANAGER_STATE, CUSTOMER_STATE } from '../../../utils/authStates';
import { dashboardPath } from '../../../utils/testData';

/**
 * Access control: only users who may manage the store reach the dashboard.
 */
test.describe( 'logged-out visitors', () => {
	test( 'are redirected away from the dashboard', async ( { page } ) => {
		await page.goto( `${ dashboardPath }/` );

		await expect( page ).toHaveURL( /my-account/ );
		await expect( page.locator( '.my-storesuite-sidebar' ) ).toHaveCount( 0 );
	} );

	// Core fires login_redirect while rendering the login form, before anyone
	// has logged in; StoreSuite used to treat that as a login and bounce the
	// whole screen (and any custom login slug built on it) to My Account.
	test( 'can open the WordPress login form', async ( { page } ) => {
		await page.goto( '/wp-login.php' );

		await expect( page ).toHaveURL( /wp-login\.php/ );
		await expect( page.locator( '#loginform' ) ).toBeVisible();
	} );
} );

test.describe( 'customers', () => {
	test.use( { storageState: CUSTOMER_STATE } );

	test( 'are redirected away from the dashboard', async ( { page } ) => {
		await page.goto( `${ dashboardPath }/` );

		await expect( page ).not.toHaveURL( new RegExp( dashboardPath ) );
		await expect( page.locator( '.my-storesuite-sidebar' ) ).toHaveCount( 0 );
	} );
} );

test.describe( 'shop managers', () => {
	test.use( { storageState: MANAGER_STATE } );

	test( 'reach the dashboard', async ( { page } ) => {
		await page.goto( `${ dashboardPath }/` );

		await expect( page ).toHaveURL( new RegExp( dashboardPath ) );
		await expect( page.locator( '.my-storesuite-sidebar' ) ).toBeVisible();
	} );
} );
