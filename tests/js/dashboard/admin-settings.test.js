/**
 * The module under test reads its sources at import time (a `getSetting` call
 * plus the `storeSuiteDashboardSettings` global), so each case loads a fresh
 * copy inside an isolated module registry.
 */
const loadAdminSettings = ( { wcAdmin = {}, dashboardGlobal } = {} ) => {
	let mod;

	jest.isolateModules( () => {
		const wcSettings = require( '@woocommerce/settings' );
		wcSettings.setMockSettings( { admin: wcAdmin } );

		if ( dashboardGlobal !== undefined ) {
			global.storeSuiteDashboardSettings = dashboardGlobal;
		}

		mod = require( '../../../src/dashboard/utils/admin-settings' );
	} );

	return mod;
};

describe( 'dashboard/utils/admin-settings getAdminSetting', () => {
	afterEach( () => {
		delete global.storeSuiteDashboardSettings;
	} );

	it( 'returns a value from the wc-admin settings', () => {
		const { getAdminSetting } = loadAdminSettings( {
			wcAdmin: { siteTitle: 'My Store' },
		} );

		expect( getAdminSetting( 'siteTitle' ) ).toBe( 'My Store' );
	} );

	it( 'lets the storeSuiteDashboardSettings global override wc-admin values', () => {
		const { getAdminSetting } = loadAdminSettings( {
			wcAdmin: { siteTitle: 'My Store' },
			dashboardGlobal: { siteTitle: 'StoreSuite Shop', custom: 'yes' },
		} );

		expect( getAdminSetting( 'siteTitle' ) ).toBe( 'StoreSuite Shop' );
		expect( getAdminSetting( 'custom' ) ).toBe( 'yes' );
	} );

	it( 'returns the fallback for unknown keys', () => {
		const { getAdminSetting } = loadAdminSettings();

		expect( getAdminSetting( 'missing' ) ).toBe( false );
		expect( getAdminSetting( 'missing', 'default' ) ).toBe( 'default' );
	} );

	it( 'never exposes mutable sources, returning the fallback instead', () => {
		const { getAdminSetting } = loadAdminSettings( {
			wcAdmin: {
				wcAdminSettings: { secret: true },
				preloadSettings: { secret: true },
			},
		} );

		expect( getAdminSetting( 'wcAdminSettings', 'blocked' ) ).toBe(
			'blocked'
		);
		expect( getAdminSetting( 'preloadSettings', 'blocked' ) ).toBe(
			'blocked'
		);
	} );

	it( 'passes the value and fallback through the filter callback', () => {
		const { getAdminSetting } = loadAdminSettings( {
			wcAdmin: { perPage: '25' },
		} );

		const asNumber = ( value ) => Number( value );

		expect( getAdminSetting( 'perPage', 10, asNumber ) ).toBe( 25 );
		expect(
			getAdminSetting( 'absent', 10, ( value, fallback ) => fallback )
		).toBe( 10 );
	} );
} );
