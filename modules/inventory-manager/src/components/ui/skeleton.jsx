/**
 * Skeleton — shadcn/ui placeholder block with a pulsing animation, shown while
 * data is loading. Colored with the dashboard border tone so it reads on the
 * white table background.
 */
import { cn } from '../../lib/utils';

function Skeleton( { className, ...props } ) {
	return (
		<div
			data-slot="skeleton"
			className={ cn(
				'ss:animate-pulse ss:rounded-md ss:bg-muted',
				className
			) }
			{ ...props }
		/>
	);
}

export { Skeleton };
