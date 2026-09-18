/**
 * Stock list — searchable, filterable table of managed-stock products with
 * inline quantity editing and a bulk update action.
 */
import { useState, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Search } from 'lucide-react';
import { getStock, setStock, bulkUpdate } from '../lib/api';
import { toast } from '../components/ui/sonner';
import { Button } from '../components/ui/button';
import { Input } from '../components/ui/input';
import { Checkbox } from '../components/ui/checkbox';
import { Switch } from '../components/ui/switch';
import { EmptyState } from '../components/EmptyState';
import { Badge } from '../components/ui/badge';
import {
	Select,
	SelectTrigger,
	SelectValue,
	SelectContent,
	SelectItem,
} from '../components/ui/select';
import {
	Table,
	TableHeader,
	TableBody,
	TableRow,
	TableHead,
	TableCell,
} from '../components/ui/table';
import {
	AlertDialog,
	AlertDialogContent,
	AlertDialogHeader,
	AlertDialogFooter,
	AlertDialogTitle,
	AlertDialogDescription,
	AlertDialogAction,
	AlertDialogCancel,
} from '../components/ui/alert-dialog';
import Pagination from '../components/Pagination';
import TableSkeleton from '../components/TableSkeleton';

const cfg = window.StoreSuiteInventory || {};
const PER_PAGE = cfg.perPage || 20;

// Skeleton widths per column: checkbox, product, sku, status, stock, threshold.
const SKELETON_COLUMNS = [
	'ss:h-4 ss:w-4',
	'ss:h-5 ss:w-40',
	'ss:h-5 ss:w-16',
	'ss:h-6 ss:w-20',
	'ss:h-9 ss:w-24',
	'ss:h-5 ss:w-8',
];

// Map a WC stock status to a badge variant + label.
const STATUS = {
	instock: { variant: 'success', label: __( 'In stock', 'storesuite' ) },
	outofstock: { variant: 'danger', label: __( 'Out of stock', 'storesuite' ) },
	onbackorder: { variant: 'warning', label: __( 'On backorder', 'storesuite' ) },
};

// Read the initial filters from the URL so deep links keep working.
function initialFilters() {
	const q = new URLSearchParams( window.location.search );
	return {
		search: q.get( 'search_by' ) || '',
		stock_status: q.get( 'stock_status' ) || '',
		low_only: q.get( 'low_only' ) === '1',
		page: Math.max( 1, parseInt( q.get( 'paged' ), 10 ) || 1 ),
	};
}

// Reflect the current filters back into the URL (no reload).
function syncUrl( filters ) {
	const q = new URLSearchParams( window.location.search );
	q.set( 'view', 'list' );
	filters.search ? q.set( 'search_by', filters.search ) : q.delete( 'search_by' );
	filters.stock_status
		? q.set( 'stock_status', filters.stock_status )
		: q.delete( 'stock_status' );
	filters.low_only ? q.set( 'low_only', '1' ) : q.delete( 'low_only' );
	filters.page > 1 ? q.set( 'paged', filters.page ) : q.delete( 'paged' );
	window.history.replaceState( null, '', '?' + q.toString() );
}

// One editable table row (owns its quantity input state).
function Row( { item, checked, onToggle, onSaved } ) {
	const [ qty, setQty ] = useState( item.stock_qty );
	const [ saving, setSaving ] = useState( false );
	const status = STATUS[ item.stock_status ] || {
		variant: 'neutral',
		label: item.stock_status,
	};

	const isDirty = String( qty ) !== String( item.stock_qty );

	async function save() {
		setSaving( true );
		try {
			const res = await setStock( item.id, qty );
			onSaved( res.item );
			toast.success( res.message );
		} catch ( e ) {
			toast.error( e.message || __( 'Something went wrong', 'storesuite' ) );
		}
		setSaving( false );
	}

	return (
		<TableRow className={ item.is_low ? 'ss:bg-warning-bg/40' : '' }>
			<TableCell className="ss:w-10">
				<Checkbox checked={ checked } onCheckedChange={ () => onToggle( item.id ) } />
			</TableCell>
			<TableCell className="ss:text-foreground">
				<a
					href={ item.edit_url }
					className="ss:text-primary ss:hover:underline"
				>
					{ item.name }
				</a>
			</TableCell>
			<TableCell>{ item.sku || '—' }</TableCell>
			<TableCell>
				<Badge variant={ status.variant }>{ status.label }</Badge>
			</TableCell>
			<TableCell>
				<div className="ss:flex ss:items-center ss:gap-2">
					<Input
						type="number"
						value={ qty }
						onChange={ ( e ) => setQty( e.target.value ) }
						className="ss:h-9 ss:w-24"
					/>
					<Button
						size="sm"
						variant={ isDirty ? 'default' : 'outline' }
						disabled={ saving || ! isDirty }
						onClick={ save }
					>
						{ __( 'Save', 'storesuite' ) }
					</Button>
				</div>
			</TableCell>
			<TableCell>{ item.low_stock }</TableCell>
		</TableRow>
	);
}

