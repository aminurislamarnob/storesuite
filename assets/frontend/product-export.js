/* global StoreSuite_ProductExport, Swal */
/**
 * Product CSV export for the StoreSuite dashboard.
 *
 * Drives WooCommerce's product CSV exporter through the `storesuite_product_export` AJAX action
 * using the same multi-step batch loop as WooCommerce's wc-product-export.js, wrapped in the
 * StoreSuite modal / SweetAlert2 conventions.
 */
( function ( $ ) {
	'use strict';

	var StoreSuiteProductExport = {
		xhr: false,

		config: function () {
			return typeof StoreSuite_ProductExport !== 'undefined'
				? StoreSuite_ProductExport
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
			this.$modal = $( '#storesuite-product-export-modal' );
			this.$form = $( '#storesuite-product-export-form' );

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

			this.bindEvents();
		},

		bindEvents: function () {
			var self = this;

			// Toolbar "Export" button: export all / current filters.
			$( document ).on(
				'click',
				'#storesuite-export-toggle',
				function ( event ) {
					event.preventDefault();
					self.openModal( [] );
				}
			);

			// Bulk action "Export": export the checked products.
			$( document ).on(
				'submit',
				'#storesuite-product-bulk-actions',
				function ( event ) {
					if (
						$( '#bulk-action-selector-products' ).val() !== 'export'
					) {
						return;
					}

					event.preventDefault();

					var selectedProductIds = $( this )
						.find( 'input[name="bulk_product_ids[]"]:checked' )
						.map( function () {
							return $( this ).val();
						} )
						.get();

					if ( ! selectedProductIds.length ) {
						self.warn();
						return;
					}

					self.openModal( selectedProductIds );
				}
			);

			// Hide the category filter when exporting variations.
			this.$form
				.find( '.storesuite-export-types' )
				.on( 'change', function () {
					self.toggleCategoryField();
				} );

			// "Clear your selection" inside the bulk export notice: export everything instead.
			this.$form.on(
				'click',
				'.storesuite-export-clear-selection',
				function ( event ) {
					event.preventDefault();
					self.clearSelection();
				}
			);

			// Run the export.
			this.$form.on( 'submit', function ( event ) {
				event.preventDefault();
				self.onSubmit();
			} );
		},

		toggleCategoryField: function () {
			var values = this.$form.find( '.storesuite-export-types' ).val() || [];
			var $categoryRow = this.$form.find( '.storesuite-export-category-row' );

			if ( $.inArray( 'variation', values ) !== -1 ) {
				$categoryRow.hide();
				this.$form
					.find( '.storesuite-export-category' )
					.val( null )
					.trigger( 'change' );
			} else {
				$categoryRow.show();
			}
		},

		warn: function () {
			if ( typeof Swal === 'undefined' ) {
				return;
			}
			var i18n = this.i18n();
			Swal.fire( {
				icon: 'warning',
				title: i18n.select_products_title,
				text: i18n.select_products_message,
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

		openModal: function ( selectedProductIds ) {
			var ids = Array.isArray( selectedProductIds )
				? selectedProductIds
				: [];

			this.$form
				.find( '#storesuite-export-product-ids' )
				.val( ids.join( ',' ) );

			this.renderBulkNotice( ids.length );
			this.toggleBulkFields( ids.length > 0 );
			this.resetProgress();

			var suiteModal = this.suiteModal();
			if ( suiteModal ) {
				suiteModal.open( this.$modal );
			}
		},

		// Bulk export targets an explicit product list, so the type / category filters
		// don't apply — hide them and leave only columns + custom meta (matching WooCommerce).
		toggleBulkFields: function ( isBulk ) {
			var $typesRow = this.$form.find( '.storesuite-export-types-row' );
			var $categoryRow = this.$form.find( '.storesuite-export-category-row' );

			if ( isBulk ) {
				// Drop any stale filter values so they don't narrow the selected products.
				this.$form
					.find(
						'.storesuite-export-types, .storesuite-export-category'
					)
					.val( null )
					.trigger( 'change' );
				$typesRow.hide();
				$categoryRow.hide();
				return;
			}

			$typesRow.show();
			$categoryRow.show();
			this.toggleCategoryField();
		},

		// Show WooCommerce-style "You are about to export N products" notice for bulk exports.
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
			this.$form.find( '#storesuite-export-product-ids' ).val( '' );
			this.renderBulkNotice( 0 );
			this.toggleBulkFields( false );

			// Mirror WooCommerce: clearing the selection unchecks the product list.
			$( 'input[name="bulk_product_ids[]"]:checked, #cb-select-all-products' )
				.prop( 'checked', false );
			$( '#cb-select-all-products' ).prop( 'indeterminate', false );
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
				'storesuite-product-export-' +
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
					action: 'storesuite_product_export',
					security: config.export_nonce,
					step: step,
					columns: columns,
					selected_columns:
						self.$form.find( '.storesuite-export-columns' ).val(),
					export_meta: self.$form.find(
						'#storesuite-export-meta:checked'
					).length
						? 1
						: 0,
					export_types:
						self.$form.find( '.storesuite-export-types' ).val(),
					export_category:
						self.$form.find( '.storesuite-export-category' ).val(),
					export_product_ids:
						self.$form
							.find( '#storesuite-export-product-ids' )
							.val() || '',
					filename: filename,
				},
				success: function ( response ) {
					if ( ! response || ! response.success ) {
						self.resetProgress();
						self.showError(
							response && response.data
								? response.data.error
								: ''
						);
						return;
					}

					var data = response.data;

					if ( 'done' === data.step ) {
						self.$form
							.find( '.storesuite-export-progress' )
							.val( data.percentage );
						window.location = data.url;
						setTimeout( function () {
							self.resetProgress();
							self.closeModal();
						}, 2000 );
						return;
					}

					self.$form
						.find( '.storesuite-export-progress' )
						.val( data.percentage );
					self.processStep(
						parseInt( data.step, 10 ),
						data.columns,
						filename
					);
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
		StoreSuiteProductExport.init();
	} );
} )( jQuery );
