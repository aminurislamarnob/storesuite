/**
 * WordPress dependencies
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
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

/**
 * External dependencies
 */
import { Link, useParams } from 'react-router-dom';

/**
 * Internal dependencies
 */
import { CheckBadgeIcon, ChevronLeftIcon, ExclamationCircleIcon } from './icons';

const MODULES_PATH = '/storesuite/v1/modules';

const renderField = ( key, field, value, onChange ) => {
	const label = field.label || key;
	const help = field.description || undefined;

	switch ( field.type ) {
		case 'toggle':
			return (
				<ToggleControl
					key={ key }
					label={ label }
					help={ help }
					checked={ !! value }
					onChange={ ( next ) => onChange( key, next ) }
					__nextHasNoMarginBottom
				/>
			);

		case 'select': {
			const options = Object.entries( field.options || {} ).map(
				( [ optValue, optLabel ] ) => ( {
					value: optValue,
					label: optLabel,
				} )
			);
			return (
				<SelectControl
					key={ key }
					label={ label }
					help={ help }
					value={ value ?? '' }
					options={ options }
					onChange={ ( next ) => onChange( key, next ) }
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			);
		}

		case 'number':
			return (
				<TextControl
					key={ key }
					type="number"
					label={ label }
					help={ help }
					value={ value ?? '' }
					min={ field.min }
					max={ field.max }
					onChange={ ( next ) => onChange( key, next ) }
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			);

		case 'text':
		default:
			return (
				<TextControl
					key={ key }
					label={ label }
					help={ help }
					value={ value ?? '' }
					onChange={ ( next ) => onChange( key, next ) }
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			);
	}
};

const ModuleSettings = () => {
	const { slug } = useParams();
	const [ moduleName, setModuleName ] = useState( '' );
	const [ schema, setSchema ] = useState( {} );
	const [ values, setValues ] = useState( {} );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ notFound, setNotFound ] = useState( false );
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );

	useEffect( () => {
		let cancelled = false;
		setIsLoading( true );

		apiFetch( { path: `${ MODULES_PATH }/${ slug }/settings` } )
			.then( ( response ) => {
				if ( cancelled ) {
					return;
				}
				setModuleName( response?.name ?? '' );
				setSchema( response?.schema ?? {} );
				setValues( response?.values ?? {} );
			} )
			.catch( ( err ) => {
				if ( cancelled ) {
					return;
				}
				if ( err?.data?.status === 404 ) {
					setNotFound( true );
				} else {
					createErrorNotice( err.message, {
						type: 'snackbar',
						id: `storesuite-module-settings-error-${ slug }`,
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
	}, [ slug, createErrorNotice ] );

	const onFieldChange = useCallback( ( key, next ) => {
		setValues( ( current ) => ( { ...current, [ key ]: next } ) );
	}, [] );

	const handleSubmit = async ( event ) => {
		event.preventDefault();
		setIsSaving( true );

		try {
			const response = await apiFetch( {
				path: `${ MODULES_PATH }/${ slug }/settings`,
				method: 'POST',
				data: { values },
			} );

			setModuleName( response?.name ?? '' );
			setSchema( response?.schema ?? {} );
			setValues( response?.values ?? {} );

			createSuccessNotice( __( 'Module settings saved.', 'storesuite' ), {
				type: 'snackbar',
				id: `storesuite-module-settings-saved-${ slug }`,
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
			} );
		} catch ( err ) {
			createErrorNotice( err.message, {
				type: 'snackbar',
				id: `storesuite-module-settings-save-error-${ slug }`,
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

	if ( notFound ) {
		return (
			<div className="storesuite-section storesuite-section--narrow">
				<Card>
					<CardBody className="storesuite-form-section-body">
						<p>
							{ __(
								'This module either does not exist or has no configurable settings.',
								'storesuite'
							) }
						</p>
						<Link to="/modules">
							<Button variant="secondary">
								{ __( 'Back to modules', 'storesuite' ) }
							</Button>
						</Link>
					</CardBody>
				</Card>
			</div>
		);
	}

	const fieldEntries = Object.entries( schema );

	return (
		<div
			className="storesuite-section storesuite-section--narrow"
			id="storesuite-module-settings"
		>
			<form onSubmit={ handleSubmit }>
				<Card className="storesuite-form-header-card">
					<CardBody className="storesuite-form-section-header">
						<h3 className="storesuite-section-title">
							<Link
								to="/modules"
								className="storesuite-module-settings-back"
								aria-label={ __(
									'Back to modules',
									'storesuite'
								) }
							>
								<ChevronLeftIcon />
							</Link>
							{ moduleName
								? sprintf(
										/* translators: %s: module name */
										__( '%s Settings', 'storesuite' ),
										moduleName
								  )
								: __( 'Module Settings', 'storesuite' ) }
						</h3>
						<p className="storesuite-section-description">
							{ __(
								'Configure how this module behaves on your store.',
								'storesuite'
							) }
						</p>
					</CardBody>
				</Card>
				<Card>
					<CardBody className="storesuite-form-section-body">
						{ isLoading ? (
							<div className="storesuite-loading">
								<Spinner />
							</div>
						) : (
							<>
								{ fieldEntries.length === 0 && (
									<p>
										{ __(
											'No settings are exposed by this module.',
											'storesuite'
										) }
									</p>
								) }
								{ fieldEntries.map( ( [ key, field ] ) => (
									<div
										key={ key }
										className="storesuite-settings-group"
									>
										{ renderField(
											key,
											field,
											values[ key ],
											onFieldChange
										) }
										{ !! field.link?.url && (
											<a
												className="storesuite-settings-field-link"
												href={ field.link.url }
											>
												{ field.link.label ||
													__(
														'Configure',
														'storesuite'
													) }
											</a>
										) }
									</div>
								) ) }
								{ fieldEntries.length > 0 && (
									<Button
										variant="primary"
										type="submit"
										isBusy={ isSaving }
										disabled={ isSaving }
									>
										{ isSaving && <Spinner /> }
										{ __( 'Save Changes', 'storesuite' ) }
									</Button>
								) }
							</>
						) }
					</CardBody>
				</Card>
			</form>
		</div>
	);
};

export default ModuleSettings;
