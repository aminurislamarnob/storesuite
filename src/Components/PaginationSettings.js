import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardBody,
	TextControl,
	Spinner,
} from '@wordpress/components';

import { useSettings } from '../context/SettingsContext';

const PAGINATION_FIELDS = [
	{
		key: 'productPerPage',
		apiKey: 'storesuite_product_per_page',
		label: __( 'Products per page', 'storesuite' ),
	},
	{
		key: 'inventoryPerPage',
		apiKey: 'storesuite_inventory_per_page',
		label: __( 'Inventory items per page', 'storesuite' ),
	},
	{
		key: 'orderPerPage',
		apiKey: 'storesuite_order_per_page',
		label: __( 'Orders per page', 'storesuite' ),
	},
	{
		key: 'categoryPerPage',
		apiKey: 'storesuite_category_per_page',
		label: __( 'Categories per page', 'storesuite' ),
	},
	{
		key: 'tagPerPage',
		apiKey: 'storesuite_tag_per_page',
		label: __( 'Tags per page', 'storesuite' ),
	},
	{
		key: 'brandPerPage',
		apiKey: 'storesuite_brand_per_page',
		label: __( 'Brands per page', 'storesuite' ),
	},
	{
		key: 'couponPerPage',
		apiKey: 'storesuite_coupon_per_page',
		label: __( 'Coupons per page', 'storesuite' ),
	},
];

const HELP = __( 'Default: 10', 'storesuite' );

const PaginationSettings = () => {
	const { settings, isSaving, saveSettings } = useSettings();

	const [ pagination, setPagination ] = useState( () =>
		Object.fromEntries(
			PAGINATION_FIELDS.map( ( { key, apiKey } ) => [
				key,
				settings[ apiKey ] !== undefined
					? String( settings[ apiKey ] )
					: '',
			] )
		)
	);

	const handleSubmit = ( event ) => {
		event.preventDefault();
		const data = {};
		PAGINATION_FIELDS.forEach( ( { key, apiKey } ) => {
			data[ apiKey ] = pagination[ key ] ?? '';
		} );
		saveSettings( data );
	};

	return (
		<div
			className="storesuite-section storesuite-section--medium"
			id="storesuite-pagination-settings"
		>
			<form onSubmit={ handleSubmit }>
				<Card className="storesuite-form-header-card">
					<CardBody className="storesuite-form-section-header">
						<h3 className="storesuite-section-title">
							{ __( 'Pagination Settings', 'storesuite' ) }
						</h3>
						<p className="storesuite-section-description">
							{ __(
								'Set the number of items displayed per page on each list view.',
								'storesuite'
							) }
						</p>
					</CardBody>
				</Card>
				<Card>
					<CardBody className="storesuite-form-section-body">
						<div className="storesuite-pagination-grid">
							{ PAGINATION_FIELDS.map( ( { key, label } ) => (
								<div
									key={ key }
									className="storesuite-settings-group"
								>
									<TextControl
										label={ label }
										help={ HELP }
										value={ pagination[ key ] }
										onChange={ ( value ) =>
											setPagination( ( prev ) => ( {
												...prev,
												[ key ]: value,
											} ) )
										}
									/>
								</div>
							) ) }
						</div>
						<Button
							variant="primary"
							type="submit"
							isBusy={ isSaving }
							disabled={ isSaving }
						>
							{ isSaving && <Spinner /> }
							{ __( 'Save Changes', 'storesuite' ) }
						</Button>
					</CardBody>
				</Card>
			</form>
		</div>
	);
};

export default PaginationSettings;
