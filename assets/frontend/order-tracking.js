/* global jQuery, StoreSuiteOrderTracking, Swal */
/**
 * Shipment tracking on the orders list and order details pages
 * (Advanced Shipment Tracking integration).
 */
( function ( $ ) {
	'use strict';

	var StoreSuiteOrderTrackingUi = {
		init: function () {
			this.suiteModal =
				window.StoreSuite && window.StoreSuite.storeSuiteModal;
			this.$modal = $( '#storesuite-order-tracking-modal' );
			this.$form = $( '#storesuite-order-tracking-form' );
			this.$error = this.$modal.find( '.storesuite-order-tracking-error' );

			if ( ! this.suiteModal || ! this.$modal.length ) {
				return;
			}

			this.suiteModal.initOverlay( this.$modal, {
				fade: true,
				closeSelector:
					'.storesuite-order-tracking-modal-cancel, .storesuite-order-tracking-modal-close',
			} );

			$( document )
				.on(
					'click',
					'.storesuite-add-tracking',
					this.openModal.bind( this )
				)
				.on(
					'click',
					'.storesuite-delete-tracking',
					this.deleteTracking.bind( this )
				);

			this.$form
				.on( 'submit', this.addTracking.bind( this ) )
				.on( 'input change', this.clearError.bind( this ) )
				.on(
					'change',
					'input[name="mark_order_as"]',
					this.keepOneMarkAs
				);
		},

		/**
		 * "Mark order as" offers alternatives, so ticking one unticks the others.
		 */
		keepOneMarkAs: function () {
			if ( this.checked ) {
				$( 'input[name="mark_order_as"]' )
					.not( this )
					.prop( 'checked', false );
			}
		},

		showError: function ( message ) {
			this.$error
				.text( message || StoreSuiteOrderTracking.i18n_error )
				.prop( 'hidden', false );
		},

		clearError: function () {
			this.$error.text( '' ).prop( 'hidden', true );
			this.$form
				.find( '.storesuite-field-invalid' )
				.removeClass( 'storesuite-field-invalid' );
		},

		resetForm: function () {
			this.clearError();
			this.$form.find( '#storesuite-tracking-number' ).val( '' );
			this.$form.find( '#storesuite-tracking-provider' ).val( '' );

			var $date = this.$form.find( '#storesuite-tracking-date' );
			$date.val( $date.data( 'default' ) );

			this.$form
				.find( 'input[name="mark_order_as"]' )
				.each( function () {
					$( this ).prop(
						'checked',
						String( $( this ).data( 'default' ) ) === '1'
					);
				} );
		},

		openModal: function ( e ) {
			e.preventDefault();

			var $trigger = $( e.currentTarget );
			var orderId = $trigger.data( 'order-id' );

			if ( ! orderId ) {
				return;
			}

			// Close the row-actions dropdown the trigger may sit in.
			$trigger.closest( '.storesuite-dropdown-menu' ).hide();

			this.resetForm();
			this.$form.find( '#storesuite-tracking-order-id' ).val( orderId );
			this.$modal
				.find( '#storesuite-order-tracking-title' )
				.text(
					StoreSuiteOrderTracking.i18n_title.replace(
						'%s',
						$trigger.data( 'order-number' ) || orderId
					)
				);

			this.suiteModal.open( this.$modal );
		},

		/**
		 * Replace the shipment list / list cell of an order with fresh markup.
		 *
		 * @param {Object} data AJAX success payload.
		 */
		refresh: function ( data ) {
			$(
				'.storesuite-tracking-items[data-order-id="' +
					data.order_id +
					'"]'
			).html( data.items_html );
			$(
				'.storesuite-tracking-cell[data-order-id="' +
					data.order_id +
					'"]'
			).html( data.cell_html );
		},

		addTracking: function ( e ) {
			e.preventDefault();

			var self = this;
			var $number = this.$form.find( '#storesuite-tracking-number' );
			var $carrier = this.$form.find( '#storesuite-tracking-provider' );
			var $submit = this.$modal.find(
				'.storesuite-order-tracking-submit'
			);

			this.clearError();

			if ( ! $.trim( $number.val() ) ) {
				$number.addClass( 'storesuite-field-invalid' ).trigger( 'focus' );
				this.showError( StoreSuiteOrderTracking.i18n_no_number );
				return;
			}

			if ( ! $carrier.val() ) {
				$carrier
					.addClass( 'storesuite-field-invalid' )
					.trigger( 'focus' );
				this.showError( StoreSuiteOrderTracking.i18n_no_carrier );
				return;
			}

			$submit.prop( 'disabled', true );

			$.ajax( {
				url: StoreSuiteOrderTracking.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: {
					action: StoreSuiteOrderTracking.add_action,
					security: StoreSuiteOrderTracking.add_nonce,
					order_id: this.$form
						.find( '#storesuite-tracking-order-id' )
						.val(),
					tracking_number: $number.val(),
					tracking_provider: $carrier.val(),
					date_shipped: this.$form
						.find( '#storesuite-tracking-date' )
						.val(),
					mark_order_as:
						this.$form
							.find( 'input[name="mark_order_as"]:checked' )
							.val() || '',
				},
				success: function ( response ) {
					if ( ! response || ! response.success ) {
						self.showError(
							response && response.data && response.data.error
						);
						return;
					}

					// The order status changed, so the badge and status-dependent UI are stale.
					if ( response.data.status_changed ) {
						window.location.reload();
						return;
					}

					self.refresh( response.data );
					self.suiteModal.close( self.$modal );
				},
				error: function () {
					self.showError();
				},
				complete: function () {
					$submit.prop( 'disabled', false );
				},
			} );
		},

		deleteTracking: function ( e ) {
			e.preventDefault();

			var self = this;
			var $trigger = $( e.currentTarget );

			Swal.fire( {
				text: StoreSuiteOrderTracking.i18n_delete,
				icon: 'warning',
				showCancelButton: true,
				confirmButtonText: StoreSuiteOrderTracking.i18n_confirm,
				cancelButtonText: StoreSuiteOrderTracking.i18n_cancel,
			} ).then( function ( result ) {
				if ( ! result.isConfirmed ) {
					return;
				}

				$.ajax( {
					url: StoreSuiteOrderTracking.ajax_url,
					type: 'POST',
					dataType: 'json',
					data: {
						action: StoreSuiteOrderTracking.delete_action,
						security: StoreSuiteOrderTracking.delete_nonce,
						order_id: $trigger.data( 'order-id' ),
						tracking_id: $trigger.data( 'tracking-id' ),
					},
					success: function ( response ) {
						if ( ! response || ! response.success ) {
							Swal.fire( {
								icon: 'error',
								text:
									( response &&
										response.data &&
										response.data.error ) ||
									StoreSuiteOrderTracking.i18n_error,
							} );
							return;
						}

						self.refresh( response.data );
					},
					error: function () {
						Swal.fire( {
							icon: 'error',
							text: StoreSuiteOrderTracking.i18n_error,
						} );
					},
				} );
			} );
		},
	};

	$( function () {
		StoreSuiteOrderTrackingUi.init();
	} );
} )( jQuery );
