import { __ } from '@wordpress/i18n';
import { useState, useEffect, useCallback } from '@wordpress/element';
import {
	Button,
	Spinner,
	Card,
	CardBody,
	SnackbarList,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { Link, Outlet, useLocation } from 'react-router-dom';
import { useSelect, useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';

import { useSettings } from '../context/SettingsContext';
import {
	BellIcon,
	ClockIcon,
	CodeBracketSquareIcon,
	GearIcon,
	PaletteIcon,
	PuzzlePieceIcon,
	SparklesIcon,
	Squares2X2Icon,
} from './icons';
import SettingsHeader from './SettingsHeader';

const BUILT_IN_TABS = [
	{ to: '/', icon: GearIcon, label: __( 'General', 'storesuite' ) },
	{
		to: '/appearance-settings',
		icon: PaletteIcon,
		label: __( 'Appearance', 'storesuite' ),
	},
	{
		to: '/pagination-settings',
		icon: CodeBracketSquareIcon,
		label: __( 'Pagination', 'storesuite' ),
	},
	{
		to: '/ai-settings',
		icon: SparklesIcon,
		label: __( 'AI', 'storesuite' ),
	},
	{
		to: '/notifications-settings',
		icon: BellIcon,
		label: __( 'Notifications', 'storesuite' ),
	},
	{
		to: '/modules',
		icon: PuzzlePieceIcon,
		label: __( 'Modules', 'storesuite' ),
	},
	{
		to: '/changelog',
		icon: ClockIcon,
		label: __( 'Changelog', 'storesuite' ),
	},
];

export const MODULES_CHANGED_EVENT = 'storesuite:modules-changed';

const SKELETON_WIDTHS = [ 120, 110, 100, 90 ];

const Layout = () => {
	const { isLoading } = useSettings();
	const { pathname } = useLocation();
	const [ moduleTabs, setModuleTabs ] = useState( [] );

	const notices = useSelect( ( select ) =>
		select( noticesStore ).getNotices()
	);
	const { removeNotice } = useDispatch( noticesStore );
	const snackbarNotices = notices.filter(
		( notice ) => notice.type === 'snackbar'
	);

	const refreshModuleTabs = useCallback( () => {
		apiFetch( { path: '/storesuite/v1/modules' } )
			.then( ( modules ) => {
				if ( ! Array.isArray( modules ) ) {
					setModuleTabs( [] );
					return;
				}
				const extras = modules
					.filter(
						( module ) =>
							module.active && Array.isArray( module.admin_tabs )
					)
					.flatMap( ( module ) =>
						module.admin_tabs.map( ( tab ) => ( {
							to: tab.to,
							label: tab.label,
							icon: PuzzlePieceIcon,
						} ) )
					);
				setModuleTabs( extras );
			} )
			.catch( () => setModuleTabs( [] ) );
	}, [] );

	useEffect( () => {
		refreshModuleTabs();

		const listener = () => refreshModuleTabs();
		window.addEventListener( MODULES_CHANGED_EVENT, listener );

		return () => {
			window.removeEventListener( MODULES_CHANGED_EVENT, listener );
		};
	}, [ refreshModuleTabs ] );

	// Insert module-injected tabs immediately before the Modules tab so the
	// Modules entry stays anchored to the right edge of the nav.
	const modulesIndex = BUILT_IN_TABS.findIndex(
		( tab ) => tab.to === '/modules'
	);
	const tabs =
		modulesIndex === -1
			? [ ...BUILT_IN_TABS, ...moduleTabs ]
			: [
					...BUILT_IN_TABS.slice( 0, modulesIndex ),
					...moduleTabs,
					...BUILT_IN_TABS.slice( modulesIndex ),
			  ];

	return (
		<div className="storesuite-admin-app">
			<SettingsHeader
				icon={ Squares2X2Icon }
				logo={ window.storeSuiteAdmin?.logoUrl }
				title={ __( 'StoreSuite', 'storesuite' ) }
				subTitle={ __(
					'Configure your frontend dashboard pages, appearance, and pagination.',
					'storesuite'
				) }
				actions={
					<>
						<Button
							variant="secondary"
							href="https://storesuite.dev/docs/"
							target="_blank"
							rel="noreferrer"
						>
							{ __( 'Documentation', 'storesuite' ) }
						</Button>
						<Button
							variant="primary"
							href="https://buymeacoffee.com/aiarnob"
							target="_blank"
							rel="noreferrer"
						>
							{ __( 'Support Me', 'storesuite' ) }
						</Button>
					</>
				}
			/>

			<main className="storesuite-main-content storesuite-setting-wrapper">
				<div className="storesuite-content-body">
					{ isLoading ? (
						<>
							<div className="storesuite-hash-nav">
								{ SKELETON_WIDTHS.map( ( width ) => (
									<div
										key={ width }
										className="storesuite-skeleton-tab"
										style={ { width: `${ width }px` } }
									/>
								) ) }
							</div>
							<div className="storesuite-section">
								<Card>
									<CardBody className="storesuite-form-section-body">
										<div className="storesuite-loading">
											<Spinner />
										</div>
									</CardBody>
								</Card>
							</div>
						</>
					) : (
						<>
							<div className="storesuite-hash-nav">
								{ tabs.map( ( { to, icon: Icon, label } ) => (
									<Link
										key={ to }
										to={ to }
										className={
											pathname === to ? 'is-active' : ''
										}
									>
										<Icon />
										{ label }
									</Link>
								) ) }
							</div>
							<Outlet />
						</>
					) }
				</div>
			</main>

			<SnackbarList
				notices={ snackbarNotices }
				className="components-editor-notices__snackbar"
				onRemove={ removeNotice }
			/>
		</div>
	);
};

export default Layout;
