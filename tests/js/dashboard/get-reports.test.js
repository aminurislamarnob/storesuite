/**
 * WordPress dependencies
 */
import { addFilter, removeFilter } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import getReports from '../../../src/dashboard/get-reports';

const REPORTS_FILTER = 'storesuite_dashboard_analytics_reports_list';
const HOOK_NAMESPACE = 'storesuite/tests';

const DEFAULT_REPORTS = [
	'DashboardDateRangePicker',
	'StorePerformance',
	'NetSalesChart',
	'DashboardLeaderboards',
	'RecentOrders',
	'QuickActions',
];

describe( 'dashboard/get-reports', () => {
	afterEach( () => {
		removeFilter( REPORTS_FILTER, HOOK_NAMESPACE );
	} );

	it( 'returns the default dashboard widgets in order', () => {
		const reports = getReports();

		expect( reports.map( ( { report } ) => report ) ).toEqual(
			DEFAULT_REPORTS
		);
	} );

	it( 'gives every widget a title and a component', () => {
		getReports().forEach( ( report ) => {
			expect( report.title ).toBeTruthy();
			expect( report.component ).toBeDefined();
		} );
	} );

	it( 'lets the reports filter add a custom widget', () => {
		const CustomWidget = () => null;

		addFilter( REPORTS_FILTER, HOOK_NAMESPACE, ( reports ) => [
			...reports,
			{
				report: 'CustomWidget',
				title: 'Custom Widget',
				component: CustomWidget,
			},
		] );

		const reports = getReports();

		expect( reports ).toHaveLength( DEFAULT_REPORTS.length + 1 );
		expect( reports[ reports.length - 1 ] ).toMatchObject( {
			report: 'CustomWidget',
			component: CustomWidget,
		} );
	} );

	it( 'lets the reports filter remove a default widget', () => {
		addFilter( REPORTS_FILTER, HOOK_NAMESPACE, ( reports ) =>
			reports.filter( ( { report } ) => report !== 'QuickActions' )
		);

		const reports = getReports();

		expect( reports ).toHaveLength( DEFAULT_REPORTS.length - 1 );
		expect( reports.map( ( { report } ) => report ) ).not.toContain(
			'QuickActions'
		);
	} );
} );
