/**
 * Switch — pill toggle matching the product edit page design.
 * 40x20 track, 14px thumb, primary color when on. Mirrors the Checkbox
 * API (`checked` / `onCheckedChange`) so it drops in wherever a boolean
 * toggle is needed.
 */
import { forwardRef } from '@wordpress/element';
import { cn } from '../../lib/utils';

const Switch = forwardRef(
	( { className, checked = false, onCheckedChange, disabled, ...props }, ref ) => {
		return (
			<button
				ref={ ref }
				type="button"
				role="switch"
				aria-checked={ checked }
				disabled={ disabled }
				data-slot="switch"
				data-state={ checked ? 'checked' : 'unchecked' }
				onClick={ () => onCheckedChange && onCheckedChange( ! checked ) }
				className={ cn(
					'ss:relative ss:inline-flex ss:h-5 ss:w-10 ss:shrink-0 ss:cursor-pointer ss:items-center ss:rounded-full ss:bg-input ss:outline-none ss:transition-colors ss:data-[state=checked]:bg-primary ss:disabled:cursor-not-allowed ss:disabled:opacity-[0.55]',
					className
				) }
				{ ...props }
			>
				<span
					className={ cn(
						'ss:pointer-events-none ss:absolute ss:left-[3px] ss:h-3.5 ss:w-3.5 ss:rounded-full ss:bg-white ss:transition-transform',
						checked ? 'ss:translate-x-5' : 'ss:translate-x-0'
					) }
				/>
			</button>
		);
	}
);

export { Switch };
