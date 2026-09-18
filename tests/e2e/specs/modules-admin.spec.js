/**
 * Modules admin screen: the deactivate/re-activate toggle round-trip for the
 * Inventory Manager module, verified against the persisted active-modules
 * option after each REST response.
 */
const { test, expect } = require( '@playwright/test' );
const { STORAGE_STATE, wpEval } = require( '../helpers' );

const MODULES_PAGE = '/wp-admin/admin.php?page=storesuite#/modules';
const SLUG = 'inventory-manager';

test.use( { storageState: STORAGE_STATE.admin } );

/**
 * Is the module recorded active in the storesuite_active_modules option?
 *
 * @return {boolean} Active state.
 */
function isActiveOnServer() {
	return (
		wpEval(
			`echo in_array( '${ SLUG }', (array) get_option( 'storesuite_active_modules', array() ), true ) ? 'yes' : 'no';`
		) === 'yes'
	);
}

// Safety net: whatever happens mid-test, leave the module active (the
// frontend inventory specs and the dev site rely on it) and queue a rewrite
// flush so its endpoint stays routable.
test.afterAll( () => {
	wpEval(
		`$m = (array) get_option( 'storesuite_active_modules', array() ); if ( ! in_array( '${ SLUG }', $m, true ) ) { $m[] = '${ SLUG }'; update_option( 'storesuite_active_modules', $m ); } update_option( 'storesuite_flush_rewrite_rules', 1 );`
	);
} );

test( 'inventory manager can be deactivated and re-activated from the modules screen', async ( { page } ) => {
	expect( isActiveOnServer(), 'Precondition: module active' ).toBe( true );

	await page.goto( MODULES_PAGE );

	const card = page
		.locator( '.storesuite-module-card' )
		.filter( { hasText: 'Inventory Manager' } );
	await expect( card ).toBeVisible( { timeout: 15000 } );

	const toggle = card.locator( 'input[type="checkbox"]' );
	await expect( toggle ).toBeChecked();

	// --- Deactivate ---
	await toggle.click();
	await expect( card.getByText( 'Inactive', { exact: true } ) ).toBeVisible( { timeout: 15000 } );
	await expect( toggle ).not.toBeChecked();
	expect( isActiveOnServer(), 'Server must record the deactivation' ).toBe( false );

	// --- Re-activate ---
	await toggle.click();
	await expect( card.getByText( 'Active', { exact: true } ) ).toBeVisible( { timeout: 15000 } );
	await expect( toggle ).toBeChecked();
	expect( isActiveOnServer(), 'Server must record the re-activation' ).toBe( true );
} );
