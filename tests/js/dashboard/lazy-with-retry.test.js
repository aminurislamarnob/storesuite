/**
 * jsdom's window.location is unforgeable, so `reload` cannot be stubbed
 * there. Running in the node environment lets the test provide its own
 * minimal `window` with a controllable sessionStorage and location.
 *
 * @jest-environment node
 */

/**
 * Internal dependencies
 */
import lazyWithRetry from '../../../src/dashboard/utils/lazy-with-retry';

// Capture the loader instead of creating a real React lazy component so the
// retry/reload behavior can be exercised directly.
jest.mock( '@wordpress/element', () => ( {
	lazy: ( load ) => ( { load } ),
} ) );

const RELOAD_FLAG = 'storesuite_dashboard_chunk_reloaded';

const makeSessionStorage = () => {
	const store = new Map();
	return {
		getItem: ( key ) => ( store.has( key ) ? store.get( key ) : null ),
		setItem: ( key, value ) => store.set( key, String( value ) ),
		removeItem: ( key ) => store.delete( key ),
	};
};

describe( 'dashboard/utils/lazy-with-retry', () => {
	beforeEach( () => {
		global.window = {
			sessionStorage: makeSessionStorage(),
			location: { reload: jest.fn() },
		};
	} );

	afterEach( () => {
		delete global.window;
	} );

	it( 'resolves the module and clears the reload flag on success', async () => {
		window.sessionStorage.setItem( RELOAD_FLAG, '1' );
		const moduleExports = { default: () => null };
		const importer = jest.fn().mockResolvedValue( moduleExports );

		const { load } = lazyWithRetry( importer );

		await expect( load() ).resolves.toBe( moduleExports );
		expect( importer ).toHaveBeenCalledTimes( 1 );
		expect( window.sessionStorage.getItem( RELOAD_FLAG ) ).toBeNull();
	} );

	it( 'retries once and resolves when the second attempt succeeds', async () => {
		const moduleExports = { default: () => null };
		const importer = jest
			.fn()
			.mockRejectedValueOnce( new Error( 'ChunkLoadError' ) )
			.mockResolvedValueOnce( moduleExports );

		const { load } = lazyWithRetry( importer );

		await expect( load() ).resolves.toBe( moduleExports );
		expect( importer ).toHaveBeenCalledTimes( 2 );
		expect( window.location.reload ).not.toHaveBeenCalled();
	} );

	it( 'forces a single page reload when both attempts fail', async () => {
		const importer = jest
			.fn()
			.mockRejectedValue( new Error( 'ChunkLoadError' ) );

		const { load } = lazyWithRetry( importer );
		const pending = load();

		// The promise must stay pending while the page reloads.
		const settled = await Promise.race( [
			pending.then( () => 'settled' ),
			new Promise( ( resolve ) =>
				setTimeout( () => resolve( 'pending' ), 20 )
			),
		] );

		expect( settled ).toBe( 'pending' );
		expect( importer ).toHaveBeenCalledTimes( 2 );
		expect( window.location.reload ).toHaveBeenCalledTimes( 1 );
		expect( window.sessionStorage.getItem( RELOAD_FLAG ) ).toBe( '1' );
	} );

	it( 'rethrows the error instead of reloading again after a reload already happened', async () => {
		window.sessionStorage.setItem( RELOAD_FLAG, '1' );
		const error = new Error( 'ChunkLoadError' );
		const importer = jest.fn().mockRejectedValue( error );

		const { load } = lazyWithRetry( importer );

		await expect( load() ).rejects.toBe( error );
		expect( importer ).toHaveBeenCalledTimes( 2 );
		expect( window.location.reload ).not.toHaveBeenCalled();
	} );
} );
