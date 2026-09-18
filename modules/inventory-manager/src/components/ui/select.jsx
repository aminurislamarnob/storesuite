/**
 * Select — Radix select styled like `.storesuite-form-control`.
 *
 * NOTE: the dropdown content renders in a portal at <body>, outside the app
 * mount, so `SelectContent` carries the `ss-ui` class to keep the scoped
 * tokens + reset working there.
 */
import { forwardRef } from '@wordpress/element';
import * as SelectPrimitive from '@radix-ui/react-select';
import { Check, ChevronDown } from 'lucide-react';
import { cn } from '../../lib/utils';

const Select = SelectPrimitive.Root;
const SelectValue = SelectPrimitive.Value;

const SelectTrigger = forwardRef( ( { className, children, ...props }, ref ) => (
	<SelectPrimitive.Trigger
		ref={ ref }
		data-slot="select-trigger"
		className={ cn(
			'ss:flex ss:h-10 ss:w-full ss:items-center ss:justify-between ss:gap-2 ss:rounded-md ss:border ss:border-input ss:bg-background ss:px-3 ss:py-2 ss:text-sm ss:text-foreground ss:outline-none ss:cursor-pointer ss:transition-colors ss:focus:border-primary ss:disabled:opacity-[0.55] ss:[&>span]:truncate',
			className
		) }
		{ ...props }
	>
		{ children }
		<SelectPrimitive.Icon asChild>
			<ChevronDown className="ss:h-4 ss:w-4 ss:opacity-60" />
		</SelectPrimitive.Icon>
	</SelectPrimitive.Trigger>
) );

const SelectContent = forwardRef(
	( { className, children, position = 'popper', ...props }, ref ) => (
		<SelectPrimitive.Portal>
			<SelectPrimitive.Content
				ref={ ref }
				data-slot="select-content"
				className={ cn(
					'ss-ui ss:relative ss:z-[100060] ss:max-h-72 ss:min-w-[8rem] ss:overflow-hidden ss:rounded-md ss:border ss:border-input ss:bg-popover ss:text-popover-foreground ss:shadow-lg',
					position === 'popper' && 'ss:mt-1',
					className
				) }
				position={ position }
				{ ...props }
			>
				<SelectPrimitive.Viewport className="ss:p-1">
					{ children }
				</SelectPrimitive.Viewport>
			</SelectPrimitive.Content>
		</SelectPrimitive.Portal>
	)
);

const SelectItem = forwardRef( ( { className, children, ...props }, ref ) => (
	<SelectPrimitive.Item
		ref={ ref }
		data-slot="select-item"
		className={ cn(
			'ss:relative ss:flex ss:w-full ss:cursor-pointer ss:select-none ss:items-center ss:rounded-sm ss:py-1.5 ss:pl-3 ss:pr-8 ss:text-sm ss:outline-none ss:data-[highlighted]:bg-muted ss:data-[state=checked]:font-medium',
			className
		) }
		{ ...props }
	>
		<SelectPrimitive.ItemText>{ children }</SelectPrimitive.ItemText>
		<span className="ss:absolute ss:right-2 ss:flex ss:items-center">
			<SelectPrimitive.ItemIndicator>
				<Check className="ss:h-4 ss:w-4 ss:text-primary" />
			</SelectPrimitive.ItemIndicator>
		</span>
	</SelectPrimitive.Item>
) );

export {
	Select,
	SelectValue,
	SelectTrigger,
	SelectContent,
	SelectItem,
};
