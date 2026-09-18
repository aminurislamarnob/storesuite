/**
 * Inventory app root — renders either the stock list or the movement log,
 * chosen by the `?view=` query arg. Switching between the two lives in the
 * dashboard sidebar (Inventory ▸ Stock list / Movement log), so each view is a
 * normal page load rather than an in-page tab.
 *
 * The page title + breadcrumb are rendered server-side by the dashboard shell
 * (see Module::endpoint_title()), so the app itself starts at the content.
 */
import { Toaster } from './components/ui/sonner';
import StockList from './views/StockList';
import StockLog from './views/StockLog';

// Read the active view from the URL (?view=log); anything else is the list.
function currentView() {
	return new URLSearchParams( window.location.search ).get( 'view' ) === 'log'
		? 'log'
		: 'list';
}

export default function App() {
	const view = currentView();

	return (
		<div className="ss:text-foreground">
			{ view === 'log' ? <StockLog /> : <StockList /> }

			<Toaster />
		</div>
	);
}
