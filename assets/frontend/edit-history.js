/* global StoreSuite_EditHistory, Swal */
/**
 * Undo for inline / bulk edits recorded in the StoreSuite edit history.
 *
 * Exposes window.StoreSuite.editHistory with:
 *  - toast( undoPayload )  small "Saved · Undo" toast after an inline edit
 *  - undo( undoPayload, options )  revert a batch over AJAX
 * and handles the Undo buttons on the History page and the orders feedback notice.
 */
( function ( $ ) {
	'use strict';

	var StoreSuiteEditHistory = {
		config: function () {
			return typeof StoreSuite_EditHistory !== 'undefined'
				? StoreSuite_EditHistory
				: {};
		},

		i18n: function () {
			return this.config().i18n || {};
		},

		init: function () {
			var self = this;

			$( document ).on( 'click', '.storesuite-history-undo', function ( event ) {
				event.preventDefault();

				var $button = $( this );
				var batchId = parseInt( $button.data( 'batch-id' ), 10 );
				if ( ! batchId ) {
					return;
				}

				self.undo(
					{
						batch_id: batchId,
						nonce: $button.data( 'nonce' ) || self.config().nonce,
					},
					{ confirm: true, reload: true, $button: $button }
				);
			} );
		},

		/**
		 * Non-blocking toast with an Undo link (inline edit).
		 */
		toast: function ( payload ) {
			var self = this;
			if ( typeof Swal === 'undefined' || ! payload || ! payload.batch_id ) {
				return;
			}

			var i18n = this.i18n();
			var buttonId = 'storesuite-undo-toast-' + payload.batch_id;

			Swal.fire( {
				toast: true,
				position: 'bottom-end',
				icon: 'success',
				timer: 8000,
				timerProgressBar: true,
				showConfirmButton: false,
				customClass: { popup: 'storesuite-undo-toast' },
				html:
					'<span>' +
					$( '<div>' ).text( i18n.saved || '' ).html() +
					'</span>' +
					'<button type="button" class="storesuite-undo-toast-button" id="' +
					buttonId +
					'">' +
					$( '<div>' ).text( i18n.undo || '' ).html() +
					'</button>',
				didOpen: function ( popup ) {
					$( popup )
						.find( '#' + buttonId )
						.on( 'click', function () {
							Swal.close();
							self.undo( payload, { confirm: false, reload: false } );
						} );
				},
			} );
		},

		/**
		 * Revert a batch.
		 *
		 * options.confirm  ask first (default true)
		 * options.reload   reload the page afterwards (default true); when false and
		 *                  the server returns row HTML, rows are swapped in place.
		 */
		undo: function ( payload, options ) {
			var self = this;
			var opts = $.extend( { confirm: true, reload: true }, options || {} );
			var i18n = this.i18n();

			if ( typeof Swal === 'undefined' || ! payload || ! payload.batch_id ) {
				return;
			}

			var run = function () {
				Swal.fire( {
					title: i18n.undoing,
					allowOutsideClick: false,
					didOpen: function () {
						Swal.showLoading();
					},
				} );

				$.ajax( {
					url: self.config().ajax_url,
					type: 'POST',
					dataType: 'json',
					data: {
						action: 'storesuite_undo_edit_batch',
						security: payload.nonce || self.config().nonce,
						batch_id: payload.batch_id,
						context: self.config().context || '',
					},
				} )
					.done( function ( response ) {
						if ( ! response || ! response.success ) {
							self.fail(
								response && response.data ? response.data.message : ''
							);
							return;
						}

						var data = response.data || {};

						if ( ! opts.reload && data.rows && self.swapRows( data.rows ) ) {
							Swal.fire( {
								toast: true,
								position: 'bottom-end',
								icon: 'success',
								timer: 4000,
								showConfirmButton: false,
								title: data.message || i18n.undone_title,
							} );
							return;
						}

						Swal.fire( {
							icon: 'success',
							title: i18n.undone_title,
							text: data.message || '',
							confirmButtonText: i18n.ok_button,
						} ).then( function () {
							window.location.reload();
						} );
					} )
					.fail( function ( xhr ) {
						self.fail(
							xhr && xhr.responseJSON && xhr.responseJSON.data
								? xhr.responseJSON.data.message
								: ''
						);
					} );
			};

			if ( ! opts.confirm ) {
				run();
				return;
			}

			Swal.fire( {
				title: i18n.confirm_title,
				text: i18n.confirm_text,
				icon: 'warning',
				showCancelButton: true,
				confirmButtonText: i18n.confirm_button,
				cancelButtonText: i18n.cancel_button,
			} ).then( function ( result ) {
				if ( result.isConfirmed ) {
					run();
				}
			} );
		},

		// Replace list rows in place; returns false when a row is not on this page.
		swapRows: function ( rows ) {
			var swapped = false;
			var missing = false;

			$.each( rows, function ( productId, html ) {
				var $row = $( '#product-row-' + productId );
				if ( ! $row.length ) {
					missing = true;
					return;
				}
				var $newRow = $( html );
				$row.replaceWith( $newRow );
				$newRow.addClass( 'storesuite-inline-saved-flash' );
				setTimeout( function () {
					$newRow.removeClass( 'storesuite-inline-saved-flash' );
				}, 1600 );
				swapped = true;
			} );

			return swapped && ! missing;
		},

		fail: function ( message ) {
			var i18n = this.i18n();
			Swal.fire( {
				icon: 'error',
				title: i18n.error_title,
				text: message || i18n.unexpected_error,
				confirmButtonText: i18n.ok_button,
			} );
		},
	};

	window.StoreSuite = window.StoreSuite || {};
	window.StoreSuite.editHistory = StoreSuiteEditHistory;

	$( function () {
		StoreSuiteEditHistory.init();
	} );
} )( jQuery );
