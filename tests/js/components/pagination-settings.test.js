/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import PaginationSettings from '../../../src/Components/PaginationSettings';
import { useSettings } from '../../../src/context/SettingsContext';

jest.mock( '../../../src/context/SettingsContext', () => ( {
	useSettings: jest.fn(),
} ) );

const FIELD_LABELS = [
	'Products per page',
	'Orders per page',
	'Categories per page',
	'Tags per page',
	'Brands per page',
	'Coupons per page',
];

const mockContext = ( overrides = {} ) => {
	const context = {
		settings: {},
		isSaving: false,
		saveSettings: jest.fn(),
		...overrides,
	};
	useSettings.mockReturnValue( context );
	return context;
};

describe( 'PaginationSettings', () => {
	// @wordpress/components logs a deprecation warning for TextControl's
	// default size, which @wordpress/jest-console turns into a failure. A
	// plain function is used on purpose: jest.spyOn would return the
	// preset's existing spy, which still records the calls.
	const originalWarn = console.warn;

	beforeAll( () => {
		console.warn = () => {};
	} );

	afterAll( () => {
		console.warn = originalWarn;
	} );

	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'renders one field per list view', () => {
		mockContext();

		render( <PaginationSettings /> );

		FIELD_LABELS.forEach( ( label ) => {
			expect( screen.getByLabelText( label ) ).toBeInTheDocument();
		} );
	} );

	it( 'prefills fields from saved settings and leaves unset ones empty', () => {
		mockContext( {
			settings: {
				storesuite_product_per_page: 15,
				storesuite_coupon_per_page: '5',
			},
		} );

		render( <PaginationSettings /> );

		expect( screen.getByLabelText( 'Products per page' ) ).toHaveValue(
			'15'
		);
		expect( screen.getByLabelText( 'Coupons per page' ) ).toHaveValue(
			'5'
		);
		expect( screen.getByLabelText( 'Orders per page' ) ).toHaveValue( '' );
	} );

	it( 'submits every field keyed by its API option name', async () => {
		const user = userEvent.setup();
		const { saveSettings } = mockContext( {
			settings: { storesuite_product_per_page: '10' },
		} );

		render( <PaginationSettings /> );

		const ordersField = screen.getByLabelText( 'Orders per page' );
		await user.type( ordersField, '25' );

		await user.click(
			screen.getByRole( 'button', { name: 'Save Changes' } )
		);

		expect( saveSettings ).toHaveBeenCalledTimes( 1 );
		expect( saveSettings ).toHaveBeenCalledWith( {
			storesuite_product_per_page: '10',
			storesuite_order_per_page: '25',
			storesuite_category_per_page: '',
			storesuite_tag_per_page: '',
			storesuite_brand_per_page: '',
			storesuite_coupon_per_page: '',
		} );
	} );

	it( 'disables the save button while a save is in flight', () => {
		mockContext( { isSaving: true } );

		render( <PaginationSettings /> );

		expect(
			screen.getByRole( 'button', { name: /Save Changes/ } )
		).toBeDisabled();
	} );
} );
