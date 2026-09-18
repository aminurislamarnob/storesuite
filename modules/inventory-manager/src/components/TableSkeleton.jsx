/**
 * TableSkeleton — placeholder rows shown while a table's data loads. Pass one
 * Skeleton width/height class per column so the placeholders line up with the
 * real columns.
 */
import { TableRow, TableCell } from './ui/table';
import { Skeleton } from './ui/skeleton';

export default function TableSkeleton( { rows = 6, columns } ) {
	return Array.from( { length: rows } ).map( ( _, rowIndex ) => (
		<TableRow key={ `skeleton-${ rowIndex }` }>
			{ columns.map( ( className, colIndex ) => (
				<TableCell key={ colIndex }>
					<Skeleton className={ className } />
				</TableCell>
			) ) }
		</TableRow>
	) );
}
