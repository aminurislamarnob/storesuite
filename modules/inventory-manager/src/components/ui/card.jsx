/**
 * Card — matches StoreSuite `.storesuite-card-with-header` (radius 8px, soft
 * shadow, 16px/500 header with a bottom border, 24px content padding).
 */
import { cn } from '../../lib/utils';

function Card( { className, ...props } ) {
	return (
		<div
			data-slot="card"
			className={ cn(
				'ss:overflow-hidden ss:rounded-lg ss:border ss:border-border ss:bg-card ss:text-card-foreground ss:shadow-sm',
				className
			) }
			{ ...props }
		/>
	);
}

function CardHeader( { className, ...props } ) {
	return (
		<div
			data-slot="card-header"
			className={ cn(
				'ss:border-b ss:border-border ss:px-6 ss:py-4 ss:text-base ss:font-medium',
				className
			) }
			{ ...props }
		/>
	);
}

function CardContent( { className, ...props } ) {
	return (
		<div
			data-slot="card-content"
			className={ cn( 'ss:p-6', className ) }
			{ ...props }
		/>
	);
}

export { Card, CardHeader, CardContent };
