/**
 * Badge — matches StoreSuite `.storesuite-badge` and its status variants
 * (success / warning / danger / neutral).
 */
import { cva } from 'class-variance-authority';
import { cn } from '../../lib/utils';

const badgeVariants = cva(
	'ss:inline-flex ss:items-center ss:justify-center ss:rounded-md ss:px-2.5 ss:py-1.5 ss:text-[11px] ss:font-medium ss:leading-none ss:capitalize',
	{
		variants: {
			variant: {
				neutral: 'ss:bg-muted ss:text-muted-foreground',
				success: 'ss:bg-success-bg ss:text-success',
				warning: 'ss:bg-warning-bg ss:text-warning',
				danger: 'ss:bg-danger-bg ss:text-danger',
			},
		},
		defaultVariants: {
			variant: 'neutral',
		},
	}
);

function Badge( { className, variant, ...props } ) {
	return (
		<span
			data-slot="badge"
			className={ cn( badgeVariants( { variant } ), className ) }
			{ ...props }
		/>
	);
}

export { Badge, badgeVariants };
