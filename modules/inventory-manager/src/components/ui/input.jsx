/**
 * Input — matches StoreSuite `.storesuite-form-control` (40px, radius 6px,
 * focus border = primary).
 */
import { forwardRef } from '@wordpress/element';
import { cn } from '../../lib/utils';

const Input = forwardRef( ( { className, type = 'text', ...props }, ref ) => {
	return (
		<input
			ref={ ref }
			type={ type }
			data-slot="input"
			className={ cn(
				'ss:h-10 ss:w-full ss:rounded-md ss:border ss:border-input ss:bg-background ss:px-3 ss:py-2 ss:text-sm ss:text-foreground ss:outline-none ss:transition-colors ss:placeholder:text-muted-foreground ss:focus:border-primary ss:disabled:opacity-[0.55]',
				className
			) }
			{ ...props }
		/>
	);
} );

export { Input };
