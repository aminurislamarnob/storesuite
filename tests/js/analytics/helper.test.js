/**
 * Internal dependencies
 */
import {
	mapToDashboardRoute,
	redirectIfAdminUrl,
	handleAdminLinkClick,
} from '../../../src/analytics/utils/helper';
import {
	getMockHistory,
	resetMockHistory,
} from '../__mocks__/woocommerce/navigation';

jest.mock( '../../../src/analytics/config', () => ( {
	storeSuiteConfig: {
		reportsPath: '/my-shop/reports/',
	},
} ) );

// window.location itself is unforgeable in jsdom; same-origin history
// navigation is the supported way to point the tests at a different URL.
const setLocation = ( pathAndQuery ) => {
	window.history.replaceState( null, '', pathAndQuery );
};

const makeClickEvent = ( target, overrides = {} ) => ( {
	defaultPrevented: false,
	button: 0,
	metaKey: false,
	ctrlKey: false,
	shiftKey: false,
	altKey: false,
	target,
	preventDefault: jest.fn(),
	stopImmediatePropagation: jest.fn(),
	...overrides,
} );

const makeAnchor = ( href ) => {
	const anchor = document.createElement( 'a' );
	anchor.setAttribute( 'href', href );
	document.body.appendChild( anchor );
	return anchor;
};

describe( 'analytics/utils/helper', () => {
	afterEach( () => {
		resetMockHistory();
		document.body.innerHTML = '';
	} );

	describe( 'mapToDashboardRoute', () => {
		it( 'returns non-admin URLs untouched', () => {
			const url = 'https://example.test/my-shop/reports/?report=products';
			expect( mapToDashboardRoute( url ) ).toBe( url );
		} );

		it( 'maps an analytics path to a report param', () => {
			const url =
				'https://example.test/wp-admin/admin.php?page=wc-admin&path=%2Fanalytics%2Fproducts&period=month';
			expect( mapToDashboardRoute( url ) ).toBe(
				'/my-shop/reports/?report=products&period=month'
			);
		} );

		it( 'maps the wc-admin /customers path to the customers report', () => {
			const url =
				'https://example.test/wp-admin/admin.php?page=wc-admin&path=%2Fcustomers';
			expect( mapToDashboardRoute( url ) ).toBe(
				'/my-shop/reports/?report=customers'
			);
		} );

		it( 'keeps drill-down params like filter and products', () => {
			const url =
				'https://example.test/wp-admin/admin.php?page=wc-admin&path=%2Fanalytics%2Fproducts&filter=single_product&products=42';
			expect( mapToDashboardRoute( url ) ).toBe(
				'/my-shop/reports/?report=products&filter=single_product&products=42'
			);
		} );
	} );

	describe( 'redirectIfAdminUrl', () => {
		it( 'does nothing on a frontend URL', () => {
			setLocation( '/my-shop/reports/?report=revenue' );
			redirectIfAdminUrl();
			expect( getMockHistory().replace ).not.toHaveBeenCalled();
		} );

		it( 'replaces an admin.php URL with the mapped route', () => {
			setLocation(
				'/wp-admin/admin.php?page=wc-admin&path=%2Fanalytics%2Fcoupons'
			);
			redirectIfAdminUrl();
			expect( getMockHistory().replace ).toHaveBeenCalledWith(
				'/my-shop/reports/?report=coupons'
			);
		} );
	} );

	describe( 'handleAdminLinkClick', () => {
		it( 'intercepts an internal wc-admin link and pushes the mapped route', () => {
			const anchor = makeAnchor(
				'https://example.test/wp-admin/admin.php?page=wc-admin&path=%2Fanalytics%2Forders'
			);
			const event = makeClickEvent( anchor );

			handleAdminLinkClick( event );

			expect( event.preventDefault ).toHaveBeenCalled();
			expect( event.stopImmediatePropagation ).toHaveBeenCalled();
			expect( getMockHistory().push ).toHaveBeenCalledWith(
				'/my-shop/reports/?report=orders'
			);
		} );

		it( 'resolves clicks on elements nested inside the anchor', () => {
			const anchor = makeAnchor(
				'https://example.test/wp-admin/admin.php?page=wc-admin&path=%2Fanalytics%2Fproducts'
			);
			const inner = document.createElement( 'span' );
			anchor.appendChild( inner );
			const event = makeClickEvent( inner );

			handleAdminLinkClick( event );

			expect( getMockHistory().push ).toHaveBeenCalledWith(
				'/my-shop/reports/?report=products'
			);
		} );

		it.each( [
			[ 'a modifier key is held', { metaKey: true } ],
			[ 'it is not a primary-button click', { button: 1 } ],
			[ 'the event is already handled', { defaultPrevented: true } ],
		] )( 'lets the browser handle the click when %s', ( _label, overrides ) => {
			const anchor = makeAnchor(
				'https://example.test/wp-admin/admin.php?page=wc-admin&path=%2Fanalytics%2Forders'
			);
			const event = makeClickEvent( anchor, overrides );

			handleAdminLinkClick( event );

			expect( event.preventDefault ).not.toHaveBeenCalled();
			expect( getMockHistory().push ).not.toHaveBeenCalled();
		} );

		it( 'ignores non-admin.php links such as post.php edit links', () => {
			const anchor = makeAnchor(
				'https://example.test/wp-admin/post.php?post=15&action=edit'
			);
			const event = makeClickEvent( anchor );

			handleAdminLinkClick( event );

			expect( event.preventDefault ).not.toHaveBeenCalled();
			expect( getMockHistory().push ).not.toHaveBeenCalled();
		} );

		it( 'ignores clicks that are not inside an anchor', () => {
			const div = document.createElement( 'div' );
			document.body.appendChild( div );
			const event = makeClickEvent( div );

			handleAdminLinkClick( event );

			expect( event.preventDefault ).not.toHaveBeenCalled();
			expect( getMockHistory().push ).not.toHaveBeenCalled();
		} );
	} );
} );
