import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardBody,
	Spinner,
	SelectControl,
	ToggleControl,
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

import { useSettings } from '../context/SettingsContext';
import DashboardSidebarImageControl from './DashboardSidebarImageControl';

const GeneralSettings = () => {
	const { settings, isSaving, saveSettings } = useSettings();

	const [ pages, setPages ] = useState( [] );
	const [ dashboardPage, setDashboardPage ] = useState(
		settings.storesuite_dashboard_page_id ?? ''
	);
	const [ preventAdminAccess, setPreventAdminAccess ] = useState(
		settings.storesuite_prevent_admin_access === 'yes' ||
			settings.storesuite_prevent_admin_access === true
	);
	const [ sidebarLogoId, setSidebarLogoId ] = useState(
		parseInt( settings.storesuite_dashboard_sidebar_logo_id, 10 ) || 0
	);
	const [ sidebarIconId, setSidebarIconId ] = useState(
		parseInt( settings.storesuite_dashboard_sidebar_icon_id, 10 ) || 0
	);
	const [ sidebarLogoDarkId, setSidebarLogoDarkId ] = useState(
		parseInt( settings.storesuite_dashboard_sidebar_logo_dark_id, 10 ) || 0
	);
	const [ sidebarIconDarkId, setSidebarIconDarkId ] = useState(
		parseInt( settings.storesuite_dashboard_sidebar_icon_dark_id, 10 ) || 0
	);
	const [ editHistoryEnabled, setEditHistoryEnabled ] = useState(
		settings.storesuite_edit_history_enabled !== 'no'
	);
	const [ editHistoryRetention, setEditHistoryRetention ] = useState(
		parseInt( settings.storesuite_edit_history_retention_days, 10 ) || 90
	);

	useEffect( () => {
		apiFetch( { path: '/wp/v2/pages?per_page=100&page=1' } )
			.then( ( wpPages ) => {
				setPages( [
					{
						value: '',
						label: __( 'Select dashboard page', 'storesuite' ),
						disabled: true,
					},
					...wpPages.map( ( page ) => ( {
						label: page.title.rendered,
						value: page.id,
					} ) ),
				] );
			} )
			.catch( () => {} );
	}, [] );

	const handleSubmit = ( event ) => {
		event.preventDefault();
		saveSettings( {
			storesuite_dashboard_page_id: dashboardPage,
			storesuite_prevent_admin_access: preventAdminAccess ? 'yes' : 'no',
			storesuite_dashboard_sidebar_logo_id: sidebarLogoId,
			storesuite_dashboard_sidebar_icon_id: sidebarIconId,
			storesuite_dashboard_sidebar_logo_dark_id: sidebarLogoDarkId,
			storesuite_dashboard_sidebar_icon_dark_id: sidebarIconDarkId,
			storesuite_edit_history_enabled: editHistoryEnabled ? 'yes' : 'no',
			storesuite_edit_history_retention_days: editHistoryRetention,
		} );
	};

	return (
		<div
			className="storesuite-section storesuite-section--narrow"
			id="storesuite-general-settings"
		>
			<form onSubmit={ handleSubmit }>
				<Card className="storesuite-form-header-card">
					<CardBody className="storesuite-form-section-header">
						<h3 className="storesuite-section-title">
							{ __( 'General Settings', 'storesuite' ) }
						</h3>
						<p className="storesuite-section-description">
							{ __(
								'Configure your dashboard page, sidebar branding, and admin area access.',
								'storesuite'
							) }
						</p>
					</CardBody>
				</Card>
				<Card>
					<CardBody className="storesuite-form-section-body">
						<div className="storesuite-settings-group">
							<SelectControl
								label={ __(
									'Select Dashboard Page',
									'storesuite'
								) }
								value={ dashboardPage }
								options={ pages }
								onChange={ setDashboardPage }
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						</div>
						<DashboardSidebarImageControl
							label={ __(
								'Dashboard sidebar logo',
								'storesuite'
							) }
							help={ __(
								'Shown in the expanded sidebar. If set, the site title is visually hidden but kept for screen readers.',
								'storesuite'
							) }
							darkHelp={ __(
								'Used when the dashboard is in dark mode. If empty, the light mode logo is used.',
								'storesuite'
							) }
							attachmentId={ sidebarLogoId }
							darkAttachmentId={ sidebarLogoDarkId }
							onChange={ setSidebarLogoId }
							onDarkChange={ setSidebarLogoDarkId }
						/>
						<DashboardSidebarImageControl
							label={ __(
								'Dashboard sidebar icon',
								'storesuite'
							) }
							help={ __(
								'Shown in the collapsed (icon-only) sidebar. If empty, the logo is used when collapsed when a logo is set.',
								'storesuite'
							) }
							darkHelp={ __(
								'Used when the dashboard is in dark mode. If empty, the light mode icon is used.',
								'storesuite'
							) }
							attachmentId={ sidebarIconId }
							darkAttachmentId={ sidebarIconDarkId }
							onChange={ setSidebarIconId }
							onDarkChange={ setSidebarIconDarkId }
						/>
						<div className="storesuite-settings-group admin-area-access">
							<ToggleControl
								label={ __(
									'Restrict Admin Area Access',
									'storesuite'
								) }
								help={ __(
									'Prevent shop manager from accessing the wp-admin dashboard area.',
									'storesuite'
								) }
								checked={ preventAdminAccess }
								onChange={ setPreventAdminAccess }
							/>
						</div>
						<div className="storesuite-settings-group edit-history">
							<ToggleControl
								label={ __( 'Edit history', 'storesuite' ) }
								help={ __(
									'Record the before and after values of inline and bulk edits so they can be undone from the History page.',
									'storesuite'
								) }
								checked={ editHistoryEnabled }
								onChange={ setEditHistoryEnabled }
								__nextHasNoMarginBottom
							/>
						</div>
						{ editHistoryEnabled && (
							<div className="storesuite-settings-group edit-history-retention">
								<NumberControl
									label={ __(
										'Keep history for (days)',
										'storesuite'
									) }
									help={ __(
										'Older entries are removed by a daily cleanup.',
										'storesuite'
									) }
									value={ editHistoryRetention }
									min={ 1 }
									max={ 3650 }
									onChange={ ( value ) =>
										setEditHistoryRetention(
											parseInt( value, 10 ) || 90
										)
									}
									__next40pxDefaultSize
								/>
							</div>
						) }
						<Button
							variant="primary"
							type="submit"
							isBusy={ isSaving }
							disabled={ isSaving }
						>
							{ isSaving && <Spinner /> }
							{ __( 'Save Changes', 'storesuite' ) }
						</Button>
					</CardBody>
				</Card>
			</form>
		</div>
	);
};

export default GeneralSettings;
