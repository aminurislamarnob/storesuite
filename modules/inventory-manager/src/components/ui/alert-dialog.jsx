/**
 * AlertDialog — Radix alert dialog styled like StoreSuite's bulk modal
 * (overlay rgba(15,23,42,.45), dialog radius 8px, soft large shadow).
 *
 * Overlay + Content render in a portal at <body>, so both carry `ss-ui`.
 */
import { forwardRef } from '@wordpress/element';
import * as AlertDialogPrimitive from '@radix-ui/react-alert-dialog';
import { cn } from '../../lib/utils';
import { buttonVariants } from './button';

const AlertDialog = AlertDialogPrimitive.Root;
const AlertDialogTrigger = AlertDialogPrimitive.Trigger;
const AlertDialogCancel = forwardRef( ( { className, ...props }, ref ) => (
	<AlertDialogPrimitive.Cancel
		ref={ ref }
		className={ cn( buttonVariants( { variant: 'outline' } ), className ) }
		{ ...props }
	/>
) );
const AlertDialogAction = forwardRef( ( { className, ...props }, ref ) => (
	<AlertDialogPrimitive.Action
		ref={ ref }
		className={ cn( buttonVariants(), className ) }
		{ ...props }
	/>
) );

function AlertDialogContent( { className, children, ...props } ) {
	return (
		<AlertDialogPrimitive.Portal>
			<AlertDialogPrimitive.Overlay
				className="ss-ui ss:fixed ss:inset-0 ss:z-[100050] ss:bg-[rgba(15,23,42,0.45)]"
			/>
			<AlertDialogPrimitive.Content
				className={ cn(
					'ss-ui ss:fixed ss:left-1/2 ss:top-1/2 ss:z-[100051] ss:w-[calc(100%-3rem)] ss:max-w-[640px] ss:-translate-x-1/2 ss:-translate-y-1/2 ss:rounded-lg ss:border ss:border-border ss:bg-background ss:p-6 ss:shadow-[0_20px_50px_rgba(15,23,42,0.2)]',
					className
				) }
				{ ...props }
			>
				{ children }
			</AlertDialogPrimitive.Content>
		</AlertDialogPrimitive.Portal>
	);
}

function AlertDialogHeader( { className, ...props } ) {
	return (
		<div
			className={ cn( 'ss:mb-4 ss:flex ss:flex-col ss:gap-2', className ) }
			{ ...props }
		/>
	);
}

function AlertDialogFooter( { className, ...props } ) {
	return (
		<div
			className={ cn(
				'ss:mt-6 ss:flex ss:items-center ss:justify-end ss:gap-2',
				className
			) }
			{ ...props }
		/>
	);
}

// Rendered via `asChild` as a <div> instead of Radix's default <h2>: the
// dashboard theme styles bare heading tags with rules our scoped utilities
// can't override. Radix still wires up the dialog's aria-labelledby.
const AlertDialogTitle = forwardRef( ( { className, children, ...props }, ref ) => (
	<AlertDialogPrimitive.Title asChild>
		<div
			ref={ ref }
			className={ cn( 'ss:text-lg ss:font-semibold ss:text-foreground', className ) }
			{ ...props }
		>
			{ children }
		</div>
	</AlertDialogPrimitive.Title>
) );

const AlertDialogDescription = forwardRef( ( { className, ...props }, ref ) => (
	<AlertDialogPrimitive.Description
		ref={ ref }
		className={ cn( 'ss:text-sm ss:text-muted-foreground', className ) }
		{ ...props }
	/>
) );

export {
	AlertDialog,
	AlertDialogTrigger,
	AlertDialogContent,
	AlertDialogHeader,
	AlertDialogFooter,
	AlertDialogTitle,
	AlertDialogDescription,
	AlertDialogAction,
	AlertDialogCancel,
};
