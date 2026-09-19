import { test, expect } from '../../../utils/test';
import { MANAGER_STATE } from '../../../utils/authStates';
import { dashboardPath, seed } from '../../../utils/testData';

test.use( { storageState: MANAGER_STATE } );

test.describe( 'inventory view', () => {
	test( 'shows stock badges matching the low stock threshold', async ( { page } ) => {
		await page.goto( `${ dashboardPath }/inventory/` );

		const rows = page.locator( 'tr.single-inventory-item' );
		expect( await rows.count() ).toBeGreaterThan( 0 );

		// Red Cap (stock 2, threshold 3) is low → warning badge.
		const capRow = rows.filter( { hasText: seed.products.cap.name } );
		await expect( capRow.locator( '.storesuite-badge-warning' ).first() ).toBeVisible();

		// Blue Hoodie (stock 20) is healthy → success badge.
		const hoodieRow = rows.filter( { hasText: seed.products.hoodie.name } );
		await expect( hoodieRow.locator( '.storesuite-badge-success' ).first() ).toBeVisible();
	} );

	test( 'low stock filter narrows the list to low stock products', async ( { page } ) => {
		await page.goto( `${ dashboardPath }/inventory/?low_stock=1` );

		const rows = page.locator( 'tr.single-inventory-item' );

		await expect( rows.filter( { hasText: seed.products.cap.name } ) ).toHaveCount( 1 );
		await expect( rows.filter( { hasText: seed.products.stickers.name } ) ).toHaveCount( 1 );
		await expect( rows.filter( { hasText: seed.products.hoodie.name } ) ).toHaveCount( 0 );
	} );
} );
