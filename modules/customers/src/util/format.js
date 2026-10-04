/**
 * Formatting helpers shared by the CRM components.
 */
import CurrencyFactory from '@woocommerce/currency';
import { dateI18n } from '@wordpress/date';

let currency;

/**
 * A configured WooCommerce currency instance (uses wcSettings on the frontend).
 *
 * @return {Object} Currency instance.
 */
function getCurrency() {
	if ( ! currency ) {
		const config =
			( window.wcSettings && window.wcSettings.currency ) || {};
		currency = CurrencyFactory( config );
	}
	return currency;
}

/**
 * Format a monetary amount using the store currency.
 *
 * @param {number|string} amount Amount.
 * @return {string} Formatted price.
 */
export function formatMoney( amount ) {
	return getCurrency().formatAmount( amount || 0 );
}

/**
 * Format an ISO date for display, or an em dash when empty.
 *
 * @param {string} value ISO date string.
 * @return {string} Localised date.
 */
export function formatDate( value ) {
	if ( ! value ) {
		return '—';
	}
	return dateI18n(
		( window.wcSettings && window.wcSettings.dateFormat ) || 'F j, Y',
		value
	);
}
