/**
 * Checkbox — Radix checkbox, checked state uses the primary color.
 */
import { forwardRef } from '@wordpress/element';
import * as CheckboxPrimitive from '@radix-ui/react-checkbox';
import { Check } from 'lucide-react';
import { cn } from '../../lib/utils';

const Checkbox = forwardRef( ( { className, ...props }, ref ) => {
	return (
		<CheckboxPrimitive.Root
			ref={ ref }
			data-slot="checkbox"
			className={ cn(
				'ss:flex ss:h-4 ss:w-4 ss:shrink-0 ss:items-center ss:justify-center ss:rounded-[4px] ss:border ss:border-input ss:bg-background ss:outline-none ss:cursor-pointer ss:transition-colors ss:data-[state=checked]:border-primary ss:data-[state=checked]:bg-primary ss:data-[state=checked]:text-primary-foreground ss:disabled:opacity-[0.55]',
				className
			) }
			{ ...props }
		>
			<CheckboxPrimitive.Indicator
				data-slot="checkbox-indicator"
				className="ss:flex ss:items-center ss:justify-center ss:text-current"
			>
				<Check className="ss:h-3.5 ss:w-3.5" strokeWidth={ 3 } />
			</CheckboxPrimitive.Indicator>
		</CheckboxPrimitive.Root>
	);
} );

export { Checkbox };
