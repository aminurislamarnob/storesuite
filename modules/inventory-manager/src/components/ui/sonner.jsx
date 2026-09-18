/**
 * Toaster — Sonner toasts for success/error feedback (replaces SweetAlert2 on
 * this screen). The container gets `ss-ui` so our scoped tokens apply, and
 * `toast` is re-exported for callers.
 */
import { Toaster as SonnerToaster, toast } from 'sonner';

function Toaster( props ) {
	return (
		<SonnerToaster
			className="ss-ui"
			position="bottom-right"
			toastOptions={ {
				style: {
					fontSize: '14px',
				},
			} }
			{ ...props }
		/>
	);
}

export { Toaster, toast };
