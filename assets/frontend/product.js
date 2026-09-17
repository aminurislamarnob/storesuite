( function ( $ ) {
	var StoreFrontProduct = {
		init: function () {
			this.bindEvents();
			this.errorTips();
			this.initSalePriceSchedule();
			this.initSelect2();
			this.initWcProductSearch();
			this.toggleStockFields();
			this.toggleProductTypeFields();
			this.toggleVirtualFields();
			this.toggleDownloadableFields();
			this.initDownloadableFilesSortable();
			this.salePriceDatesPicker();
			this.handleProductSubmit();
			this.handleStickyActions();
			this.handleProductBulkEditSubmit();
			this.handleProductDelete();
			this.initProductBulkEditModal();
			this.initProductQuickEditModal();
		},

		// Enhance .wc-product-search selects (Upsells / Cross-sells) with
		// SelectWoo + AJAX product search. Previously these worked only
		// because order.js was enqueued on the product page; the per-endpoint
		// asset refactor removed that, so the product page now ships its own
		// init using StoreSuite_Product nonces.
		initWcProductSearch: function () {
			if ( typeof $.fn.selectWoo !== 'function' ) {
				return;
			}
			if ( typeof StoreSuite_Product === 'undefined' ) {
				return;
			}

			$( ':input.wc-product-search' )
				.filter( ':not(.enhanced)' )
				.each( function () {
					var $select = $( this );
					$select
						.selectWoo( {
							allowClear: !! $select.data( 'allow_clear' ),
							placeholder: $select.data( 'placeholder' ),
							minimumInputLength:
								$select.data( 'minimum_input_length' ) || 3,
							escapeMarkup: function ( m ) {
								return m;
							},
							ajax: {
								url: StoreSuite_Product.ajax_url,
								dataType: 'json',
								delay: 250,
								data: function ( params ) {
									return {
										term: params.term,
										action:
											$select.data( 'action' ) ||
											'woocommerce_json_search_products_and_variations',
										security:
											StoreSuite_Product.search_products_nonce,
										exclude: $select.data( 'exclude' ),
										exclude_type:
											$select.data( 'exclude_type' ),
										include: $select.data( 'include' ),
										limit: $select.data( 'limit' ),
										display_stock:
											$select.data( 'display_stock' ),
									};
								},
								processResults: function ( data ) {
									var terms = [];
									if ( data ) {
										$.each( data, function ( id, text ) {
											terms.push( { id: id, text: text } );
										} );
									}
									return { results: terms };
								},
								cache: true,
							},
						} )
						.addClass( 'enhanced' );
				} );
		},
		bindEvents: function () {
			var self = this;
			$( document ).on( 'change', '#_manage_stock', function () {
				self.toggleStockFields();
			} );
			$( document ).on( 'change', 'select#post_type', function () {
				self.toggleProductTypeFields();
			} );
			$( document ).on( 'change', '#_downloadable', function () {
				self.togglePosVisibility();
				self.toggleDownloadableFields();
			} );
			$( document ).on( 'change', '#_virtual', function () {
				self.toggleVirtualFields();
			} );
			$( document ).on(
				'click',
				'.storesuite-add-downloadable-file',
				this.addDownloadableFileRow
			);
			$( document ).on(
				'click',
				'.storesuite-delete-file',
				this.removeDownloadableFileRow
			);
			$( document ).on(
				'click',
				'.storesuite-upload-file-button',
				this.openDownloadableFileMedia
			);
			$( document.body ).on(
				'keyup',
				'input[type=text][name*=_global_unique_id]',
				this.validateGlobalUniqueIdOnKeyUp
			);
			$( document.body ).on(
				'change',
				'input[type=text][name*=_global_unique_id]',
				this.validateGlobalUniqueIdOnChange
			);
			$( document ).on(
				'click',
				'.sale_schedule',
				this.openSaleSchedule
			);
			$( document ).on(
				'click',
				'.cancel_sale_schedule',
				this.cancelSaleSchedule
			);
		},
		/**
		 * Common validation function for required fields
		 * Makes field border red and shows error message below invalid field
		 *
		 * @param {jQuery} $form - The form element
		 * @param {Array} fields - Array of objects with selector and message properties
		 * @return {boolean} - Returns true if all fields are valid, false otherwise
		 */
		validateRequiredFields: function ( $form, fields ) {
			var isValid = true;
			var self = this;

			// Clear all previous errors
			$form.find( '.storesuite-field-error' ).remove();
			$form
				.find( '.storesuite-form-control' )
				.removeClass( 'storesuite-field-invalid' );

			// Validate each field
			$.each( fields, function ( index, field ) {
				var $field = $form.find( field.selector );
				var value = $field.val();

				// Check if field is empty or invalid
				var isEmpty = false;
				if ( $field.is( 'select' ) ) {
					isEmpty = ! value || value === '';
				} else if ( field.type === 'number' ) {
					isEmpty =
						! value ||
						value.trim() === '' ||
						parseFloat( value ) < 0;
				} else {
					isEmpty = ! value || value.trim() === '';
				}

				if ( isEmpty ) {
					isValid = false;
					self.markFieldAsInvalid( $field, field.message );
				}
			} );

			return isValid;
		},

		/**
		 * Mark a field as invalid by adding red border and error message
		 *
		 * @param {jQuery} $field - The field element
		 * @param {string} message - Error message to display
		 */
		markFieldAsInvalid: function ( $field, message ) {
			// Add invalid class to field
			$field.addClass( 'storesuite-field-invalid' );

			// Create error message element
			var $errorMsg = $(
				'<span class="storesuite-field-error">' + message + '</span>'
			);

			// Insert error message after the field
			$field.after( $errorMsg );

			// Remove error on field change
			$field.one( 'input change', function () {
				$( this ).removeClass( 'storesuite-field-invalid' );
				$( this ).siblings( '.storesuite-field-error' ).remove();
			} );
		},
		/**
		 * Handle Product Submit (Add/Edit)
		 */
		handleProductSubmit: function () {
			var self = this;

			$( document ).on(
				'submit',
				'#storesuite-add-product',
				function ( e ) {
					e.preventDefault();

					var $form = $( this );

					// Define required fields for validation
					var requiredFields = [
						{
							selector: '#product_title',
							message:
								storeSuiteFormHandler.i18n
									.product_title_required,
						},
						{
							selector: '#post_type',
							message:
								storeSuiteFormHandler.i18n
									.product_type_required,
						},
						{
							selector: '#post_status',
							message:
								storeSuiteFormHandler.i18n
									.product_status_required,
						},
					];

					// Validate required fields
					if (
						! self.validateRequiredFields( $form, requiredFields )
					) {
						return;
					}

					// Force TinyMCE editor content to update the textarea
					if ( typeof tinyMCE !== 'undefined' ) {
						var editor = tinyMCE.get( 'product_description' );
						if ( editor ) {
							editor.save();
						}
					}

					var formData = new FormData( this );

					var $submitBtn = $form.find( 'button[type="submit"]' );
					$submitBtn.prop( 'disabled', true );

					// Show loader
					window.StoreSuite.storeSuiteLoader.block(
						$( '.my-storesuite-wrapper' )
					);

					$.ajax( {
						url: storeSuiteFormHandler.ajax_url,
						type: 'POST',
						data: formData,
						processData: false,
						contentType: false,
						success: function ( response ) {
							Swal.close();
							if ( response.success ) {
								Swal.fire( {
									icon: 'success',
									title: storeSuiteFormHandler.i18n
										.success_title,
									text: response.data.message,
									confirmButtonText:
										storeSuiteFormHandler.i18n.ok_button,
								} );

								// Changes saved — hide the unsaved-changes bar
								// after any form-reset change triggers settle.
								setTimeout( function () {
									self.hideStickyActions();
								}, 0 );

								// Also persist any unsaved variation row changes.
								if (
									window.StoreSuiteVariations &&
									typeof window.StoreSuiteVariations
										.saveVariations === 'function'
								) {
									window.StoreSuiteVariations.saveVariations( {
										silent: true,
									} );
								}

								// Reset form fields only if adding (not editing)
								if ( response.data.context === 'add' ) {
									$form[ 0 ].reset();

									// Clear TinyMCE editor
									if ( typeof tinyMCE !== 'undefined' ) {
										var editor = tinyMCE.get(
											'product_description'
										);
										if ( editor ) {
											editor.setContent( '' );
										}
									}

									// Clear select2 fields if present
									$form
										.find( '.storesuite-select2' )
										.val( null )
										.trigger( 'change' );

									// Native form reset leaves the image/gallery
									// previews (DOM markup + state classes) intact,
									// so clear them back to their empty state.
									self.resetProductImageFields();
								}

								// Reflect the server-sanitized slug when editing.
								if (
									response.data.context === 'edit' &&
									response.data.slug
								) {
									var $slugField = $( '#product_slug' );
									if ( $slugField.length ) {
										$slugField.val( response.data.slug );
									}
								}
							} else {
								self.showError(
									response.data.error || response.data
								);
							}
						},
						error: function ( xhr, status, error ) {
							Swal.close();
							self.showError( self.getXhrErrorMessage( xhr ) );
						},
						complete: function () {
							$submitBtn.prop( 'disabled', false );
							window.StoreSuite.storeSuiteLoader.unblock(
								$( '.my-storesuite-wrapper' )
							);
						},
					} );
				}
			);
		},

		/**
		 * Reset the featured image and gallery back to their empty state after a
		 * successful add. Mirrors the manual remove handlers in script.js: the
		 * hidden inputs, preview markup and the state classes/placeholder text
		 * that `form.reset()` does not touch.
		 */
		resetProductImageFields: function () {
			var uploadText =
				( typeof storeSuiteFrontScript !== 'undefined' &&
					storeSuiteFrontScript.upload_image_text ) ||
				'';

			// Featured image.
			$( '#product_thumbnail_id' ).val( '' );
			$( '#product_thumbnail_url' ).val( '' );
			$( '#product_thumb_img' ).html( '' );
			$( '#product-single-image' )
				.removeClass( 'image-drop-bg' )
				.find( '.image-drop-text span' )
				.text( uploadText );

			// Gallery.
			$( '#product_image_gallery' ).val( '' );
			$( '#product_image_gallery_url' ).val( '' );
			$( '#product_gallery_img' ).empty();
			$( '#product-gallery-images' ).removeClass(
				'sm-gallery-image-uploader'
			);
			$( '.product-gallery-images-wrapper' ).addClass(
				'gallery-has-no-image'
			);
		},

		/**
		 * Floating "Unsaved Changes" bar — visible only when the form is dirty
		 * and the in-form Update button is scrolled out of view.
		 */
		handleStickyActions: function () {
			var self = this;
			var $bar = $( '#storesuite-product-sticky-actions' );
			var btn = document.getElementById( 'storesuite-product-actions' );
			if ( ! $bar.length ) {
				return;
			}

			self._dirty = false;

			// Single source of truth for the bar's visibility.
			self.refreshStickyBar = function () {
				var inView = false;
				if ( btn ) {
					var rect = btn.getBoundingClientRect();
					inView = rect.top < window.innerHeight && rect.bottom > 0;
				}
				var show = self._dirty && ! inView;
				$bar.toggleClass( 'is-visible', show ).attr(
					'aria-hidden',
					show ? 'false' : 'true'
				);
			};

			$( document ).on(
				'change input',
				'#storesuite-add-product :input',
				function ( e ) {
					if (
						$( e.target ).closest( '.storesuite-sticky-actions' )
							.length
					) {
						return;
					}
					// These pickers are UI controls, not saved fields, so
					// changing them must not mark the form dirty.
					if (
						$( e.target ).is(
							'#storesuite-bulk-action-select, #storesuite-add-attribute-select'
						)
					) {
						return;
					}
					self._dirty = true;
					self.refreshStickyBar();
				}
			);

			$( document ).on(
				'click',
				'.storesuite-sticky-discard',
				function ( e ) {
					e.preventDefault();
					window.location.reload();
				}
			);

			$( window ).on( 'scroll resize', self.refreshStickyBar );
			self.refreshStickyBar();
		},

		hideStickyActions: function () {
			this._dirty = false;
			if ( this.refreshStickyBar ) {
				this.refreshStickyBar();
			}
		},

		showError: function ( message ) {
			Swal.fire( {
				icon: 'error',
				title: storeSuiteFormHandler.i18n.error_title,
				text: message || storeSuiteFormHandler.i18n.unexpected_error,
				confirmButtonText: storeSuiteFormHandler.i18n.ok_button,
			} );
		},
		getXhrErrorMessage: function ( xhr ) {
			if ( ! xhr || ! xhr.responseJSON || ! xhr.responseJSON.data ) {
				return '';
			}

			if ( typeof xhr.responseJSON.data === 'string' ) {
				return xhr.responseJSON.data;
			}

			return (
				xhr.responseJSON.data.message ||
				xhr.responseJSON.data.error ||
				''
			);
		},
		initSelect2: function ( $scope, selector ) {
			var $root = $scope && $scope.length ? $scope : $( document );
			var targetSelector =
				typeof selector === 'string' && selector
					? selector
					: '.storesuite-select2';

			if ( typeof $.fn.selectWoo !== 'function' ) {
				return;
			}

			$root
				.find( targetSelector )
				.filter( ':not(.enhanced)' )
				.each( function () {
					var $selectField = $( this );
					var $modalDropdownParent = $selectField.closest(
						'.storesuite-product-bulk-modal-overlay'
					);
					var select2_args = {
						allowClear: $selectField.data( 'allow_clear' )
							? true
							: false,
						placeholder: $selectField.data( 'placeholder' ) || '',
						minimumResultsForSearch:
							$selectField.data( 'minimum_results_for_search' ) ||
							0,
						width: '100%',
					};

					if ( $modalDropdownParent.length ) {
						select2_args.dropdownParent = $modalDropdownParent;
					}

					$selectField
						.selectWoo( select2_args )
						.addClass( 'enhanced' );
				} );
		},

		// Tear down selectWoo on inline quick edit taxonomy fields (before hide / replace).
		destroyInlineQuickEditSelectWoo: function ( $scope ) {
			if ( ! $scope || ! $scope.length ) {
				return;
			}
			if ( typeof $.fn.selectWoo !== 'function' ) {
				return;
			}
			$scope
				.find( '.storesuite-inline-quick-edit-select2.enhanced' )
				.each( function () {
					var $el = $( this );
					try {
						$el.selectWoo( 'destroy' );
					} catch ( err ) {
						// Ignore if already destroyed.
					}
					$el.removeClass( 'enhanced' );
				} );
		},

		toggleStockFields: function () {
			const product_type = $( 'select#post_type' ).val();
			const is_checked = $( '#_manage_stock' ).is( ':checked' );

			if ( is_checked && 'external' !== product_type ) {
				$( '.show_if_stock_management' ).slideDown( 'fast' );
			} else {
				$( '.show_if_stock_management' ).slideUp( 'fast' );
			}

			if ( 'simple' === product_type ) {
				is_checked
					? $( '._stock_status_field' ).slideUp( 'fast' )
					: $( '._stock_status_field' ).slideDown( 'fast' );
			}
		},
		toggleProductTypeFields: function () {
			const product_type = $( 'select#post_type' ).val();

			$(
				'.show_if_simple, .show_if_variable, .show_if_external, .show_if_grouped'
			).hide();
			$( '.show_if_' + product_type ).show();

			$(
				'.hide_if_simple, .hide_if_variable, .hide_if_external, .hide_if_grouped'
			).show();
			$( '.hide_if_' + product_type ).hide();

			this.togglePosVisibility();
		},

		initDownloadableFilesSortable: function () {
			var $tbody = $( '.downloadable_files tbody' );
			if ( ! $tbody.length || typeof $tbody.sortable !== 'function' ) {
				return;
			}
			$tbody.sortable( {
				items: 'tr',
				cursor: 'move',
				axis: 'y',
				handle: 'td.sort',
				scrollSensitivity: 40,
				forcePlaceholderSize: true,
				helper: 'clone',
				opacity: 0.65,
			} );
		},

		addDownloadableFileRow: function ( e ) {
			e.preventDefault();
			var row = $( this ).data( 'row' );
			if ( ! row ) {
				return;
			}
			$( this )
				.closest( '.storesuite-downloadable-files' )
				.find( 'tbody' )
				.append( row );
		},

		removeDownloadableFileRow: function ( e ) {
			e.preventDefault();
			$( this ).closest( 'tr' ).remove();
		},

		openDownloadableFileMedia: function ( e ) {
			e.preventDefault();
			var $button = $( this );
			var $input = $button
				.closest( 'tr' )
				.find( '.storesuite-downloadable-file-url-input' );

			if ( typeof wp === 'undefined' || ! wp.media ) {
				return;
			}

			var frame = wp.media( {
				title: $button.data( 'choose' ) || 'Choose a file',
				button: {
					text: $button.data( 'update' ) || 'Insert file URL',
				},
				multiple: false,
			} );

			frame.on( 'select', function () {
				var attachment = frame
					.state()
					.get( 'selection' )
					.first()
					.toJSON();
				$input.val( attachment.url );
			} );

			frame.open();
		},

		toggleVirtualFields: function () {
			const is_virtual = $( '#_virtual' ).is( ':checked' );
			$( '.hide_if_virtual' ).toggle( ! is_virtual );
			$( '.show_if_virtual' ).toggle( is_virtual );
		},

		toggleDownloadableFields: function () {
			const is_downloadable = $( '#_downloadable' ).is( ':checked' );
			$( '.show_if_downloadable' ).toggle( is_downloadable );
			$( '.hide_if_downloadable' ).toggle( ! is_downloadable );
		},

		togglePosVisibility: function () {
			const $supported = $( '#pos_visibility_supported' );
			const $unsupported = $( '#pos_visibility_unsupported' );
			if ( ! $supported.length && ! $unsupported.length ) {
				return;
			}
			const product_type = $( 'select#post_type' ).val();
			const is_downloadable = $( '#_downloadable' ).is( ':checked' );
			const is_pos_supported =
				( 'simple' === product_type || 'variable' === product_type ) &&
				! is_downloadable;

			if ( is_pos_supported ) {
				$supported.show();
				$unsupported.hide();
			} else {
				$supported.hide();
				$unsupported.show();
			}
		},
		validateGlobalUniqueIdOnKeyUp: function () {
			var global_unique_id = $( this ).val();

			if ( /[^0-9\-]/.test( global_unique_id ) ) {
				$( document.body ).triggerHandler( 'wc_add_error_tip', [
					$( this ),
					'i18n_global_unique_id_error',
				] );
			} else {
				$( document.body ).triggerHandler( 'wc_remove_error_tip', [
					$( this ),
					'i18n_global_unique_id_error',
				] );
			}
		},
		validateGlobalUniqueIdOnChange: function () {
			var global_unique_id = $( this ).val();
			$( this ).val(
				global_unique_id
					.replace( /[^0-9\-]/g, '' )
					.replace( /^-+|-+$/g, '' )
			);

			$( document.body ).triggerHandler( 'wc_remove_error_tip', [
				$( this ),
				'i18n_global_unique_id_error',
			] );
		},
		errorTips: function () {
			$( document.body )
				.on( 'wc_add_error_tip', function ( e, element, error_type ) {
					var offset = element.position();

					if (
						element.parent().find( '.wc_error_tip' ).length === 0
					) {
						element.after(
							'<div class="wc_error_tip ' +
								error_type +
								'">' +
								StoreSuite_Product[ error_type ] +
								'</div>'
						);
						element
							.parent()
							.find( '.wc_error_tip' )
							.css(
								'left',
								offset.left +
									element.width() -
									element.width() / 2 -
									$( '.wc_error_tip' ).width() / 2
							)
							.css( 'top', offset.top + element.height() )
							.fadeIn( '100' );
					}
				} )

				.on(
					'wc_remove_error_tip',
					function ( e, element, error_type ) {
						element
							.parent()
							.find( '.wc_error_tip.' + error_type )
							.fadeOut( '100', function () {
								$( this ).remove();
							} );
					}
				);
		},
		salePriceDatesPicker: function () {
			var self = this;
			$( '.sale_price_dates_fields' ).each( function () {
				$( this )
					.find( 'input' )
					.datepicker( {
						defaultDate: '',
						dateFormat: 'yy-mm-dd',
						numberOfMonths: 1,
						showButtonPanel: true,
						onSelect: function () {
							self.datePickerSelect( $( this ) );
						},
					} );
				$( this )
					.find( 'input' )
					.each( function () {
						self.datePickerSelect( $( this ) );
					} );
			} );
		},
		datePickerSelect: function ( datepicker ) {
			var option = $( datepicker ).next().is( '.hasDatepicker' )
					? 'minDate'
					: 'maxDate',
				otherDateField =
					'minDate' === option
						? $( datepicker ).next()
						: $( datepicker ).prev(),
				date = $( datepicker ).datepicker( 'getDate' );

			$( otherDateField ).datepicker( 'option', option, date );
			$( datepicker ).trigger( 'change' );
		},
		initSalePriceSchedule: function () {
			$( '.sale_price_dates_fields' ).each( function () {
				var sale_schedule_set = false;

				$( this )
					.find( 'input' )
					.each( function () {
						if ( '' !== $( this ).val() ) {
							sale_schedule_set = true;
						}
					} );

				if ( sale_schedule_set ) {
					$( '.sale_schedule' ).hide();
					$( '.cancel_sale_schedule' ).show();
					$( '.sale_price_dates_fields' ).slideDown();
				} else {
					$( '.sale_schedule' ).show();
					$( '.cancel_sale_schedule' ).hide();
					$( '.sale_price_dates_fields' ).slideUp();
				}
			} );
		},
		openSaleSchedule: function () {
			$( this ).hide();
			$( '.cancel_sale_schedule' ).show();
			$( '.sale_price_dates_fields' ).slideDown();

			return false;
		},
		cancelSaleSchedule: function () {
			$( this ).hide();
			$( '.sale_schedule' ).show();
			$( '.sale_price_dates_fields' ).slideUp();
			$( '.sale_price_dates_fields' ).find( 'input' ).val( '' );

			return false;
		},

		/**
		 * Handle product delete (move to trash) via AJAX.
		 */
		handleProductDelete: function () {
			var self = this;

			$( document ).on(
				'click',
				'.storesuite-delete-product',
				function ( e ) {
					e.preventDefault();

					var productId = $( this ).data( 'product-id' );

					if (
						! productId ||
						typeof Swal === 'undefined' ||
						typeof storeSuiteFormHandler === 'undefined'
					) {
						return;
					}

					var i18n = storeSuiteFormHandler.i18n || {};
					var confirmTitle =
						i18n.product_delete_confirm_title || i18n.are_you_sure;
					var confirmText = i18n.product_delete_warning;
					var confirmButton = i18n.yes_delete;
					var cancelButton = i18n.cancel_button;
					var deletingText = i18n.deleting;
					var successTitle = i18n.success_title;
					var errorTitle = i18n.error_title;

					Swal.fire( {
						title: confirmTitle,
						text: confirmText,
						icon: 'warning',
						showCancelButton: true,
						confirmButtonText: confirmButton,
						cancelButtonText: cancelButton,
					} ).then( function ( result ) {
						if ( ! result.isConfirmed ) {
							return;
						}

						Swal.fire( {
							title: deletingText,
							text: storeSuiteFormHandler.i18n.please_wait,
							allowOutsideClick: false,
							didOpen: function () {
								Swal.showLoading();
							},
						} );

						var formData = new FormData();
						formData.append( 'id', productId );
						formData.append(
							'action',
							'storesuite_delete_product'
						);
						formData.append(
							'storesuite_delete_product_nonce',
							storeSuiteFormHandler.storesuite_woo_delete_nonce_
						);

						$.ajax( {
							url: storeSuiteFormHandler.ajax_url,
							type: 'POST',
							data: formData,
							processData: false,
							contentType: false,
							success: function ( response ) {
								Swal.close();

								if ( response.success ) {
									Swal.fire( {
										icon: 'success',
										title: successTitle,
										text: response.data.message || '',
									} );
									$( '#product-row-' + productId ).fadeOut(
										300,
										function () {
											$( this ).remove();
										}
									);
								} else {
									Swal.fire( {
										icon: 'error',
										title: errorTitle,
										text:
											response.data && response.data.error
												? response.data.error
												: storeSuiteFormHandler.i18n
														.unexpected_error,
									} );
								}
							},
							error: function () {
								Swal.close();
								Swal.fire( {
									icon: 'error',
									title: errorTitle,
									text: storeSuiteFormHandler.i18n
										.unexpected_error,
								} );
							},
						} );
					} );
				}
			);
		},

		/**
		 * Products list: bulk Edit opens modal (a11y via StoreSuite.storeSuiteModal).
		 */
		initProductBulkEditModal: function () {
			var self = this;
			var suiteModal =
				window.StoreSuite && window.StoreSuite.storeSuiteModal;
			var $bulkEditModal = $( '#storesuite-product-bulk-edit-modal' );

			if ( ! suiteModal || ! $bulkEditModal.length ) {
				return;
			}

			suiteModal.initOverlay( $bulkEditModal, {
				fade: true,
				closeSelector:
					'.storesuite-product-bulk-modal-cancel, .storesuite-product-bulk-modal-close',
			} );

			$( document ).on(
				'submit',
				'#storesuite-product-bulk-actions',
				function ( submitEvent ) {
					var selectedBulkAction = $(
						'#bulk-action-selector-products'
					).val();
					if (
						selectedBulkAction !== 'edit' &&
						selectedBulkAction !== 'trash' &&
						selectedBulkAction !== 'delete'
					) {
						return;
					}

					submitEvent.preventDefault();

					var selectedProductIds = $(
						'#storesuite-product-bulk-actions'
					)
						.find( 'input[name="bulk_product_ids[]"]:checked' )
						.map( function () {
							return $( this ).val();
						} )
						.get();

					if ( ! selectedProductIds.length ) {
						if ( typeof Swal === 'undefined' ) {
							return;
						}
						var bulkEditConfig = StoreSuite_Product.bulk_edit || {};
						Swal.fire( {
							icon: 'warning',
							title: bulkEditConfig.select_products_title,
							text: bulkEditConfig.select_products_message,
							confirmButtonText: bulkEditConfig.ok_button,
						} );
						return;
					}

					if ( selectedBulkAction === 'edit' ) {
						self.openProductBulkModal(
							$bulkEditModal,
							selectedProductIds
						);
						return;
					}

					var bulkEditConfig = StoreSuite_Product.bulk_edit || {};
					var isBulkDelete = selectedBulkAction === 'delete';
					var removalNonce = isBulkDelete
						? bulkEditConfig.delete_nonce
						: bulkEditConfig.trash_nonce;
					if ( ! removalNonce ) {
						self.showError();
						return;
					}

					var $bulkActionsForm = $( this );
					var runBulkRemoval = function () {
						var $bulkActionsSubmitButton = $bulkActionsForm.find(
							'button[type="submit"]'
						);
						var bulkRemovalFormData = new FormData();
						var productIndex;

						bulkRemovalFormData.append(
							'action',
							isBulkDelete
								? 'storesuite_bulk_delete_products'
								: 'storesuite_bulk_trash_products'
						);
						bulkRemovalFormData.append( 'security', removalNonce );
						for (
							productIndex = 0;
							productIndex < selectedProductIds.length;
							productIndex++
						) {
							bulkRemovalFormData.append(
								'product_ids[]',
								selectedProductIds[ productIndex ]
							);
						}

						$bulkActionsSubmitButton.prop( 'disabled', true );
						window.StoreSuite.storeSuiteLoader.block(
							$( '.my-storesuite-wrapper' )
						);

						$.ajax( {
							url: storeSuiteFormHandler.ajax_url,
							type: 'POST',
							data: bulkRemovalFormData,
							processData: false,
							contentType: false,
							success: function ( bulkRemovalResponse ) {
								Swal.close();
								if ( bulkRemovalResponse.success ) {
									Swal.fire( {
										icon: 'success',
										title: isBulkDelete
											? bulkEditConfig.delete_success_title ||
											  bulkEditConfig.success_title
											: bulkEditConfig.trash_success_title ||
											  bulkEditConfig.success_title,
										text:
											bulkRemovalResponse.data &&
											bulkRemovalResponse.data.message
												? bulkRemovalResponse.data.message
												: '',
										confirmButtonText: bulkEditConfig.ok_button,
									} ).then( function () {
										window.location.reload();
									} );
								} else {
									self.showError(
										bulkRemovalResponse.data &&
											( bulkRemovalResponse.data.message ||
												bulkRemovalResponse.data.error )
									);
								}
							},
							error: function ( xhr ) {
								Swal.close();
								self.showError( self.getXhrErrorMessage( xhr ) );
							},
							complete: function () {
								$bulkActionsSubmitButton.prop( 'disabled', false );
								window.StoreSuite.storeSuiteLoader.unblock(
									$( '.my-storesuite-wrapper' )
								);
							},
						} );
					};

					if ( isBulkDelete ) {
						if ( typeof Swal === 'undefined' ) {
							return;
						}
						Swal.fire( {
							icon: 'warning',
							title: bulkEditConfig.delete_confirm_title,
							text: bulkEditConfig.delete_confirm_message,
							showCancelButton: true,
							confirmButtonText: bulkEditConfig.delete_confirm_button,
							cancelButtonText: bulkEditConfig.cancel_button,
						} ).then( function ( confirmResult ) {
							if ( ! confirmResult.isConfirmed ) {
								return;
							}
							// Second confirmation: permanent deletion skips
							// the trash, so a mis-click cannot be undone.
							Swal.fire( {
								icon: 'error',
								title: bulkEditConfig.delete_recheck_title,
								text: (
									bulkEditConfig.delete_recheck_message || ''
								).replace( '%d', selectedProductIds.length ),
								showCancelButton: true,
								confirmButtonText:
									bulkEditConfig.delete_recheck_button,
								cancelButtonText: bulkEditConfig.cancel_button,
								focusCancel: true,
							} ).then( function ( recheckResult ) {
								if ( recheckResult.isConfirmed ) {
									runBulkRemoval();
								}
							} );
						} );
						return;
					}

					runBulkRemoval();
				}
			);
		},

		openProductBulkModal: function ( $bulkEditModal, selectedProductIds ) {
			var suiteModal =
				window.StoreSuite && window.StoreSuite.storeSuiteModal;
			if ( ! suiteModal ) {
				return;
			}

			var $bulkHiddenPostInputs = $( '#storesuite-bulk-edit-post-ids' );
			var $bulkEditForm = $bulkEditModal.find( 'form' ).first();
			var productIndex;

			// Start from a clean form every time: the modal is hidden, not
			// destroyed, on close, so a previous Add/Remove choice would
			// otherwise be applied silently to the next selection.
			if ( $bulkEditForm.length ) {
				$bulkEditForm[ 0 ].reset();
				$bulkEditForm
					.find( 'select[multiple]' )
					.val( null )
					.trigger( 'change' );
				$bulkEditForm
					.find( '.storesuite-bulk-edit-submit' )
					.prop( 'disabled', false );
			}

			$bulkHiddenPostInputs.empty();

			for (
				productIndex = 0;
				productIndex < selectedProductIds.length;
				productIndex++
			) {
				$bulkHiddenPostInputs.append(
					$( '<input>', {
						type: 'hidden',
						name: 'post[]',
						value: selectedProductIds[ productIndex ],
					} )
				);
			}

			// Enhance the category/tag multi-selects on first open.
			this.initSelect2( $bulkEditModal );

			suiteModal.open( $bulkEditModal );
		},

		/**
		 * Bulk edit products (modal): AJAX submit aligned with handleProductSubmit.
		 */
		handleProductBulkEditSubmit: function () {
			var self = this;

			$( document ).on(
				'submit',
				'#storesuite-product-bulk-edit-form',
				function ( submitEvent ) {
					submitEvent.preventDefault();

					var $bulkEditForm = $( this );
					var $bulkEditModal = $(
						'#storesuite-product-bulk-edit-modal'
					);
					var suiteModal =
						window.StoreSuite && window.StoreSuite.storeSuiteModal;
					var bulkEditConfig =
						typeof StoreSuite_Product !== 'undefined'
							? StoreSuite_Product.bulk_edit || {}
							: {};

					if ( ! $bulkEditModal.length || ! bulkEditConfig.nonce ) {
						return;
					}

					var bulkEditFormData = new FormData( this );
					bulkEditFormData.append(
						'action',
						'storesuite_bulk_edit_products'
					);
					bulkEditFormData.append( 'security', bulkEditConfig.nonce );

					var $bulkSubmitButton = $bulkEditForm.find(
						'.storesuite-bulk-edit-submit'
					);
					$bulkSubmitButton.prop( 'disabled', true );

					window.StoreSuite.storeSuiteLoader.block(
						$( '.my-storesuite-wrapper' )
					);

					$.ajax( {
						url: storeSuiteFormHandler.ajax_url,
						type: 'POST',
						data: bulkEditFormData,
						processData: false,
						contentType: false,
						success: function ( bulkSaveResponse ) {
							Swal.close();
							if ( bulkSaveResponse.success ) {
								if ( suiteModal && $bulkEditModal.length ) {
									suiteModal.close( $bulkEditModal );
								}
								var bulkUndo =
									bulkSaveResponse.data &&
									bulkSaveResponse.data.undo &&
									window.StoreSuite &&
									window.StoreSuite.editHistory
										? bulkSaveResponse.data.undo
										: null;
								Swal.fire( {
									icon: 'success',
									title: bulkEditConfig.success_title,
									text:
										bulkSaveResponse.data &&
										bulkSaveResponse.data.message
											? bulkSaveResponse.data.message
											: '',
									confirmButtonText:
										storeSuiteFormHandler.i18n.ok_button,
									showDenyButton: !! bulkUndo,
									denyButtonText: bulkUndo
										? window.StoreSuite.editHistory.i18n().undo
										: '',
								} ).then( function ( bulkResult ) {
									if ( bulkUndo && bulkResult.isDenied ) {
										window.StoreSuite.editHistory.undo( bulkUndo, {
											confirm: false,
											reload: true,
										} );
										return;
									}
									window.location.reload();
								} );
							} else {
								var bulkSaveErrorMessage;
								if ( bulkSaveResponse.data ) {
									bulkSaveErrorMessage =
										bulkSaveResponse.data.message ||
										bulkSaveResponse.data.error;
									if (
										! bulkSaveErrorMessage &&
										typeof bulkSaveResponse.data ===
											'string'
									) {
										bulkSaveErrorMessage =
											bulkSaveResponse.data;
									}
								}
								self.showError( bulkSaveErrorMessage );
							}
						},
						error: function ( xhr ) {
							Swal.close();
							self.showError( self.getXhrErrorMessage( xhr ) );
						},
						complete: function () {
							$bulkSubmitButton.prop( 'disabled', false );
							window.StoreSuite.storeSuiteLoader.unblock(
								$( '.my-storesuite-wrapper' )
							);
						},
					} );
				}
			);
		},

		/**
		 * Products list: quick edit in StoreSuite modal (form HTML from AJAX).
		 */
		initProductQuickEditModal: function () {
			var self = this;
			var suiteModal =
				window.StoreSuite && window.StoreSuite.storeSuiteModal;
			var $quickEditModal = $( '#storesuite-product-quick-edit-modal' );
			var $quickEditModalBody = $quickEditModal.find(
				'#storesuite-quick-edit-modal-body'
			);
			var quickEditConfig =
				typeof StoreSuite_Product !== 'undefined' &&
				StoreSuite_Product.quick_edit
					? StoreSuite_Product.quick_edit
					: null;

			if (
				! suiteModal ||
				! $quickEditModal.length ||
				! quickEditConfig
			) {
				return;
			}

			var activeQuickEditProductId = null;

			suiteModal.initOverlay( $quickEditModal, {
				fade: true,
				closeSelector:
					'.storesuite-product-quick-edit-modal-cancel, .storesuite-product-quick-edit-modal-close',
			} );

			function getResponseErrorMessage( responseData ) {
				if ( ! responseData ) {
					return '';
				}
				return responseData.message || responseData.error || '';
			}

			function resetQuickEditModalContent() {
				self.destroyInlineQuickEditSelectWoo( $quickEditModalBody );
				$quickEditModalBody.empty();
				activeQuickEditProductId = null;
			}

			$quickEditModal.on(
				'transitionend.storesuiteQuickEditModal',
				function ( transitionEvent ) {
					if ( transitionEvent.target !== $quickEditModal[ 0 ] ) {
						return;
					}
					if ( $quickEditModal.prop( 'hidden' ) ) {
						resetQuickEditModalContent();
					}
				}
			);

			$( document ).on(
				'click',
				'.storesuite-item-inline-edit',
				function ( clickEvent ) {
					clickEvent.preventDefault();
					var productId = $( this ).data( 'product-id' );
					var $wrapper = $( '.my-storesuite-wrapper' );
					if ( ! productId ) {
						return;
					}

					// Close the row menu the click came from before the modal opens.
					$( this ).closest( '.storesuite-dropdown-menu' ).stop( true, false ).slideUp( 150 );

					resetQuickEditModalContent();
					activeQuickEditProductId = String( productId );
					if (
						window.StoreSuite &&
						window.StoreSuite.storeSuiteLoader
					) {
						window.StoreSuite.storeSuiteLoader.block( $wrapper );
					}

					$.ajax( {
						url: storeSuiteFormHandler.ajax_url,
						type: 'POST',
						dataType: 'json',
						data: {
							action: 'storesuite_get_product_quick_edit_form',
							security: quickEditConfig.load_form_nonce,
							product_id: productId,
						},
						success: function ( loadFormResponse ) {
							if (
								! loadFormResponse.success ||
								! loadFormResponse.data ||
								! loadFormResponse.data.html
							) {
								self.showError(
									getResponseErrorMessage(
										loadFormResponse.data
									)
								);
								suiteModal.close( $quickEditModal );
								return;
							}
							$quickEditModalBody.html(
								loadFormResponse.data.html
							);
							self.bindStoresuiteInlineQuickEditToggles(
								$quickEditModalBody
							);
							self.initSelect2(
								$quickEditModalBody,
								'.storesuite-inline-quick-edit-select2'
							);
							suiteModal.open( $quickEditModal );
						},
						error: function ( xhr ) {
							self.showError( self.getXhrErrorMessage( xhr ) );
							suiteModal.close( $quickEditModal );
						},
						complete: function () {
							if (
								window.StoreSuite &&
								window.StoreSuite.storeSuiteLoader
							) {
								window.StoreSuite.storeSuiteLoader.unblock(
									$wrapper
								);
							}
						},
					} );
				}
			);

			$quickEditModal.on(
				'click',
				'.storesuite-product-quick-edit-submit',
				function ( clickEvent ) {
					clickEvent.preventDefault();

					var $quickEditFieldset = $quickEditModalBody
						.find( 'fieldset' )
						.first();
					if (
						! activeQuickEditProductId ||
						! $quickEditFieldset.length
					) {
						return;
					}

					var $submitButton = $( this );
					var $submitButtonWrap = $submitButton.closest(
						'.storesuite-product-quick-edit-update-wrap'
					);

					var quickEditFieldPayload = {};
					$quickEditModal
						.find( '[data-field-name]' )
						.each( function () {
							var $dataField = $( this );
							var fieldName = $dataField.data( 'field-name' );
							if (
								! fieldName ||
								$dataField.closest( '.storesuite-hide' ).length
							) {
								return;
							}

							if ( $dataField.attr( 'type' ) === 'checkbox' ) {
								if ( $dataField.is( ':checked' ) ) {
									quickEditFieldPayload[ fieldName ] = true;
								}
								return;
							}

							if ( $dataField.prop( 'multiple' ) ) {
								quickEditFieldPayload[ fieldName ] =
									$dataField.val() === null
										? []
										: $dataField.val();
								return;
							}

							quickEditFieldPayload[ fieldName ] =
								$dataField.val() === null
									? ''
									: $dataField.val();
						} );

					var productIdForListRow = activeQuickEditProductId;

					$submitButtonWrap.addClass(
						'storesuite-quick-edit-loading'
					);
					$quickEditFieldset.prop( 'disabled', true );

					if (
						window.StoreSuite &&
						window.StoreSuite.storeSuiteLoader
					) {
						window.StoreSuite.storeSuiteLoader.block(
							$( '.my-storesuite-wrapper' )
						);
					}

					$.ajax( {
						url: storeSuiteFormHandler.ajax_url,
						method: 'POST',
						dataType: 'json',
						data: {
							action: 'storesuite_product_quick_edit',
							security: quickEditConfig.nonce,
							data: quickEditFieldPayload,
						},
					} )
						.done( function ( saveResponse ) {
							if (
								saveResponse.success &&
								saveResponse.data &&
								saveResponse.data.row
							) {
								self.destroyInlineQuickEditSelectWoo(
									$quickEditModalBody
								);
								$quickEditModalBody.empty();
								activeQuickEditProductId = null;
								suiteModal.close( $quickEditModal );
								$(
									'#product-row-' + productIdForListRow
								).replaceWith( saveResponse.data.row );
							} else {
								self.showError(
									getResponseErrorMessage( saveResponse.data )
								);
							}
						} )
						.fail( function ( xhr ) {
							self.showError( self.getXhrErrorMessage( xhr ) );
						} )
						.always( function () {
							$submitButtonWrap.removeClass(
								'storesuite-quick-edit-loading'
							);
							$quickEditFieldset.prop( 'disabled', false );
							if (
								window.StoreSuite &&
								window.StoreSuite.storeSuiteLoader
							) {
								window.StoreSuite.storeSuiteLoader.unblock(
									$( '.my-storesuite-wrapper' )
								);
							}
						} );
				}
			);
		},

		bindStoresuiteInlineQuickEditToggles: function ( $quickEditFieldRoot ) {
			if ( ! $quickEditFieldRoot || ! $quickEditFieldRoot.length ) {
				return;
			}

			$quickEditFieldRoot
				.off( 'change.storesuiteInlineQe' )
				.on(
					'change.storesuiteInlineQe',
					'input[data-field-toggler]',
					function () {
						var $toggler = $( this );
						var togglerFieldName = $toggler.data( 'field-name' );
						var isTogglerChecked = $toggler.is( ':checked' );

						if ( ! togglerFieldName ) {
							return;
						}

						$quickEditFieldRoot
							.find(
								'[data-field-toggle="' + togglerFieldName + '"]'
							)
							.each( function () {
								var $toggleTargetRow = $( this );
								var shouldShowRow =
									isTogglerChecked ===
									( $toggleTargetRow.attr(
										'data-field-show-on'
									) ===
										'true' );
								$toggleTargetRow.toggleClass(
									'storesuite-hide',
									! shouldShowRow
								);
							} );
					}
				);

			$quickEditFieldRoot
				.find( 'input[data-field-toggler]' )
				.trigger( 'change' );
		},

	};
	StoreFrontProduct.init();
} )( jQuery );
