/**
 * Staff Manager — top-level admin screen.
 *
 * Lives inside the module (not in core `src/`) so the React surface is
 * colocated with the PHP that powers it. Deactivating the module also
 * prevents this bundle from being enqueued, so deactivated modules ship zero
 * JS to the browser.
 *
 * Imports avoid `react-router-dom` and `Components/icons` so the module
 * bundle stays small — react-router-dom would otherwise be duplicated, and
 * core's `icons.js` lives outside the module.
 *
 * @package StoreSuite
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardBody,
	SelectControl,
	Spinner,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import {
	CheckBadgeIcon,
	ExclamationCircleIcon,
} from '@heroicons/react/24/outline';

const SCREEN_PATH = '/storesuite/v1/staff-manager/screen';

const REDIRECT_OPTIONS = [
	{ value: 'dashboard', label: __( 'Dashboard home', 'storesuite' ) },
	{ value: 'products', label: __( 'Products list', 'storesuite' ) },
	{ value: 'orders', label: __( 'Orders list', 'storesuite' ) },
];

const StaffManagerAdmin = () => {
	const [ values, setValues ] = useState( null );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ isSaving, setIsSaving ] = useState( false );
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );

	useEffect( () => {
		let cancelled = false;

		apiFetch( { path: SCREEN_PATH } )
			.then( ( response ) => {
				if ( ! cancelled ) {
					setValues( response ?? {} );
				}
			} )
			.catch( ( err ) => {
				if ( ! cancelled ) {
					createErrorNotice( err.message, {
						type: 'snackbar',
						id: 'storesuite-staff-screen-fetch-error',
					} );
				}
			} )
			.finally( () => {
				if ( ! cancelled ) {
					setIsLoading( false );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [ createErrorNotice ] );

	const onChange = ( key, next ) => {
		setValues( ( current ) => ( { ...current, [ key ]: next } ) );
	};

	const handleSubmit = async ( event ) => {
		event.preventDefault();
		setIsSaving( true );

		try {
			const response = await apiFetch( {
				path: SCREEN_PATH,
				method: 'POST',
				data: values,
			} );

			setValues( response ?? {} );

			createSuccessNotice(
				__( 'Staff screen settings saved.', 'storesuite' ),
				{
					type: 'snackbar',
					id: 'storesuite-staff-screen-saved',
					isDismissible: false,
					icon: (
						<CheckBadgeIcon
							style={ {
								width: '24px',
								height: '24px',
								color: 'rgb(16 185 129)',
							} }
						/>
					),
				}
			);
		} catch ( err ) {
			createErrorNotice( err.message, {
				type: 'snackbar',
				id: 'storesuite-staff-screen-save-error',
				icon: (
					<ExclamationCircleIcon
						style={ {
							width: '24px',
							height: '24px',
							color: 'rgb(244 63 94)',
						} }
					/>
				),
			} );
		} finally {
			setIsSaving( false );
		}
	};

	return (
		<div
			className="storesuite-section storesuite-section--narrow"
			id="storesuite-staff-manager-admin"
		>
			<Card className="storesuite-form-header-card">
				<CardBody className="storesuite-form-section-header">
					<h3 className="storesuite-section-title">
						{ __( 'Staff', 'storesuite' ) }
					</h3>
					<p className="storesuite-section-description">
						{ __(
							'These options control the dedicated Staff workspace. The tab itself appears in the top nav whenever the Staff Manager module is active — toggle it from the',
							'storesuite'
						) }{ ' ' }
						<a href="#/modules">
							{ __( 'Modules tab', 'storesuite' ) }
						</a>
						.
					</p>
				</CardBody>
			</Card>

			<Card>
				<CardBody className="storesuite-form-section-body">
					{ isLoading || values === null ? (
						<div className="storesuite-loading">
							<Spinner />
						</div>
					) : (
						<form onSubmit={ handleSubmit }>
							<div className="storesuite-settings-group">
								<TextControl
									label={ __(
										'Landing heading',
										'storesuite'
									) }
									help={ __(
										'Greeting shown at the top of the staff workspace.',
										'storesuite'
									) }
									value={ values.landing_heading ?? '' }
									onChange={ ( next ) =>
										onChange( 'landing_heading', next )
									}
									__next40pxDefaultSize
									__nextHasNoMarginBottom
								/>
							</div>

							<div className="storesuite-settings-group">
								<SelectControl
									label={ __(
										'Post-login landing page',
										'storesuite'
									) }
									help={ __(
										'Where staff members land after they sign in.',
										'storesuite'
									) }
									value={
										values.staff_dashboard_redirect ??
										'dashboard'
									}
									options={ REDIRECT_OPTIONS }
									onChange={ ( next ) =>
										onChange(
											'staff_dashboard_redirect',
											next
										)
									}
									__next40pxDefaultSize
									__nextHasNoMarginBottom
								/>
							</div>

							<div className="storesuite-settings-group">
								<ToggleControl
									label={ __(
										'Allow new staff signups',
										'storesuite'
									) }
									help={ __(
										'Show a self-service signup form so prospective staff can request access.',
										'storesuite'
									) }
									checked={
										!! values.enable_new_staff_signup
									}
									onChange={ ( next ) =>
										onChange(
											'enable_new_staff_signup',
											next
										)
									}
									__nextHasNoMarginBottom
								/>
							</div>

							<div className="storesuite-settings-group">
								<ToggleControl
									label={ __(
										'Notify admin on new signups',
										'storesuite'
									) }
									help={ __(
										'Email the site administrator whenever a new staff account requests access.',
										'storesuite'
									) }
									checked={
										!! values.notify_admin_on_signup
									}
									onChange={ ( next ) =>
										onChange(
											'notify_admin_on_signup',
											next
										)
									}
									__nextHasNoMarginBottom
								/>
							</div>

							<Button
								variant="primary"
								type="submit"
								isBusy={ isSaving }
								disabled={ isSaving }
							>
								{ isSaving && <Spinner /> }
								{ __( 'Save Changes', 'storesuite' ) }
							</Button>
						</form>
					) }
				</CardBody>
			</Card>
		</div>
	);
};

export default StaffManagerAdmin;
