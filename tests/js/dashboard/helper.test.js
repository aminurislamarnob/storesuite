/**
 * Internal dependencies
 */
import {
	mapToAnalyticsRoute,
	mapToDashboardRoute,
	redirectIfAdminUrl,
} from '../../../src/dashboard/utils/helper';
import {
	getMockHistory,
	resetMockHistory,
} from '../__mocks__/woocommerce/navigation';

jest.mock( '../../../src/dashboard/config', () => ( {
	storeSuiteDashboard: {
		dashboardPath: '/my-shop/',
		reportsPath: '/my-shop/reports/',
	},
} ) );

// window.location itself is unforgeable in jsdom; same-origin history
// navigation is the supported way to point the tests at a different URL.
const setLocation = ( pathAndQuery ) => {
	window.history.replaceState( null, '', pathAndQuery );
};

describe( 'dashboard/utils/helper', () => {
	afterEach( () => {
		resetMockHistory();
	} );

	describe( 'mapToAnalyticsRoute', () => {
		it( 'returns non-admin URLs untouched', () => {
			const url = 'https://example.test/my-shop/?report=products';
			expect( mapToAnalyticsRoute( url ) ).toBe( url );
		} );

		it( 'maps an analytics path to a report param on the reports base', () => {
			const url =
				'https://example.test/wp-admin/admin.php?page=wc-admin&path=%2Fanalytics%2Fproducts';
			expect( mapToAnalyticsRoute( url ) ).toBe(
				'/my-shop/reports/?report=products'
			);
		} );

		it( 'preserves query params other than page and path', () => {
			const url =
				'https://example.test/wp-admin/admin.php?page=wc-admin&path=%2Fanalytics%2Frevenue&period=month&compare=previous_year';
			expect( mapToAnalyticsRoute( url ) ).toBe(
				'/my-shop/reports/?report=revenue&period=month&compare=previous_year'
			);
		} );

		it( 'drops page and path without adding a report when the path is not an analytics one', () => {
			const url =
				'https://example.test/wp-admin/admin.php?page=wc-admin&path=%2Fcustomers&period=week';
			expect( mapToAnalyticsRoute( url ) ).toBe(
				'/my-shop/reports/?period=week'
			);
		} );

		it( 'returns an unparseable admin.php URL unchanged', () => {
			const url = 'admin.php?page=wc-admin&path=%2Fanalytics%2Fproducts';
			expect( mapToAnalyticsRoute( url ) ).toBe( url );
		} );
	} );

	describe( 'mapToDashboardRoute', () => {
		it( 'returns non-admin URLs untouched', () => {
			const url = 'https://example.test/my-shop/';
			expect( mapToDashboardRoute( url ) ).toBe( url );
		} );

		it( 'maps to the dashboard base preserving period params', () => {
			const url =
				'https://example.test/wp-admin/admin.php?page=wc-admin&period=quarter&compare=previous_period';
			expect( mapToDashboardRoute( url ) ).toBe(
				'/my-shop/?period=quarter&compare=previous_period'
			);
		} );

		it( 'produces a bare base when only page/path params exist', () => {
			const url =
				'https://example.test/wp-admin/admin.php?page=wc-admin&path=%2Fhome';
			expect( mapToDashboardRoute( url ) ).toBe( '/my-shop/' );
		} );
	} );

	describe( 'redirectIfAdminUrl', () => {
		it( 'does nothing when the current URL is not an admin.php URL', () => {
			setLocation( '/my-shop/?report=products' );
			redirectIfAdminUrl();
			expect( getMockHistory().replace ).not.toHaveBeenCalled();
		} );

		it( 'replaces an analytics admin URL with the mapped analytics route', () => {
			setLocation(
				'/wp-admin/admin.php?page=wc-admin&path=%2Fanalytics%2Forders&period=month'
			);
			redirectIfAdminUrl();
			expect( getMockHistory().replace ).toHaveBeenCalledWith(
				'/my-shop/reports/?report=orders&period=month'
			);
		} );

		it( 'replaces a non-analytics admin URL with the dashboard route', () => {
			setLocation( '/wp-admin/admin.php?page=wc-admin&period=week' );
			redirectIfAdminUrl();
			expect( getMockHistory().replace ).toHaveBeenCalledWith(
				'/my-shop/?period=week'
			);
		} );
	} );
} );
