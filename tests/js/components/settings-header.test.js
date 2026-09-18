/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import SettingsHeader from '../../../src/Components/SettingsHeader';

describe( 'SettingsHeader', () => {
	it( 'renders the title with the icon when no logo is set', () => {
		const Icon = () => <svg data-testid="header-icon" />;

		render( <SettingsHeader icon={ Icon } title="General Settings" /> );

		expect(
			screen.getByRole( 'heading', { name: 'General Settings' } )
		).toBeInTheDocument();
		expect( screen.getByTestId( 'header-icon' ) ).toBeInTheDocument();
	} );

	it( 'renders the logo instead of icon and title when a logo is set', () => {
		render(
			<SettingsHeader
				logo="https://example.test/logo.png"
				title="StoreSuite"
			/>
		);

		const logo = screen.getByRole( 'img', { name: 'StoreSuite' } );
		expect( logo ).toHaveAttribute(
			'src',
			'https://example.test/logo.png'
		);
		expect( screen.queryByRole( 'heading' ) ).not.toBeInTheDocument();
	} );

	it( 'renders the subtitle only when provided', () => {
		const { rerender } = render( <SettingsHeader title="Settings" /> );

		expect(
			document.querySelector( '.storesuite-header-subtitle' )
		).toBeNull();

		rerender(
			<SettingsHeader
				title="Settings"
				subTitle="Configure your storefront dashboard"
			/>
		);

		expect(
			screen.getByText( 'Configure your storefront dashboard' )
		).toBeInTheDocument();
	} );

	it( 'renders custom actions in the actions slot', () => {
		render(
			<SettingsHeader
				title="Settings"
				actions={ <button>Save Changes</button> }
			/>
		);

		expect(
			screen.getByRole( 'button', { name: 'Save Changes' } )
		).toBeInTheDocument();
	} );
} );
