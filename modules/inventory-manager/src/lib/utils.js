/**
 * cn() — merge class names, resolving Tailwind conflicts.
 *
 * Configured with the `ss` prefix so tailwind-merge understands our prefixed
 * utilities (e.g. `ss:bg-primary`) when deduping.
 */
import { clsx } from 'clsx';
import { extendTailwindMerge } from 'tailwind-merge';

const twMerge = extendTailwindMerge( { prefix: 'ss' } );

export function cn( ...inputs ) {
	return twMerge( clsx( inputs ) );
}
