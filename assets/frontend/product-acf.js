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
			this.initConditionalLogic( $groups );
		},

		/**
		 * ACF conditional logic. Each field wrapper carries its rules as
		 * `data-conditions` (OR-groups of AND-rules, {field, operator, value}).
		 * Rules are evaluated on load and whenever any field changes; hidden
		 * fields get their inputs disabled so they drop out of the request
		 * and the server leaves their stored values alone.
		 */
		initConditionalLogic: function ( $groups ) {
			var self = this;
			var $conditional = $groups.find( '[data-conditions]' );

			if ( ! $conditional.length ) {
				return;
			}

			this.$conditionalFields = $conditional;
			this.$allFields = $groups.find( '.storesuite-acf-field' );

			var evaluate = function () {
				self.evaluateConditions();
			};

			$groups.on( 'change input', ':input', evaluate );

			// WYSIWYG controllers write to their textarea only on save; listen
			// to the editors directly.
			$( document ).on( 'tinymce-editor-init', function ( event, editor ) {
				if ( $groups.find( '#' + editor.id ).length ) {
					editor.on( 'change keyup', evaluate );
				}
			} );

			this.evaluateConditions();
		},

		evaluateConditions: function () {
			var self = this;
			var changed = true;
			var passes = 0;

			// A controller may itself be conditional; loop until nothing flips.
			while ( changed && passes < 10 ) {
				changed = false;
				passes++;

				this.$conditionalFields.each( function () {
					var $field = $( this );
					var visible = self.conditionsPass( $field.data( 'conditions' ) );
					var wasHidden = $field.hasClass( 'storesuite-acf-hidden' );

					if ( visible === wasHidden ) {
						self.setFieldVisible( $field, visible );
						changed = true;
					}
				} );
			}
		},

		setFieldVisible: function ( $field, visible ) {
			$field
				.toggleClass( 'storesuite-acf-hidden', ! visible )
				.attr( 'aria-hidden', visible ? null : 'true' )
				.find( ':input' )
				.prop( 'disabled', ! visible );
		},

		/**
		 * OR across groups, AND within a group (ACF semantics).
		 */
		conditionsPass: function ( groups ) {
			var self = this;

			if ( ! Array.isArray( groups ) || ! groups.length ) {
				return true;
			}

			return groups.some( function ( rules ) {
				return rules.every( function ( rule ) {
					return self.rulePasses( rule );
				} );
			} );
		},

		rulePasses: function ( rule ) {
			var $controller = this.$allFields.filter(
				'[data-key="' + rule.field + '"]'
			);

			if ( ! $controller.length ) {
				return false;
			}

			// A hidden controller counts as having no value (mirrors ACF).
			var value = $controller.hasClass( 'storesuite-acf-hidden' )
				? ''
				: this.readFieldValue( $controller );
			var isArray = Array.isArray( value );
			var text = isArray ? value.join( ',' ) : String( value );

			switch ( rule.operator ) {
				case '==':
					return isArray
						? value.some( function ( v ) {
								return this.looselyEqual( v, rule.value );
						  }, this )
						: this.looselyEqual( value, rule.value );
				case '!=':
					return ! this.rulePasses( {
						field: rule.field,
						operator: '==',
						value: rule.value,
					} );
				case '==empty':
					return this.isEmptyValue( value );
				case '!=empty':
					return ! this.isEmptyValue( value );
				case '==contains':
					return isArray
						? value.indexOf( rule.value ) !== -1
						: text.indexOf( rule.value ) !== -1;
				case '==pattern':
					try {
						return new RegExp( rule.value ).test( text );
					} catch ( e ) {
						return false;
					}
				case '>':
					return isArray
						? value.length > parseFloat( rule.value )
						: parseFloat( value ) > parseFloat( rule.value );
				case '<':
					return isArray
						? value.length < parseFloat( rule.value )
						: parseFloat( value ) < parseFloat( rule.value );
			}

			return false;
		},

		looselyEqual: function ( a, b ) {
			var na = parseFloat( a );
			var nb = parseFloat( b );

			if (
				! isNaN( na ) &&
				! isNaN( nb ) &&
				String( a ).trim() !== '' &&
				String( b ).trim() !== ''
			) {
				return na === nb;
			}

			return String( a ) === String( b );
		},

		isEmptyValue: function ( value ) {
			if ( Array.isArray( value ) ) {
				return ! value.length;
			}

			return value === '' || value === null || value === undefined;
		},

		/**
		 * Current value of a field wrapper, shaped per type: arrays for
		 * multi-value fields, '1' / '' for the switch, the stored value for
		 * placeholders (which have no input).
		 */
		readFieldValue: function ( $field ) {
			var type = $field.data( 'type' );

			if ( $field.is( '[data-value]' ) ) {
				var stored = $field.data( 'value' );
				return stored === null || stored === undefined ? '' : stored;
			}

			switch ( type ) {
				case 'true_false':
					return $field.find( 'input[type="checkbox"]' ).is( ':checked' )
						? '1'
						: '';
				case 'checkbox':
					return $field
						.find( 'input[type="checkbox"]:checked' )
						.map( function () {
							return this.value;
						} )
						.get();
				case 'radio':
				case 'button_group':
					return $field.find( 'input[type="radio"]:checked' ).val() || '';
				case 'select':
					var selected = $field.find( 'select' ).val();
					if ( Array.isArray( selected ) ) {
						return selected;
					}
					return selected === null || selected === undefined
						? ''
						: selected;
				case 'wysiwyg':
					var $textarea = $field.find( 'textarea' );
					if (
						typeof tinyMCE !== 'undefined' &&
						tinyMCE.get( $textarea.attr( 'id' ) )
					) {
						return tinyMCE.get( $textarea.attr( 'id' ) ).getContent();
					}
					return $textarea.val() || '';
			}

			// Text-like inputs (text, number, email, url, dates, colour, image id, …):
			// the last named, non-hidden control wins so sentinels are skipped.
			var $inputs = $field.find( ':input[name]' ).not( '[type="hidden"]' );
			if ( ! $inputs.length ) {
				$inputs = $field.find( ':input[name]' );
			}

			var val = $inputs.last().val();
			return val === null || val === undefined ? '' : val;
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
