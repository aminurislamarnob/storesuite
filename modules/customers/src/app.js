/**
 * Customer CRM — root component.
 *
 * Switches between the customer list and a customer profile based on the
 * `customer` URL query param, so the profile is a shareable/bookmarkable URL and
 * the browser back button works.
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { getQueryArg } from '@wordpress/url';
import CustomerList from './components/customer-list';
import CustomerProfile from './components/customer-profile';

const readCustomerId = () => {
	const raw = getQueryArg( window.location.href, 'customer' );
	const id = parseInt( raw, 10 );
	return Number.isNaN( id ) ? 0 : id;
};

const App = () => {
	const [ customerId, setCustomerId ] = useState( readCustomerId() );

	// Keep state in sync with browser navigation (back/forward).
	useEffect( () => {
		const onPop = () => setCustomerId( readCustomerId() );
		window.addEventListener( 'popstate', onPop );
		return () => window.removeEventListener( 'popstate', onPop );
	}, [] );

	const openProfile = useCallback( ( id ) => {
		const url = new URL( window.location.href );
		url.searchParams.set( 'customer', String( id ) );
		window.history.pushState( {}, '', url.toString() );
		setCustomerId( id );
	}, [] );

	const backToList = useCallback( () => {
		const url = new URL( window.location.href );
		url.searchParams.delete( 'customer' );
		window.history.pushState( {}, '', url.toString() );
		setCustomerId( 0 );
	}, [] );

	if ( customerId ) {
		return (
			<CustomerProfile customerId={ customerId } onBack={ backToList } />
		);
	}

	return <CustomerList onOpenProfile={ openProfile } />;
};

export default App;
