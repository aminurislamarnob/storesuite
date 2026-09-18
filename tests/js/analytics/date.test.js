/**
 * Internal dependencies
 */
import {
	getDefaultDateRange,
	DEFAULT_DATE_RANGE,
} from '../../../src/analytics/utils/date';
import {
	SETTINGS_STORE_NAME,
	OPTIONS_STORE_NAME,
} from '../__mocks__/woocommerce/data';

/**
 * Builds a `@wordpress/data`-style select double where the settings store and
 * the options store return the given values.
 */
const makeSelect = ( { wcAdminSettings, option } = {} ) =>
	jest.fn( ( storeName ) => {
		if ( storeName === SETTINGS_STORE_NAME ) {
			return { getSetting: () => wcAdminSettings };
		}
		if ( storeName === OPTIONS_STORE_NAME ) {
			return { getOption: () => option };
		}
		throw new Error( `Unexpected store: ${ storeName }` );
	} );

describe( 'analytics/utils/date getDefaultDateRange', () => {
	it( 'prefers the hydrated wcAdminSettings value', () => {
		const select = makeSelect( {
			wcAdminSettings: {
				woocommerce_default_date_range: 'period=week&compare=previous_period',
			},
			option: 'period=year&compare=previous_year',
		} );

		expect( getDefaultDateRange( select ) ).toBe(
			'period=week&compare=previous_period'
		);
	} );

	it( 'falls back to the preloaded option when settings are not hydrated', () => {
		const select = makeSelect( {
			wcAdminSettings: undefined,
			option: 'period=year&compare=previous_year',
		} );

		expect( getDefaultDateRange( select ) ).toBe(
			'period=year&compare=previous_year'
		);
	} );

	it( 'falls back to the WooCommerce default when neither source has a value', () => {
		const select = makeSelect( {} );

		expect( getDefaultDateRange( select ) ).toBe( DEFAULT_DATE_RANGE );
		expect( DEFAULT_DATE_RANGE ).toBe( 'period=month&compare=previous_year' );
	} );

	it( 'treats an empty-string setting as missing', () => {
		const select = makeSelect( {
			wcAdminSettings: { woocommerce_default_date_range: '' },
			option: '',
		} );

		expect( getDefaultDateRange( select ) ).toBe( DEFAULT_DATE_RANGE );
	} );
} );
