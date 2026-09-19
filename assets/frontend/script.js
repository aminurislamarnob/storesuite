( function ( $ ) {
	var storeSuiteLoader = {
		block: function ( $container, text ) {
			text = text || 'Processing...';
			if (
				$container.find( '.storesuite-loader-overlay' ).length === 0
			) {
				$container.append(
					'<div class="storesuite-loader-overlay">' +
						'<span class="storesuite-loader-spinner"></span>' +
						'<span class="storesuite-loader-text">' +
						text +
						'</span>' +
						'</div>'
				);
			} else {
				$container.find( '.storesuite-loader-text' ).text( text );
			}

			setTimeout( function () {
				$container
					.find( '.storesuite-loader-overlay' )
					.addClass( 'active' );
			}, 10 );
		},
		unblock: function ( $container ) {
			$container
				.find( '.storesuite-loader-overlay' )
				.removeClass( 'active' );
		},
	};

	/**
	 * Resolve a usable image URL from a wp.media attachment.
	 *
	 * Not every attachment has a generated `thumbnail` size (small images,
	 * SVG/GIF, or sizes not yet regenerated), so fall back through medium and
	 * full to the original URL. Accessing `sizes.thumbnail.url` directly throws
	 * when the size is missing, which aborts the select handler and leaves no
	 * image rendered.
	 *
	 * @param {Object} attachment wp.media attachment JSON.
	 * @return {string} Best-available image URL.
	 */
	function storeSuiteAttachmentImageUrl( attachment ) {
		if ( ! attachment ) {
			return '';
		}
		var sizes = attachment.sizes || {};
		if ( sizes.thumbnail && sizes.thumbnail.url ) {
			return sizes.thumbnail.url;
		}
		if ( sizes.medium && sizes.medium.url ) {
			return sizes.medium.url;
		}
		if ( sizes.full && sizes.full.url ) {
			return sizes.full.url;
		}
		return attachment.url || '';
	}

	/**
	 * Overlay modal helpers: focus return, Escape, Tab cycle, backdrop and close buttons.
	 * Expects the root overlay to use the hidden attribute when closed.
	 * Pass initOverlay { fade: true } and matching CSS (see .storesuite-modal-fade) for opacity transitions.
	 */
	var storeSuiteModal = {
		fadeCloseFallbackMs: 350,

		focusableSelector:
			'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',

		/**
		 * @param {jQuery} $overlay      Root overlay element.
		 * @param {string} dialogSelector Selector for the dialog region (default [role="dialog"]).
		 * @return {jQuery}
		 */
		getFocusables: function ( $overlay, dialogSelector ) {
			dialogSelector = dialogSelector || '[role="dialog"]';
			return $overlay
				.find( dialogSelector )
				.find( this.focusableSelector )
				.filter( ':visible' );
		},

		isOpen: function ( $overlay ) {
			return ! $overlay.prop( 'hidden' );
		},

		clearCloseTransition: function ( $overlay ) {
			var timer = $overlay.data( 'storesuite-modal-close-timer' );
			if ( timer ) {
				clearTimeout( timer );
				$overlay.removeData( 'storesuite-modal-close-timer' );
			}
			$overlay.off( 'transitionend.storesuiteModalClose' );
		},

		/**
		 * @param {jQuery} $overlay
		 * @param {object} [options]
		 * @param {string} [options.dialogSelector]
		 */
		open: function ( $overlay, options ) {
			options = options || {};
			var dialogSelector = options.dialogSelector || '[role="dialog"]';
			var self = this;
			var fade = $overlay.data( 'storesuite-modal-fade' );

			this.clearCloseTransition( $overlay );
			$overlay.removeData( 'storesuite-modal-closing' );

			$overlay.data(
				'storesuite-modal-prev-focus',
				document.activeElement
			);
			$overlay.prop( 'hidden', false ).attr( 'aria-hidden', 'false' );

			var focusFirst = function () {
				var $first = self
					.getFocusables( $overlay, dialogSelector )
					.first();
				if ( $first.length ) {
					$first.trigger( 'focus' );
					return;
				}
				var $dialog = $overlay.find( dialogSelector ).first();
				if ( $dialog.length ) {
					$dialog.trigger( 'focus' );
				}
			};

			if ( fade && $overlay[ 0 ] ) {
				window.requestAnimationFrame( function () {
					window.requestAnimationFrame( focusFirst );
				} );
			} else {
				focusFirst();
			}
		},

		close: function ( $overlay ) {
			var fade = $overlay.data( 'storesuite-modal-fade' );
			var prev = $overlay.data( 'storesuite-modal-prev-focus' );
			var self = this;

			var restoreAndCleanup = function () {
				$overlay.removeData( 'storesuite-modal-prev-focus' );
				$overlay.removeData( 'storesuite-modal-closing' );
				if ( prev && typeof prev.focus === 'function' ) {
					prev.focus();
				}
			};

			if ( ! fade ) {
				$overlay.prop( 'hidden', true ).attr( 'aria-hidden', 'true' );
				restoreAndCleanup();
				return;
			}

			if ( $overlay.prop( 'hidden' ) ) {
				this.clearCloseTransition( $overlay );
				restoreAndCleanup();
				return;
			}

			if ( $overlay.data( 'storesuite-modal-closing' ) ) {
				return;
			}
			$overlay.data( 'storesuite-modal-closing', true );

			var el = $overlay[ 0 ];
			var finished = false;
			var done = function () {
				if ( finished ) {
					return;
				}
				finished = true;
				self.clearCloseTransition( $overlay );
				restoreAndCleanup();
			};

			$overlay.attr( 'aria-hidden', 'true' );
			$overlay.prop( 'hidden', true );

			$overlay.one( 'transitionend.storesuiteModalClose', function ( e ) {
				if ( e.target !== el ) {
					return;
				}
				done();
			} );

			var timer = setTimeout( done, self.fadeCloseFallbackMs );
			$overlay.data( 'storesuite-modal-close-timer', timer );
		},

		/**
		 * One-time bind per overlay: Escape, Tab trap, backdrop click, close/cancel clicks.
		 *
		 * @param {jQuery} $overlay
		 * @param {object} [options]
		 * @param {string} [options.dialogSelector]
		 * @param {string} [options.closeSelector] Delegated selector for close controls.
		 * @param {boolean} [options.fade] Opacity fade (requires .storesuite-modal-fade CSS on overlay).
		 * @param {boolean} [options.closeOnOverlayClick] Close when the backdrop is clicked. Defaults to true.
		 */
		initOverlay: function ( $overlay, options ) {
			options = options || {};
			var dialogSelector = options.dialogSelector || '[role="dialog"]';
			var closeSelector =
				options.closeSelector ||
				'.storesuite-modal-cancel, .storesuite-modal-close';
			var closeOnOverlayClick = options.closeOnOverlayClick !== false;

			if ( $overlay.data( 'storesuite-modal-a11y-bound' ) ) {
				return;
			}
			$overlay.data( 'storesuite-modal-a11y-bound', true );

			if ( options.fade ) {
				$overlay.data( 'storesuite-modal-fade', true );
				$overlay.addClass( 'storesuite-modal-fade' );
			}

			var self = this;

			$overlay.on( 'keydown.storesuiteModal', function ( e ) {
				if ( ! self.isOpen( $overlay ) ) {
					return;
				}

				if ( e.key === 'Escape' ) {
					e.preventDefault();
					self.close( $overlay );
					return;
				}

				if ( e.key !== 'Tab' ) {
					return;
				}

				var $focusable = self.getFocusables( $overlay, dialogSelector );
				if ( $focusable.length < 2 ) {
					return;
				}

				var first = $focusable[ 0 ];
				var last = $focusable[ $focusable.length - 1 ];

				if ( e.shiftKey && document.activeElement === first ) {
					e.preventDefault();
					last.focus();
				} else if ( ! e.shiftKey && document.activeElement === last ) {
					e.preventDefault();
					first.focus();
				}
			} );

			if ( closeOnOverlayClick ) {
				$overlay.on( 'click.storesuiteModal', function ( e ) {
					if ( e.target === $overlay[ 0 ] ) {
						self.close( $overlay );
					}
				} );
			}

			$overlay.on(
				'click.storesuiteModal',
				closeSelector,
				function ( e ) {
					e.preventDefault();
					self.close( $overlay );
				}
			);
		},
	};

	// Expose for other StoreSuite scripts
	window.StoreSuite = window.StoreSuite || {};
	window.StoreSuite.storeSuiteLoader = storeSuiteLoader;
	window.StoreSuite.storeSuiteModal = storeSuiteModal;

	var StoreFrontCommonConfig = {
		init: function () {
			this.bindEvents();
		},
		bindEvents: function () {
			this.uploadProductImage(); // Upload product image
			this.initAccountTabs(); // Account settings sidebar tabs
			this.uploadAccountAvatar(); // Upload account profile picture
			this.uploadProductGallaryImages(); // Upload product gallery images
			this.removeGalleryImage(); // Remove gallery image
			this.uploadCategoryImage(); // Upload category image
			this.uploadBrandImage(); // Upload brand image
			this.handleFilterOffcanvas(); // Handle filter off-canvas
			this.handleOrderFilterOffcanvas(); // Handle order filter off-canvas
			this.handleBulkActionCheckbox(); // Handle bulk action checkbox
			this.handleSearchToggle(); // Toggle the search box on mobile
			this.handlePrintDocument(); // Print PDF-invoice plugin documents
		},
		/**
		 * Print an invoice-plugin document instead of navigating to it.
		 *
		 * The document endpoint returns printable HTML, so it is loaded into a
		 * hidden iframe and printed from there, leaving the dashboard in place.
		 */
		handlePrintDocument: function () {
			$( document ).on(
				'click',
				'.storesuite-print-document',
				function ( event ) {
					event.preventDefault();

					var url = $( this ).attr( 'href' );
					if ( ! url ) {
						return;
					}

					$.get( url )
						.done( function ( html, status, xhr ) {
							var type = xhr.getResponseHeader( 'Content-Type' );

							// The endpoint reports failures as a plain-text body.
							if ( type && type.indexOf( 'text/plain' ) !== -1 ) {
								window.open( url, '_blank' );
								return;
							}

							var iframe = document.createElement( 'iframe' );
							iframe.style.display = 'none';
							document.body.appendChild( iframe );

							iframe.contentWindow.addEventListener(
								'afterprint',
								function () {
									iframe.remove();
								}
							);

							var doc = iframe.contentWindow.document;
							doc.open();
							doc.write( html );
							doc.close();

							// Give the document a tick to lay out its styles and images.
							setTimeout( function () {
								iframe.contentWindow.focus();
								iframe.contentWindow.print();
							}, 500 );
						} )
						.fail( function () {
							window.open( url, '_blank' );
						} );
				}
			);
		},
		handleSearchToggle: function () {
			var searchToggle = $( '#storesuite-search-toggle' );
			var toolbar = $( '.storesuite-products-toolbar' );

			searchToggle.on( 'click', function ( event ) {
				event.preventDefault();
				var isOpen = toolbar
					.toggleClass( 'storesuite-search-open' )
					.hasClass( 'storesuite-search-open' );
				searchToggle.attr(
					'aria-expanded',
					isOpen ? 'true' : 'false'
				);
				if ( isOpen ) {
					toolbar.find( '#search_by' ).trigger( 'focus' );
				}
			} );
		},
		handleBulkActionCheckbox: function () {
			// One delegated select-all implementation shared by every list
			// table (products, orders, coupons, taxonomy lists) via the
			// .storesuite-bulk-select-all / .storesuite-bulk-cb classes from
			// templates/shared/list-bulk-checkbox.php. Delegation keeps rows
			// working after an AJAX row swap; scoping is per table.
			$( document ).on( 'change', '.storesuite-bulk-select-all', function () {
				var isChecked = $( this ).prop( 'checked' );
				$( this )
					.closest( 'table' )
					.find( '.storesuite-bulk-cb' )
					.prop( 'checked', isChecked );
				$( this ).prop( 'indeterminate', false );
			} );

			$( document ).on( 'change', '.storesuite-bulk-cb', function () {
				var $table = $( this ).closest( 'table' );
				var total = $table.find( '.storesuite-bulk-cb' ).length;
				var checked = $table.find( '.storesuite-bulk-cb:checked' ).length;
				$table
					.find( '.storesuite-bulk-select-all' )
					.prop( 'checked', total > 0 && total === checked )
					.prop( 'indeterminate', checked > 0 && checked < total );
			} );
		},
		uploadProductImage: function () {
			$( '#product-single-image' ).click( function ( event ) {
				event.preventDefault();

				var image_id = $( '#product_thumbnail_url' ).val();
				var targetContainer = $( this );

				if ( image_id && image_id.length > 0 ) {
					$( '#product_thumbnail_id' ).val( '' );
					$( '#product_thumbnail_url' ).val( '' );
					$( '#product_thumb_img' ).html( '' );
					$( targetContainer )
						.find( '.image-drop-text span' )
						.text( storeSuiteFrontScript.upload_image_text );
					$( targetContainer ).removeClass( 'image-drop-bg' );
					// Notify the dirty-state tracker (sticky "Unsaved Changes" bar).
					$( '#product_thumbnail_id' ).trigger( 'change' );
				} else {
					// If the media frame already exists, reopen it.
					if ( frame ) {
						frame.open();
						return false;
					}

					// Create a new media frame
					var frame = wp.media( {
						title: storeSuiteFrontScript.upload_product_image,
						button: {
							text: storeSuiteFrontScript.insert_image,
						},
						multiple: false,
					} );

					frame.on( 'select', function () {
						var attachment = frame
							.state()
							.get( 'selection' )
							.first()
							.toJSON();

						// Send the attachment id to our hidden input
						$( '#product_thumbnail_id' ).val( attachment.id );

						// Send the attachment URL to our custom image input field.
						$( '#product_thumb_img' ).html(
							'<img src="' +
								storeSuiteAttachmentImageUrl( attachment ) +
								'" alt="' +
								storeSuiteFrontScript.product_image +
								'"/>'
						);
						$( '#product_thumbnail_url' ).val(
							storeSuiteAttachmentImageUrl( attachment )
						);

						//add class to hide text normaly
						$( targetContainer ).addClass( 'image-drop-bg' );
						$( '#product-single-image .image-drop-text span' ).text(
							storeSuiteFrontScript.remove_image_text
						);
						// Notify the dirty-state tracker (sticky "Unsaved Changes" bar).
						$( '#product_thumbnail_id' ).trigger( 'change' );
					} );

					frame.open();
				}
			} );
		},
		initAccountTabs: function () {
			// If the address tab is the active one on load, its selects are
			// already visible, so WooCommerce enhances them natively on ready.
			var addressEnhanced = $(
				'.storesuite-account-panel[data-account-panel="address"].is-active'
			).length
				? true
				: false;

			$( document ).on(
				'click',
				'.storesuite-account-nav-link[data-account-tab]',
				function ( event ) {
					event.preventDefault();
					var tab = $( this ).data( 'account-tab' );

					$( '.storesuite-account-nav-link' ).removeClass(
						'is-active'
					);
					$( this ).addClass( 'is-active' );

					$( '.storesuite-account-panel' ).removeClass( 'is-active' );
					$(
						'.storesuite-account-panel[data-account-panel="' +
							tab +
							'"]'
					).addClass( 'is-active' );

					// Reflect the active tab in the URL (?tab=...).
					if ( window.history && window.history.replaceState ) {
						var url = new URL( window.location.href );
						url.searchParams.set( 'tab', tab );
						window.history.replaceState( {}, '', url.toString() );
					}

					// WooCommerce's country-select only enhances visible
					// selects; the Address tab is hidden on load, so enhance
					// its country/state dropdowns the first time it is shown.
					if ( 'address' === tab && ! addressEnhanced ) {
						addressEnhanced = true;
						$( document.body ).trigger(
							'country_to_state_changed'
						);
					}
				}
			);

			// Copy billing address values into the shipping fields.
			$( document ).on(
				'click',
				'.storesuite-copy-billing',
				function ( event ) {
					event.preventDefault();

					$( '[name^="billing_"]' ).each( function () {
						var $billing = $( this );
						var shippingName = $billing
							.attr( 'name' )
							.replace( /^billing_/, 'shipping_' );
						var $shipping = $( '[name="' + shippingName + '"]' );

						if ( ! $shipping.length ) {
							return;
						}

						$shipping.val( $billing.val() );
						// Refresh selectWoo-enhanced country/state dropdowns.
						if ( $shipping.is( 'select' ) ) {
							$shipping.trigger( 'change' );
						}
					} );
				}
			);
		},
		uploadAccountAvatar: function () {
			var avatarFrame;

			// Open the media library to choose a profile picture.
			$( document ).on(
				'click',
				'.storesuite-account-avatar-upload',
				function ( event ) {
					event.preventDefault();

					if ( avatarFrame ) {
						avatarFrame.open();
						return;
					}

					avatarFrame = wp.media( {
						title:
							storeSuiteFrontScript.upload_profile_picture ||
							'Upload Profile Picture',
						button: {
							text: storeSuiteFrontScript.insert_image,
						},
						multiple: false,
						library: { type: 'image' },
					} );

					avatarFrame.on( 'select', function () {
						var attachment = avatarFrame
							.state()
							.get( 'selection' )
							.first()
							.toJSON();
						var url = storeSuiteAttachmentImageUrl( attachment );
						var $wrap = $( '#storesuite-account-avatar' );

						$( '#account_profile_picture_id' ).val( attachment.id );
						$wrap
							.addClass( 'has-image' )
							.find( '.storesuite-account-avatar-preview img' )
							.attr( 'src', url );
						$wrap
							.find( '.storesuite-account-avatar-remove' )
							.removeClass( 'storesuite-hidden' );
						// Notify the dirty-state tracker (sticky save bar).
						$( '#account_profile_picture_id' ).trigger( 'change' );
					} );

					avatarFrame.open();
				}
			);

			// Clear the selected picture (revert to the default avatar).
			$( document ).on(
				'click',
				'.storesuite-account-avatar-remove',
				function ( event ) {
					event.preventDefault();

					var $wrap = $( '#storesuite-account-avatar' );
					$( '#account_profile_picture_id' ).val( '' );
					$wrap
						.removeClass( 'has-image' )
						.find( '.storesuite-account-avatar-preview img' )
						.attr(
							'src',
							storeSuiteFrontScript.default_avatar_url || ''
						);
					$( this ).addClass( 'storesuite-hidden' );
					// Notify the dirty-state tracker (sticky save bar).
					$( '#account_profile_picture_id' ).trigger( 'change' );
				}
			);
		},
		uploadProductGallaryImages: function () {
			$( '#product-gallery-images' ).click( function ( event ) {
				event.preventDefault();

				var targetContainer = $( this );
				// If the media frame already exists, reopen it.
				if ( gframe ) {
					gframe.open();
					return false;
				}

				// Create a new media frame
				var gframe = wp.media( {
					title: storeSuiteFrontScript.upload_gallery_images,
					button: {
						text: storeSuiteFrontScript.insert_image,
					},
					multiple: true,
				} );

				gframe.on( 'select', function () {
					$( '#product_gallery_img' ).html();
					var galleryImageIds = $.map(
						( $( '#product_image_gallery' ).val() || '' ).split(
							','
						),
						function ( imageId ) {
							imageId = $.trim( imageId );
							return imageId ? imageId : null;
						}
					);

					var galleryImageUrls = $.map(
						( $( '#product_image_gallery_url' ).val() || '' ).split(
							','
						),
						function ( imageUrl ) {
							imageUrl = $.trim( imageUrl );
							return imageUrl ? imageUrl : null;
						}
					);
					var attachments = gframe
						.state()
						.get( 'selection' )
						.toJSON();

					for ( var singleItem in attachments ) {
						var attachment = attachments[ singleItem ];
						if (
							$.inArray(
								String( attachment.id ),
								galleryImageIds
							) === -1
						) {
							galleryImageIds.push( String( attachment.id ) );
							galleryImageUrls.push(
								storeSuiteAttachmentImageUrl( attachment )
							);
							$( '#product_gallery_img' ).append(
								'<div class="preview-image-box"><i class="las la-trash" data-id="' +
									attachment.id +
									'"></i><img src="' +
									storeSuiteAttachmentImageUrl( attachment ) +
									'" data-id="' +
									attachment.id +
									'" alt="' +
									storeSuiteFrontScript.product_gallery_image +
									'"/></div>'
							);
						}
					}
					// Send the attachment ids to our hidden input
					$( '#product_image_gallery' ).val(
						galleryImageIds.join( ',' )
					);
					$( '#product_image_gallery_url' ).val(
						galleryImageUrls.join( ',' )
					);
					// Notify the dirty-state tracker (sticky "Unsaved Changes" bar).
					$( '#product_image_gallery' ).trigger( 'change' );

					//add class to hide text normaly
					$( targetContainer ).addClass(
						'sm-gallery-image-uploader'
					);
					$( '.product-gallery-images-wrapper' ).removeClass(
						'gallery-has-no-image'
					);
				} );

				gframe.open();
			} );
		},
		removeGalleryImage: function () {
			$( document ).on(
				'click',
				'.remove-gallery-image',
				function ( event ) {
					event.preventDefault();
					var imageId = String( $( this ).data( 'id' ) );

					var ids = $.map(
						( $( '#product_image_gallery' ).val() || '' ).split(
							','
						),
						function ( id ) {
							id = $.trim( id );
							return id ? id : null;
						}
					);
					var urls = $.map(
						( $( '#product_image_gallery_url' ).val() || '' ).split(
							','
						),
						function ( url ) {
							url = $.trim( url );
							return url ? url : null;
						}
					);

					// Remove the matching id and its parallel url by index.
					var idx = $.inArray( imageId, ids );
					if ( idx !== -1 ) {
						ids.splice( idx, 1 );
						if ( idx < urls.length ) {
							urls.splice( idx, 1 );
						}
					}

					$( '#product_image_gallery' ).val( ids.join( ',' ) );
					$( '#product_image_gallery_url' ).val( urls.join( ',' ) );
					// Notify the dirty-state tracker (sticky "Unsaved Changes" bar).
					$( '#product_image_gallery' ).trigger( 'change' );
					$( this ).closest( '.preview-image-box' ).remove();
				}
			);
		},
		uploadCategoryImage: function () {
			$( '#category-single-image' ).click( function ( event ) {
				event.preventDefault();

				var image_id = $( '#product_category_thumbnail_url' ).val();
				var targetContainer = $( this );

				if ( image_id && image_id.length > 0 ) {
					$( '#product_category_thumbnail_id' ).val( '' );
					$( '#product_category_thumbnail_url' ).val( '' );
					$( '#category_thumb_img' ).html( '' );
					$( targetContainer )
						.find( '.image-drop-text span' )
						.text( storeSuiteFrontScript.upload_image_text );
					$( targetContainer ).removeClass( 'image-drop-bg' );
				} else {
					// If the media frame already exists, reopen it.
					if ( frame ) {
						frame.open();
						return false;
					}

					// Create a new media frame
					var frame = wp.media( {
						title: storeSuiteFrontScript.upload_category_image,
						button: {
							text: storeSuiteFrontScript.insert_image,
						},
						multiple: false,
					} );

					frame.on( 'select', function () {
						var attachment = frame
							.state()
							.get( 'selection' )
							.first()
							.toJSON();

						// Send the attachment id to our hidden input
						$( '#product_category_thumbnail_id' ).val(
							attachment.id
						);

						// Send the attachment URL to our custom image input field.
						$( '#category_thumb_img' ).html(
							'<img src="' +
								storeSuiteAttachmentImageUrl( attachment ) +
								'" alt="' +
								storeSuiteFrontScript.category_image +
								'"/>'
						);
						$( '#product_category_thumbnail_url' ).val(
							storeSuiteAttachmentImageUrl( attachment )
						);

						//add class to hide text normaly
						$( targetContainer ).addClass( 'image-drop-bg' );
						$(
							'#category-single-image .image-drop-text span'
						).text( storeSuiteFrontScript.remove_image_text );
					} );

					frame.open();
				}
			} );
		},
		uploadBrandImage: function () {
			$( '#brand-single-image' ).click( function ( event ) {
				event.preventDefault();

				var image_id = $( '#product_brand_thumbnail_url' ).val();
				var targetContainer = $( this );

				if ( image_id && image_id.length > 0 ) {
					$( '#product_brand_thumbnail_id' ).val( '' );
					$( '#product_brand_thumbnail_url' ).val( '' );
					$( '#brand_thumb_img' ).html( '' );
					$( targetContainer )
						.find( '.image-drop-text span' )
						.text( storeSuiteFrontScript.upload_image_text );
					$( targetContainer ).removeClass( 'image-drop-bg' );
				} else {
					// If the media frame already exists, reopen it.
					if ( frame ) {
						frame.open();
						return false;
					}

					// Create a new media frame
					var frame = wp.media( {
						title: storeSuiteFrontScript.upload_brand_image,
						button: {
							text: storeSuiteFrontScript.insert_image,
						},
						multiple: false,
					} );

					frame.on( 'select', function () {
						var attachment = frame
							.state()
							.get( 'selection' )
							.first()
							.toJSON();

						// Send the attachment id to our hidden input
						$( '#product_brand_thumbnail_id' ).val( attachment.id );

						// Send the attachment URL to our custom image input field.
						$( '#brand_thumb_img' ).html(
							'<img src="' +
								storeSuiteAttachmentImageUrl( attachment ) +
								'" alt="' +
								storeSuiteFrontScript.brand_image +
								'"/>'
						);
						$( '#product_brand_thumbnail_url' ).val(
							storeSuiteAttachmentImageUrl( attachment )
						);

						//add class to hide text normaly
						$( targetContainer ).addClass( 'image-drop-bg' );
						$( '#brand-single-image .image-drop-text span' ).text(
							storeSuiteFrontScript.remove_image_text
						);
					} );

					frame.open();
				}
			} );
		},
		handleFilterOffcanvas: function () {
			var filterToggle = $( '#storesuite-filter-toggle' );
			var filterOffcanvas = $( '#storesuite-filter-offcanvas' );
			var filterClose = $( '#storesuite-filter-close' );
			var filterOverlay = $( '#storesuite-filter-overlay' );

			// Open off-canvas when filter button is clicked
			filterToggle.on( 'click', function ( event ) {
				event.preventDefault();
				filterOffcanvas.addClass( 'active' );
				filterOverlay.addClass( 'active' );
				$( 'body' ).css( 'overflow', 'hidden' );
			} );

			// Close off-canvas when close button is clicked
			filterClose.on( 'click', function ( event ) {
				event.preventDefault();
				filterOffcanvas.removeClass( 'active' );
				filterOverlay.removeClass( 'active' );
				$( 'body' ).css( 'overflow', 'auto' );
			} );

			// Close off-canvas when overlay is clicked
			filterOverlay.on( 'click', function () {
				filterOffcanvas.removeClass( 'active' );
				filterOverlay.removeClass( 'active' );
				$( 'body' ).css( 'overflow', 'auto' );
			} );

			// Close off-canvas on Escape key
			$( document ).on( 'keydown', function ( event ) {
				if ( event.key === 'Escape' ) {
					filterOffcanvas.removeClass( 'active' );
					filterOverlay.removeClass( 'active' );
					$( 'body' ).css( 'overflow', 'auto' );
				}
			} );
		},
		handleOrderFilterOffcanvas: function () {
			var orderFilterToggle = $( '#storesuite-order-filter-toggle, #storesuite-order-filter-toggle-title' );
			var orderFilterOffcanvas = $(
				'#storesuite-order-filter-offcanvas'
			);
			var orderFilterClose = $( '#storesuite-order-filter-close' );
			var orderFilterOverlay = $( '#storesuite-order-filter-overlay' );

			// Open off-canvas when filter button is clicked
			orderFilterToggle.on( 'click', function ( event ) {
				event.preventDefault();
				orderFilterOffcanvas.addClass( 'active' );
				orderFilterOverlay.addClass( 'active' );
				$( 'body' ).css( 'overflow', 'hidden' );
			} );

			// Close off-canvas when close button is clicked
			orderFilterClose.on( 'click', function ( event ) {
				event.preventDefault();
				orderFilterOffcanvas.removeClass( 'active' );
				orderFilterOverlay.removeClass( 'active' );
				$( 'body' ).css( 'overflow', 'auto' );
			} );

			// Close off-canvas when overlay is clicked
			orderFilterOverlay.on( 'click', function () {
				orderFilterOffcanvas.removeClass( 'active' );
				orderFilterOverlay.removeClass( 'active' );
				$( 'body' ).css( 'overflow', 'auto' );
			} );

			// Close off-canvas on Escape key
			$( document ).on( 'keydown', function ( event ) {
				if ( event.key === 'Escape' ) {
					orderFilterOffcanvas.removeClass( 'active' );
					orderFilterOverlay.removeClass( 'active' );
					$( 'body' ).css( 'overflow', 'auto' );
				}
			} );
		},
	};
	StoreFrontCommonConfig.init();
} )( jQuery );
