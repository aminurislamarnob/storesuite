/**
 * WordPress dependencies
 */
import { createRoot } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { Card, CardBody } from '@wordpress/components';

/**
 * External dependencies
 */
import { HashRouter as Router, Routes, Route, useLocation } from 'react-router-dom';

/**
 * Internal dependencies
 */
import './Components/LayoutStyles.css';
import { SettingsProvider } from './context/SettingsContext';
import Layout from './Components/Layout';
import ColorsSettings from './Components/ColorsSettings';
import GeneralSettings from './Components/GeneralSettings';
import ModulesSettings from './Components/ModulesSettings';
import ModuleSettings from './Components/ModuleSettings';
import PaginationSettings from './Components/PaginationSettings';
import AISettings from './Components/AISettings';
import NotificationsSettings from './Components/NotificationsSettings';
import Changelog from './Components/Changelog';

/**
 * Module routes are contributed via the `storesuite_admin_routes`
 * `@wordpress/hooks` filter. Each module's entry bundle (enqueued after the
 * core admin script via `wp_enqueue_script` dependencies, therefore loaded
 * BEFORE `DOMContentLoaded` fires) calls `addFilter` to push its routes:
 *
 *   addFilter( 'storesuite_admin_routes', 'staff-manager', ( routes ) => {
 *       routes.push( { path: '/staff-manager', element: StaffManagerAdmin } );
 *       return routes;
 *   } );
 *
 * Using the WordPress hooks API instead of a bespoke `window.StoreSuite.*`
 * registry matches Dokan Pro's pattern: no custom global surface, any script
 * (bundled or inline) can contribute routes, and third-party modules
 * integrate without learning a StoreSuite-specific API.
 */
const collectModuleRoutes = () => {
	const routes = applyFilters( 'storesuite_admin_routes', [] );
	if ( ! Array.isArray( routes ) ) {
		return [];
	}
	return routes.filter(
		( route ) =>
			route &&
			typeof route.path === 'string' &&
			route.path.length > 0 &&
			typeof route.element === 'function'
	);
};

/**
 * Catch-all route shown when the URL hash doesn't match any built-in or
 * module-contributed route. Common causes: navigating to a stale module URL
 * after deactivating it, a module bundle that failed to enqueue, or running
 * a stale bundle (`npm run build` hasn't been re-run after pulling).
 */
const NotFound = () => {
	const { pathname } = useLocation();
	return (
		<div className="storesuite-section storesuite-section--narrow">
			<Card className="storesuite-form-header-card">
				<CardBody className="storesuite-form-section-header">
					<h3 className="storesuite-section-title">
						{ __( 'Page not found', 'storesuite' ) }
					</h3>
					<p className="storesuite-section-description">
						{ __(
							'No screen is registered for this URL.',
							'storesuite'
						) }{ ' ' }
						<code>{ pathname }</code>
					</p>
				</CardBody>
			</Card>
			<Card>
				<CardBody className="storesuite-form-section-body">
					<p>
						{ __(
							'If you were expecting a module screen, check that the module is active on the',
							'storesuite'
						) }{ ' ' }
						<a href="#/modules">
							{ __( 'Modules tab', 'storesuite' ) }
						</a>
						{ __(
							'. If you just pulled new code, run',
							'storesuite'
						) }{ ' ' }
						<code>npm run build</code>{ ' ' }
						{ __(
							'and hard-refresh the page to clear cached bundles.',
							'storesuite'
						) }
					</p>
				</CardBody>
			</Card>
		</div>
	);
};

const App = () => {
	const moduleRoutes = collectModuleRoutes();

	return (
		<SettingsProvider>
			<Router>
				<Routes>
					<Route path="/" element={ <Layout /> }>
						<Route index element={ <GeneralSettings /> } />
						<Route
							path="appearance-settings"
							element={ <ColorsSettings /> }
						/>
						<Route
							path="pagination-settings"
							element={ <PaginationSettings /> }
						/>
						<Route path="ai-settings" element={ <AISettings /> } />
						<Route
							path="notifications-settings"
							element={ <NotificationsSettings /> }
						/>
						<Route path="changelog" element={ <Changelog /> } />
						<Route path="modules" element={ <ModulesSettings /> } />
						<Route
							path="modules/:slug"
							element={ <ModuleSettings /> }
						/>
						{ moduleRoutes.map( ( route ) => {
							const Component = route.element;
							const normalized = route.path.replace( /^\/+/, '' );
							return (
								<Route
									key={ normalized }
									path={ normalized }
									element={ <Component /> }
								/>
							);
						} ) }
						<Route path="*" element={ <NotFound /> } />
					</Route>
				</Routes>
			</Router>
		</SettingsProvider>
	);
};

document.addEventListener( 'DOMContentLoaded', () => {
	const container = document.getElementById( 'storesuite-settings' );

	if ( container ) {
		const root = createRoot( container );
		root.render( <App /> );
	}
} );
