/**
 * Stock movement log — read-only table of every recorded stock change.
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { getLog } from '../lib/api';
import { toast } from '../components/ui/sonner';
import { Badge } from '../components/ui/badge';
import {
	Table,
	TableHeader,
	TableBody,
	TableRow,
	TableHead,
	TableCell,
} from '../components/ui/table';
import Pagination from '../components/Pagination';
import TableSkeleton from '../components/TableSkeleton';

const cfg = window.StoreSuiteInventory || {};
const PER_PAGE = cfg.logPerPage || 30;

// Skeleton widths per column: when, product, change, type, reference, by.
const SKELETON_COLUMNS = [
	'ss:h-5 ss:w-28',
	'ss:h-5 ss:w-40',
	'ss:h-5 ss:w-16',
	'ss:h-6 ss:w-20',
	'ss:h-5 ss:w-16',
	'ss:h-5 ss:w-20',
];

// Render "before → after", or just "→ after" when there's no before value.
function change( row ) {
	if ( row.qty_before !== null && row.qty_after !== null ) {
		return `${ row.qty_before } → ${ row.qty_after }`;
	}
	if ( row.qty_after !== null ) {
		return `→ ${ row.qty_after }`;
	}
	return '—';
}

export default function StockLog() {
	const [ page, setPage ] = useState( 1 );
	const [ data, setData ] = useState( { items: [], total: 0, total_pages: 0 } );
	const [ loading, setLoading ] = useState( true );

	useEffect( () => {
		let active = true;
		setLoading( true );
		getLog( { page, per_page: PER_PAGE } )
			.then( ( res ) => active && setData( res ) )
			.catch( ( e ) =>
				toast.error( e.message || __( 'Something went wrong', 'storesuite' ) )
			)
			.finally( () => active && setLoading( false ) );
		return () => {
			active = false;
		};
	}, [ page ] );

	return (
		<div>
			<Table>
				<TableHeader>
					<TableRow>
						<TableHead>{ __( 'When', 'storesuite' ) }</TableHead>
						<TableHead>{ __( 'Product', 'storesuite' ) }</TableHead>
						<TableHead>{ __( 'Change', 'storesuite' ) }</TableHead>
						<TableHead>{ __( 'Type', 'storesuite' ) }</TableHead>
						<TableHead>{ __( 'Reference', 'storesuite' ) }</TableHead>
						<TableHead>{ __( 'By', 'storesuite' ) }</TableHead>
					</TableRow>
				</TableHeader>
				<TableBody>
					{ loading ? (
						<TableSkeleton rows={ 8 } columns={ SKELETON_COLUMNS } />
					) : data.items.length === 0 ? (
						<TableRow>
							<TableCell colSpan={ 6 }>
								{ __( 'No stock movements recorded yet.', 'storesuite' ) }
							</TableCell>
						</TableRow>
					) : (
						data.items.map( ( row ) => (
							<TableRow key={ row.id }>
								<TableCell>{ row.created_at }</TableCell>
								<TableCell className="ss:text-foreground">
									{ row.product_name }
								</TableCell>
								<TableCell>{ change( row ) }</TableCell>
								<TableCell>
									<Badge>{ row.change_type }</Badge>
								</TableCell>
								<TableCell>{ row.reference || '—' }</TableCell>
								<TableCell>{ row.user_name }</TableCell>
							</TableRow>
						) )
					) }
				</TableBody>
			</Table>

			<Pagination
				total={ data.total }
				totalPages={ data.total_pages }
				page={ page }
				perPage={ PER_PAGE }
				onChange={ setPage }
			/>
		</div>
	);
}
