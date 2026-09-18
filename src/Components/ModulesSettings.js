/**
 * WordPress dependencies
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardBody,
	Spinner,
	ToggleControl,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';

/**
 * External dependencies
 */
import { Link } from 'react-router-dom';

/**
 * Internal dependencies
 */
import {
	CheckBadgeIcon,
	ExclamationCircleIcon,
	PuzzlePieceIcon,
} from './icons';
import { MODULES_CHANGED_EVENT } from './Layout';

const MODULES_PATH = '/storesuite/v1/modules';

const ModulesSettings = () => {
	const [ modules, setModules ] = useState( [] );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ busySlug, setBusySlug ] = useState( null );
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );

	useEffect( () => {
		let cancelled = false;

		apiFetch( { path: MODULES_PATH } )
			.then( ( response ) => {
				if ( ! cancelled ) {
					setModules( Array.isArray( response ) ? response : [] );
				}
			} )
			.catch( ( err ) => {
				if ( ! cancelled ) {
					createErrorNotice( err.message, {
						type: 'snackbar',
						id: 'storesuite-modules-fetch-error',
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

	const toggle = useCallback(
		async ( module ) => {
			const nextActive = ! module.active;
			const action = nextActive ? 'activate' : 'deactivate';

			setBusySlug( module.slug );

			try {
				const updated = await apiFetch( {
					path: `${ MODULES_PATH }/${ module.slug }/${ action }`,
					method: 'POST',
				} );

				setModules( ( current ) =>
					current.map( ( item ) =>
						item.slug === module.slug
							? { ...item, ...updated }
							: item
					)
				);

				// Tell the Layout to refresh its dynamic top-nav tabs so any
				// module-injected entries appear or disappear immediately.
				window.dispatchEvent( new CustomEvent( MODULES_CHANGED_EVENT ) );

				// Module bundles are enqueued by PHP on page load, so newly
				// activated modules' React screens aren't registered with the
				// router yet. Reload after a brief delay so the snackbar gets
				// to render and any newly-routable tab actually works on
				// first click. Deactivation also reloads — pages may still
				// link to a screen whose bundle is no longer present.
				if ( module.has_settings || ( module.admin_tabs?.length ?? 0 ) > 0 ) {
					window.setTimeout( () => window.location.reload(), 600 );
				}

				createSuccessNotice(
					nextActive
						? sprintf(
								// translators: %s is the module name.
								__( '%s activated.', 'storesuite' ),
								module.name
						  )
						: sprintf(
								// translators: %s is the module name.
								__( '%s deactivated.', 'storesuite' ),
								module.name
						  ),
					{
						type: 'snackbar',
						id: `storesuite-module-${ module.slug }`,
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
					id: `storesuite-module-error-${ module.slug }`,
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
				setBusySlug( null );
			}
		},
		[ createSuccessNotice, createErrorNotice ]
	);

	return (
		<div className="storesuite-section" id="storesuite-modules-settings">
			<Card className="storesuite-form-header-card">
				<CardBody className="storesuite-form-section-header">
					<h3 className="storesuite-section-title">
						{ __( 'Modules', 'storesuite' ) }
					</h3>
					<p className="storesuite-section-description">
						{ __(
							'Enable optional StoreSuite features. Each module can be turned on or off independently.',
							'storesuite'
						) }
					</p>
				</CardBody>
			</Card>

			{ isLoading && (
				<Card>
					<CardBody className="storesuite-form-section-body">
						<div className="storesuite-loading">
							<Spinner />
						</div>
					</CardBody>
				</Card>
			) }

			{ ! isLoading && modules.length === 0 && (
				<Card>
					<CardBody className="storesuite-form-section-body">
						<p>
							{ __(
								'No modules are installed yet.',
								'storesuite'
							) }
						</p>
					</CardBody>
				</Card>
			) }

			{ ! isLoading && modules.length > 0 && (
				<div className="storesuite-modules-grid">
					{ modules.map( ( module ) => {
						const isBusy = busySlug === module.slug;
						return (
							<Card
								key={ module.slug }
								className={ `storesuite-module-card${
									module.active ? ' is-active' : ''
								}` }
							>
								<CardBody className="storesuite-module-card__body">
									<div className="storesuite-module-card__head">
										<div className="storesuite-module-card__icon">
											<PuzzlePieceIcon />
										</div>
										<div className="storesuite-module-card__heading">
											<h4 className="storesuite-module-card__title">
												{ module.name }
											</h4>
											{ module.version && (
												<span className="storesuite-module-card__version">
													{ sprintf(
														// translators: %s is the module version.
														__(
															'v%s',
															'storesuite'
														),
														module.version
													) }
												</span>
											) }
										</div>
									</div>

									{ module.description && (
										<p className="storesuite-module-card__description">
											{ module.description }
										</p>
									) }

									<div className="storesuite-module-card__footer">
										<ToggleControl
											label={
												module.active
													? __(
															'Active',
															'storesuite'
													  )
													: __(
															'Inactive',
															'storesuite'
													  )
											}
											checked={ !! module.active }
											disabled={ isBusy }
											onChange={ () => toggle( module ) }
											__nextHasNoMarginBottom
										/>
										<div className="storesuite-module-card__actions">
											{ isBusy && <Spinner /> }
											{ module.has_settings && (
												<Link
													to={ `/modules/${ module.slug }` }
												>
													<Button
														variant="secondary"
														disabled={
															! module.active
														}
													>
														{ __(
															'Configure',
															'storesuite'
														) }
													</Button>
												</Link>
											) }
										</div>
									</div>
								</CardBody>
							</Card>
						);
					} ) }
				</div>
			) }
		</div>
	);
};

export default ModulesSettings;
