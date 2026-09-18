/**
 * Table — matches StoreSuite `.my-storesuite-tbl .storesuite-list-table`
 * (uppercase 600 headers, 10px cell padding, row borders).
 */
import { cn } from '../../lib/utils';

function Table( { className, ...props } ) {
	return (
		<div
			data-slot="table-container"
			className="ss:w-full ss:overflow-x-auto ss:rounded ss:border ss:border-border ss:bg-background"
		>
			<table
				data-slot="table"
				className={ cn(
					'ss:w-full ss:border-collapse ss:bg-background ss:text-sm',
					className
				) }
				{ ...props }
			/>
		</div>
	);
}

function TableHeader( { className, ...props } ) {
	return <thead className={ cn( 'ss:bg-background', className ) } { ...props } />;
}

function TableBody( { className, ...props } ) {
	return <tbody className={ className } { ...props } />;
}

function TableRow( { className, ...props } ) {
	return (
		<tr
			className={ cn(
				'ss:border-b ss:border-border ss:last:border-0',
				className
			) }
			{ ...props }
		/>
	);
}

function TableHead( { className, ...props } ) {
	return (
		<th
			className={ cn(
				// Bottom border lives on the cells (not the row): the header row
				// is the only child of <thead>, so TableRow's `last:border-0`
				// would otherwise strip its separator from the body.
				'ss:border-b ss:border-border ss:p-2.5 ss:text-left ss:text-xs ss:font-semibold ss:uppercase ss:tracking-wide ss:text-foreground',
				className
			) }
			{ ...props }
		/>
	);
}

function TableCell( { className, ...props } ) {
	return (
		<td
			className={ cn(
				'ss:p-2.5 ss:align-middle ss:text-muted-foreground',
				className
			) }
			{ ...props }
		/>
	);
}

export { Table, TableHeader, TableBody, TableRow, TableHead, TableCell };
