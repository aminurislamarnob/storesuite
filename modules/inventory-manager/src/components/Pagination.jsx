/**
 * Pagination — matches StoreSuite `.storesuite-pagination` (32px square
 * buttons, active = primary) with the "Showing X to Y of Z" summary.
 */
import { sprintf, __ } from '@wordpress/i18n';
import { cn } from '../lib/utils';

// A small window of page numbers around the current page (KISS: ±2, no gaps).
function pageWindow( current, total ) {
	const pages = [];
	for (
		let i = Math.max( 1, current - 2 );
		i <= Math.min( total, current + 2 );
		i++
	) {
		pages.push( i );
	}
	return pages;
}

function PageButton( { active, disabled, onClick, children } ) {
	return (
		<li>
			<button
				type="button"
				disabled={ disabled }
				onClick={ onClick }
				className={ cn(
					'ss:inline-flex ss:h-8 ss:w-8 ss:items-center ss:justify-center ss:rounded-[5px] ss:border ss:border-border ss:bg-background ss:text-sm ss:text-foreground ss:transition-colors ss:hover:bg-primary ss:hover:text-primary-foreground ss:disabled:opacity-[0.55] ss:disabled:pointer-events-none',
					active && 'ss:bg-primary ss:text-primary-foreground ss:border-primary'
				) }
			>
				{ children }
			</button>
		</li>
	);
}

export default function Pagination( { total, totalPages, page, perPage, onChange } ) {
	if ( totalPages <= 1 ) {
		return null;
	}

	const start = ( page - 1 ) * perPage + 1;
	const end = Math.min( total, page * perPage );

	return (
		<div className="ss:mt-5 ss:flex ss:flex-wrap ss:items-center ss:justify-between ss:gap-4">
			<div className="ss:text-sm ss:text-muted-foreground">
				{ sprintf(
					/* translators: 1: first row, 2: last row, 3: total rows */
					__( 'Showing %1$d to %2$d of %3$d', 'storesuite' ),
					start,
					end,
					total
				) }
			</div>
			<ul className="ss:flex ss:flex-wrap ss:items-center ss:gap-2.5">
				<PageButton
					disabled={ page === 1 }
					onClick={ () => onChange( page - 1 ) }
				>
					←
				</PageButton>
				{ pageWindow( page, totalPages ).map( ( p ) => (
					<PageButton
						key={ p }
						active={ p === page }
						onClick={ () => onChange( p ) }
					>
						{ p }
					</PageButton>
				) ) }
				<PageButton
					disabled={ page === totalPages }
					onClick={ () => onChange( page + 1 ) }
				>
					→
				</PageButton>
			</ul>
		</div>
	);
}
