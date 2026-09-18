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
			var $groups = $( '.storesuite-acf-field-group' );

			if ( ! $groups.length ) {
				return;
			}

			this.bindRangeOutput( $groups );
			this.bindColorPicker( $groups );
			this.initDatePickers( $groups );
		},

		/**
		 * jQuery UI datepicker on date fields, configured like the sale-price
		 * date fields (ISO output, one month, button panel).
		 */
		initDatePickers: function ( $groups ) {
			if ( typeof $.fn.datepicker !== 'function' ) {
				return;
			}

			$groups.find( '.storesuite-acf-datepicker' ).datepicker( {
				defaultDate: '',
				dateFormat: 'yy-mm-dd',
				numberOfMonths: 1,
				showButtonPanel: true,
				onSelect: function () {
					$( this ).trigger( 'change' );
				},
			} );
		},

		/**
		 * Keep the read-out next to a range slider in sync with its value.
		 */
		bindRangeOutput: function ( $groups ) {
			$groups.on( 'input change', '.storesuite-acf-range-input', function () {
				$( this )
					.closest( '.storesuite-acf-range' )
					.find( '.storesuite-acf-range-output' )
					.text( this.value );
			} );
		},

		/**
		 * Two-way sync between the native colour swatch and the hex text box.
		 * The text box is the submitted input so the value can be left empty.
		 */
		bindColorPicker: function ( $groups ) {
			$groups.on( 'input', '.storesuite-acf-color-swatch', function () {
				$( $( this ).data( 'target' ) ).val( this.value ).trigger( 'change' );
			} );

			$groups.on( 'input change', '.storesuite-acf-color-text', function () {
				var hex = $.trim( this.value ).toLowerCase();
				var $swatch = $( this ).siblings( '.storesuite-acf-color-swatch' );

				if ( /^#[0-9a-f]{3}$/.test( hex ) ) {
					hex = '#' + hex[ 1 ] + hex[ 1 ] + hex[ 2 ] + hex[ 2 ] + hex[ 3 ] + hex[ 3 ];
				}

				if ( /^#[0-9a-f]{6}$/.test( hex ) ) {
					$swatch.val( hex );
				}
			} );
		},
	};

	$( function () {
		StoreSuiteProductAcf.init();
	} );
} )( jQuery );
