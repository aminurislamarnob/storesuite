( function ( $ ) {
	// Stand-in wcTracks.recordEvent in case tracks is not available (for any reason).
	window.wcTracks = window.wcTracks || {};
	window.wcTracks.recordEvent = window.wcTracks.recordEvent || function () {};

	var StoreFrontOrderConfig = {
		init: function () {
			this.bindEvents();
		},
		bindEvents: function () {
			$( '#customer_user' ).show().selectWoo().hide();
			this.handleSelect2Customer(); // Handle select2 customer
			this.initDatePicker(); // Handle order date picker
			$( '#customer_user' ).on( 'change', this.changeCustomerUser );
			$( '.edit-storesuite-order-billing-address' ).on(
				'click',
				this.showBillingAddressFields
			);
			$( '.edit-storesuite-order-shipping-address' ).on(
				'click',
				this.showShippingAddressFields
			);
		},

		/**
		 * Initialize datepicker for the order created date field.
		 */
		initDatePicker: function () {
			$( 'input.date-picker' ).datepicker( {
				defaultDate: '',
				dateFormat: 'yy-mm-dd',
				numberOfMonths: 1,
				showButtonPanel: true,
			} );
		},

		handleSelect2Customer: function () {
			// Ajax customer search boxes
			$( ':input.wc-customer-search' )
				.filter( ':not(.enhanced)' )
				.each( function () {
					var select2_args = {
						allowClear: $( this ).data( 'allow_clear' )
							? true
							: false,
						placeholder: $( this ).data( 'placeholder' ),
						minimumInputLength: $( this ).data(
							'minimum_input_length'
						)
							? $( this ).data( 'minimum_input_length' )
							: '1',
						escapeMarkup: function ( m ) {
							return m;
						},
						ajax: {
							url: StoreSuite_Order.ajax_url,
							dataType: 'json',
							delay: 1000,
							data: function ( params ) {
								return {
									term: params.term,
									action: 'woocommerce_json_search_customers',
									security:
										StoreSuite_Order.search_customers_nonce,
									exclude: $( this ).data( 'exclude' ),
								};
							},
							processResults: function ( data ) {
								var terms = [];
								if ( data ) {
									$.each( data, function ( id, text ) {
										terms.push( {
											id: id,
											text: text,
										} );
									} );
								}
								return {
									results: terms,
								};
							},
							cache: true,
						},
					};

					select2_args = $.extend(
						select2_args,
						StoreFrontOrderConfig.getEnhancedSelectFormatString()
					);

					$( this ).selectWoo( select2_args ).addClass( 'enhanced' );

					if ( $( this ).data( 'sortable' ) ) {
						var $select = $( this );
						var $list = $( this )
							.next( '.select2-container' )
							.find( 'ul.select2-selection__rendered' );

						$list.sortable( {
							placeholder:
								'ui-state-highlight select2-selection__choice',
							forcePlaceholderSize: true,
							items: 'li:not(.select2-search__field)',
							tolerance: 'pointer',
							stop: function () {
								$(
									$list
										.find( '.select2-selection__choice' )
										.get()
										.reverse()
								).each( function () {
									var id = $( this ).data( 'data' ).id;
									var option = $select.find(
										'option[value="' + id + '"]'
									)[ 0 ];
									$select.prepend( option );
								} );
							},
						} );
					}
				} );
		},

		getEnhancedSelectFormatString: function () {
			return {
				language: {
					errorLoading: function () {
						// Workaround for https://github.com/select2/select2/issues/4355 instead of i18n_ajax_error.
						return StoreSuite_Order.i18n_searching;
					},
					inputTooLong: function ( args ) {
						var overChars = args.input.length - args.maximum;

						if ( 1 === overChars ) {
							return StoreSuite_Order.i18n_input_too_long_1;
						}

						return StoreSuite_Order.i18n_input_too_long_n.replace(
							'%qty%',
							overChars
						);
					},
					inputTooShort: function ( args ) {
						var remainingChars = args.minimum - args.input.length;

						if ( 1 === remainingChars ) {
							return StoreSuite_Order.i18n_input_too_short_1;
						}

						return StoreSuite_Order.i18n_input_too_short_n.replace(
							'%qty%',
							remainingChars
						);
					},
					loadingMore: function () {
						return StoreSuite_Order.i18n_load_more;
					},
					maximumSelected: function ( args ) {
						if ( args.maximum === 1 ) {
							return StoreSuite_Order.i18n_selection_too_long_1;
						}

						return StoreSuite_Order.i18n_selection_too_long_n.replace(
							'%qty%',
							args.maximum
						);
					},
					noResults: function () {
						return StoreSuite_Order.i18n_no_matches;
					},
					searching: function () {
						return StoreSuite_Order.i18n_searching;
					},
				},
			};
		},

		changeCustomerUser: function () {
			$( 'a.edit_address' ).trigger( 'click' );
			StoreFrontOrderConfig.loadBilling( true );
			StoreFrontOrderConfig.loadShipping( true );
		},

		loadBilling: function ( force ) {
			if (
				true === force ||
				window.confirm( StoreSuite_Order.load_billing )
			) {
				// Get user ID to load data for
				var user_id = $( '#customer_user' ).val();

				// if ( ! user_id ) {
				// 	window.alert( StoreSuite_Order.no_customer_selected );
				// 	return false;
				// }

				var data = {
					user_id: user_id,
					action: 'woocommerce_get_customer_details',
					security: StoreSuite_Order.get_customer_details_nonce,
				};

				window.StoreSuite.storeSuiteLoader.block(
					$( this ).closest( 'div.edit_address' )
				);

				$.ajax( {
					url: StoreSuite_Order.ajax_url,
					data: data,
					type: 'POST',
					success: function ( response ) {
						if ( response && response.billing ) {
							StoreFrontOrderConfig.showShippingAddress();
							$.each( response.billing, function ( key, data ) {
								$( ':input#_billing_' + key )
									.val( data )
									.trigger( 'change' );

								if ( data ) {
									$( 'li._billing_' + key + ' span' ).text(
										data
									);
								} else {
									$( 'li._billing_' + key ).hide();
								}
							} );
						}
						window.StoreSuite.storeSuiteLoader.unblock(
							$( 'div.edit_address' )
						);
					},
				} );
			}
			return false;
		},

		loadShipping: function ( force ) {
			if (
				true === force ||
				window.confirm( StoreSuite_Order.load_shipping )
			) {
				// Get user ID to load data for
				var user_id = $( '#customer_user' ).val();

				// if ( ! user_id ) {
				// 	window.alert( StoreSuite_Order.no_customer_selected );
				// 	return false;
				// }

				var data = {
					user_id: user_id,
					action: 'woocommerce_get_customer_details',
					security: StoreSuite_Order.get_customer_details_nonce,
				};

				window.StoreSuite.storeSuiteLoader.block(
					$( this ).closest( 'div.edit_address' )
				);

				$.ajax( {
					url: StoreSuite_Order.ajax_url,
					data: data,
					type: 'POST',
					success: function ( response ) {
						if ( response && response.shipping ) {
							StoreFrontOrderConfig.showShippingAddress();
							$.each( response.shipping, function ( key, data ) {
								$( ':input#_shipping_' + key )
									.val( data )
									.trigger( 'change' );

								if ( data ) {
									$( 'li._shipping_' + key + ' span' ).text(
										data
									);
								} else {
									$( 'li._shipping_' + key ).hide();
								}
							} );
						}
						window.StoreSuite.storeSuiteLoader.unblock(
							$( 'div.edit_address' )
						);
					},
				} );
			}
			return false;
		},
		showShippingAddress: function () {
			$( '.customer-address-box' ).addClass( 'show-address' );
			$( '.customer-address-box' ).removeClass( 'hide-address' );
		},
		copy_billing_to_shipping: function () {
			if ( window.confirm( StoreSuite_Order.copy_billing ) ) {
				$( '.order_data_column :input[name^="_billing_"]' ).each(
					function () {
						var input_name = $( this ).attr( 'name' );
						input_name = input_name.replace(
							'_billing_',
							'_shipping_'
						);
						$( ':input#' + input_name )
							.val( $( this ).val() )
							.trigger( 'change' );
					}
				);
			}
			return false;
		},

		showBillingAddressFields: function ( e ) {
			e.preventDefault();
			$( this ).hide();
			$( '.customer-billing-address' ).hide();
			$( '.storesuite-billing-address-fields' ).show();
		},

		showShippingAddressFields: function ( e ) {
			e.preventDefault();
			$( this ).hide();
			$( '.customer-shipping-address' ).hide();
			$( '.storesuite-shipping-address-fields' ).show();
		},
	};

	/**
	 * Order Notes Panel
	 */
	var NewOrderNotes = {
		init: function () {
			$( '#new_order_notes' )
				.on( 'click', 'button.add-note', this.add_order_note )
				.on( 'click', 'a.delete_note', this.delete_order_note );
		},

		add_order_note: function () {
			if ( ! $( 'textarea#add_order_note' ).val() ) {
				return;
			}

			window.StoreSuite.storeSuiteLoader.block( $( '#new_order_notes' ) );

			var data = {
				action: 'woocommerce_add_order_note',
				post_id: StoreSuite_Order.post_id,
				note: $( 'textarea#add_order_note' ).val(),
				note_type: $( 'select#order_note_type' ).val(),
				security: StoreSuite_Order.add_order_note_nonce,
			};

			$.post( StoreSuite_Order.ajax_url, data, function ( response ) {
				$( 'ul.order_notes .no-items' ).remove();
				$( 'ul.order_notes' ).prepend( response );
				window.StoreSuite.storeSuiteLoader.unblock(
					$( '#new_order_notes' )
				);
				$( '#add_order_note' ).val( '' );
			} );

			return false;
		},

		delete_order_note: function () {
			if ( window.confirm( StoreSuite_Order.i18n_delete_note ) ) {
				var note = $( this ).closest( 'li.note' );

				window.StoreSuite.storeSuiteLoader.block( $( note ) );

				var data = {
					action: 'woocommerce_delete_order_note',
					note_id: $( note ).attr( 'rel' ),
					security: StoreSuite_Order.delete_order_note_nonce,
				};

				$.post( StoreSuite_Order.ajax_url, data, function () {
					$( note ).remove();

					if ( $( 'ul.order_notes' ).find( 'li' ).length === 0 ) {
						$( 'ul.order_notes' ).append(
							'<li class="note no-items"><div class="note_content"><p>' +
								StoreSuite_Order.i18n_no_notes +
								'</p></div></li>'
						);
					}
				} );
			}

			return false;
		},
	};

	/**
	 * Order Refunds Panel
	 */
	var HandleRefunds = {
		init: function () {
			this.bindEvents();
		},
		bindEvents: function () {
			$( '#woocommerce-order-items' )
				.on( 'click', '.refund-items', this.addRefund )
				.on( 'click', '.cancel-action', this.cancel )
				.on(
					'click',
					'.refund-actions .cancel-action',
					this.trackCancel
				)
				.on( 'click', '.delete_refund', this.deleteRefund )
				.on(
					'click',
					'button.do-api-refund, button.do-manual-refund',
					this.doRefund
				)
				.on(
					'change',
					'.refund input.refund_line_total, .refund input.refund_line_tax',
					this.refundInputChanged
				)
				.on(
					'change keyup',
					'.wc-order-refund-items #refund_amount',
					this.refundAmountChanged
				)
				.on(
					'change',
					'input.refund_order_item_qty',
					this.refundQuantityChanged
				);
		},
		addRefund: function () {
			$( 'div.wc-order-refund-items' ).slideDown();
			$( 'div.wc-order-data-row-toggle' )
				.not( 'div.wc-order-refund-items' )
				.slideUp();
			$( 'div.wc-order-totals-items' ).slideUp();
			$( '#woocommerce-order-items' ).find( 'div.refund' ).show();
			$(
				'.wc-order-edit-line-item .wc-order-edit-line-item-actions'
			).hide();

			window.wcTracks.recordEvent( 'order_edit_refund_button_click', {
				order_id: StoreSuite_Order.post_id,
				status: $( '#order_status' ).val(),
			} );

			return false;
		},
		cancel: function () {
			$( 'div.wc-order-data-row-toggle' )
				.not( 'div.wc-order-bulk-actions' )
				.slideUp();
			$( 'div.wc-order-bulk-actions' ).slideDown();
			$( 'div.wc-order-totals-items' ).slideDown();
			$( '#woocommerce-order-items' ).find( 'div.refund' ).hide();
			$(
				'.wc-order-edit-line-item .wc-order-edit-line-item-actions'
			).show();

			// Reload the items
			if ( 'true' === $( this ).attr( 'data-reload' ) ) {
				HandleRefunds.reloadItems();
			}

			window.wcTracks.recordEvent( 'order_edit_add_items_cancelled', {
				order_id: StoreSuite_Order.post_id,
				status: $( '#order_status' ).val(),
			} );

			return false;
		},
		trackCancel: function () {
			window.wcTracks.recordEvent( 'order_edit_refund_cancel', {
				order_id: StoreSuite_Order.post_id,
				status: $( '#order_status' ).val(),
			} );
		},
		reloadItems: function () {
			var data = {
				order_id: StoreSuite_Order.post_id,
				action: 'woocommerce_load_order_items',
				security: StoreSuite_Order.order_item_nonce,
			};

			data = NewOrderProducts.filterData( 'reload_items', data );

			window.StoreSuite.storeSuiteLoader.block(
				$( '#woocommerce-order-items' )
			);

			$.ajax( {
				url: StoreSuite_Order.ajax_url,
				data: data,
				type: 'POST',
				success: function ( response ) {
					$( '#woocommerce-order-items' ).find( '.inside' ).empty();
					$( '#woocommerce-order-items' )
						.find( '.inside' )
						.append( response );
					window.StoreSuite.storeSuiteLoader.unblock(
						$( '#woocommerce-order-items' )
					);
				},
			} );
		},
		deleteRefund: function () {
			if ( window.confirm( StoreSuite_Order.i18n_delete_refund ) ) {
				var $refund = $( this ).closest( 'tr.refund' );
				var refund_id = $refund.attr( 'data-order_refund_id' );

				window.StoreSuite.storeSuiteLoader.block(
					$( '#woocommerce-order-items' )
				);

				var data = {
					action: 'woocommerce_delete_refund',
					refund_id: refund_id,
					security: StoreSuite_Order.order_item_nonce,
				};

				data = NewOrderProducts.filterData( 'delete_refund', data );

				$.ajax( {
					url: StoreSuite_Order.ajax_url,
					data: data,
					type: 'POST',
					success: function () {
						HandleRefunds.reloadItems();
					},
				} );
			}
			return false;
		},
		doRefund: function () {
			window.StoreSuite.storeSuiteLoader.block(
				$( '#woocommerce-order-items' )
			);

			if ( window.confirm( StoreSuite_Order.i18n_do_refund ) ) {
				var refund_amount = $( 'input#refund_amount' ).val();
				var refund_reason = $( 'input#refund_reason' ).val();
				var refunded_amount = $( 'input#refunded_amount' ).val();

				// Get line item refunds
				var line_item_qtys = {};
				var line_item_totals = {};
				var line_item_tax_totals = {};

				$( '.refund input.refund_order_item_qty' ).each(
					function ( index, item ) {
						if (
							$( item ).closest( 'tr' ).data( 'order_item_id' )
						) {
							if ( item.value ) {
								line_item_qtys[
									$( item )
										.closest( 'tr' )
										.data( 'order_item_id' )
								] = item.value;
							}
						}
					}
				);

				$( '.refund input.refund_line_total' ).each(
					function ( index, item ) {
						if (
							$( item ).closest( 'tr' ).data( 'order_item_id' )
						) {
							line_item_totals[
								$( item )
									.closest( 'tr' )
									.data( 'order_item_id' )
							] = accounting.unformat(
								item.value,
								StoreSuite_Order.mon_decimal_point
							);
						}
					}
				);

				$( '.refund input.refund_line_tax' ).each(
					function ( index, item ) {
						if (
							$( item ).closest( 'tr' ).data( 'order_item_id' )
						) {
							var tax_id = $( item ).data( 'tax_id' );

							if (
								! line_item_tax_totals[
									$( item )
										.closest( 'tr' )
										.data( 'order_item_id' )
								]
							) {
								line_item_tax_totals[
									$( item )
										.closest( 'tr' )
										.data( 'order_item_id' )
								] = {};
							}

							line_item_tax_totals[
								$( item )
									.closest( 'tr' )
									.data( 'order_item_id' )
							][ tax_id ] = accounting.unformat(
								item.value,
								StoreSuite_Order.mon_decimal_point
							);
						}
					}
				);

				var data = {
					action: 'woocommerce_refund_line_items',
					order_id: StoreSuite_Order.post_id,
					refund_amount: refund_amount,
					refunded_amount: refunded_amount,
					refund_reason: refund_reason,
					line_item_qtys: JSON.stringify( line_item_qtys, null, '' ),
					line_item_totals: JSON.stringify(
						line_item_totals,
						null,
						''
					),
					line_item_tax_totals: JSON.stringify(
						line_item_tax_totals,
						null,
						''
					),
					api_refund: $( this ).is( '.do-api-refund' ),
					restock_refunded_items: $(
						'#restock_refunded_items:checked'
					).length
						? 'true'
						: 'false',
					security: StoreSuite_Order.order_item_nonce,
				};

				data = NewOrderProducts.filterData( 'do_refund', data );

				$.ajax( {
					url: StoreSuite_Order.ajax_url,
					data: data,
					type: 'POST',
					success: function ( response ) {
						if ( true === response.success ) {
							// Redirect to same page for show the refunded status
							window.location.reload();
						} else {
							window.alert( response.data.error );
							HandleRefunds.reloadItems();
						}
					},
					complete: function () {
						window.wcTracks.recordEvent( 'order_edit_refunded', {
							order_id: data.order_id,
							status: $( '#order_status' ).val(),
							api_refund: data.api_refund,
							has_reason: Boolean( data.refund_reason.length ),
							restock: 'true' === data.restock_refunded_items,
						} );
					},
				} );
			} else {
				window.StoreSuite.storeSuiteLoader.unblock(
					$( '#woocommerce-order-items' )
				);
			}
		},
		refundInputChanged: function () {
			var refund_amount = 0;
			var $items = $( '.woocommerce_order_items' ).find(
				'tr.item, tr.fee, tr.shipping'
			);
			var round_at_subtotal =
				'yes' === StoreSuite_Order.round_at_subtotal;

			$items.each( function () {
				var $row = $( this );
				var refund_cost_fields = $row.find(
					'.refund input:not(.refund_order_item_qty)'
				);

				refund_cost_fields.each( function ( index, el ) {
					var field_amount = accounting.unformat(
						$( el ).val() || 0,
						StoreSuite_Order.mon_decimal_point
					);
					refund_amount += parseFloat(
						round_at_subtotal
							? field_amount
							: accounting.formatNumber(
									field_amount,
									StoreSuite_Order.currency_format_num_decimals,
									''
							  )
					);
				} );
			} );

			$( '#refund_amount' )
				.val(
					accounting.formatNumber(
						refund_amount,
						StoreSuite_Order.currency_format_num_decimals,
						'',
						StoreSuite_Order.mon_decimal_point
					)
				)
				.trigger( 'change' );
		},
		refundAmountChanged: function () {
			var total = accounting.unformat(
				$( this ).val(),
				StoreSuite_Order.mon_decimal_point
			);

			$( 'button .wc-order-refund-amount .amount' ).text(
				accounting.formatMoney( total, {
					symbol: StoreSuite_Order.currency_format_symbol,
					decimal: StoreSuite_Order.currency_format_decimal_sep,
					thousand: StoreSuite_Order.currency_format_thousand_sep,
					precision: StoreSuite_Order.currency_format_num_decimals,
					format: StoreSuite_Order.currency_format,
				} )
			);
		},
		refundQuantityChanged: function () {
			var $row = $( this ).closest( 'tr.item' );
			var qty = $row.find( 'input.quantity' ).val();
			var refund_qty = $( this ).val();
			var line_total = $( 'input.line_total', $row );
			var refund_line_total = $( 'input.refund_line_total', $row );

			// Totals
			var unit_total =
				accounting.unformat(
					line_total.attr( 'data-total' ),
					StoreSuite_Order.mon_decimal_point
				) / qty;

			refund_line_total
				.val(
					parseFloat(
						accounting.formatNumber(
							unit_total * refund_qty,
							StoreSuite_Order.rounding_precision,
							''
						)
					)
						.toString()
						.replace( '.', StoreSuite_Order.mon_decimal_point )
				)
				.trigger( 'change' );

			// Taxes
			$( '.refund_line_tax', $row ).each( function () {
				var $refund_line_total_tax = $( this );
				var tax_id = $refund_line_total_tax.data( 'tax_id' );
				var line_total_tax = $(
					'input.line_tax[data-tax_id="' + tax_id + '"]',
					$row
				);
				var unit_total_tax =
					accounting.unformat(
						line_total_tax.data( 'total_tax' ),
						StoreSuite_Order.mon_decimal_point
					) / qty;

				if ( 0 < unit_total_tax ) {
					$refund_line_total_tax
						.val(
							parseFloat(
								accounting.formatNumber(
									unit_total_tax * refund_qty,
									StoreSuite_Order.rounding_precision,
									''
								)
							)
								.toString()
								.replace(
									'.',
									StoreSuite_Order.mon_decimal_point
								)
						)
						.trigger( 'change' );
				} else {
					$refund_line_total_tax.val( 0 ).trigger( 'change' );
				}
			} );

			// Restock checkbox
			if ( refund_qty > 0 ) {
				$( '#restock_refunded_items' ).closest( 'tr' ).show();
			} else {
				$( '#restock_refunded_items' ).closest( 'tr' ).hide();
				$(
					'.woocommerce_order_items input.refund_order_item_qty'
				).each( function () {
					if ( $( this ).val() > 0 ) {
						$( '#restock_refunded_items' ).closest( 'tr' ).show();
					}
				} );
			}

			$( this ).trigger( 'refund_quantity_changed' );
		},
	};

	/**
	 * Order Notes Panel
	 */
	var NewOrderProducts = {
		init: function () {
			this.bindEvents();
		},
		bindEvents: function () {
			$( '#storesuite_product_search' ).show().selectWoo().hide();
			this.handleProductSearch();
			this.handleDeleteSearchItem();
			this.selectProduct();
			this.deleteSearchOrderItem();
			this.addToOrder();
			this.addCoupon();
			this.removeCoupon();
			this.addFee();
			this.addShippingToOrder();
			this.createOrder();
			this.saveLineItems();
			this.editOrderItem();
			this.deleteOrderItem();
			this.quantityChanged();
			$( document ).on(
				'click',
				'button.calculate-action',
				this.recalculateOrder
			);
		},
		displayResult: function ( self, select2_args ) {
			select2_args = $.extend(
				select2_args,
				StoreFrontOrderConfig.getEnhancedSelectFormatString()
			);

			$( self ).selectWoo( select2_args ).addClass( 'enhanced' );

			if ( $( self ).data( 'sortable' ) ) {
				var $select = $( self );
				var $list = $( self )
					.next( '.select2-container' )
					.find( 'ul.select2-selection__rendered' );

				$list.sortable( {
					placeholder: 'ui-state-highlight select2-selection__choice',
					forcePlaceholderSize: true,
					items: 'li:not(.select2-search__field)',
					tolerance: 'pointer',
					stop: function () {
						$(
							$list
								.find( '.select2-selection__choice' )
								.get()
								.reverse()
						).each( function () {
							var id = $( this ).data( 'data' ).id;
							var option = $select.find(
								'option[value="' + id + '"]'
							)[ 0 ];
							$select.prepend( option );
						} );
					},
				} );
				// Keep multiselects ordered alphabetically if they are not sortable.
			} else if ( $( self ).prop( 'multiple' ) ) {
				$( self ).on( 'change', function () {
					var $children = $( self ).children();
					$children.sort( function ( a, b ) {
						var atext = a.text.toLowerCase();
						var btext = b.text.toLowerCase();

						if ( atext > btext ) {
							return 1;
						}
						if ( atext < btext ) {
							return -1;
						}
						return 0;
					} );
					$( self ).html( $children );
				} );
			}
		},

		handleProductSearch: function () {
			// Ajax product search box
			$( ':input.wc-product-search' )
				.filter( ':not(.enhanced)' )
				.each( function () {
					var select2_args = {
						allowClear: $( this ).data( 'allow_clear' )
							? true
							: false,
						placeholder: $( this ).data( 'placeholder' ),
						minimumInputLength: $( this ).data(
							'minimum_input_length'
						)
							? $( this ).data( 'minimum_input_length' )
							: '3',
						escapeMarkup: function ( m ) {
							return m;
						},
						ajax: {
							url: StoreSuite_Order.ajax_url,
							dataType: 'json',
							delay: 250,
							data: function ( params ) {
								return {
									term: params.term,
									action:
										$( this ).data( 'action' ) ||
										'woocommerce_json_search_products_and_variations',
									security:
										StoreSuite_Order.search_products_nonce,
									exclude: $( this ).data( 'exclude' ),
									exclude_type:
										$( this ).data( 'exclude_type' ),
									include: $( this ).data( 'include' ),
									limit: $( this ).data( 'limit' ),
									display_stock:
										$( this ).data( 'display_stock' ),
								};
							},
							processResults: function ( data ) {
								var terms = [];
								if ( data ) {
									$.each( data, function ( id, text ) {
										terms.push( { id: id, text: text } );
									} );
								}
								return {
									results: terms,
								};
							},
							cache: true,
						},
					};

					NewOrderProducts.displayResult( this, select2_args );
				} );
		},

		handleDeleteSearchItem: function () {
			$( document ).on(
				'click',
				'.delete-search-order-item',
				function ( e ) {
					e.preventDefault();

					var $row = $( this ).closest( 'tr' );

					// Fade out and remove
					$row.fadeOut( 200, function () {
						$( this ).remove();
					} );
				}
			);
		},

		selectProduct: function () {
			$( '#storesuite_product_search' ).on( 'change', function ( e ) {
				var selectedValue = $( this ).val();
				var selectedText = $( this ).find( 'option:selected' ).text();

				if ( selectedValue ) {
					var row = `<td data-id="${ selectedValue }">${ selectedText }</td>
					<td><input type="number" step="1" min="0" max="9999" autocomplete="off" name="item_qty" placeholder="1" size="4" class="storesuite-form-control quantity-input" /></td>
					<td><button type="button" class="delete-btn delete-search-order-item"><span class="storesuite-delete-icon"></span></button></td>`;

					var item_table = $(
							'#search-order-items table.storesuite-table'
						),
						item_table_body = item_table.find( 'tbody' );
					item_table_body.append( '<tr>' + row + '</tr>' );

					// Show table
					NewOrderProducts.toggleOrderItemsTable();

					// Clear after a short delay to show the selection was made
					setTimeout( function () {
						$( '#storesuite_product_search' )
							.val( null )
							.trigger( 'change' );
					}, 500 );
				}
			} );
		},

		deleteSearchOrderItem: function () {
			$( document ).on(
				'click',
				'.delete-search-order-item',
				function ( e ) {
					e.preventDefault();

					// Remove the closest table row
					$( this ).closest( 'tr' ).remove();

					// Show/hide table
					NewOrderProducts.toggleOrderItemsTable();
				}
			);
		},

		toggleOrderItemsTable: function () {
			var rowCount = $( '#search-order-items tbody tr' ).length;

			if ( rowCount > 0 ) {
				$( '#search-order-items' ).show();
			} else {
				$( '#search-order-items' ).hide();
			}
		},

		addToOrder: function () {
			$( document ).on( 'click', '#add-to-order-items', function ( e ) {
				e.preventDefault();
				var prod_search_for_order_box = $(
					'.product-serach-for-order-box'
				);
				window.StoreSuite.storeSuiteLoader.block(
					prod_search_for_order_box
				);

				var item_table = $(
						'#search-order-items table.storesuite-table'
					),
					item_table_body = item_table.find( 'tbody' ),
					rows = item_table_body.find( 'tr' ),
					add_items = [];

				$( rows ).each( function () {
					var item_id = $( this ).find( 'td[data-id]' ).data( 'id' ),
						item_qty = $( this )
							.find( 'input[name="item_qty"]' )
							.val();

					add_items.push( {
						id: item_id,
						qty: item_qty ? item_qty : 1,
					} );
				} );

				var data = {
					action: 'woocommerce_add_order_item',
					order_id: StoreSuite_Order.post_id,
					security: StoreSuite_Order.order_item_nonce,
					data: add_items,
				};

				// Check if items have changed, if so pass them through so we can save them before adding a new item.
				// if ( 'true' === $( 'button.cancel-action' ).attr( 'data-reload' ) ) {
				// 	data.items = $( 'table.woocommerce_order_items :input[name], .wc-order-totals-items :input[name]' ).serialize();
				// }

				data = NewOrderProducts.filterData( 'add_items', data );

				$.ajax( {
					type: 'POST',
					url: StoreSuite_Order.ajax_url,
					data: data,
					success: function ( response ) {
						if ( response.success ) {
							// Hide table
							$( '#search-order-items' ).hide();
							$( '#search-order-items table tbody' ).empty();
							$( '.order-fee-and-shipping-box' ).addClass(
								'active'
							);

							$( '#woocommerce-order-items' )
								.find( '.inside' )
								.empty();
							$( '#woocommerce-order-items' )
								.find( '.inside' )
								.append( response.data.html );

							// Update notes.
							if ( response.data.notes_html ) {
								$( 'ul.order_notes' ).empty();
								$( 'ul.order_notes' ).append(
									$( response.data.notes_html ).find( 'li' )
								);
							}

							// prod_search_for_order_box.reloaded_items();
							window.StoreSuite.storeSuiteLoader.unblock(
								prod_search_for_order_box
							);
						} else {
							window.StoreSuite.storeSuiteLoader.unblock(
								prod_search_for_order_box
							);
							window.alert( response.data.error );
						}
					},
					complete: function () {
						NewOrderProducts.recalculateOrder();
					},
					dataType: 'json',
				} );
			} );
		},

		// When the qty is changed, increase or decrease costs
		quantityChanged: function () {
			$( document ).on( 'change', 'input.quantity', function ( e ) {
				e.preventDefault();
				var $row = $( this ).closest( 'tr.item' );
				var qty = $( this ).val();
				var o_qty = $( this ).attr( 'data-qty' );
				var line_total = $( 'input.line_total', $row );
				var line_subtotal = $( 'input.line_subtotal', $row );

				// Totals
				var unit_total =
					accounting.unformat(
						line_total.attr( 'data-total' ),
						StoreSuite_Order.mon_decimal_point
					) / o_qty;
				line_total.val(
					parseFloat(
						accounting.formatNumber(
							unit_total * qty,
							StoreSuite_Order.rounding_precision,
							''
						)
					)
						.toString()
						.replace( '.', StoreSuite_Order.mon_decimal_point )
				);

				var unit_subtotal =
					accounting.unformat(
						line_subtotal.attr( 'data-subtotal' ),
						StoreSuite_Order.mon_decimal_point
					) / o_qty;
				line_subtotal.val(
					parseFloat(
						accounting.formatNumber(
							unit_subtotal * qty,
							StoreSuite_Order.rounding_precision,
							''
						)
					)
						.toString()
						.replace( '.', StoreSuite_Order.mon_decimal_point )
				);

				// Taxes
				$( 'input.line_tax', $row ).each( function () {
					var $line_total_tax = $( this );
					var tax_id = $line_total_tax.data( 'tax_id' );
					var unit_total_tax =
						accounting.unformat(
							$line_total_tax.attr( 'data-total_tax' ),
							StoreSuite_Order.mon_decimal_point
						) / o_qty;
					var $line_subtotal_tax = $(
						'input.line_subtotal_tax[data-tax_id="' + tax_id + '"]',
						$row
					);
					var unit_subtotal_tax =
						accounting.unformat(
							$line_subtotal_tax.attr( 'data-subtotal_tax' ),
							StoreSuite_Order.mon_decimal_point
						) / o_qty;

					if ( 0 < unit_total_tax ) {
						$line_total_tax.val(
							parseFloat(
								accounting.formatNumber(
									unit_total_tax * qty,
									StoreSuite_Order.rounding_precision,
									''
								)
							)
								.toString()
								.replace(
									'.',
									StoreSuite_Order.mon_decimal_point
								)
						);
					}

					if ( 0 < unit_subtotal_tax ) {
						$line_subtotal_tax.val(
							parseFloat(
								accounting.formatNumber(
									unit_subtotal_tax * qty,
									StoreSuite_Order.rounding_precision,
									''
								)
							)
								.toString()
								.replace(
									'.',
									StoreSuite_Order.mon_decimal_point
								)
						);
					}
				} );

				$( this ).trigger( 'quantity_changed' );
			} );
		},
		addCoupon: function () {
			$( document ).on(
				'click',
				'.storesuite-apply-coupon',
				function ( e ) {
					e.preventDefault();

					var value = $( '#coupon_code' ).val();

					var prod_search_for_order_box = $(
						'.product-serach-for-order-box'
					);
					window.StoreSuite.storeSuiteLoader.block(
						prod_search_for_order_box
					);

					var user_id = $( '#customer_user' ).val();
					var user_email = $( '#_billing_email' ).val();

					var data = $.extend(
						{},
						NewOrderProducts.getTaxableAddress(),
						{
							action: 'woocommerce_add_coupon_discount',
							dataType: 'json',
							order_id: StoreSuite_Order.post_id,
							security: StoreSuite_Order.order_item_nonce,
							coupon: value,
							user_id: user_id,
							user_email: user_email,
						}
					);

					data = NewOrderProducts.filterData( 'add_coupon', data );

					$.ajax( {
						url: StoreSuite_Order.ajax_url,
						data: data,
						type: 'POST',
						success: function ( response ) {
							if ( response.success ) {
								$( '#woocommerce-order-items' )
									.find( '.inside' )
									.empty();
								$( '#woocommerce-order-items' )
									.find( '.inside' )
									.append( response.data.html );

								// Update notes.
								if ( response.data.notes_html ) {
									$( 'ul.order_notes' ).empty();
									$( 'ul.order_notes' ).append(
										$( response.data.notes_html ).find(
											'li'
										)
									);
								}

								// wc_meta_boxes_order_items.reloaded_items();
								window.StoreSuite.storeSuiteLoader.unblock(
									prod_search_for_order_box
								);
							} else {
								window.alert( response.data.error );
							}
							window.StoreSuite.storeSuiteLoader.unblock(
								prod_search_for_order_box
							);
						},
						complete: function () {},
					} );
				}
			);
		},

		removeCoupon: function () {
			$( document ).on( 'click', '.remove-coupon', function ( e ) {
				e.preventDefault();
				var $this = $( this );
				var prod_search_for_order_box = $(
					'.product-serach-for-order-box'
				);
				window.StoreSuite.storeSuiteLoader.block(
					prod_search_for_order_box
				);

				var data = $.extend( {}, NewOrderProducts.getTaxableAddress(), {
					action: 'woocommerce_remove_order_coupon',
					dataType: 'json',
					order_id: StoreSuite_Order.post_id,
					security: StoreSuite_Order.order_item_nonce,
					coupon: $this.data( 'code' ),
				} );

				data = NewOrderProducts.filterData( 'remove_coupon', data );

				$.post( StoreSuite_Order.ajax_url, data, function ( response ) {
					if ( response.success ) {
						$( '#woocommerce-order-items' )
							.find( '.inside' )
							.empty();
						$( '#woocommerce-order-items' )
							.find( '.inside' )
							.append( response.data.html );

						// Update notes.
						if ( response.data.notes_html ) {
							$( 'ul.order_notes' ).empty();
							$( 'ul.order_notes' ).append(
								$( response.data.notes_html ).find( 'li' )
							);
						}
						window.StoreSuite.storeSuiteLoader.unblock(
							prod_search_for_order_box
						);
					} else {
						window.alert( response.data.error );
					}
					window.StoreSuite.storeSuiteLoader.unblock(
						prod_search_for_order_box
					);
				} );
			} );
		},

		addFee: function () {
			$( document ).on( 'click', '.storesuite-add-fee', function ( e ) {
				e.preventDefault();

				var value = $( '#add_fee' ).val();

				var prod_search_for_order_box = $(
					'.product-serach-for-order-box'
				);
				window.StoreSuite.storeSuiteLoader.block(
					prod_search_for_order_box
				);

				var data = $.extend( {}, NewOrderProducts.getTaxableAddress(), {
					action: 'woocommerce_add_order_fee',
					dataType: 'json',
					order_id: StoreSuite_Order.post_id,
					security: StoreSuite_Order.order_item_nonce,
					amount: value,
				} );

				data = NewOrderProducts.filterData( 'add_fee', data );

				$.post( StoreSuite_Order.ajax_url, data, function ( response ) {
					if ( response.success ) {
						$( '#woocommerce-order-items' )
							.find( '.inside' )
							.empty();
						$( '#woocommerce-order-items' )
							.find( '.inside' )
							.append( response.data.html );
						window.StoreSuite.storeSuiteLoader.unblock(
							prod_search_for_order_box
						);
					} else {
						window.alert( response.data.error );
					}
					window.StoreSuite.storeSuiteLoader.unblock(
						prod_search_for_order_box
					);
				} );
			} );
		},

		addShippingToOrder: function () {
			$( document ).on(
				'click',
				'.storesuite-add-shipping',
				function ( e ) {
					e.preventDefault();
					var shippingData = {
						action: 'storesuite_add_shipping_to_order',
						order_id: StoreSuite_Order.post_id,
						shipping_method_title: $(
							'.storesuite-form-group [name="storesuite_shipping_method_title"]'
						).val(),
						shipping_cost: $(
							'.storesuite-form-group [name="storesuite_shipping_cost"]'
						).val(),
						shipping_method: $(
							'.storesuite-form-group [name="storesuite_shipping_method"]'
						).val(),
						security: StoreSuite_Order.order_item_nonce,
					};

					var prod_search_for_order_box = $(
						'.product-serach-for-order-box'
					);
					window.StoreSuite.storeSuiteLoader.block(
						prod_search_for_order_box
					);

					$.ajax( {
						url: StoreSuite_Order.ajax_url,
						type: 'POST',
						data: shippingData,
						success: function ( response ) {
							if ( response.success ) {
								$( '#woocommerce-order-items' )
									.find( '.inside' )
									.empty();
								$( '#woocommerce-order-items' )
									.find( '.inside' )
									.append( response.data.html );
								window.StoreSuite.storeSuiteLoader.unblock(
									prod_search_for_order_box
								);
							} else {
								window.StoreSuite.storeSuiteLoader.unblock(
									prod_search_for_order_box
								);
								window.alert( response.data.error );
							}
						},
						complete: function () {},
					} );
				}
			);
		},

		recalculateOrder: function () {
			var prod_search_for_order_box = $(
				'.product-serach-for-order-box'
			);
			window.StoreSuite.storeSuiteLoader.block(
				prod_search_for_order_box
			);

			var data = $.extend( {}, NewOrderProducts.getTaxableAddress(), {
				action: 'woocommerce_calc_line_taxes',
				order_id: StoreSuite_Order.post_id,
				items: $(
					'table.woocommerce_order_items :input[name], .wc-order-totals-items :input[name]'
				).serialize(),
				security: StoreSuite_Order.calc_totals_nonce,
			} );

			data = NewOrderProducts.filterData( 'recalculate', data );

			$( document.body ).trigger(
				'order-totals-recalculate-before',
				data
			);

			$.ajax( {
				url: StoreSuite_Order.ajax_url,
				data: data,
				type: 'POST',
				success: function ( response ) {
					$( '#woocommerce-order-items' ).find( '.inside' ).empty();
					$( '#woocommerce-order-items' )
						.find( '.inside' )
						.append( response );
					window.StoreSuite.storeSuiteLoader.unblock(
						prod_search_for_order_box
					);

					$( document.body ).trigger(
						'order-totals-recalculate-success',
						response
					);
				},
				complete: function ( response ) {
					$( document.body ).trigger(
						'order-totals-recalculate-complete',
						response
					);
				},
			} );

			return false;
		},

		saveLineItems: function () {
			$( document ).on(
				'click',
				'.wc-order-add-item .save-action',
				function ( e ) {
					e.preventDefault();

					var data = {
						order_id: StoreSuite_Order.post_id,
						items: $(
							'table.woocommerce_order_items :input[name], .wc-order-totals-items :input[name]'
						).serialize(),
						action: 'woocommerce_save_order_items',
						security: StoreSuite_Order.order_item_nonce,
					};

					data = NewOrderProducts.filterData(
						'save_line_items',
						data
					);

					var prod_search_for_order_box = $(
						'.product-serach-for-order-box'
					);
					window.StoreSuite.storeSuiteLoader.block(
						prod_search_for_order_box
					);

					$.ajax( {
						url: StoreSuite_Order.ajax_url,
						data: data,
						type: 'POST',
						success: function ( response ) {
							if ( response.success ) {
								$( '#woocommerce-order-items' )
									.find( '.inside' )
									.empty();
								$( '#woocommerce-order-items' )
									.find( '.inside' )
									.append( response.data.html );

								// Update notes.
								if ( response.data.notes_html ) {
									$( 'ul.order_notes' ).empty();
									$( 'ul.order_notes' ).append(
										$( response.data.notes_html ).find(
											'li'
										)
									);
								}

								// wc_meta_boxes_order_items.reloaded_items();
								window.StoreSuite.storeSuiteLoader.unblock(
									prod_search_for_order_box
								);
							} else {
								window.StoreSuite.storeSuiteLoader.unblock(
									prod_search_for_order_box
								);
								window.alert( response.data.error );
							}
						},
						complete: function () {},
					} );

					$( this ).trigger( 'items_saved' );
				}
			);

			return false;
		},

		createOrder: function () {
			$( document ).on( 'click', '.create-order-btn', function ( e ) {
				e.preventDefault();
				var data = {
					action: 'storesuite_create_order',
					order_id: StoreSuite_Order.post_id,
					customer_id: $( '#customer_user' ).val(),
					context: $( '#context' ).val(),
					order_status: $(
						'.storesuite-form-group [name="order_status"]'
					).val(),
					order_date: $(
						'.storesuite-form-group [name="order_date"]'
					).val(),
					order_date_hour: $(
						'.storesuite-form-group [name="order_date_hour"]'
					).val(),
					order_date_minute: $(
						'.storesuite-form-group [name="order_date_minute"]'
					).val(),
					order_date_second: $(
						'.storesuite-form-group [name="order_date_second"]'
					).val(),
					order_action: $(
						'.storesuite-form-group [name="order_action"]'
					).val(),

					// Billing address
					_billing_first_name: $( '#_billing_first_name' ).val(),
					_billing_last_name: $( '#_billing_last_name' ).val(),
					_billing_company: $( '#_billing_company' ).val(),
					_billing_address_1: $( '#_billing_address_1' ).val(),
					_billing_address_2: $( '#_billing_address_2' ).val(),
					_billing_city: $( '#_billing_city' ).val(),
					_billing_postcode: $( '#_billing_postcode' ).val(),
					_billing_country: $( '#_billing_country' ).val(),
					_billing_state: $( '#_billing_state' ).val(),
					_billing_email: $( '#_billing_email' ).val(),
					_billing_phone: $( '#_billing_phone' ).val(),

					// Shipping address
					_shipping_first_name: $( '#_shipping_first_name' ).val(),
					_shipping_last_name: $( '#_shipping_last_name' ).val(),
					_shipping_company: $( '#_shipping_company' ).val(),
					_shipping_address_1: $( '#_shipping_address_1' ).val(),
					_shipping_address_2: $( '#_shipping_address_2' ).val(),
					_shipping_city: $( '#_shipping_city' ).val(),
					_shipping_postcode: $( '#_shipping_postcode' ).val(),
					_shipping_country: $( '#_shipping_country' ).val(),
					_shipping_state: $( '#_shipping_state' ).val(),
					_shipping_phone: $( '#_shipping_phone' ).val(),
					customer_note: $( '#customer_note' ).val(),

					// Payment
					_payment_method: $( '#_payment_method' ).val(),
					_transaction_id: $( '#_transaction_id' ).val(),
					security: StoreSuite_Order.order_item_nonce,
				};

				var container = $( '.storesuite-dashboard-order-details' );

				// Show premium loader
				window.StoreSuite.storeSuiteLoader.block( container );

				$.ajax( {
					url: StoreSuite_Order.ajax_url,
					type: 'POST',
					data: data,
					success: function ( response ) {
						if ( response.success ) {
							$( '#woocommerce-order-items' )
								.find( '.inside' )
								.empty();
							$( '#woocommerce-order-items' )
								.find( '.inside' )
								.append( response.data.html );

							// Update notes.
							if ( response.data.notes_html ) {
								$( 'ul.order_notes' ).empty();
								$( 'ul.order_notes' ).append(
									$( response.data.notes_html ).find( 'li' )
								);
							}

							// Show success message
							if ( response.data.context === 'add' ) {
								Swal.fire( {
									icon: 'success',
									title: StoreSuite_Order.order_success_title,
									text: response.data.message,
									confirmButtonText:
										StoreSuite_Order.order_ok_button,
								} ).then( function ( result ) {
									if ( result.isConfirmed ) {
										window.location.href =
											response.data.redirect_url;
									}
								} );
							} else if ( response.data.context === 'edit' ) {
								$( '.product-serach-for-order-box' ).toggleClass( 'storesuite-hide', ! response.data.is_order_editable );

								Swal.fire( {
									icon: 'success',
									title: StoreSuite_Order.order_success_title,
									text: response.data.message,
									confirmButtonText:
										StoreSuite_Order.order_ok_button,
								} );
							}
						} else {
							window.alert( response.data.error );
						}
					},
					complete: function () {
						window.StoreSuite.storeSuiteLoader.unblock( container );
					},
				} );
			} );
		},

		filterData: function ( handle, data ) {
			const filteredData = $( '#woocommerce-order-items' ).triggerHandler(
				`woocommerce_order_meta_box_${ handle }_ajax_data`,
				[ data ]
			);

			if ( filteredData ) {
				return filteredData;
			}

			return data;
		},

		getTaxableAddress: function () {
			var country = '';
			var state = '';
			var postcode = '';
			var city = '';

			if ( 'shipping' === StoreSuite_Order.tax_based_on ) {
				country = $( '#_shipping_country' ).val();
				state = $( '#_shipping_state' ).val();
				postcode = $( '#_shipping_postcode' ).val();
				city = $( '#_shipping_city' ).val();
			}

			if ( 'billing' === StoreSuite_Order.tax_based_on || ! country ) {
				country = $( '#_billing_country' ).val();
				state = $( '#_billing_state' ).val();
				postcode = $( '#_billing_postcode' ).val();
				city = $( '#_billing_city' ).val();
			}

			return {
				country: country,
				state: state,
				postcode: postcode,
				city: city,
			};
		},

		editOrderItem: function () {
			$( document ).on( 'click', 'a.edit-order-item', function ( e ) {
				e.preventDefault();
				$( this ).closest( 'tr' ).find( '.view' ).hide();
				$( this ).closest( 'tr' ).find( '.edit' ).show();
				$( this ).hide();
				$( '.wc-order-data-row.wc-order-bulk-actions' ).hide();
				$( '.wc-order-data-row.wc-order-add-item' ).show();
				$( 'button.cancel-action' ).attr( 'data-reload', true );
				return false;
			} );
		},

		deleteOrderItem: function () {
			$( document ).on( 'click', 'a.delete-order-item', function ( e ) {
				var prod_search_for_order_box = $(
					'.storesuite-dashboard-order-details'
				);
				var notice = StoreSuite_Order.remove_item_notice;

				if (
					$( this ).parents( 'tbody#order_fee_line_items' ).length
				) {
					notice = StoreSuite_Order.remove_fee_notice;
				}

				if (
					$( this ).parents( 'tbody#order_shipping_line_items' )
						.length
				) {
					notice = StoreSuite_Order.remove_shipping_notice;
				}

				var answer = window.confirm( notice );

				if ( answer ) {
					var $item = $( this ).closest(
						'tr.item, tr.fee, tr.shipping'
					);
					var order_item_id = $item.attr( 'data-order_item_id' );

					window.StoreSuite.storeSuiteLoader.block(
						prod_search_for_order_box
					);

					var data = $.extend(
						{},
						NewOrderProducts.getTaxableAddress(),
						{
							order_id: StoreSuite_Order.post_id,
							order_item_ids: order_item_id,
							action: 'woocommerce_remove_order_item',
							security: StoreSuite_Order.order_item_nonce,
						}
					);

					// Check if items have changed, if so pass them through so we can save them before deleting.
					if (
						'true' ===
						$( 'button.cancel-action' ).attr( 'data-reload' )
					) {
						data.items = $(
							'table.woocommerce_order_items :input[name], .wc-order-totals-items :input[name]'
						).serialize();
					}

					data = NewOrderProducts.filterData( 'delete_item', data );

					$.ajax( {
						url: StoreSuite_Order.ajax_url,
						data: data,
						type: 'POST',
						success: function ( response ) {
							if ( response.success ) {
								$( '#woocommerce-order-items' )
									.find( '.inside' )
									.empty();
								$( '#woocommerce-order-items' )
									.find( '.inside' )
									.append( response.data.html );

								// Update notes.
								if ( response.data.notes_html ) {
									$( 'ul.order_notes' ).empty();
									$( 'ul.order_notes' ).append(
										$( response.data.notes_html ).find(
											'li'
										)
									);
								}

								window.StoreSuite.storeSuiteLoader.unblock(
									prod_search_for_order_box
								);
							} else {
								window.alert( response.data.error );
							}
							window.StoreSuite.storeSuiteLoader.unblock(
								prod_search_for_order_box
							);
						},
						complete: function () {},
					} );
				}
			} );
			return false;
		},
	};

	var ManageOrderAddress = {
		init: function () {
			this.bindEvents();
		},
		bindEvents: function () {
			if (
				! (
					typeof StoreSuite_Order === 'undefined' ||
					typeof StoreSuite_Order.countries === 'undefined'
				)
			) {
				/* State/Country select boxes */
				this.states = JSON.parse(
					StoreSuite_Order.countries.replace( /&quot;/g, '"' )
				);
			}

			$( '.js_field-country' )
				.selectWoo()
				.on( 'change', this.changeCountry );
			$( '.js_field-country' ).trigger( 'change', [ true ] );
			$( document.body ).on(
				'change',
				'select.js_field-state',
				this.changeState
			);
		},
		changeCountry: function ( e, stickValue ) {
			// Check for stickValue before using it
			if ( typeof stickValue === 'undefined' ) {
				stickValue = false;
			}

			// Prevent if we don't have the metabox data
			if ( ManageOrderAddress.states === null ) {
				return;
			}

			var $this = $( this ),
				country = $this.val(),
				$state = $this
					.parents( 'div.customer-address-box' )
					.find( ':input.js_field-state' ),
				$parent = $state.parent(),
				stateValue = $state.val(),
				input_name = $state.attr( 'name' ),
				input_id = $state.attr( 'id' ),
				value = $this.data( 'woocommerce.stickState-' + country )
					? $this.data( 'woocommerce.stickState-' + country )
					: stateValue,
				placeholder = $state.attr( 'placeholder' ),
				$newstate;

			if ( stickValue ) {
				$this.data( 'woocommerce.stickState-' + country, value );
			}

			// Remove the previous DOM element
			$parent.show().find( '.select2-container' ).remove();

			if ( ! $.isEmptyObject( ManageOrderAddress.states[ country ] ) ) {
				var state = ManageOrderAddress.states[ country ],
					$defaultOption = $( '<option value=""></option>' ).text(
						StoreSuite_Order.i18n_select_state_text
					);

				$newstate = $( '<select></select>' )
					.prop( 'id', input_id )
					.prop( 'name', input_name )
					.prop( 'placeholder', placeholder )
					.addClass( 'js_field-state select short' )
					.append( $defaultOption );

				$.each( state, function ( index ) {
					var $option = $( '<option></option>' )
						.prop( 'value', index )
						.text( state[ index ] );
					if ( index === stateValue ) {
						$option.prop( 'selected' );
					}
					$newstate.append( $option );
				} );

				$newstate.val( value );

				$state.replaceWith( $newstate );

				$newstate.show().selectWoo().hide().trigger( 'change' );
			} else {
				$newstate = $( '<input type="text" />' )
					.prop( 'id', input_id )
					.prop( 'name', input_name )
					.prop( 'placeholder', placeholder )
					.addClass( 'js_field-state storesuite-form-control' )
					.val( stateValue );
				$state.replaceWith( $newstate );
			}

			// Trigger custom event
			$( document.body ).trigger( 'country-change.woocommerce', [
				country,
				$( this ).closest( 'div' ),
			] );
		},

		changeState: function () {
			// Here we will find if state value on a select has changed and stick it to the country data
			var $this = $( this ),
				state = $this.val(),
				$country = $this
					.parents( 'div.customer-address-box' )
					.find( ':input.js_field-country' ),
				country = $country.val();

			$country.data( 'woocommerce.stickState-' + country, state );
		},
	};

	var itemMeta = {
		init: function () {
			this.bindEvents();
		},
		bindEvents: function () {
			itemMeta.add();
			itemMeta.remove();
		},
		add: function () {
			$( document ).on(
				'click',
				'button.add_order_item_meta',
				function ( e ) {
					e.preventDefault();
					var $button = $( this );
					var $item = $button.closest( 'tr.item, tr.shipping' );
					var $items = $item.find( 'tbody.meta_items' );
					var index = $items.find( 'tr' ).length + 1;
					var $row =
						'<tr data-meta_id="0">' +
						'<td>' +
						'<input type="text" maxlength="255" placeholder="' +
						StoreSuite_Order.placeholder_name +
						'" name="meta_key[' +
						$item.attr( 'data-order_item_id' ) +
						'][new-' +
						index +
						']" />' +
						'<textarea placeholder="' +
						StoreSuite_Order.placeholder_value +
						'" name="meta_value[' +
						$item.attr( 'data-order_item_id' ) +
						'][new-' +
						index +
						']"></textarea>' +
						'</td>' +
						'<td width="1%"><button class="remove_order_item_meta button">&times;</button></td>' +
						'</tr>';
					$items.append( $row );

					return false;
				}
			);
		},

		remove: function () {
			$( document ).on(
				'click',
				'button.remove_order_item_meta',
				function ( e ) {
					e.preventDefault();
					if ( window.confirm( StoreSuite_Order.remove_item_meta ) ) {
						var $row = $( this ).closest( 'tr' );
						$row.find( ':input' ).val( '' );
						$row.hide();
					}
					return false;
				}
			);
		},
	};

	StoreFrontOrderConfig.init();
	NewOrderNotes.init();
	NewOrderProducts.init();
	ManageOrderAddress.init();
	itemMeta.init();
	HandleRefunds.init();
} )( jQuery );
