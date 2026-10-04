/**
 * Customer CRM — customer list.
 *
 * A searchable, sortable, paginated table backed by the WooCommerce Analytics
 * customers report (registered + guest customers). Clicking a row opens the
 * profile.
 */
import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { TableCard } from '@woocommerce/components';
import { fetchCustomers } from '../api';
import { formatMoney, formatDate } from '../util/format';
import config from '../config';

const PER_PAGE = parseInt( config.settings?.per_page, 10 ) || 25;

const HEADERS = [
	{ key: 'name', label: __( 'Name', 'storesuite' ), isLeftAligned: true },
	{ key: 'email', label: __( 'Email', 'storesuite' ) },
	{ key: 'orders_count', label: __( 'Orders', 'storesuite' ), isNumeric: true },
	{ key: 'total_spend', label: __( 'Total spent', 'storesuite' ), isNumeric: true },
	{ key: 'avg_order_value', label: __( 'AOV', 'storesuite' ), isNumeric: true },
	{ key: 'country', label: __( 'Country', 'storesuite' ) },
	{ key: 'date_last_active', label: __( 'Last active', 'storesuite' ) },
];

const CustomerList = ( { onOpenProfile } ) => {
	const [ rows, setRows ] = useState( [] );
	const [ total, setTotal ] = useState( 0 );
	const [ page, setPage ] = useState( 1 );
	const [ search, setSearch ] = useState( '' );
	const [ loading, setLoading ] = useState( true );

	const load = useCallback( () => {
		setLoading( true );
		const query = { page, per_page: PER_PAGE };
		if ( search ) {
			query.search = search;
		}
		fetchCustomers( query )
			.then( ( { items, total: t } ) => {
				setRows( Array.isArray( items ) ? items : [] );
				setTotal( t );
			} )
			.catch( () => {
				setRows( [] );
				setTotal( 0 );
			} )
			.finally( () => setLoading( false ) );
	}, [ page, search ] );

	useEffect( load, [ load ] );

	// Debounce the search input so we don't fetch on every keystroke.
	const timer = useRef( null );
	const onSearchInput = ( e ) => {
		const value = e.target.value;
		if ( timer.current ) {
			clearTimeout( timer.current );
		}
		timer.current = setTimeout( () => {
			setPage( 1 );
			setSearch( value );
		}, 400 );
	};

	const tableRows = rows.map( ( c ) => {
		const name = c.name || c.email || __( 'Guest', 'storesuite' );
		return [
			{
				display: (
					<button
						type="button"
						className="storesuite-linklike"
						onClick={ () => onOpenProfile( c.id ) }
					>
						{ name }
					</button>
				),
				value: name,
			},
			{ display: c.email, value: c.email },
			{ display: c.orders_count, value: c.orders_count },
			{ display: formatMoney( c.total_spend ), value: c.total_spend },
			{ display: formatMoney( c.avg_order_value ), value: c.avg_order_value },
			{ display: c.country, value: c.country },
			{ display: formatDate( c.date_last_active ), value: c.date_last_active },
		];
	} );

	return (
		<div className="storesuite-customers-list">
			<div className="storesuite-customers-toolbar">
				<input
					type="search"
					className="storesuite-customers-search"
					placeholder={ __( 'Search customers…', 'storesuite' ) }
					onChange={ onSearchInput }
				/>
			</div>
			<TableCard
				title={ __( 'Customers', 'storesuite' ) }
				isLoading={ loading }
				rows={ tableRows }
				headers={ HEADERS }
				rowsPerPage={ PER_PAGE }
				totalRows={ total }
				showMenu={ false }
				query={ { page } }
				onPageChange={ ( newPage ) => setPage( newPage ) }
			/>
		</div>
	);
};

export default CustomerList;
