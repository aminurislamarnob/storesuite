//COVERAGE_TAG: GET /storesuite/v1/settings
//COVERAGE_TAG: POST /storesuite/v1/settings

import { test, expect } from '../../utils/test';
import { apiContext, adminAuth, managerAuth } from '../../utils/apiUtils';

const ROUTE = '/wp-json/storesuite/v1/settings';

/**
 * HTTP-level contract tests for the settings REST API — same authorization
 * matrix the PHPUnit suite covers, but through a real web server with
 * application-password authentication.
 */
test.describe( 'settings REST API', () => {
	test( 'rejects unauthenticated requests', async () => {
		const api = await apiContext();

		const response = await api.get( ROUTE );
		expect( response.status() ).toBe( 401 );

		await api.dispose();
	} );

	test( 'rejects shop managers', async () => {
		const api = await apiContext( managerAuth() );

		const read = await api.get( ROUTE );
		expect( read.status() ).toBe( 403 );

		const write = await api.post( ROUTE, { data: { storesuite_product_per_page: '7' } } );
		expect( write.status() ).toBe( 403 );

		await api.dispose();
	} );

	test( 'admin can read the settings object', async () => {
		const api = await apiContext( adminAuth() );

		const response = await api.get( ROUTE );
		expect( response.status() ).toBe( 200 );

		const settings = await response.json();
		expect( typeof settings ).toBe( 'object' );
		expect( settings.storesuite_dashboard_page_id ).toBeTruthy();

		await api.dispose();
	} );

	test( 'admin update round-trips and enums are enforced', async () => {
		const api = await apiContext( adminAuth() );

		// The settings option is shared site state: remember the current
		// value and put it back after the round trip.
		const before = await ( await api.get( ROUTE ) ).json();
		const previous = before.storesuite_order_per_page ?? '';

		const write = await api.post( ROUTE, { data: { storesuite_order_per_page: '18' } } );
		expect( write.status() ).toBe( 200 );
		expect( ( await write.json() ).storesuite_order_per_page ).toBe( '18' );

		const read = await api.get( ROUTE );
		expect( ( await read.json() ).storesuite_order_per_page ).toBe( '18' );

		// Values outside a schema enum are rejected by the REST layer.
		const invalid = await api.post( ROUTE, { data: { storesuite_color_palette_mode: 'neon' } } );
		expect( invalid.status() ).toBe( 400 );

		const restore = await api.post( ROUTE, { data: { storesuite_order_per_page: previous } } );
		expect( restore.status() ).toBe( 200 );

		await api.dispose();
	} );
} );
