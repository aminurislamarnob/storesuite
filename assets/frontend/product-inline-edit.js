/* global StoreSuite_ProductInlineEdit, Swal */
/**
 * Inline cell editing for the StoreSuite products list.
 *
 * Click (or press Enter on) a Status, SKU, Stock or Price cell to edit it in
 * place. Enter saves through the `storesuite_product_inline_cell_edit` AJAX
 * action and swaps in the re-rendered row; Escape or clicking elsewhere
 * cancels. SweetAlert2 is used for errors only.
 */
( function ( $ ) {
	'use strict';

	var StoreSuiteProductInlineEdit = {
		$activeCell: null,
		originalHtml: '',
		saving: false,

		config: function () {
			return typeof StoreSuite_ProductInlineEdit !== 'undefined'
				? StoreSuite_ProductInlineEdit
				: {};
		},

		i18n: function () {
			return this.config().i18n || {};
		},

		init: function () {
			if ( ! $( '.my-storesuite-product-list-table' ).length ) {
				return;
			}
			this.bindEvents();
		},

		bindEvents: function () {
			var self = this;

			$( document ).on( 'click', 'td.storesuite-inline-cell', function ( event ) {
				// Ignore clicks on links or on an already-open editor.
				if (
					$( event.target ).closest( 'a, .storesuite-inline-editor' ).length ||
					$( this ).hasClass( 'storesuite-inline-editing' )
				) {
					return;
				}
				self.openEditor( $( this ) );
			} );

			$( document ).on( 'keydown', 'td.storesuite-inline-cell', function ( event ) {
				if (
					'Enter' === event.key &&
					! $( this ).hasClass( 'storesuite-inline-editing' )
				) {
					event.preventDefault();
					self.openEditor( $( this ) );
				}
			} );

			$( document ).on( 'keydown', '.storesuite-inline-editor', function ( event ) {
				if ( 'Enter' === event.key ) {
					event.preventDefault();
					self.save();
				} else if ( 'Escape' === event.key ) {
					event.preventDefault();
					self.cancel();
				}
			} );

			// Clicking anywhere outside the open editor cancels it.
			$( document ).on( 'mousedown', function ( event ) {
				if (
					self.$activeCell &&
					! self.saving &&
					! $( event.target ).closest( '.storesuite-inline-editor' ).length
				) {
					self.cancel();
				}
			} );
		},

		openEditor: function ( $cell ) {
			if ( this.$activeCell ) {
				if ( this.saving ) {
					return;
				}
				this.cancel();
			}

			this.$activeCell = $cell;
			this.originalHtml = $cell.html();

			var field = $cell.data( 'inline-field' );
			var $editor = $( '<div class="storesuite-inline-editor" />' );

			if ( 'status' === field ) {
				$editor.append( this.buildSelect( $cell, this.config().statuses || {}, this.i18n().status ) );
			} else if ( 'stock_status' === field ) {
				$editor.append( this.buildSelect( $cell, this.config().stock_statuses || {}, this.i18n().stock_status ) );
			} else if ( 'price' === field ) {
				$editor.append(
					this.buildInput( 'regular_price', String( $cell.data( 'regular-price' ) || '' ), this.i18n().regular_price ),
					this.buildInput( 'sale_price', String( $cell.data( 'sale-price' ) || '' ), this.i18n().sale_price )
				);
			} else if ( 'stock_quantity' === field ) {
				$editor.append( this.buildInput( 'value', String( $cell.data( 'inline-value' ) ), this.i18n().stock_quantity ).attr( 'type', 'number' ).attr( 'step', 'any' ) );
			} else {
				$editor.append( this.buildInput( 'value', String( $cell.data( 'inline-value' ) || '' ), this.i18n().sku ) );
			}

			$editor.append( $( '<span class="storesuite-inline-editor-hint" />' ).text( this.i18n().hint || '' ) );

			$cell.addClass( 'storesuite-inline-editing' ).html( $editor );
			$editor.find( 'input, select' ).first().trigger( 'focus' ).trigger( 'select' );
		},

		buildInput: function ( name, value, label ) {
			return $( '<input type="text" class="storesuite-form-control storesuite-inline-input" />' )
				.attr( 'name', name )
				.attr( 'aria-label', label || '' )
				.attr( 'placeholder', label || '' )
				.val( 'undefined' === value ? '' : value );
		},

		buildSelect: function ( $cell, options, label ) {
			var current = String( $cell.data( 'inline-value' ) );
			var $select = $( '<select class="storesuite-form-control storesuite-inline-input" name="value" />' )
				.attr( 'aria-label', label || '' );

			$.each( options, function ( key, optionLabel ) {
				$select.append(
					$( '<option />' ).attr( 'value', key ).text( optionLabel ).prop( 'selected', key === current )
				);
			} );

			return $select;
		},

		save: function () {
			var self = this;
			var $cell = this.$activeCell;

			if ( ! $cell || this.saving ) {
				return;
			}

			var field = $cell.data( 'inline-field' );
			var data = {
				action: 'storesuite_product_inline_cell_edit',
				security: this.config().nonce,
				product_id: $cell.closest( 'tr' ).find( 'input[name="bulk_product_ids[]"]' ).val(),
				field: field,
				context: this.config().context || 'products',
			};

			if ( 'price' === field ) {
				data.regular_price = $cell.find( 'input[name="regular_price"]' ).val();
				data.sale_price = $cell.find( 'input[name="sale_price"]' ).val();
			} else {
				data.value = $cell.find( '[name="value"]' ).val();
			}

			this.saving = true;
			$cell.addClass( 'storesuite-inline-saving' );
			$cell.find( 'input, select' ).prop( 'disabled', true );

			$.ajax( {
				url: this.config().ajax_url,
				method: 'POST',
				dataType: 'json',
				data: data,
			} )
				.done( function ( response ) {
					if ( response && response.success && response.data && response.data.row ) {
						var $row = $cell.closest( 'tr' );
						var $newRow = $( response.data.row );
						$row.replaceWith( $newRow );
						$newRow.addClass( 'storesuite-inline-saved-flash' );
						setTimeout( function () {
							$newRow.removeClass( 'storesuite-inline-saved-flash' );
						}, 1600 );
						self.reset();
					} else {
						self.fail( ( response && response.data && response.data.message ) || self.i18n().unexpected_error );
					}
				} )
				.fail( function ( xhr ) {
					var message =
						xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
							? xhr.responseJSON.data.message
							: self.i18n().unexpected_error;
					self.fail( message );
				} );
		},

		fail: function ( message ) {
			var self = this;
			var $cell = this.$activeCell;

			this.saving = false;
			if ( $cell ) {
				$cell.removeClass( 'storesuite-inline-saving' );
				$cell.find( 'input, select' ).prop( 'disabled', false );
			}

			if ( 'undefined' !== typeof Swal ) {
				Swal.fire( {
					icon: 'error',
					title: self.i18n().error_title,
					text: message,
					confirmButtonText: self.i18n().ok_button,
				} ).then( function () {
					if ( self.$activeCell ) {
						self.$activeCell.find( 'input, select' ).first().trigger( 'focus' );
					}
				} );
			} else {
				window.alert( message ); // eslint-disable-line no-alert
			}
		},

		cancel: function () {
			var $cell = this.$activeCell;
			if ( ! $cell || this.saving ) {
				return;
			}
			$cell.removeClass( 'storesuite-inline-editing storesuite-inline-saving' ).html( this.originalHtml );
			$cell.trigger( 'focus' );
			this.reset();
		},

		reset: function () {
			if ( this.$activeCell ) {
				this.$activeCell.removeClass( 'storesuite-inline-editing storesuite-inline-saving' );
			}
			this.$activeCell = null;
			this.originalHtml = '';
			this.saving = false;
		},
	};

	$( function () {
		StoreSuiteProductInlineEdit.init();
	} );
} )( jQuery );
