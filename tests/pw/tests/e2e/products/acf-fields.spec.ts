import { test, expect } from '../../../utils/test';
import { MANAGER_STATE } from '../../../utils/authStates';
import { dashboardPath } from '../../../utils/testData';
import { createProductViaApi, isPluginActive } from '../../../utils/apiUtils';
import { AcfCardPage } from './acfCardPage';

test.use( { storageState: MANAGER_STATE } );

/**
 * The "E2E Custom Fields" group seeded by bin/e2e-provision.sh: every
 * supported type, one conditional rule (E2E Dependent shows when E2E Switch
 * is on) and one unsupported type (relationship).
 */
const GROUP = 'group_e2e_acf';
const IMAGE_TITLE = 'E2E Image';

test.describe( 'ACF fields on the product form', () => {
	test.beforeAll( async () => {
		test.skip( ! ( await isPluginActive( 'advanced-custom-fields' ) ), 'ACF is not active on this site.' );
	} );

	test( 'every supported type round-trips through the edit form', async ( { page } ) => {
		const productId = await createProductViaApi( `ACF e2e ${ Date.now() }` );
		await page.goto( `${ dashboardPath }/edit-product/${ productId }/` );

		const acf = new AcfCardPage( page, GROUP );
		await expect( acf.card ).toBeVisible();
		await expect( acf.card.locator( '.storesuite-card-title' ) ).toHaveText( 'E2E Custom Fields' );

		// Layout-only and unsupported fields render but carry no input.
		await expect( acf.field( 'field_e2e_message' ) ).toContainText( 'Rendered message.' );
		await expect( acf.field( 'field_e2e_separator' ).locator( 'hr.storesuite-acf-separator' ) ).toBeVisible();
		await expect( acf.field( 'field_e2e_related' ) ).toContainText( "can't be edited here" );
		await expect( acf.field( 'field_e2e_related' ).locator( 'input, select, textarea' ) ).toHaveCount( 0 );

		// Conditional field: hidden until the switch is on, live.
		await expect( acf.field( 'field_e2e_dependent' ) ).toBeHidden();
		await acf.setSwitch( 'field_e2e_switch', true );
		await expect( acf.field( 'field_e2e_dependent' ) ).toBeVisible();
		await acf.setSwitch( 'field_e2e_switch', false );
		await expect( acf.field( 'field_e2e_dependent' ) ).toBeHidden();
		await acf.setSwitch( 'field_e2e_switch', true );

		await acf.input( 'field_e2e_text' ).fill( 'Cotton' );
		await acf.input( 'field_e2e_textarea' ).fill( 'Line one\nLine two' );
		await acf.input( 'field_e2e_number' ).fill( '42' );
		await acf.input( 'field_e2e_range' ).fill( '7' );
		await acf.input( 'field_e2e_email' ).fill( 'shop@example.com' );
		await acf.input( 'field_e2e_url' ).fill( 'https://example.com/spec' );
		await acf.input( 'field_e2e_password' ).fill( 'hunter2' );
		await acf.input( 'field_e2e_color' ).fill( '#ff8800' );
		await acf.input( 'field_e2e_select' ).selectOption( 'green' );
		await acf.selectMulti( 'field_e2e_multi', [ 'red', 'blue' ] );
		await acf.choice( 'field_e2e_checkbox', 'green' ).check();
		await acf.choice( 'field_e2e_checkbox', 'blue' ).check();
		await acf.choice( 'field_e2e_radio', 'blue' ).check();
		await acf.field( 'field_e2e_buttons' ).locator( 'label', { hasText: 'Red' } ).click();
		await acf.input( 'field_e2e_dependent' ).fill( 'Only when on' );
		await acf.input( 'field_e2e_date' ).fill( '2027-01-15' );
		await acf.input( 'field_e2e_datetime' ).fill( '2027-01-15T09:05' );
		await acf.input( 'field_e2e_time' ).fill( '23:15' );
		await acf.fillEditor( 'field_e2e_wysiwyg', 'Rich text body' );
		await acf.pickImage( 'field_e2e_image', IMAGE_TITLE );
		await expect( acf.imageId( 'field_e2e_image' ) ).not.toHaveValue( '' );
		const imageId = await acf.imageId( 'field_e2e_image' ).inputValue();

		await acf.save();
		await expect( acf.dialog() ).toContainText( /success/i );

		// Everything persisted.
		await page.reload();
		const saved = new AcfCardPage( page, GROUP );
		await expect( saved.input( 'field_e2e_text' ) ).toHaveValue( 'Cotton' );
		await expect( saved.input( 'field_e2e_textarea' ) ).toHaveValue( 'Line one\nLine two' );
		await expect( saved.input( 'field_e2e_number' ) ).toHaveValue( '42' );
		await expect( saved.input( 'field_e2e_range' ) ).toHaveValue( '7' );
		await expect( saved.input( 'field_e2e_email' ) ).toHaveValue( 'shop@example.com' );
		await expect( saved.input( 'field_e2e_url' ) ).toHaveValue( 'https://example.com/spec' );
		await expect( saved.input( 'field_e2e_password' ) ).toHaveValue( '', { timeout: 1000 } );
		await expect( saved.input( 'field_e2e_color' ) ).toHaveValue( '#ff8800' );
		await expect( saved.input( 'field_e2e_select' ) ).toHaveValue( 'green' );
		await expect( saved.field( 'field_e2e_multi' ).locator( 'select' ) ).toHaveValues( [ 'red', 'blue' ] );
		await expect( saved.choice( 'field_e2e_checkbox', 'green' ) ).toBeChecked();
		await expect( saved.choice( 'field_e2e_checkbox', 'blue' ) ).toBeChecked();
		await expect( saved.choice( 'field_e2e_checkbox', 'red' ) ).not.toBeChecked();
		await expect( saved.choice( 'field_e2e_radio', 'blue' ) ).toBeChecked();
		await expect( saved.choice( 'field_e2e_buttons', 'red' ) ).toBeChecked();
		await expect( saved.field( 'field_e2e_switch' ).locator( 'input[type="checkbox"]' ) ).toBeChecked();
		await expect( saved.field( 'field_e2e_dependent' ) ).toBeVisible();
		await expect( saved.input( 'field_e2e_dependent' ) ).toHaveValue( 'Only when on' );
		await expect( saved.input( 'field_e2e_date' ) ).toHaveValue( '2027-01-15' );
		await expect( saved.input( 'field_e2e_datetime' ) ).toHaveValue( '2027-01-15T09:05' );
		await expect( saved.input( 'field_e2e_time' ) ).toHaveValue( '23:15' );
		await expect( saved.editorText( 'field_e2e_wysiwyg' ) ).toContainText( 'Rich text body' );
		await expect( saved.imageId( 'field_e2e_image' ) ).toHaveValue( imageId );
		await expect( saved.field( 'field_e2e_image' ).locator( '.preview-image img' ) ).toBeVisible();
	} );

	test( 'a required field violation shows the error dialog and saves nothing', async ( { page } ) => {
		const name = `ACF invalid ${ Date.now() }`;
		const productId = await createProductViaApi( name );
		await page.goto( `${ dashboardPath }/edit-product/${ productId }/` );

		const acf = new AcfCardPage( page, GROUP );
		await expect( acf.card ).toBeVisible();

		// Bypass the browser's own required check so the server rule is exercised.
		await acf.form.evaluate( ( form ) => form.setAttribute( 'novalidate', 'novalidate' ) );
		await acf.input( 'field_e2e_text' ).fill( '' );
		await acf.input( 'field_e2e_number' ).fill( '500' );
		await acf.form.locator( '#product_title' ).fill( `${ name } renamed` );

		await acf.save();
		await expect( acf.dialog() ).toContainText( 'E2E Text value is required' );
		await expect( acf.dialog() ).toContainText( 'E2E Number' );

		await page.reload();
		await expect( page.locator( '#product_title' ) ).toHaveValue( name );
		await expect( new AcfCardPage( page, GROUP ).input( 'field_e2e_number' ) ).toHaveValue( '' );
	} );
} );
