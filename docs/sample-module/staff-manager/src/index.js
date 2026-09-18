/**
 * Staff Manager — module entry.
 *
 * Loaded after the core `storesuite-admin-page` bundle. Contributes its
 * route(s) by hooking the WordPress filter `storesuite_admin_routes`, the
 * same pattern Dokan Pro uses for `dokan-dashboard-routes`. Core reads the
 * filter at mount time and renders a `<Route>` per entry.
 *
 * @package StoreSuite
 */

import { addFilter } from '@wordpress/hooks';
import StaffManagerAdmin from './admin/StaffManagerAdmin';

addFilter(
	'storesuite_admin_routes',
	'storesuite/staff-manager',
	( routes ) => {
		if ( ! Array.isArray( routes ) ) {
			return routes;
		}
		routes.push( {
			path: '/staff-manager',
			element: StaffManagerAdmin,
		} );
		return routes;
	}
);
