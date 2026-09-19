( function ( $ ) {
	'use strict';

	var StoreSuiteCouponBulkPage = {
		init: function () {
			if ( typeof StoreSuiteCouponBulk === 'undefined' ) {
				return;
			}
			// Select-all/indeterminate sync is handled globally in script.js
			// (StoreFrontCommonConfig.handleBulkActionCheckbox).
			this.bindBulkApply();
			this.bindBulkEditSubmit();
			this.initBulkEditModal();
		},

		i18n: function () {
			return ( StoreSuiteCouponBulk && StoreSuiteCouponBulk.i18n ) || {};
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


		getSelectedIds: function () {
			return $( '#storesuite-coupon-bulk-actions' )
				.find( 'input[name="bulk_coupon_ids[]"]:checked' )
				.map( function () {
					return $( this ).val();
				} )
				.get();
		},

		/**
		 * Bulk action Apply: Edit opens the modal, Move to Trash deletes via AJAX.
		 */
		bindBulkApply: function () {
			var self = this;

			$( document ).on(
				'submit',
				'#storesuite-coupon-bulk-actions',
				function ( e ) {
					var action = $( '#bulk-action-selector-coupons' ).val();
					if ( action !== 'edit' && action !== 'trash' ) {
						return;
					}

					e.preventDefault();

					var i18n = self.i18n();
					var selectedIds = self.getSelectedIds();

					if ( ! selectedIds.length ) {
						Swal.fire( {
							icon: 'warning',
							title: i18n.select_items_title,
							text: i18n.select_items_message,
							confirmButtonText: i18n.ok_button,
						} );
						return;
					}

					if ( action === 'edit' ) {
						self.openBulkEditModal( selectedIds );
						return;
					}

					self.bulkTrash( selectedIds );
				}
			);
		},

		openBulkEditModal: function ( selectedIds ) {
			var suiteModal =
				window.StoreSuite && window.StoreSuite.storeSuiteModal;
			var $modal = $( '#storesuite-coupon-bulk-edit-modal' );
			if ( ! suiteModal || ! $modal.length ) {
				return;
			}

			var $hiddenIds = $( '#storesuite-bulk-edit-coupon-ids' );
			$hiddenIds.empty();
			selectedIds.forEach( function ( id ) {
				$hiddenIds.append(
					$( '<input>', {
						type: 'hidden',
						name: 'post[]',
						value: id,
					} )
				);
			} );

			suiteModal.open( $modal );
		},

		bulkTrash: function ( selectedIds ) {
			var self = this;
			var i18n = self.i18n();

			Swal.fire( {
				title: i18n.are_you_sure,
				text: i18n.bulk_trash_warning,
				icon: 'warning',
				showCancelButton: true,
				confirmButtonText: i18n.yes_delete,
				cancelButtonText: i18n.cancel_button,
			} ).then( function ( result ) {
				if ( ! result.isConfirmed ) {
					return;
				}

				var formData = new FormData();
				formData.append( 'action', 'storesuite_bulk_trash_coupons' );
				formData.append( 'security', StoreSuiteCouponBulk.trash_nonce );
				selectedIds.forEach( function ( id ) {
					formData.append( 'coupon_ids[]', id );
				} );

				self.blockUi();

				$.ajax( {
					url: StoreSuiteCouponBulk.ajax_url,
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
									response.data && response.data.message
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
						self.showError( self.getXhrErrorMessage( xhr ) );
					},
					complete: function () {
						self.unblockUi();
					},
				} );
			} );
		},

		initBulkEditModal: function () {
			var suiteModal =
				window.StoreSuite && window.StoreSuite.storeSuiteModal;
			var $modal = $( '#storesuite-coupon-bulk-edit-modal' );

			if ( ! suiteModal || ! $modal.length ) {
				return;
			}

			suiteModal.initOverlay( $modal, {
				fade: true,
				closeSelector:
					'.storesuite-coupon-bulk-modal-cancel, .storesuite-coupon-bulk-modal-close',
			} );
		},

		bindBulkEditSubmit: function () {
			var self = this;

			$( document ).on(
				'submit',
				'#storesuite-coupon-bulk-edit-form',
				function ( e ) {
					e.preventDefault();

					var $form = $( this );
					var $modal = $( '#storesuite-coupon-bulk-edit-modal' );
					var suiteModal =
						window.StoreSuite && window.StoreSuite.storeSuiteModal;
					var i18n = self.i18n();

					var formData = new FormData( this );
					formData.append( 'action', 'storesuite_bulk_edit_coupons' );
					formData.append(
						'security',
						StoreSuiteCouponBulk.edit_nonce
					);

					var $submit = $form.find(
						'.storesuite-coupon-bulk-edit-submit'
					);
					$submit.prop( 'disabled', true );
					self.blockUi();

					$.ajax( {
						url: StoreSuiteCouponBulk.ajax_url,
						type: 'POST',
						data: formData,
						processData: false,
						contentType: false,
						success: function ( response ) {
							if ( response.success ) {
								if ( suiteModal && $modal.length ) {
									suiteModal.close( $modal );
								}
								Swal.fire( {
									icon: 'success',
									title: i18n.success_title,
									text:
										response.data && response.data.message
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
							self.showError( self.getXhrErrorMessage( xhr ) );
						},
						complete: function () {
							$submit.prop( 'disabled', false );
							self.unblockUi();
						},
					} );
				}
			);
		},
	};

	StoreSuiteCouponBulkPage.init();
} )( jQuery );
