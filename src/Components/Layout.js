import { __ } from '@wordpress/i18n';
import {
	Button,
	Spinner,
	Card,
	CardBody,
	SnackbarList,
} from '@wordpress/components';
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
	SparklesIcon,
	Squares2X2Icon,
} from './icons';
import SettingsHeader from './SettingsHeader';

const TABS = [
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
		to: '/changelog',
		icon: ClockIcon,
		label: __( 'Changelog', 'storesuite' ),
	},
];

const SKELETON_WIDTHS = [ 120, 110, 100 ];

const Layout = () => {
	const { isLoading } = useSettings();
	const { pathname } = useLocation();

	const notices = useSelect( ( select ) =>
		select( noticesStore ).getNotices()
	);
	const { removeNotice } = useDispatch( noticesStore );
	const snackbarNotices = notices.filter(
		( notice ) => notice.type === 'snackbar'
	);

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
								{ TABS.map( ( { to, icon: Icon, label } ) => (
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
