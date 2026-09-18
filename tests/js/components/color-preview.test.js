/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import ColorPreview from '../../../src/Components/ColorPreview';

const COLORS = {
	sidebarBackground: 'rgb(17, 24, 39)',
	sidebarActiveBackground: 'rgb(79, 70, 229)',
	sidebarActiveText: 'rgb(255, 255, 255)',
	sidebarMenuText: 'rgb(156, 163, 175)',
	buttonBackground: 'rgb(79, 70, 229)',
	buttonText: 'rgb(255, 255, 255)',
	buttonHoverBackground: 'rgb(67, 56, 202)',
	buttonHoverText: 'rgb(243, 244, 246)',
	borderColor: 'rgb(209, 213, 219)',
};

describe( 'ColorPreview', () => {
	it( 'applies the sidebar background color', () => {
		const { container } = render( <ColorPreview colors={ COLORS } /> );

		expect(
			container.querySelector( '.storesuite-preview-sidebar' )
		).toHaveStyle( { backgroundColor: COLORS.sidebarBackground } );
	} );

	it( 'styles the active menu item differently from inactive ones', () => {
		const { container } = render( <ColorPreview colors={ COLORS } /> );

		const items = container.querySelectorAll(
			'.storesuite-preview-menu-item'
		);
		expect( items ).toHaveLength( 6 );

		expect( items[ 0 ] ).toHaveStyle( {
			backgroundColor: COLORS.sidebarActiveBackground,
		} );
		expect( items[ 0 ].querySelector( 'span' ) ).toHaveStyle( {
			backgroundColor: COLORS.sidebarActiveText,
		} );
		// toHaveStyle normalizes 'transparent' away, so read the inline
		// style directly for this one.
		expect( items[ 1 ].style.backgroundColor ).toBe( 'transparent' );
		expect( items[ 1 ].querySelector( 'span' ) ).toHaveStyle( {
			backgroundColor: COLORS.sidebarMenuText,
		} );
	} );

	it( 'applies button, hover, and border colors to the sample buttons', () => {
		render( <ColorPreview colors={ COLORS } /> );

		expect( screen.getByText( 'Button' ) ).toHaveStyle( {
			backgroundColor: COLORS.buttonBackground,
			color: COLORS.buttonText,
		} );
		expect( screen.getByText( 'Button Hover' ) ).toHaveStyle( {
			backgroundColor: COLORS.buttonHoverBackground,
			color: COLORS.buttonHoverText,
		} );
		expect( screen.getByText( 'Button Border' ) ).toHaveStyle( {
			borderColor: COLORS.borderColor,
		} );
	} );

	it( 'renders the preview label', () => {
		render( <ColorPreview colors={ COLORS } /> );

		expect( screen.getByText( 'Preview' ) ).toBeInTheDocument();
	} );
} );
