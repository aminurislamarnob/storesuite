/**
 * Custom Order Statuses — manage screen CRUD + order-edit transition guard.
 *
 * Served raw (not webpack-built). Localised as StoreSuiteOrderStatuses.
 */
( function ( $ ) {
	'use strict';

	if ( typeof StoreSuiteOrderStatuses === 'undefined' ) {
		return;
	}

	var cfg = StoreSuiteOrderStatuses;

	function post( data ) {
		return $.ajax( {
			url: cfg.ajax_url,
			type: 'POST',
			data: $.extend( { security: cfg.nonce }, data ),
		} );
	}

	function notifyError( message ) {
		if ( typeof Swal !== 'undefined' ) {
			Swal.fire( { icon: 'error', title: cfg.i18n.error, text: message } );
		} else {
			window.alert( message ); // eslint-disable-line no-alert
		}
	}

	/* ------------------------------------------------------------------
	 * Manage screen
	 * ---------------------------------------------------------------- */
	if ( cfg.context === 'manage' ) {
		$( document ).on( 'click', '.storesuite-color-swatch', function () {
			$( '#storesuite-status-color' ).val( $( this ).data( 'color' ) );
		} );

		function resetForm() {
			var $form = $( '#storesuite-status-form' );
			$form[ 0 ].reset();
			$form.find( '[name="slug"]' ).val( '' );
			$form.find( '#storesuite-status-transitions option' ).prop( 'selected', false );
		}

		$( document ).on( 'click', '#storesuite-status-reset', resetForm );

		$( document ).on( 'click', '.storesuite-edit-status', function () {
			var $row = $( this ).closest( 'tr' );
			var $form = $( '#storesuite-status-form' );
			var transitions = String( $row.data( 'transitions' ) || '' ).split( ',' );

			$form.find( '[name="slug"]' ).val( $row.data( 'slug' ) );
			$form.find( '[name="label"]' ).val( $row.data( 'label' ) );
			$form.find( '[name="color"]' ).val( $row.data( 'color' ) );
			$form.find( '[name="is_paid"]' ).prop( 'checked', String( $row.data( 'paid' ) ) === '1' );
			$form.find( '[name="in_reports"]' ).prop( 'checked', String( $row.data( 'reports' ) ) === '1' );
			$form.find( '#storesuite-status-transitions option' ).each( function () {
				this.selected = transitions.indexOf( this.value ) !== -1;
			} );
		} );

		$( document ).on( 'submit', '#storesuite-status-form', function ( e ) {
			e.preventDefault();
			var $form = $( this );
			var transitions = [];
			$form.find( '#storesuite-status-transitions option:selected' ).each( function () {
				transitions.push( this.value );
			} );

			post( {
				action: 'storesuite_save_order_status',
				slug: $form.find( '[name="slug"]' ).val(),
				label: $form.find( '[name="label"]' ).val(),
				color: $form.find( '[name="color"]' ).val(),
				is_paid: $form.find( '[name="is_paid"]' ).is( ':checked' ) ? 1 : 0,
				in_reports: $form.find( '[name="in_reports"]' ).is( ':checked' ) ? 1 : 0,
				transitions: transitions,
			} ).done( function ( res ) {
				if ( res && res.success ) {
					window.location.reload();
				} else {
					notifyError( res && res.data ? res.data.error : cfg.i18n.error );
				}
			} ).fail( function () {
				notifyError( cfg.i18n.error );
			} );
		} );

		$( document ).on( 'click', '.storesuite-delete-status', function () {
			var slug = $( this ).closest( 'tr' ).data( 'slug' );
			var run = function () {
				post( {
					action: 'storesuite_delete_order_status',
					slug: slug,
				} ).done( function ( res ) {
					if ( res && res.success ) {
						window.location.reload();
					} else {
						notifyError( res && res.data ? res.data.error : cfg.i18n.error );
					}
				} );
			};

			if ( typeof Swal !== 'undefined' ) {
				Swal.fire( {
					icon: 'warning',
					title: cfg.i18n.confirmDelete,
					showCancelButton: true,
				} ).then( function ( result ) {
					if ( result.isConfirmed ) {
						run();
					}
				} );
			} else {
				run();
			}
		} );
	}

	/* ------------------------------------------------------------------
	 * Order edit — restrict the status dropdown to allowed transitions
	 * ---------------------------------------------------------------- */
	if ( cfg.context === 'edit' && cfg.transitions ) {
		$( function () {
			// The order form status select — match common StoreSuite selectors.
			var $select = $( 'select[name="order_status"], select#order_status, select[name="status"]' ).first();
			if ( ! $select.length ) {
				return;
			}

			var current = String( $select.val() || '' ).replace( /^wc-/, '' );
			var allowed = cfg.transitions[ current ];

			// No rule for the current status → allow anything.
			if ( ! allowed || ! allowed.length ) {
				return;
			}

			$select.find( 'option' ).each( function () {
				var value = String( this.value ).replace( /^wc-/, '' );
				if ( value !== current && allowed.indexOf( value ) === -1 ) {
					$( this ).prop( 'disabled', true );
				}
			} );
		} );
	}
} )( jQuery );
