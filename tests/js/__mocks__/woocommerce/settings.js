/**
 * Mock for the @woocommerce/settings webpack external.
 *
 * Backing store is mutable so tests can seed values with `setMockSettings()`
 * before (re-)importing modules that read settings at import time.
 */
const settings = {};

export const getSetting = jest.fn( ( name, fallback = false ) =>
	Object.prototype.hasOwnProperty.call( settings, name )
		? settings[ name ]
		: fallback
);

export function setMockSettings( values ) {
	Object.keys( settings ).forEach( ( key ) => delete settings[ key ] );
	Object.assign( settings, values );
}
