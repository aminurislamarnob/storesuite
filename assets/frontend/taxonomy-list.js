( function ( $ ) {
	'use strict';

	var StoreSuiteTaxonomyListPage = {
		init: function () {
			if ( typeof StoreSuiteTaxonomyList === 'undefined' ) {
				return;
			}
			// Select-all/indeterminate sync is handled globally in script.js
			// (StoreFrontCommonConfig.handleBulkActionCheckbox).
			this.bindBulkApply();
			this.initQuickEditModal();
		},

		i18n: function () {
			return ( StoreSuiteTaxonomyList && StoreSuiteTaxonomyList.i18n ) || {};
		},

		showError: function ( message ) {
			var i18n = this.i18n();
			Swal.fire( {
				icon: 'error',
				title: i18n.error_title,
				text: message || i18n.unexpected_error,
				confirmButtonText: i18n.ok_button,
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

		blockUi: function () {
			if ( window.StoreSuite && window.StoreSuite.storeSuiteLoader ) {
				window.StoreSuite.storeSuiteLoader.block(
					$( '.my-storesuite-wrapper' )
				);
			}
		},

		unblockUi: function () {
			if ( window.StoreSuite && window.StoreSuite.storeSuiteLoader ) {
				window.StoreSuite.storeSuiteLoader.unblock(
					$( '.my-storesuite-wrapper' )
				);
			}
		},

		/**
		 * Bulk action Apply: delete the selected terms after confirmation.
		 */
		bindBulkApply: function () {
			var self = this;

			$( document ).on(
				'click',
				'.storesuite-bulk-apply',
				function ( e ) {
					e.preventDefault();

					var i18n = self.i18n();
					var $actions = $( this ).closest(
						'.storesuite-list-bulk-actions'
					);
					var action = $actions
						.find( '.storesuite-bulk-action-select' )
						.val();

					if ( action !== 'delete' ) {
						return;
					}

					var objectType = $actions.data( 'object-type' );
					var taxonomy = $actions.data( 'taxonomy' ) || '';

					var selectedIds = $( '.storesuite-bulk-cb:checked' )
						.map( function () {
							return $( this ).val();
						} )
						.get();

					if ( ! selectedIds.length ) {
						Swal.fire( {
							icon: 'warning',
							title: i18n.select_items_title,
							text: i18n.select_items_message,
							confirmButtonText: i18n.ok_button,
						} );
						return;
					}

					Swal.fire( {
						title: i18n.are_you_sure,
						text: i18n.bulk_delete_warning,
						icon: 'warning',
						showCancelButton: true,
						confirmButtonText: i18n.yes_delete,
						cancelButtonText: i18n.cancel_button,
					} ).then( function ( result ) {
						if ( ! result.isConfirmed ) {
							return;
						}

						var formData = new FormData();
						formData.append( 'action', 'storesuite_bulk_delete_terms' );
						formData.append(
							'security',
							StoreSuiteTaxonomyList.delete_nonce
						);
						formData.append( 'object_type', objectType );
						formData.append( 'taxonomy', taxonomy );
						selectedIds.forEach( function ( id ) {
							formData.append( 'ids[]', id );
						} );

						self.blockUi();

						$.ajax( {
							url: StoreSuiteTaxonomyList.ajax_url,
							type: 'POST',
							data: formData,
							processData: false,
							contentType: false,
							success: function ( response ) {
								if ( response.success ) {
									Swal.fire( {
										icon: 'success',
										title: i18n.success_title,
										text:
											response.data &&
											response.data.message
												? response.data.message
												: '',
										confirmButtonText: i18n.ok_button,
									} ).then( function () {
										window.location.reload();
									} );
								} else {
									self.showError(
										response.data &&
											( response.data.message ||
												response.data.error )
									);
								}
							},
							error: function ( xhr ) {
								self.showError(
									self.getXhrErrorMessage( xhr )
								);
							},
							complete: function () {
								self.unblockUi();
							},
						} );
					} );
				}
			);
		},

		/**
		 * Quick edit: load the form into the shared modal, then save via AJAX.
		 */
		initQuickEditModal: function () {
			var self = this;
			var suiteModal =
				window.StoreSuite && window.StoreSuite.storeSuiteModal;
			var $modal = $( '#storesuite-list-quick-edit-modal' );
			var $modalBody = $modal.find(
				'#storesuite-list-quick-edit-modal-body'
			);

			if ( ! suiteModal || ! $modal.length ) {
				return;
			}

			suiteModal.initOverlay( $modal, {
				fade: true,
				closeSelector:
					'.storesuite-list-quick-edit-modal-cancel, .storesuite-list-quick-edit-modal-close',
			} );

			// Clear the body once the close transition settles.
			$modal.on(
				'transitionend.storesuiteListQuickEdit',
				function ( transitionEvent ) {
					if ( transitionEvent.target !== $modal[ 0 ] ) {
						return;
					}
					if ( $modal.prop( 'hidden' ) ) {
						$modalBody.empty();
					}
				}
			);

			$( document ).on(
				'click',
				'.storesuite-item-quick-edit',
				function ( e ) {
					e.preventDefault();

					var $trigger = $( this );
					var objectType = $trigger.data( 'object-type' );
					var itemId = $trigger.data( 'id' );
					var taxonomy = $trigger.data( 'taxonomy' ) || '';

					if ( ! objectType || ! itemId ) {
						return;
					}

					// Close the row menu the click came from before the modal opens.
					$trigger.closest( '.storesuite-dropdown-menu' ).stop( true, false ).slideUp( 150 );

					$modalBody.empty();
					self.blockUi();

					$.ajax( {
						url: StoreSuiteTaxonomyList.ajax_url,
						type: 'POST',
						dataType: 'json',
						data: {
							action: 'storesuite_get_list_quick_edit_form',
							security: StoreSuiteTaxonomyList.load_form_nonce,
							object_type: objectType,
							id: itemId,
							taxonomy: taxonomy,
						},
						success: function ( response ) {
							if (
								! response.success ||
								! response.data ||
								! response.data.html
							) {
								self.showError(
									response.data && response.data.message
								);
								return;
							}
							$modalBody.html( response.data.html );
							suiteModal.open( $modal );
						},
						error: function ( xhr ) {
							self.showError( self.getXhrErrorMessage( xhr ) );
						},
						complete: function () {
							self.unblockUi();
						},
					} );
				}
			);

			$modal.on(
				'click',
				'.storesuite-list-quick-edit-submit',
				function ( e ) {
					e.preventDefault();

					var $fieldset = $modalBody.find( 'fieldset' ).first();
					if ( ! $fieldset.length ) {
						return;
					}

					var payload = {};
					$modalBody
						.find( '[data-field-name]' )
						.each( function () {
							var $field = $( this );
							var fieldName = $field.data( 'field-name' );
							if ( ! fieldName ) {
								return;
							}
							if ( $field.attr( 'type' ) === 'checkbox' ) {
								if ( $field.is( ':checked' ) ) {
									payload[ fieldName ] = '1';
								}
								return;
							}
							payload[ fieldName ] =
								$field.val() === null ? '' : $field.val();
						} );

					$fieldset.prop( 'disabled', true );
					self.blockUi();

					$.ajax( {
						url: StoreSuiteTaxonomyList.ajax_url,
						method: 'POST',
						dataType: 'json',
						data: {
							action: 'storesuite_save_list_quick_edit',
							security: StoreSuiteTaxonomyList.save_nonce,
							data: payload,
						},
					} )
						.done( function ( response ) {
							if ( response.success ) {
								suiteModal.close( $modal );
								Swal.fire( {
									icon: 'success',
									title: self.i18n().success_title,
									text:
										response.data &&
										response.data.message
											? response.data.message
											: '',
									confirmButtonText: self.i18n().ok_button,
								} ).then( function () {
									window.location.reload();
								} );
							} else {
								self.showError(
									response.data && response.data.message
								);
							}
						} )
						.fail( function ( xhr ) {
							self.showError( self.getXhrErrorMessage( xhr ) );
						} )
						.always( function () {
							$fieldset.prop( 'disabled', false );
							self.unblockUi();
						} );
				}
			);
		},
	};

	StoreSuiteTaxonomyListPage.init();
} )( jQuery );
