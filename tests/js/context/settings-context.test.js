/**
 * External dependencies
 */
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import {
	SettingsProvider,
	useSettings,
} from '../../../src/context/SettingsContext';

jest.mock( '@wordpress/api-fetch' );

const mockCreateSuccessNotice = jest.fn();
const mockCreateErrorNotice = jest.fn();

jest.mock( '@wordpress/data', () => ( {
	useDispatch: () => ( {
		createSuccessNotice: mockCreateSuccessNotice,
		createErrorNotice: mockCreateErrorNotice,
	} ),
} ) );

// Not an installed package — on the dashboard it comes from the wp.* globals.
jest.mock( '@wordpress/notices', () => ( { store: 'core/notices' } ), {
	virtual: true,
} );

/**
 * Probe component exposing the context state so tests can assert on it and
 * trigger saves.
 */
const Probe = ( { saveData, saveMessage } ) => {
	const { settings, isLoading, isSaving, saveSettings } = useSettings();

	return (
		<div>
			<span data-testid="loading">{ String( isLoading ) }</span>
			<span data-testid="saving">{ String( isSaving ) }</span>
			<span data-testid="settings">{ JSON.stringify( settings ) }</span>
			<button onClick={ () => saveSettings( saveData, saveMessage ) }>
				Save
			</button>
		</div>
	);
};

const renderWithProvider = ( props = {} ) =>
	render(
		<SettingsProvider>
			<Probe { ...props } />
		</SettingsProvider>
	);

describe( 'SettingsContext', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'fetches settings on mount and clears the loading state', async () => {
		apiFetch.mockResolvedValueOnce( { storesuite_product_per_page: '15' } );

		renderWithProvider();

		expect( screen.getByTestId( 'loading' ) ).toHaveTextContent( 'true' );

		await waitFor( () =>
			expect( screen.getByTestId( 'loading' ) ).toHaveTextContent(
				'false'
			)
		);

		expect( apiFetch ).toHaveBeenCalledWith( {
			path: '/storesuite/v1/settings',
		} );
		expect( screen.getByTestId( 'settings' ) ).toHaveTextContent(
			'"storesuite_product_per_page":"15"'
		);
	} );

	it( 'falls back to an empty settings object when the API returns nothing', async () => {
		apiFetch.mockResolvedValueOnce( null );

		renderWithProvider();

		await waitFor( () =>
			expect( screen.getByTestId( 'loading' ) ).toHaveTextContent(
				'false'
			)
		);

		expect( screen.getByTestId( 'settings' ) ).toHaveTextContent( '{}' );
	} );

	it( 'raises an error notice when the initial fetch fails', async () => {
		apiFetch.mockRejectedValueOnce( new Error( 'Forbidden' ) );

		renderWithProvider();

		await waitFor( () =>
			expect( mockCreateErrorNotice ).toHaveBeenCalledWith(
				'Forbidden',
				expect.objectContaining( { id: 'storesuite-fetch-error' } )
			)
		);
		expect( screen.getByTestId( 'loading' ) ).toHaveTextContent( 'false' );
	} );

	it( 'saves settings, stores the response, and shows a success notice', async () => {
		const user = userEvent.setup();
		apiFetch
			.mockResolvedValueOnce( {} ) // initial fetch
			.mockResolvedValueOnce( { storesuite_order_per_page: '20' } ); // save

		renderWithProvider( {
			saveData: { storesuite_order_per_page: '20' },
		} );

		await waitFor( () =>
			expect( screen.getByTestId( 'loading' ) ).toHaveTextContent(
				'false'
			)
		);

		await user.click( screen.getByRole( 'button', { name: 'Save' } ) );

		await waitFor( () =>
			expect( screen.getByTestId( 'saving' ) ).toHaveTextContent(
				'false'
			)
		);

		expect( apiFetch ).toHaveBeenLastCalledWith( {
			path: '/storesuite/v1/settings',
			method: 'POST',
			data: { storesuite_order_per_page: '20' },
		} );
		expect( screen.getByTestId( 'settings' ) ).toHaveTextContent(
			'"storesuite_order_per_page":"20"'
		);
		expect( mockCreateSuccessNotice ).toHaveBeenCalledWith(
			'Settings saved successfully!',
			expect.objectContaining( { id: 'storesuite-save-success' } )
		);
		expect( screen.getByTestId( 'saving' ) ).toHaveTextContent( 'false' );
	} );

	it( 'uses the custom message for the success notice when provided', async () => {
		const user = userEvent.setup();
		apiFetch.mockResolvedValueOnce( {} ).mockResolvedValueOnce( {} );

		renderWithProvider( {
			saveData: {},
			saveMessage: 'Module activated.',
		} );

		await waitFor( () =>
			expect( screen.getByTestId( 'loading' ) ).toHaveTextContent(
				'false'
			)
		);

		await user.click( screen.getByRole( 'button', { name: 'Save' } ) );

		await waitFor( () =>
			expect( mockCreateSuccessNotice ).toHaveBeenCalledWith(
				'Module activated.',
				expect.anything()
			)
		);
	} );

	it( 'raises an error notice and resets the saving state when a save fails', async () => {
		const user = userEvent.setup();
		apiFetch
			.mockResolvedValueOnce( {} )
			.mockRejectedValueOnce( new Error( 'Invalid value' ) );

		renderWithProvider( { saveData: { bad: true } } );

		await waitFor( () =>
			expect( screen.getByTestId( 'loading' ) ).toHaveTextContent(
				'false'
			)
		);

		await user.click( screen.getByRole( 'button', { name: 'Save' } ) );

		await waitFor( () =>
			expect( mockCreateErrorNotice ).toHaveBeenCalledWith(
				'Invalid value',
				expect.objectContaining( { id: 'storesuite-save-error' } )
			)
		);
		expect( mockCreateSuccessNotice ).not.toHaveBeenCalled();
		expect( screen.getByTestId( 'saving' ) ).toHaveTextContent( 'false' );
	} );

	it( 'throws when useSettings is used outside the provider', () => {
		// Silence React's logging for the expected throw. A plain function is
		// used on purpose: jest.spyOn would return @wordpress/jest-console's
		// existing spy, which still records (and then fails on) the calls.
		const originalError = console.error;
		console.error = () => {};

		try {
			expect( () => render( <Probe /> ) ).toThrow(
				'useSettings must be used within a SettingsProvider'
			);
		} finally {
			console.error = originalError;
		}
	} );
} );
