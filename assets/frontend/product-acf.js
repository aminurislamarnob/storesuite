/**
 * ACF field behaviour on the StoreSuite product add/edit form.
 *
 * Loaded only when Advanced Custom Fields is active and a product form is
 * rendered. Field-type specific behaviour (media picker, conditional logic,
 * date pickers) is added here as those types land.
 */
( function ( $ ) {
	'use strict';

	var StoreSuiteProductAcf = {
		init: function () {
			if ( ! $( '.storesuite-acf-field-group' ).length ) {
				return;
			}
		},
	};

	$( function () {
		StoreSuiteProductAcf.init();
	} );
} )( jQuery );
