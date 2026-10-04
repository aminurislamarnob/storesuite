/* global StoreSuite_OrderExport, Swal */
/**
 * Order CSV export for the StoreSuite dashboard.
 *
 * Drives the batched order exporter through the `storesuite_order_export` AJAX action using the
 * same multi-step loop as the product export, wrapped in the StoreSuite modal / SweetAlert2
 * conventions.
 */
( function ( $ ) {
	'use strict';

	var StoreSuiteOrderExport = {
		xhr: false,

		config: function () {
			return typeof StoreSuite_OrderExport !== 'undefined'
				? StoreSuite_OrderExport
				: {};
		},

		i18n: function () {
			return this.config().i18n || {};
		},

		suiteModal: function () {
			return window.StoreSuite && window.StoreSuite.storeSuiteModal
				? window.StoreSuite.storeSuiteModal
				: null;
		},

		init: function () {
			this.$modal = $( '#storesuite-order-export-modal' );
			this.$form = $( '#storesuite-order-export-form' );

			if ( ! this.$modal.length || ! this.$form.length ) {
				return;
			}

			var suiteModal = this.suiteModal();
			if ( suiteModal ) {
				suiteModal.initOverlay( this.$modal, {
					fade: true,
					closeSelector:
						'.storesuite-product-bulk-modal-cancel, .storesuite-product-bulk-modal-close',
				} );
			}

			this.initSelect2();
			this.bindEvents();
		},

		// Enhance the modal's multi-selects; the dropdown must render inside the overlay.
		initSelect2: function () {
			var $modal = this.$modal;

			if ( typeof $.fn.selectWoo !== 'function' ) {
				return;
			}

			this.$form
				.find( '.storesuite-select2' )
				.filter( ':not(.enhanced)' )
				.each( function () {
					var $selectField = $( this );
					$selectField
						.selectWoo( {
							allowClear: false,
							placeholder: $selectField.data( 'placeholder' ) || '',
							minimumResultsForSearch: 0,
							width: '100%',
							dropdownParent: $modal,
						} )
						.addClass( 'enhanced' );
				} );
		},

		bindEvents: function () {
			var self = this;

			// Toolbar "Export" button: export by status / date.
			$( document ).on(
				'click',
				'#storesuite-order-export-toggle',
				function ( event ) {
					event.preventDefault();
					self.openModal( [] );
				}
			);

			// Bulk action "Export": export the checked orders instead of posting the form.
			$( document ).on(
				'submit',
				'#storesuite-order-bulk-actions',
				function ( event ) {
					if ( $( '#bulk-action-selector-top' ).val() !== 'export' ) {
						return;
					}

					event.preventDefault();

					var selectedOrderIds = $( this )
						.find( 'input[name="bulk_order_ids[]"]:checked' )
						.map( function () {
							return $( this ).val();
						} )
						.get();

					if ( ! selectedOrderIds.length ) {
						self.warn();
						return;
					}

					self.openModal( selectedOrderIds );
				}
			);

			this.$form.on(
				'click',
				'.storesuite-export-clear-selection',
				function ( event ) {
					event.preventDefault();
					self.clearSelection();
				}
			);

			this.$form.on( 'submit', function ( event ) {
				event.preventDefault();
				self.onSubmit();
			} );
		},

		warn: function () {
			if ( typeof Swal === 'undefined' ) {
				return;
			}
			var i18n = this.i18n();
			Swal.fire( {
				icon: 'warning',
				title: i18n.select_orders_title,
				text: i18n.select_orders_message,
				confirmButtonText: i18n.ok_button,
			} );
		},

		showError: function ( message ) {
			if ( typeof Swal === 'undefined' ) {
				return;
			}
			var i18n = this.i18n();
			Swal.fire( {
				icon: 'error',
				title: i18n.error_title,
				text: message || i18n.unexpected_error,
				confirmButtonText: i18n.ok_button,
			} );
		},

		openModal: function ( selectedOrderIds ) {
			var ids = Array.isArray( selectedOrderIds ) ? selectedOrderIds : [];

			this.$form.find( '#storesuite-export-order-ids' ).val( ids.join( ',' ) );

			this.renderBulkNotice( ids.length );
			this.toggleBulkFields( ids.length > 0 );
			this.resetProgress();

			var suiteModal = this.suiteModal();
			if ( suiteModal ) {
				suiteModal.open( this.$modal );
			}
		},

		// Bulk export targets an explicit order list, so status / date filters don't apply.
		toggleBulkFields: function ( isBulk ) {
			var $statusRow = this.$form.find( '.storesuite-export-statuses-row' );
			var $datesRow = this.$form.find( '.storesuite-export-dates-row' );

			if ( isBulk ) {
				this.$form
					.find( '.storesuite-export-statuses' )
					.val( null )
					.trigger( 'change' );
				this.$form.find( 'input[type="date"]' ).val( '' );
				$statusRow.hide();
				$datesRow.hide();
				return;
			}

			$statusRow.show();
			$datesRow.show();
		},

		renderBulkNotice: function ( count ) {
			var $notice = this.$form.find( '.storesuite-export-bulk-notice' );

			if ( ! count ) {
				$notice.empty().attr( 'hidden', 'hidden' );
				return;
			}

			var i18n = this.i18n();
			var link =
				'<a href="#" class="storesuite-export-clear-selection">' +
				$( '<div>' ).text( i18n.clear_selection || '' ).html() +
				'</a>';
			var html = ( i18n.bulk_export_notice || '' )
				.replace( '%1$s', String( count ) )
				.replace( '%2$s', link );

			$notice.html( html ).removeAttr( 'hidden' );
		},

		clearSelection: function () {
			this.$form.find( '#storesuite-export-order-ids' ).val( '' );
			this.renderBulkNotice( 0 );
			this.toggleBulkFields( false );

			$( 'input[name="bulk_order_ids[]"]:checked, .storesuite-bulk-select-all' ).prop(
				'checked',
				false
			);
			$( '.storesuite-bulk-select-all' ).prop( 'indeterminate', false );
		},

		closeModal: function () {
			var suiteModal = this.suiteModal();
			if ( suiteModal ) {
				suiteModal.close( this.$modal );
			}
		},

		resetProgress: function () {
			this.$form.find( '.storesuite-export-progress' ).val( 0 );
			this.$form.find( '.storesuite-export-submit' ).prop( 'disabled', false );
		},

		generateFilename: function () {
			var now = new Date();
			return (
				'storesuite-order-export-' +
				now.getDate() +
				'-' +
				( now.getMonth() + 1 ) +
				'-' +
				now.getFullYear() +
				'-' +
				now.getTime() +
				'.csv'
			);
		},

		onSubmit: function () {
			this.$form.find( '.storesuite-export-progress' ).val( 0 );
			this.$form.find( '.storesuite-export-submit' ).prop( 'disabled', true );
			this.processStep( 1, '', this.generateFilename() );
		},

		processStep: function ( step, columns, filename ) {
			var self = this;
			var config = this.config();

			this.xhr = $.ajax( {
				type: 'POST',
				url: config.ajax_url,
				dataType: 'json',
				data: {
					action: 'storesuite_order_export',
					security: config.export_nonce,
					step: step,
					columns: columns,
					selected_columns: self.$form.find( '.storesuite-export-columns' ).val(),
					export_statuses: self.$form.find( '.storesuite-export-statuses' ).val(),
					date_from: self.$form.find( 'input[name="date_from"]' ).val() || '',
					date_to: self.$form.find( 'input[name="date_to"]' ).val() || '',
					export_order_ids:
						self.$form.find( '#storesuite-export-order-ids' ).val() || '',
					filename: filename,
				},
				success: function ( response ) {
					if ( ! response || ! response.success ) {
						self.resetProgress();
						self.showError(
							response && response.data ? response.data.error : ''
						);
						return;
					}

					var data = response.data;

					if ( 'done' === data.step ) {
						self.$form.find( '.storesuite-export-progress' ).val( data.percentage );
						window.location = data.url;
						setTimeout( function () {
							self.resetProgress();
							self.closeModal();
						}, 2000 );
						return;
					}

					self.$form.find( '.storesuite-export-progress' ).val( data.percentage );
					self.processStep( parseInt( data.step, 10 ), data.columns, filename );
				},
				error: function ( xhr ) {
					self.resetProgress();
					self.showError(
						xhr && xhr.responseJSON && xhr.responseJSON.data
							? xhr.responseJSON.data.error
							: ''
					);
				},
			} );
		},
	};

	$( function () {
		StoreSuiteOrderExport.init();
	} );
} )( jQuery );