export default function StockList() {
	const [ filters, setFilters ] = useState( initialFilters );
	const [ searchInput, setSearchInput ] = useState( filters.search );
	const [ data, setData ] = useState( { items: [], total: 0, total_pages: 0, low: 0 } );
	const [ loading, setLoading ] = useState( true );
	const [ selected, setSelected ] = useState( [] );
	const [ bulkOp, setBulkOp ] = useState( 'set' );
	const [ bulkQty, setBulkQty ] = useState( 0 );
	const [ confirmOpen, setConfirmOpen ] = useState( false );

	// Debounce the search box into the applied filters. Skip the very first
	// run: on mount the search box already matches the applied filter, so
	// re-applying it would trigger a second, redundant fetch (a double skeleton
	// flash) right after the initial load.
	const isFirstSearch = useRef( true );
	useEffect( () => {
		if ( isFirstSearch.current ) {
			isFirstSearch.current = false;
			return;
		}
		const t = setTimeout( () => {
			setFilters( ( f ) => ( { ...f, search: searchInput, page: 1 } ) );
		}, 400 );
		return () => clearTimeout( t );
	}, [ searchInput ] );

	// Fetch whenever filters change.
	useEffect( () => {
		let active = true;
		setLoading( true );
		syncUrl( filters );
		getStock( {
			search: filters.search,
			stock_status: filters.stock_status,
			low_only: filters.low_only,
			page: filters.page,
			per_page: PER_PAGE,
		} )
			.then( ( res ) => {
				if ( ! active ) {
					return;
				}
				setData( {
					items: res.items,
					total: res.total,
					total_pages: res.total_pages,
					low: res.totals.low,
				} );
				setSelected( [] );
			} )
			.catch( ( e ) =>
				toast.error( e.message || __( 'Something went wrong', 'storesuite' ) )
			)
			.finally( () => active && setLoading( false ) );
		return () => {
			active = false;
		};
	}, [ filters ] );

	function update( patch ) {
		setFilters( ( f ) => ( { ...f, ...patch } ) );
	}

	function toggle( id ) {
		setSelected( ( s ) =>
			s.includes( id ) ? s.filter( ( x ) => x !== id ) : [ ...s, id ]
		);
	}

	function toggleAll() {
		const ids = data.items.map( ( i ) => i.id );
		setSelected( ( s ) => ( s.length === ids.length ? [] : ids ) );
	}

	// Patch a single row in place after an inline save.
	function patchRow( item ) {
		setData( ( d ) => ( {
			...d,
			items: d.items.map( ( i ) => ( i.id === item.id ? item : i ) ),
		} ) );
	}

	async function applyBulk() {
		setConfirmOpen( false );
		try {
			const res = await bulkUpdate( selected, bulkOp, parseInt( bulkQty, 10 ) || 0 );
			toast.success( res.message );
			update( {} ); // trigger a refetch
		} catch ( e ) {
			toast.error( e.message || __( 'Something went wrong', 'storesuite' ) );
		}
	}

	const allChecked =
		data.items.length > 0 && selected.length === data.items.length;

	return (
		<div>
			{ /* Low-stock banner */ }
			{ ! filters.low_only && data.low > 0 && (
				<div className="ss:mb-4 ss:flex ss:items-center ss:gap-2 ss:rounded-md ss:border ss:border-border ss:bg-warning-bg/50 ss:px-4 ss:py-3 ss:text-sm">
					<Badge variant="warning">{ data.low }</Badge>
					<span className="ss:text-foreground">
						{ __(
							'product(s) are at or below their low-stock threshold.',
							'storesuite'
						) }
					</span>
					<button
						type="button"
						onClick={ () => update( { low_only: true, page: 1 } ) }
						className="ss:bg-transparent ss:font-medium ss:text-primary ss:hover:underline"
					>
						{ __( 'View low stock', 'storesuite' ) }
					</button>
				</div>
			) }

			{ /* Toolbar — mirrors the Products list: bulk group (left),
			     search (middle), filters (right). */ }
			<div className="ss:mb-4 ss:flex ss:flex-wrap ss:items-center ss:gap-3">
				{ /* Bulk update group */ }
				<Select value={ bulkOp } onValueChange={ setBulkOp }>
					<SelectTrigger className="ss:w-40">
						<SelectValue />
					</SelectTrigger>
					<SelectContent>
						<SelectItem value="set">{ __( 'Set stock to', 'storesuite' ) }</SelectItem>
						<SelectItem value="increase">{ __( 'Increase by', 'storesuite' ) }</SelectItem>
						<SelectItem value="decrease">{ __( 'Decrease by', 'storesuite' ) }</SelectItem>
					</SelectContent>
				</Select>
				<Input
					type="number"
					value={ bulkQty }
					onChange={ ( e ) => setBulkQty( e.target.value ) }
					className="ss:w-20"
				/>
				<Button
					disabled={ selected.length === 0 }
					onClick={ () => setConfirmOpen( true ) }
				>
					{ __( 'Apply', 'storesuite' ) }
				</Button>

				{ /* Search box with leading magnifier icon */ }
				<div className="ss:relative ss:w-full ss:max-w-[290px]">
					<Search className="ss:pointer-events-none ss:absolute ss:left-3 ss:top-1/2 ss:h-4 ss:w-4 ss:-translate-y-1/2 ss:text-muted-foreground" />
					<Input
						type="text"
						value={ searchInput }
						onChange={ ( e ) => setSearchInput( e.target.value ) }
						placeholder={ __( 'Search product or SKU', 'storesuite' ) }
						className="ss:pl-9"
					/>
				</div>

				{ /* Filters (pushed right) */ }
				<div className="ss:ml-auto ss:flex ss:flex-wrap ss:items-center ss:gap-3">
					<Select
						value={ filters.stock_status || 'all' }
						onValueChange={ ( v ) =>
							update( { stock_status: v === 'all' ? '' : v, page: 1 } )
						}
					>
						<SelectTrigger className="ss:w-48">
							<SelectValue />
						</SelectTrigger>
						<SelectContent>
							<SelectItem value="all">{ __( 'All stock statuses', 'storesuite' ) }</SelectItem>
							<SelectItem value="instock">{ __( 'In stock', 'storesuite' ) }</SelectItem>
							<SelectItem value="outofstock">{ __( 'Out of stock', 'storesuite' ) }</SelectItem>
							<SelectItem value="onbackorder">{ __( 'On backorder', 'storesuite' ) }</SelectItem>
						</SelectContent>
					</Select>
					<label className="ss:flex ss:items-center ss:gap-3 ss:text-sm ss:text-foreground ss:cursor-pointer">
						<Switch
							checked={ filters.low_only }
							onCheckedChange={ ( c ) => update( { low_only: !! c, page: 1 } ) }
						/>
						{ __( 'Low stock only', 'storesuite' ) }
					</label>
				</div>
			</div>

			{ /* Table */ }
			<Table>
				<TableHeader>
					<TableRow>
						<TableHead className="ss:w-10">
							<Checkbox checked={ allChecked } onCheckedChange={ toggleAll } />
						</TableHead>
						<TableHead>{ __( 'Product', 'storesuite' ) }</TableHead>
						<TableHead>{ __( 'SKU', 'storesuite' ) }</TableHead>
						<TableHead>{ __( 'Status', 'storesuite' ) }</TableHead>
						<TableHead>{ __( 'Stock', 'storesuite' ) }</TableHead>
						<TableHead>{ __( 'Low threshold', 'storesuite' ) }</TableHead>
					</TableRow>
				</TableHeader>
				<TableBody>
					{ loading ? (
						<TableSkeleton rows={ 8 } columns={ SKELETON_COLUMNS } />
					) : data.items.length === 0 ? (
						<TableRow>
							<TableCell colSpan={ 6 }>
								<EmptyState
									title={ __( 'No stock-managed products found!', 'storesuite' ) }
									description={ __(
										'There is nothing to display at the moment. Enable stock management on a product to see it here.',
										'storesuite'
									) }
								/>
							</TableCell>
						</TableRow>
					) : (
						data.items.map( ( item ) => (
							<Row
								// Include qty so the row remounts (resetting its
								// input) when the value changes via bulk/refetch.
								key={ `${ item.id }-${ item.stock_qty }` }
								item={ item }
								checked={ selected.includes( item.id ) }
								onToggle={ toggle }
								onSaved={ patchRow }
							/>
						) )
					) }
				</TableBody>
			</Table>

			<Pagination
				total={ data.total }
				totalPages={ data.total_pages }
				page={ filters.page }
				perPage={ PER_PAGE }
				onChange={ ( p ) => update( { page: p } ) }
			/>

			{ /* Bulk confirm */ }
			<AlertDialog open={ confirmOpen } onOpenChange={ setConfirmOpen }>
				<AlertDialogContent>
					<AlertDialogHeader>
						<AlertDialogTitle>
							{ __( 'Apply stock change?', 'storesuite' ) }
						</AlertDialogTitle>
						<AlertDialogDescription>
							{ __(
								'This stock change will be applied to the selected products.',
								'storesuite'
							) }
						</AlertDialogDescription>
					</AlertDialogHeader>
					<AlertDialogFooter>
						<AlertDialogCancel>{ __( 'Cancel', 'storesuite' ) }</AlertDialogCancel>
						<AlertDialogAction onClick={ applyBulk }>
							{ __( 'Apply', 'storesuite' ) }
						</AlertDialogAction>
					</AlertDialogFooter>
				</AlertDialogContent>
			</AlertDialog>
		</div>
	);
}
