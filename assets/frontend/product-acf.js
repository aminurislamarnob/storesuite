/**
 * ACF field behaviour on the StoreSuite product add/edit form.
 *
 * Loaded only when Advanced Custom Fields is active and a product form is
 * rendered. Field-type specific behaviour (media picker, conditional logic,
 * date pickers) is added here as those types land.
 */
( function ( $ ) {
	'use strict';

	var StoreSuiteProductAcf = {
		init: function () {
			var $groups = $( '.storesuite-acf-field-group' );

			if ( ! $groups.length ) {
				return;
			}

			this.bindRangeOutput( $groups );
			this.bindColorPicker( $groups );
			this.initDatePickers( $groups );
			this.bindMediaPickers( $groups );
		},

		/**
		 * Generic single-image picker, driven by data attributes on the
		 * `[data-storesuite-media-picker]` wrapper: `data-target` (the hidden
		 * input holding the attachment id), `data-preview-size`,
		 * `data-mime-types` (library filter) and `data-title`. Any number of
		 * pickers per form work independently. Clicking a filled picker
		 * removes the image; clicking an empty one opens the media frame.
		 */
		bindMediaPickers: function ( $groups ) {
			var self = this;

			$groups.on( 'click keydown', '.storesuite-media-picker-drop', function ( event ) {
				if (
					event.type === 'keydown' &&
					event.key !== 'Enter' &&
					event.key !== ' '
				) {
					return;
				}
				event.preventDefault();

				var $drop = $( this );
				var $picker = $drop.closest( '[data-storesuite-media-picker]' );
				var $input = $( $picker.data( 'target' ) );

				if ( $input.val() ) {
					self.clearMediaPicker( $picker, $drop, $input );
					return;
				}

				self.openMediaFrame( $picker, $drop, $input );
			} );
		},

		clearMediaPicker: function ( $picker, $drop, $input ) {
			var i18n = window.storeSuiteFrontScript || {};

			$input.val( '' ).trigger( 'change' );
			$drop
				.removeClass( 'image-drop-bg' )
				.find( '.storesuite-media-picker-preview' )
				.empty();
			$drop
				.find( '.image-drop-text span' )
				.text( i18n.upload_image_text || 'Upload Image' );
		},

		openMediaFrame: function ( $picker, $drop, $input ) {
			if ( typeof wp === 'undefined' || ! wp.media ) {
				return;
			}

			var i18n = window.storeSuiteFrontScript || {};
			var frame = $picker.data( 'storesuiteMediaFrame' );

			if ( frame ) {
				frame.open();
				return;
			}

			var mimeTypes = String( $picker.data( 'mimeTypes' ) || '' )
				.split( ',' )
				.filter( Boolean );

			frame = wp.media( {
				title: $picker.data( 'title' ) || i18n.upload_product_image || '',
				button: { text: i18n.insert_image || 'Insert Image' },
				multiple: false,
				library: { type: mimeTypes.length ? mimeTypes : 'image' },
			} );

			frame.on( 'select', function () {
				var attachment = frame
					.state()
					.get( 'selection' )
					.first()
					.toJSON();
				var size = $picker.data( 'previewSize' );
				var sizes = attachment.sizes || {};
				var url =
					( size && sizes[ size ] && sizes[ size ].url ) ||
					( sizes.full && sizes.full.url ) ||
					attachment.url ||
					'';

				$input.val( attachment.id ).trigger( 'change' );
				$drop
					.addClass( 'image-drop-bg' )
					.find( '.storesuite-media-picker-preview' )
					.html(
						$( '<img>' ).attr( {
							src: url,
							alt: attachment.alt || '',
						} )
					);
				$drop
					.find( '.image-drop-text span' )
					.text( i18n.remove_image_text || 'Remove Image' );
			} );

			$picker.data( 'storesuiteMediaFrame', frame );
			frame.open();
		},

		/**
		 * jQuery UI datepicker on date fields, configured like the sale-price
		 * date fields (ISO output, one month, button panel).
		 */
		initDatePickers: function ( $groups ) {
			if ( typeof $.fn.datepicker !== 'function' ) {
				return;
			}

			$groups.find( '.storesuite-acf-datepicker' ).datepicker( {
				defaultDate: '',
				dateFormat: 'yy-mm-dd',
				numberOfMonths: 1,
				showButtonPanel: true,
				onSelect: function () {
					$( this ).trigger( 'change' );
				},
			} );
		},

		/**
		 * Keep the read-out next to a range slider in sync with its value.
		 */
		bindRangeOutput: function ( $groups ) {
			$groups.on( 'input change', '.storesuite-acf-range-input', function () {
				$( this )
					.closest( '.storesuite-acf-range' )
					.find( '.storesuite-acf-range-output' )
					.text( this.value );
			} );
		},

		/**
		 * Two-way sync between the native colour swatch and the hex text box.
		 * The text box is the submitted input so the value can be left empty.
		 */
		bindColorPicker: function ( $groups ) {
			$groups.on( 'input', '.storesuite-acf-color-swatch', function () {
				$( $( this ).data( 'target' ) ).val( this.value ).trigger( 'change' );
			} );

			$groups.on( 'input change', '.storesuite-acf-color-text', function () {
				var hex = $.trim( this.value ).toLowerCase();
				var $swatch = $( this ).siblings( '.storesuite-acf-color-swatch' );

				if ( /^#[0-9a-f]{3}$/.test( hex ) ) {
					hex = '#' + hex[ 1 ] + hex[ 1 ] + hex[ 2 ] + hex[ 2 ] + hex[ 3 ] + hex[ 3 ];
				}

				if ( /^#[0-9a-f]{6}$/.test( hex ) ) {
					$swatch.val( hex );
				}
			} );
		},
	};

	$( function () {
		StoreSuiteProductAcf.init();
	} );
} )( jQuery );
