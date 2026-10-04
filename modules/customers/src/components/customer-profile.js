/**
 * Customer CRM — customer profile.
 *
 * Header + lifetime stats + billing/shipping + order history, plus the module's
 * own notes and tags. Works for guests (no WP user id) using the analytics
 * customer record.
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Card, CardBody, Button, Spinner } from '@wordpress/components';
import {
	fetchCustomer,
	fetchCustomerOrders,
	fetchWcCustomer,
} from '../api';
import { formatMoney, formatDate } from '../util/format';
import NotesPanel from './notes-panel';
import TagsEditor from './tags-editor';
import config from '../config';

const StatTile = ( { label, value } ) => (
	<div className="storesuite-stat-tile">
		<span className="storesuite-stat-value">{ value }</span>
		<span className="storesuite-stat-label">{ label }</span>
	</div>
);

const AddressBlock = ( { title, address } ) => {
	if ( ! address ) {
		return null;
	}
	const lines = [
		`${ address.first_name || '' } ${ address.last_name || '' }`.trim(),
		address.company,
		address.address_1,
		address.address_2,
		`${ address.city || '' } ${ address.state || '' } ${ address.postcode || '' }`.trim(),
		address.country,
		address.phone,
		address.email,
	].filter( Boolean );

	return (
		<div className="storesuite-address-block">
			<h4>{ title }</h4>
			{ lines.map( ( line, i ) => (
				<div key={ i }>{ line }</div>
			) ) }
		</div>
	);
};

const CustomerProfile = ( { customerId, onBack } ) => {
	const [ customer, setCustomer ] = useState( null );
	const [ orders, setOrders ] = useState( [] );
	const [ wcCustomer, setWcCustomer ] = useState( null );
	const [ loading, setLoading ] = useState( true );

	useEffect( () => {
		let active = true;
		setLoading( true );

		fetchCustomer( customerId )
			.then( async ( record ) => {
				if ( ! active ) {
					return;
				}
				setCustomer( record );
				const [ ordersData, wc ] = await Promise.all( [
					fetchCustomerOrders( customerId ).catch( () => [] ),
					record && record.user_id
						? fetchWcCustomer( record.user_id )
						: Promise.resolve( null ),
				] );
				if ( ! active ) {
					return;
				}
				setOrders( Array.isArray( ordersData ) ? ordersData : [] );
				setWcCustomer( wc );
			} )
			.catch( () => setCustomer( null ) )
			.finally( () => active && setLoading( false ) );

		return () => {
			active = false;
		};
	}, [ customerId ] );

	if ( loading ) {
		return (
			<div className="storesuite-customers-loading">
				<Spinner />
			</div>
		);
	}

	if ( ! customer ) {
		return (
			<Card>
				<CardBody>
					<Button variant="secondary" onClick={ onBack }>
						{ __( '← Back to customers', 'storesuite' ) }
					</Button>
					<p>{ __( 'Customer not found.', 'storesuite' ) }</p>
				</CardBody>
			</Card>
		);
	}

	const name = customer.name || customer.email || __( 'Guest customer', 'storesuite' );

	return (
		<div className="storesuite-customer-profile">
			<Button variant="tertiary" onClick={ onBack }>
				{ __( '← Back to customers', 'storesuite' ) }
			</Button>

			<Card className="storesuite-profile-header">
				<CardBody>
					<h2>{ name }</h2>
					<p>{ customer.email }</p>
					<p>
						{ customer.country }
						{ customer.date_registered
							? ` · ${ __( 'Since', 'storesuite' ) } ${ formatDate(
									customer.date_registered
							  ) }`
							: ` · ${ __( 'Guest', 'storesuite' ) }` }
					</p>
					<TagsEditor customerId={ customerId } />
				</CardBody>
			</Card>

			<div className="storesuite-stat-row">
				<StatTile
					label={ __( 'Orders', 'storesuite' ) }
					value={ customer.orders_count || 0 }
				/>
				<StatTile
					label={ __( 'Total spent', 'storesuite' ) }
					value={ formatMoney( customer.total_spend ) }
				/>
				<StatTile
					label={ __( 'Avg. order value', 'storesuite' ) }
					value={ formatMoney( customer.avg_order_value ) }
				/>
				<StatTile
					label={ __( 'Last order', 'storesuite' ) }
					value={ formatDate( customer.date_last_order ) }
				/>
			</div>

			<div className="storesuite-profile-columns">
				<div className="storesuite-profile-main">
					<Card>
						<CardBody>
							<h3>{ __( 'Order history', 'storesuite' ) }</h3>
							{ orders.length === 0 && (
								<p>{ __( 'No orders yet.', 'storesuite' ) }</p>
							) }
							{ orders.length > 0 && (
								<table className="storesuite-profile-orders">
									<thead>
										<tr>
											<th>{ __( 'Order', 'storesuite' ) }</th>
											<th>{ __( 'Date', 'storesuite' ) }</th>
											<th>{ __( 'Status', 'storesuite' ) }</th>
											<th>{ __( 'Total', 'storesuite' ) }</th>
										</tr>
									</thead>
									<tbody>
										{ orders.map( ( o ) => (
											<tr key={ o.order_id }>
												<td>
													<a
														href={ `${ config.orderDetailsPath || '' }?order-details=${ o.order_id }` }
													>
														#{ o.order_number || o.order_id }
													</a>
												</td>
												<td>{ formatDate( o.date_created ) }</td>
												<td>{ o.status }</td>
												<td>{ formatMoney( o.total_sales ?? o.net_total ) }</td>
											</tr>
										) ) }
									</tbody>
								</table>
							) }
						</CardBody>
					</Card>

					<NotesPanel customerId={ customerId } />
				</div>

				<div className="storesuite-profile-side">
					<Card>
						<CardBody>
							<AddressBlock
								title={ __( 'Billing', 'storesuite' ) }
								address={ wcCustomer ? wcCustomer.billing : null }
							/>
							<AddressBlock
								title={ __( 'Shipping', 'storesuite' ) }
								address={ wcCustomer ? wcCustomer.shipping : null }
							/>
							{ ! wcCustomer && (
								<p>
									{ __(
										'Address details are available for registered customers.',
										'storesuite'
									) }
								</p>
							) }
						</CardBody>
					</Card>
				</div>
			</div>
		</div>
	);
};

export default CustomerProfile;
