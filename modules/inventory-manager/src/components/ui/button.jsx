/**
 * Button — shadcn/ui style, adapted to StoreSuite's `.my-storesuite-button`.
 *
 * Variants map to the dashboard's button looks:
 *   default → primary filled, outline → white/bordered "light" button,
 *   ghost → borderless, destructive → danger.
 */
import { forwardRef } from '@wordpress/element';
import { Slot } from '@radix-ui/react-slot';
import { cva } from 'class-variance-authority';
import { cn } from '../../lib/utils';

const buttonVariants = cva(
	'ss:inline-flex ss:items-center ss:justify-center ss:gap-2 ss:whitespace-nowrap ss:rounded-md ss:text-sm ss:font-medium ss:transition-colors ss:cursor-pointer ss:disabled:pointer-events-none ss:disabled:opacity-[0.55] ss:[&_svg]:size-4 ss:[&_svg]:shrink-0',
	{
		variants: {
			variant: {
				default:
					'ss:bg-primary ss:text-primary-foreground ss:border ss:border-primary ss:hover:bg-primary-hover ss:hover:border-primary-hover',
				outline:
					'ss:bg-background ss:text-muted-foreground ss:border ss:border-input ss:hover:bg-muted',
				ghost: 'ss:bg-transparent ss:text-foreground ss:hover:bg-muted',
				destructive:
					'ss:bg-destructive ss:text-destructive-foreground ss:border ss:border-destructive ss:hover:opacity-90',
			},
			size: {
				default: 'ss:h-10 ss:px-5 ss:py-2',
				sm: 'ss:h-9 ss:px-3',
				icon: 'ss:h-9 ss:w-9',
			},
		},
		defaultVariants: {
			variant: 'default',
			size: 'default',
		},
	}
);

const Button = forwardRef(
	( { className, variant, size, asChild = false, ...props }, ref ) => {
		const Comp = asChild ? Slot : 'button';
		return (
			<Comp
				ref={ ref }
				data-slot="button"
				className={ cn( buttonVariants( { variant, size, className } ) ) }
				{ ...props }
			/>
		);
	}
);

export { Button, buttonVariants };
