import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardBody,
	Spinner,
	ToggleControl,
} from '@wordpress/components';

import { useSettings } from '../context/SettingsContext';

// Store events that can record a dashboard notification. Each defaults to
// enabled until a merchant explicitly turns it off.
const NOTIFICATION_EVENTS = [
	{
		key: 'new_order',
		apiKey: 'storesuite_notification_new_order',
		label: __( 'New order', 'storesuite' ),
		help: __(
			'Notify shop managers when a customer places a new order.',
			'storesuite'
		),
	},
	{
		key: 'new_customer',
		apiKey: 'storesuite_notification_new_customer',
		label: __( 'New customer registration', 'storesuite' ),
		help: __(
			'Notify shop managers when a new customer account is registered.',
			'storesuite'
		),
	},
	{
		key: 'product_review',
		apiKey: 'storesuite_notification_product_review',
		label: __( 'New product review', 'storesuite' ),
		help: __(
			'Notify shop managers when a product review is submitted.',
			'storesuite'
		),
	},
];

// An event is on unless it was explicitly saved as "no".
const isEnabled = ( value ) => value !== 'no' && value !== false;

const NotificationsSettings = () => {
	const { settings, isSaving, saveSettings } = useSettings();

	const [ events, setEvents ] = useState( () =>
		Object.fromEntries(
			NOTIFICATION_EVENTS.map( ( { key, apiKey } ) => [
				key,
				isEnabled( settings[ apiKey ] ),
			] )
		)
	);

	const handleSubmit = ( event ) => {
		event.preventDefault();
		const data = {};
		NOTIFICATION_EVENTS.forEach( ( { key, apiKey } ) => {
			data[ apiKey ] = events[ key ] ? 'yes' : 'no';
		} );
		saveSettings( data );
	};

	return (
		<div
			className="storesuite-section storesuite-section--medium"
			id="storesuite-notifications-settings"
		>
			<form onSubmit={ handleSubmit }>
				<Card className="storesuite-form-header-card">
					<CardBody className="storesuite-form-section-header">
						<h3 className="storesuite-section-title">
							{ __( 'Notification Settings', 'storesuite' ) }
						</h3>
						<p className="storesuite-section-description">
							{ __(
								'Choose which store events show up in the dashboard notification bell.',
								'storesuite'
							) }
						</p>
					</CardBody>
				</Card>
				<Card>
					<CardBody className="storesuite-form-section-body">
						{ NOTIFICATION_EVENTS.map( ( { key, label, help } ) => (
							<div
								key={ key }
								className="storesuite-settings-group"
							>
								<ToggleControl
									label={ label }
									help={ help }
									checked={ events[ key ] }
									onChange={ ( value ) =>
										setEvents( ( prev ) => ( {
											...prev,
											[ key ]: value,
										} ) )
									}
								/>
							</div>
						) ) }
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

export default NotificationsSettings;
