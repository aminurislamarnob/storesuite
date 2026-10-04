( function ( $ ) {
	'use strict';

	var StoreFrontFormHandler = {
		init: function () {
			this.bindEvents();
			this.initDatePicker();
			this.initProductSearch();
			this.initSelect2();
		},

		// Enhance plain .storesuite-select2 fields (e.g. coupon Product
		// Categories / Exclude Categories) with selectWoo. Mirrors the same
		// helper in product.js so non-product pages that load form-handler
		// (coupons, categories, brands, tags, account) also get enhanced.
		initSelect2: function () {
			if ( typeof $.fn.selectWoo !== 'function' ) {
				return;
			}
			$( '.storesuite-select2' )
				.filter( ':not(.enhanced)' )
				.each( function () {
					var $field = $( this );
					$field
						.selectWoo( {
							allowClear: !! $field.data( 'allow_clear' ),
							placeholder: $field.data( 'placeholder' ) || '',
							minimumResultsForSearch:
								$field.data( 'minimum_results_for_search' ) || 0,
							width: '100%',
						} )
						.addClass( 'enhanced' );
				} );
		},

		bindEvents: function () {
			this.handleCategoryAdd();
			this.handleCategoryEdit();
			this.handleCategoryDelete();
			this.handleTagAdd();
			this.handleTagEdit();
			this.handleTagDelete();
			this.handleBrandAdd();
			this.handleBrandEdit();
			this.handleBrandDelete();
			this.handleAttributeAdd();
			this.handleAttributeEdit();
			this.handleAttributeDelete();
			this.handleAttributeTermAdd();
			this.handleAttributeTermEdit();
			this.handleAttributeTermDelete();
			this.handleCouponAdd();
			this.handleCouponEdit();
			this.handleCouponDelete();
			this.handleGenerateCouponCode();
			this.handleEditAccount();
			this.handleDuplicateItem();
			this.bindEditAccountPasswordLiveValidation();
			this.bindPasswordVisibilityToggle();
			this.initEditAccountPasswordToggle();
			this.preventPasswordAutofill();
		},

		/**
		 * Stop browsers from autofilling the account password fields on load.
		 *
		 * The fields render with the `readonly` attribute so browsers skip them
		 * during page-load autofill. We drop `readonly` on first focus/touch so
		 * the user can still type into them normally.
		 */
		preventPasswordAutofill: function () {
			var fieldsSelector = '#password_current, #password_1, #password_2';

			$( document )
				.off(
					'focus.storesuitePasswordAutofill touchstart.storesuitePasswordAutofill blur.storesuitePasswordAutofill',
					fieldsSelector
				)
				// Drop readonly on interaction so the field is typeable.
				.on(
					'focus.storesuitePasswordAutofill touchstart.storesuitePasswordAutofill',
					fieldsSelector,
					function () {
						$( this ).removeAttr( 'readonly' );
					}
				)
				// Re-arm the autofill guard when the user leaves an empty field.
				.on(
					'blur.storesuitePasswordAutofill',
					fieldsSelector,
					function () {
						if ( '' === $( this ).val() ) {
							$( this ).attr( 'readonly', 'readonly' );
						}
					}
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

			// Insert error message after the field or its wrapper
			if (
				$field.parent().hasClass( 'storesuite-coupon-code-wrapper' )
			) {
				$field.parent().after( $errorMsg );
			} else if (
				$field.parent().hasClass( 'storesuite-password-field' )
			) {
				$field.parent().after( $errorMsg );
			} else {
				$field.after( $errorMsg );
			}

			// Remove error on field change
			$field.one( 'input change', function () {
				$( this ).removeClass( 'storesuite-field-invalid' );
				$( this ).siblings( '.storesuite-field-error' ).remove();
				$( this )
					.parent()
					.siblings( '.storesuite-field-error' )
					.remove();
			} );
		},

		/**
		 * Check whether new password and confirm password match.
		 *
		 * @param {jQuery} $form Edit account form.
		 * @return {boolean} True when valid.
		 */
		validateEditAccountPasswordMatch: function ( $form ) {
			var $newPassword = $form.find( '#password_1' );
			var $confirmPassword = $form.find( '#password_2' );
			var mismatchMessage =
				storeSuiteFormHandler.i18n.account_password_mismatch;

			if ( ! $newPassword.length || ! $confirmPassword.length ) {
				return true;
			}

			var newPasswordValue = $newPassword.val() || '';
			var confirmPasswordValue = $confirmPassword.val() || '';

			// Clear previous mismatch UI.
			$newPassword.removeClass( 'storesuite-field-invalid' );
			$confirmPassword.removeClass( 'storesuite-field-invalid' );
			$newPassword.siblings( '.storesuite-field-error' ).remove();
			$confirmPassword.siblings( '.storesuite-field-error' ).remove();
			$newPassword
				.parent()
				.siblings( '.storesuite-field-error' )
				.remove();
			$confirmPassword
				.parent()
				.siblings( '.storesuite-field-error' )
				.remove();

			if (
				( newPasswordValue || confirmPasswordValue ) &&
				newPasswordValue !== confirmPasswordValue
			) {
				this.markFieldAsInvalid( $confirmPassword, mismatchMessage );
				return false;
			}

			return true;
		},

		/**
		 * Validate password fields while user types.
		 */
		bindEditAccountPasswordLiveValidation: function () {
			var self = this;
			var fieldsSelector =
				'#storesuite-edit-account-form #password_1, #storesuite-edit-account-form #password_2';
			var eventName =
				'input.storesuiteAccountPassword change.storesuiteAccountPassword';

			$( document ).off( eventName, fieldsSelector );
			$( document ).on( eventName, fieldsSelector, function () {
				self.validateEditAccountPasswordMatch(
					$( '#storesuite-edit-account-form' )
				);
			} );
		},

		/**
		 * Toggle password visibility for account password fields.
		 */
		bindPasswordVisibilityToggle: function () {
			$( document )
				.off(
					'click.storesuitePasswordToggle',
					'.storesuite-password-toggle'
				)
				.on(
					'click.storesuitePasswordToggle',
					'.storesuite-password-toggle',
					function () {
						var $toggleButton = $( this );
						var targetSelector =
							$toggleButton.data( 'target' ) || '';
						var $targetField = $( targetSelector );
						var $showIcon = $toggleButton.find(
							'.storesuite-password-icon-show'
						);
						var $hideIcon = $toggleButton.find(
							'.storesuite-password-icon-hide'
						);
						var showLabel =
							$toggleButton.data( 'show-label' ) ||
							'Show password';
						var hideLabel =
							$toggleButton.data( 'hide-label' ) ||
							'Hide password';

						if ( ! $targetField.length ) {
							return;
						}

						var isPasswordHidden =
							$targetField.attr( 'type' ) === 'password';
						$targetField.attr(
							'type',
							isPasswordHidden ? 'text' : 'password'
						);
						$toggleButton.attr(
							'aria-label',
							isPasswordHidden ? hideLabel : showLabel
						);
						$toggleButton.attr(
							'title',
							isPasswordHidden ? hideLabel : showLabel
						);
						$showIcon.toggleClass( 'storesuite-hide' );
						$hideIcon.toggleClass( 'storesuite-hide' );
					}
				);
		},

		/**
		 * Initialize datepicker for expiry date field
		 */
		initDatePicker: function () {
			if ( $( '#expiry_date' ).length ) {
				$( '#expiry_date' ).datepicker( {
					defaultDate: '',
					dateFormat: 'yy-mm-dd',
					numberOfMonths: 1,
					showButtonPanel: true,
					minDate: 0, // Prevent selecting past dates
				} );
			}
		},

		/**
		 * Initialize AJAX product search for coupon form
		 */
		initProductSearch: function () {
			$( ':input.wc-coupon-product-search' )
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
							url: storeSuiteFormHandler.ajax_url,
							dataType: 'json',
							delay: 250,
							data: function ( params ) {
								return {
									term: params.term,
									action:
										$( this ).data( 'action' ) ||
										'woocommerce_json_search_products_and_variations',
									security:
										storeSuiteFormHandler.search_products_nonce,
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

					$( this ).selectWoo( select2_args ).addClass( 'enhanced' );
				} );
		},

		/**
		 * Handle generate coupon code button click
		 */
		handleGenerateCouponCode: function () {
			$( document ).on(
				'click',
				'.button.generate-coupon-code',
				function ( e ) {
					e.preventDefault();

					var $coupon_code_field = $( '#coupon_code' ),
						result = '',
						generator = storeSuiteFormHandler.coupon_code_generator;

					// Generate random code
					for ( var i = 0; i < generator.char_length; i++ ) {
						result += generator.characters.charAt(
							Math.floor(
								Math.random() * generator.characters.length
							)
						);
					}

					// Add prefix and suffix
					result = generator.prefix + result + generator.suffix;

					// Set the generated code to the input field
					$coupon_code_field
						.trigger( 'focus' )
						.val( result )
						.trigger( 'input' )
						.trigger( 'change' );
				}
			);
		},

		/**
		 * Show loading state with SweetAlert2
		 */
		showLoading: function ( title ) {
			Swal.fire( {
				title: title || storeSuiteFormHandler.i18n.processing,
				text: storeSuiteFormHandler.i18n.please_wait,
				icon: 'info',
				allowOutsideClick: false,
				didOpen: () => {
					Swal.showLoading();
				},
			} );
		},

		/**
		 * Show success message
		 */
		showSuccess: function ( message ) {
			return Swal.fire( {
				icon: 'success',
				title: storeSuiteFormHandler.i18n.success_title,
				text: message,
				confirmButtonText: storeSuiteFormHandler.i18n.ok_button,
			} );
		},

		/**
		 * Show error message
		 */
		showError: function ( message ) {
			Swal.fire( {
				icon: 'error',
				title: storeSuiteFormHandler.i18n.error_title,
				text: message || storeSuiteFormHandler.i18n.unexpected_error,
				confirmButtonText: storeSuiteFormHandler.i18n.ok_button,
			} );
		},

		/**
		 * Simple HTML escape helper for SweetAlert inputs.
		 */
		escapeHtml: function ( string ) {
			if ( 'string' !== typeof string ) {
				return '';
			}
			return string
				.replace( /&/g, '&amp;' )
				.replace( /</g, '&lt;' )
				.replace( />/g, '&gt;' )
				.replace( /"/g, '&quot;' )
				.replace( /'/g, '&#039;' );
		},

		/**
		 * Handle Category Add
		 */
		handleCategoryAdd: function () {
			var self = this;

			$( document ).on(
				'submit',
				'#storesuite-add-category',
				function ( e ) {
					e.preventDefault();

					var $form = $( this );

					// Define required fields for validation
					var requiredFields = [
						{
							selector: '#product_category_name',
							message:
								storeSuiteFormHandler.i18n
									.category_name_required,
						},
					];

					// Validate required fields
					if (
						! self.validateRequiredFields( $form, requiredFields )
					) {
						return;
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
								self.showSuccess( response.data.message );
								$form[ 0 ].reset();
								// Clear category image.
								$( '#product_category_thumbnail_id' ).val( '' );
								$( '#product_category_thumbnail_url' ).val(
									''
								);
								$( '#category_thumb_img' ).html( '' );
								$( '#category-single-image' ).removeClass(
									'image-drop-bg'
								);
								$(
									'#category-single-image .image-drop-text span'
								).text(
									storeSuiteFormHandler.i18n.upload_image_text
								);
							} else {
								self.showError( response.data.error );
							}
						},
						error: function ( xhr, status, error ) {
							Swal.close();
							self.showError();
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
		 * Handle Category Edit
		 */
		handleCategoryEdit: function () {
			var self = this;

			$( document ).on(
				'submit',
				'#storesuite-edit-category',
				function ( e ) {
					e.preventDefault();

					var $form = $( this );

					// Define required fields for validation
					var requiredFields = [
						{
							selector: '#product_category_name',
							message:
								storeSuiteFormHandler.i18n
									.category_name_required,
						},
					];

					// Validate required fields
					if (
						! self.validateRequiredFields( $form, requiredFields )
					) {
						return;
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
								self.showSuccess( response.data.message );
							} else {
								self.showError( response.data.error );
							}
						},
						error: function ( xhr, status, error ) {
							Swal.close();
							self.showError();
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
		 * Handle Category Delete
		 */
		handleCategoryDelete: function () {
			var self = this;

			$( document ).on(
				'click',
				'.storesuite-delete-category',
				function ( e ) {
					e.preventDefault();

					var categoryId = $( this ).data( 'category-id' );

					if ( ! categoryId ) {
						return;
					}

					Swal.fire( {
						title: storeSuiteFormHandler.i18n.are_you_sure,
						text: storeSuiteFormHandler.i18n
							.delete_category_warning,
						icon: 'warning',
						showCancelButton: true,
						confirmButtonText:
							storeSuiteFormHandler.i18n.yes_delete,
						cancelButtonText:
							storeSuiteFormHandler.i18n.cancel_button,
					} ).then( function ( result ) {
						if ( ! result.isConfirmed ) {
							return;
						}

						self.showLoading( storeSuiteFormHandler.i18n.deleting );

						var formData = new FormData();
						formData.append( 'id', categoryId );
						formData.append(
							'action',
							'storesuite_delete_product_category'
						);
						formData.append(
							'storesuite_delete_product_category_nonce',
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
									self.showSuccess( response.data.message );
									$( '#category-row-' + categoryId ).fadeOut(
										300,
										function () {
											$( this ).remove();
										}
									);
								} else {
									self.showError( response.data.error );
								}
							},
							error: function ( xhr, status, error ) {
								Swal.close();
								self.showError();
							},
						} );
					} );
				}
			);
		},

		/**
		 * Handle Tag Add
		 */
		handleTagAdd: function () {
			var self = this;

			$( document ).on( 'submit', '#storesuite-add-tag', function ( e ) {
				e.preventDefault();

				var $form = $( this );

				// Define required fields for validation
				var requiredFields = [
					{
						selector: '#name',
						message: storeSuiteFormHandler.i18n.tag_name_required,
					},
				];

				// Validate required fields
				if ( ! self.validateRequiredFields( $form, requiredFields ) ) {
					return;
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
							self.showSuccess( response.data.message );
							$form[ 0 ].reset();
						} else {
							self.showError( response.data.error );
						}
					},
					error: function ( xhr, status, error ) {
						Swal.close();
						self.showError();
					},
					complete: function () {
						$submitBtn.prop( 'disabled', false );
						window.StoreSuite.storeSuiteLoader.unblock(
							$( '.my-storesuite-wrapper' )
						);
					},
				} );
			} );
		},

		/**
		 * Handle Tag Edit
		 */
		handleTagEdit: function () {
			var self = this;

			$( document ).on( 'submit', '#storesuite-edit-tag', function ( e ) {
				e.preventDefault();

				var $form = $( this );

				// Define required fields for validation
				var requiredFields = [
					{
						selector: '#name',
						message: storeSuiteFormHandler.i18n.tag_name_required,
					},
				];

				// Validate required fields
				if ( ! self.validateRequiredFields( $form, requiredFields ) ) {
					return;
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
							self.showSuccess( response.data.message );
						} else {
							self.showError( response.data.error );
						}
					},
					error: function ( xhr, status, error ) {
						Swal.close();
						self.showError();
					},
					complete: function () {
						$submitBtn.prop( 'disabled', false );
						window.StoreSuite.storeSuiteLoader.unblock(
							$( '.my-storesuite-wrapper' )
						);
					},
				} );
			} );
		},

		/**
		 * Handle Tag Delete
		 */
		handleTagDelete: function () {
			var self = this;

			$( document ).on(
				'click',
				'.storesuite-delete-tag',
				function ( e ) {
					e.preventDefault();

					var tagId = $( this ).data( 'tag-id' );

					if ( ! tagId ) {
						return;
					}

					Swal.fire( {
						title: storeSuiteFormHandler.i18n.are_you_sure,
						text: storeSuiteFormHandler.i18n.delete_tag_warning,
						icon: 'warning',
						showCancelButton: true,
						confirmButtonText:
							storeSuiteFormHandler.i18n.yes_delete,
						cancelButtonText:
							storeSuiteFormHandler.i18n.cancel_button,
					} ).then( function ( result ) {
						if ( ! result.isConfirmed ) {
							return;
						}

						self.showLoading( storeSuiteFormHandler.i18n.deleting );

						var formData = new FormData();
						formData.append( 'id', tagId );
						formData.append(
							'action',
							'storesuite_delete_product_tag'
						);
						formData.append(
							'storesuite_delete_product_tag_nonce',
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
									self.showSuccess( response.data.message );
									$( '#tag-row-' + tagId ).fadeOut(
										300,
										function () {
											$( this ).remove();
										}
									);
								} else {
									self.showError( response.data.error );
								}
							},
							error: function ( xhr, status, error ) {
								Swal.close();
								self.showError();
							},
						} );
					} );
				}
			);
		},

		/**
		 * Handle Brand Add
		 */
		handleBrandAdd: function () {
			var self = this;

			$( document ).on(
				'submit',
				'#storesuite-add-brand',
				function ( e ) {
					e.preventDefault();

					var $form = $( this );

					// Define required fields for validation
					var requiredFields = [
						{
							selector: '#product_brand_name',
							message:
								storeSuiteFormHandler.i18n.brand_name_required,
						},
					];

					// Validate required fields
					if (
						! self.validateRequiredFields( $form, requiredFields )
					) {
						return;
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
								self.showSuccess( response.data.message );
								$form[ 0 ].reset();
								// Clear brand image.
								$( '#product_brand_thumbnail_id' ).val( '' );
								$( '#product_brand_thumbnail_url' ).val( '' );
								$( '#brand_thumb_img' ).html( '' );
								$( '#brand-single-image' ).removeClass(
									'image-drop-bg'
								);
								$(
									'#brand-single-image .image-drop-text span'
								).text(
									storeSuiteFormHandler.i18n.upload_image_text
								);
							} else {
								self.showError( response.data.error );
							}
						},
						error: function ( xhr, status, error ) {
							Swal.close();
							self.showError();
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
		 * Handle Brand Edit
		 */
		handleBrandEdit: function () {
			var self = this;

			$( document ).on(
				'submit',
				'#storesuite-edit-brand',
				function ( e ) {
					e.preventDefault();

					var $form = $( this );

					// Define required fields for validation
					var requiredFields = [
						{
							selector: '#product_brand_name',
							message:
								storeSuiteFormHandler.i18n.brand_name_required,
						},
					];

					// Validate required fields
					if (
						! self.validateRequiredFields( $form, requiredFields )
					) {
						return;
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
								self.showSuccess( response.data.message );
							} else {
								self.showError( response.data.error );
							}
						},
						error: function ( xhr, status, error ) {
							Swal.close();
							self.showError();
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
		 * Handle Brand Delete
		 */
		handleBrandDelete: function () {
			var self = this;

			$( document ).on(
				'click',
				'.storesuite-delete-brand',
				function ( e ) {
					e.preventDefault();

					var brandId = $( this ).data( 'brand-id' );

					if ( ! brandId ) {
						return;
					}

					Swal.fire( {
						title: storeSuiteFormHandler.i18n.are_you_sure,
						text: storeSuiteFormHandler.i18n.delete_brand_warning,
						icon: 'warning',
						showCancelButton: true,
						confirmButtonText:
							storeSuiteFormHandler.i18n.yes_delete,
						cancelButtonText:
							storeSuiteFormHandler.i18n.cancel_button,
					} ).then( function ( result ) {
						if ( ! result.isConfirmed ) {
							return;
						}

						var formData = new FormData();
						formData.append( 'id', brandId );
						formData.append(
							'action',
							'storesuite_delete_product_brand'
						);
						formData.append(
							'storesuite_delete_product_brand_nonce',
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
									self.showSuccess( response.data.message );
									$( '#brand-row-' + brandId ).fadeOut(
										300,
										function () {
											$( this ).remove();
										}
									);
								} else {
									self.showError( response.data.error );
								}
							},
							error: function ( xhr, status, error ) {
								Swal.close();
								self.showError();
							},
						} );
					} );
				}
			);
		},

		/**
		 * Handle Attribute Add
		 */
		handleAttributeAdd: function () {
			var self = this;

			$( document ).on(
				'submit',
				'#storesuite-add-attribute',
				function ( e ) {
					e.preventDefault();

					var $form = $( this );

					var requiredFields = [
						{
							selector: '#attribute_label',
							message:
								storeSuiteFormHandler.i18n
									.attribute_name_required,
						},
					];

					if (
						! self.validateRequiredFields( $form, requiredFields )
					) {
						return;
					}

					var formData = new FormData( this );
					var $submitBtn = $form.find( 'button[type="submit"]' );
					$submitBtn.prop( 'disabled', true );

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
								self.showSuccess( response.data.message );
								$form[ 0 ].reset();
							} else {
								self.showError( response.data.error );
							}
						},
						error: function () {
							Swal.close();
							self.showError();
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
		 * Handle Attribute Edit
		 */
		handleAttributeEdit: function () {
			var self = this;

			$( document ).on(
				'submit',
				'#storesuite-edit-attribute',
				function ( e ) {
					e.preventDefault();

					var $form = $( this );

					var requiredFields = [
						{
							selector: '#attribute_label',
							message:
								storeSuiteFormHandler.i18n
									.attribute_name_required,
						},
					];

					if (
						! self.validateRequiredFields( $form, requiredFields )
					) {
						return;
					}

					var formData = new FormData( this );
					var $submitBtn = $form.find( 'button[type="submit"]' );
					$submitBtn.prop( 'disabled', true );

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
								self.showSuccess( response.data.message );
							} else {
								self.showError( response.data.error );
							}
						},
						error: function () {
							Swal.close();
							self.showError();
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
		 * Handle Attribute Delete
		 */
		handleAttributeDelete: function () {
			var self = this;

			$( document ).on(
				'click',
				'.storesuite-delete-attribute',
				function ( e ) {
					e.preventDefault();

					var attributeId = $( this ).data( 'attribute-id' );

					if (
						! attributeId ||
						typeof Swal === 'undefined' ||
						typeof storeSuiteFormHandler === 'undefined'
					) {
						return;
					}

					var i18n = storeSuiteFormHandler.i18n;

					Swal.fire( {
						title: i18n.are_you_sure,
						text: i18n.delete_attribute_warning,
						icon: 'warning',
						showCancelButton: true,
						confirmButtonText: i18n.yes_delete,
						cancelButtonText: i18n.cancel_button,
					} ).then( function ( result ) {
						if ( ! result.isConfirmed ) {
							return;
						}

						window.StoreSuite.storeSuiteLoader.block(
							$( '.my-storesuite-wrapper' )
						);

						var formData = new FormData();
						formData.append( 'id', attributeId );
						formData.append(
							'action',
							'storesuite_delete_product_attribute'
						);
						formData.append(
							'storesuite_delete_product_attribute_nonce',
							storeSuiteFormHandler.storesuite_woo_delete_nonce_
						);

						$.ajax( {
							url: storeSuiteFormHandler.ajax_url,
							type: 'POST',
							data: formData,
							processData: false,
							contentType: false,
							success: function ( response ) {
								if ( response.success ) {
									Swal.fire( {
										icon: 'success',
										title: i18n.success_title,
										text: response.data.message,
									} );
									$(
										'#attribute-row-' + attributeId
									).fadeOut( 300, function () {
										$( this ).remove();
									} );
								} else {
									Swal.fire( {
										icon: 'error',
										title: i18n.error_title,
										text:
											response.data && response.data.error
												? response.data.error
												: i18n.unexpected_error,
									} );
								}
							},
							error: function () {
								Swal.close();
								Swal.fire( {
									icon: 'error',
									title: i18n.error_title,
									text: i18n.unexpected_error,
								} );
							},
							complete: function () {
								window.StoreSuite.storeSuiteLoader.unblock(
									$( '.my-storesuite-wrapper' )
								);
							},
						} );
					} );
				}
			);
		},

		/**
		 * Handle Attribute Term Add
		 */
		handleAttributeTermAdd: function () {
			var self = this;

			$( document ).on(
				'submit',
				'#storesuite-add-attribute-term',
				function ( e ) {
					e.preventDefault();

					var $form = $( this );
					var i18n = storeSuiteFormHandler.i18n;

					var requiredFields = [
						{
							selector: '#term_name',
							message: i18n.attribute_term_name_required,
						},
					];

					if (
						! self.validateRequiredFields( $form, requiredFields )
					) {
						return;
					}

					var formData = new FormData( this );
					var $submitBtn = $form.find( 'button[type="submit"]' );
					$submitBtn.prop( 'disabled', true );

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
							if ( response.success ) {
								$form[ 0 ].reset();
								self.showSuccess(
									response.data.message
								).then( function () {
									// Reload so the new term shows in the
									// correct sorted/paginated position.
									window.location.reload();
								} );
							} else {
								self.showError( response.data.error );
							}
						},
						error: function () {
							Swal.fire( {
								icon: 'error',
								title: i18n.error_title,
								text: i18n.unexpected_error,
							} );
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
		 * Handle Attribute Term Edit (edit page form)
		 */
		handleAttributeTermEdit: function () {
			var self = this;

			$( document ).on(
				'submit',
				'#storesuite-edit-attribute-term',
				function ( e ) {
					e.preventDefault();

					var $form = $( this );

					var requiredFields = [
						{
							selector: '#term_name',
							message:
								storeSuiteFormHandler.i18n
									.attribute_term_name_required,
						},
					];

					if (
						! self.validateRequiredFields( $form, requiredFields )
					) {
						return;
					}

					var formData = new FormData( this );
					var $submitBtn = $form.find( 'button[type="submit"]' );
					$submitBtn.prop( 'disabled', true );

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
								self.showSuccess(
									response.data.message
								).then( function () {
									window.location.reload();
								} );
							} else {
								self.showError( response.data.error );
							}
						},
						error: function () {
							Swal.close();
							self.showError();
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
		 * Handle Attribute Term Delete
		 */
		handleAttributeTermDelete: function () {
			$( document ).on(
				'click',
				'.storesuite-delete-attribute-term',
				function ( e ) {
					e.preventDefault();

					var termId = $( this ).data( 'term-id' );
					var taxonomy = $( this ).data( 'taxonomy' );

					if (
						! termId ||
						! taxonomy ||
						typeof Swal === 'undefined' ||
						typeof storeSuiteFormHandler === 'undefined'
					) {
						return;
					}

					var i18n = storeSuiteFormHandler.i18n;

					Swal.fire( {
						title: i18n.are_you_sure,
						text: i18n.delete_attribute_term_warning,
						icon: 'warning',
						showCancelButton: true,
						confirmButtonText: i18n.yes_delete,
						cancelButtonText: i18n.cancel_button,
					} ).then( function ( result ) {
						if ( ! result.isConfirmed ) {
							return;
						}

						window.StoreSuite.storeSuiteLoader.block(
							$( '.my-storesuite-wrapper' )
						);

						var formData = new FormData();
						formData.append(
							'action',
							'storesuite_delete_attribute_term'
						);
						formData.append(
							'storesuite_delete_attribute_term_nonce',
							storeSuiteFormHandler.storesuite_woo_delete_nonce_
						);
						formData.append( 'id', termId );
						formData.append( 'taxonomy', taxonomy );

						$.ajax( {
							url: storeSuiteFormHandler.ajax_url,
							type: 'POST',
							data: formData,
							processData: false,
							contentType: false,
							success: function ( response ) {
								if ( response.success ) {
									Swal.fire( {
										icon: 'success',
										title: i18n.success_title,
										text: response.data.message,
										confirmButtonText: i18n.ok_button,
									} ).then( function () {
										// Pull the latest terms (and correct
										// pagination) from the server. If the
										// deleted term was the only item left on
										// this page, go to the previous page
										// instead of landing on an empty one.
										var $remaining = $(
											'.storesuite-attribute-terms-table tbody tr[id^="term-row-"]'
										);
										var $prevPage = $(
											'.storesuite-pagination a.prev'
										);
										if (
											$remaining.length <= 1 &&
											$prevPage.length
										) {
											window.location.assign(
												$prevPage.attr( 'href' )
											);
											return;
										}
										window.location.reload();
									} );
								} else {
									Swal.fire( {
										icon: 'error',
										title: i18n.error_title,
										text:
											response.data && response.data.error
												? response.data.error
												: i18n.unexpected_error,
									} );
								}
							},
							error: function () {
								Swal.fire( {
									icon: 'error',
									title: i18n.error_title,
									text: i18n.unexpected_error,
								} );
							},
							complete: function () {
								window.StoreSuite.storeSuiteLoader.unblock(
									$( '.my-storesuite-wrapper' )
								);
							},
						} );
					} );
				}
			);
		},

		/**
		 * Handle Coupon Add
		 */
		handleCouponAdd: function () {
			var self = this;

			$( document ).on(
				'submit',
				'#storesuite-add-coupon',
				function ( e ) {
					e.preventDefault();

					var $form = $( this );

					// Define required fields for validation
					var requiredFields = [
						{
							selector: '#coupon_code',
							message:
								storeSuiteFormHandler.i18n.coupon_code_required,
						},
						{
							selector: '#discount_type',
							message:
								storeSuiteFormHandler.i18n
									.coupon_discount_type_required,
						},
						{
							selector: '#coupon_amount',
							message:
								storeSuiteFormHandler.i18n
									.coupon_amount_required,
							type: 'number',
						},
					];

					// Validate required fields
					if (
						! self.validateRequiredFields( $form, requiredFields )
					) {
						return;
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
								self.showSuccess( response.data.message );
								setTimeout( function () {
									window.location.href =
										storeSuiteFormHandler.coupons_url ||
										window.location.href.replace(
											'add-new-coupon',
											'coupons'
										);
								}, 1500 );
							} else {
								self.showError( response.data.error );
							}
						},
						error: function ( xhr, status, error ) {
							Swal.close();
							self.showError();
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
		 * Handle Coupon Edit
		 */
		handleCouponEdit: function () {
			var self = this;

			$( document ).on(
				'submit',
				'#storesuite-edit-coupon',
				function ( e ) {
					e.preventDefault();

					var $form = $( this );

					// Define required fields for validation
					var requiredFields = [
						{
							selector: '#coupon_code',
							message:
								storeSuiteFormHandler.i18n.coupon_code_required,
						},
						{
							selector: '#discount_type',
							message:
								storeSuiteFormHandler.i18n
									.coupon_discount_type_required,
						},
						{
							selector: '#coupon_amount',
							message:
								storeSuiteFormHandler.i18n
									.coupon_amount_required,
							type: 'number',
						},
					];

					// Validate required fields
					if (
						! self.validateRequiredFields( $form, requiredFields )
					) {
						return;
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
								self.showSuccess( response.data.message );
								setTimeout( function () {
									window.location.href =
										storeSuiteFormHandler.coupons_url ||
										window.location.href.replace(
											/edit-coupon\/\d+/,
											'coupons'
										);
								}, 1500 );
							} else {
								self.showError( response.data.error );
							}
						},
						error: function ( xhr, status, error ) {
							Swal.close();
							self.showError();
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
		 * Handle Coupon Delete
		 */
		handleCouponDelete: function () {
			var self = this;

			$( document ).on( 'click', '.storesuite-delete-coupon', function ( e ) {
				e.preventDefault();

				var $button = $( this );
				var couponId = $button.data( 'coupon-id' );
				var couponNonce = $button.data( 'nonce' );

				if ( ! couponId ) {
					return;
				}

				Swal.fire( {
					title: storeSuiteFormHandler.i18n.are_you_sure,
					text: storeSuiteFormHandler.i18n.delete_coupon_warning,
					icon: 'warning',
					showCancelButton: true,
					confirmButtonText: storeSuiteFormHandler.i18n.yes_delete,
					cancelButtonText: storeSuiteFormHandler.i18n.cancel_button,
				} ).then( function ( result ) {
					if ( ! result.isConfirmed ) {
						return;
					}

					var formData = new FormData();
					formData.append( 'action', 'storesuite_delete_coupon' );
					formData.append( 'coupon_id', couponId );
					formData.append(
						'storesuite_delete_coupon_nonce',
						couponNonce
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
								self.showSuccess( response.data.message );
								$button
									.closest( 'tr' )
									.fadeOut( 300, function () {
										$( this ).remove();
										// Reload page if no coupons left
										if (
											$( '.single-coupon-item' )
												.length === 0
										) {
											window.location.reload();
										}
									} );
							} else {
								self.showError( response.data.error );
							}
						},
						error: function ( xhr, status, error ) {
							Swal.close();
							self.showError();
						},
					} );
				} );

				return false;
			} );
		},

		/**
		 * Duplicate a product or coupon from the list row actions, then open
		 * the edit page of the draft copy.
		 */
		handleDuplicateItem: function () {
			var self = this;

			$( document ).on( 'click', '.storesuite-duplicate-item', function ( e ) {
				e.preventDefault();

				var $button = $( this );
				var objectType = $button.data( 'object' );
				var objectId = $button.data( 'id' );

				if ( ! objectId || ! objectType ) {
					return;
				}

				var action =
					'coupon' === objectType
						? 'storesuite_duplicate_coupon'
						: 'storesuite_duplicate_product';

				self.showLoading( storeSuiteFormHandler.i18n.duplicating );

				$.ajax( {
					url: storeSuiteFormHandler.ajax_url,
					type: 'POST',
					data: {
						action: action,
						id: objectId,
						security: storeSuiteFormHandler.duplicate_nonce,
					},
					success: function ( response ) {
						if ( response.success && response.data.redirect ) {
							window.location.href = response.data.redirect;
							return;
						}

						Swal.close();
						self.showError( response.data && response.data.error );
					},
					error: function () {
						Swal.close();
						self.showError();
					},
				} );
			} );
		},

		/**
		 * Handle Edit Account form submit (AJAX)
		 */
		handleEditAccount: function () {
			var self = this;

			$( document ).on(
				'submit',
				'#storesuite-edit-account-form',
				function ( e ) {
					e.preventDefault();

					var $form = $( this );

					var requiredFields = [
						{
							selector: '#account_first_name',
							message:
								storeSuiteFormHandler.i18n
									.account_first_name_required,
						},
						{
							selector: '#account_last_name',
							message:
								storeSuiteFormHandler.i18n
									.account_last_name_required,
						},
						{
							selector: '#account_display_name',
							message:
								storeSuiteFormHandler.i18n
									.account_display_name_required,
						},
						{
							selector: '#account_email',
							message:
								storeSuiteFormHandler.i18n
									.account_email_required,
						},
					];

					if (
						! self.validateRequiredFields( $form, requiredFields )
					) {
						return;
					}

					if ( ! self.validateEditAccountPasswordMatch( $form ) ) {
						$form.find( '#password_2' ).trigger( 'focus' );
						return;
					}

					var formData = new FormData( this );
					var $submitBtn = $form.find( 'button[type="submit"]' );
					$submitBtn.prop( 'disabled', true );

					if (
						window.StoreSuite &&
						window.StoreSuite.storeSuiteLoader
					) {
						window.StoreSuite.storeSuiteLoader.block(
							$( '.storesuite-main-dashboard' )
						);
					}

					$.ajax( {
						url: storeSuiteFormHandler.ajax_url,
						type: 'POST',
						data: formData,
						processData: false,
						contentType: false,
						success: function ( response ) {
							if ( response.success ) {
								self.showSuccess( response.data.message );
							} else {
								self.showError( response.data.error );
							}
						},
						error: function () {
							self.showError();
						},
						complete: function () {
							$submitBtn.prop( 'disabled', false );
							if (
								window.StoreSuite &&
								window.StoreSuite.storeSuiteLoader
							) {
								window.StoreSuite.storeSuiteLoader.unblock(
									$( '.storesuite-main-dashboard' )
								);
							}
						},
					} );
				}
			);
		},

		/**
		 * Toggle visibility of the password change card on edit-account form.
		 */
		initEditAccountPasswordToggle: function () {
			var $switch = $( '#show_password_change' );
			var $card = $( '#storesuite-edit-account-password-card' );
			if ( ! $switch.length || ! $card.length ) {
				return;
			}
			$card.toggle( $switch.is( ':checked' ) );
			$switch.on( 'change', function () {
				$card.toggle( $( this ).is( ':checked' ) );
			} );
		},
	};

	StoreFrontFormHandler.init();
} )( jQuery );
