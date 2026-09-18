/* global jQuery, StoreSuite_ProductSeo, tinymce */
/**
 * Yoast SEO card on the StoreSuite product form.
 *
 *   - "Insert variable": a suggestion menu for Yoast snippet variables, opened
 *     from the button or by typing % in the SEO title / meta description.
 *   - Google preview: a live mobile/desktop search snippet built from the SEO
 *     fields and the product's title, permalink, descriptions and terms.
 *   - Progress bars using Yoast's limits (title width in px, description length).
 *   - Social tab image pickers. Only the attachment ID is submitted; the server
 *     looks the URL up itself.
 *
 * Reads the localized StoreSuite_ProductSeo global enqueued by YoastSeoIntegration.
 *
 * @param {Function} $ jQuery.
 */
( function ( $ ) {
	const StoreSuiteProductSeo = {
		VARIABLE_PATTERN: /%%([a-z_]+)%%/g,
		// A lone % (not part of a finished %%var%%) followed by the partial name being typed.
		TRIGGER_PATTERN: /(^|[^%])%([a-z_]*)$/,
		// Yoast measures the SEO title as Google renders it on desktop.
		TITLE_FONT: '20px Arial, sans-serif',

		init() {
			// The global's name is set by wp_localize_script() and follows the plugin's PHP naming.
			/* eslint-disable camelcase */
			this.config =
				typeof StoreSuite_ProductSeo !== 'undefined'
					? StoreSuite_ProductSeo
					: null;
			/* eslint-enable camelcase */
			this.$card = $( '#storesuite-yoast-seo' );

			if ( ! this.config || ! this.$card.length ) {
				return;
			}

			this.$title = $( '#storesuite_yoast_title' );
			this.$desc = $( '#storesuite_yoast_metadesc' );
			this.$keyphrase = $( '#storesuite_yoast_focuskw' );
			this.$preview = this.$card.find( '.storesuite-seo-preview' );
			this.$menu = null;
			this.$menuField = null;
			this.menuTrigger = null;

			this.bindTabs();
			this.bindImagePickers();
			this.bindPreview();
			this.bindVariableMenu();
			this.render();
		},

		/* ---------------------------------------------------------------
		 * Tabs (only rendered when the card has more than one)
		 * ------------------------------------------------------------- */

		bindTabs() {
			const self = this;

			this.$card.on( 'click', '.storesuite-seo-tab', function () {
				const tab = $( this ).attr( 'data-seo-tab' );

				self.closeMenu();
				self.$card
					.find( '.storesuite-seo-tab' )
					.removeClass( 'is-active' )
					.attr( 'aria-selected', 'false' );
				$( this )
					.addClass( 'is-active' )
					.attr( 'aria-selected', 'true' );
				self.$card.find( '.storesuite-seo-panel' ).each( function () {
					const active = $( this ).attr( 'data-seo-panel' ) === tab;
					$( this )
						.toggleClass( 'is-active', active )
						.prop( 'hidden', ! active );
				} );
			} );
		},

		/* ---------------------------------------------------------------
		 * Social image pickers
		 * ------------------------------------------------------------- */

		bindImagePickers() {
			const self = this;

			this.$card.on(
				'click',
				'.storesuite-seo-image-select',
				function () {
					if ( typeof wp === 'undefined' || ! wp.media ) {
						return;
					}

					const $picker = $( this ).closest(
						'.storesuite-seo-image'
					);
					const frame = wp.media( {
						library: { type: 'image' },
						multiple: false,
					} );

					frame.on( 'select', function () {
						const image = frame
							.state()
							.get( 'selection' )
							.first()
							.toJSON();
						const preview =
							( image.sizes &&
								( image.sizes.medium || image.sizes.full ) ) ||
							image;

						self.setImage( $picker, image.id, preview.url );
					} );
					frame.open();
				}
			);

			this.$card.on(
				'click',
				'.storesuite-seo-image-remove',
				function () {
					self.setImage(
						$( this ).closest( '.storesuite-seo-image' ),
						'',
						''
					);
				}
			);
		},

		setImage( $picker, id, url ) {
			const $select = $picker.find( '.storesuite-seo-image-select' );

			$picker.toggleClass( 'has-image', !! id );
			$picker
				.find( '.storesuite-seo-image-preview' )
				.empty()
				.append( url ? $( '<img>', { src: url, alt: '' } ) : null );
			$select.text(
				$select.attr( id ? 'data-replace-label' : 'data-select-label' )
			);
			// Lets the form's unsaved-changes bar react.
			$picker
				.find( 'input[type="hidden"]' )
				.val( id )
				.trigger( 'change' );
		},

		/* ---------------------------------------------------------------
		 * Live values from the product form
		 * ------------------------------------------------------------- */

		stripTags( html ) {
			const el = document.createElement( 'div' );
			el.innerHTML = html;
			return ( el.textContent || '' ).replace( /\s+/g, ' ' ).trim();
		},

		getDescriptionText() {
			const editor =
				typeof tinymce !== 'undefined'
					? tinymce.get( 'product_description' )
					: null;
			const html =
				editor && ! editor.isHidden()
					? editor.getContent()
					: $( '#product_description' ).val() || '';
			return this.stripTags( html );
		},

		getSelectedLabels( selector ) {
			return $( selector )
				.find( 'option:selected' )
				.map( function () {
					return $( this ).text().trim();
				} )
				.get();
		},

		slugify( text ) {
			return text
				.toLowerCase()
				.replace( /[^a-z0-9\s-]/g, '' )
				.trim()
				.replace( /[\s-]+/g, '-' );
		},

		getContext() {
			const title = ( $( '#product_title' ).val() || '' ).trim();
			const shortDesc = this.stripTags(
				$( '#product_short_description' ).val() || ''
			);
			const categories = this.getSelectedLabels( '#product_category' );
			let excerpt = shortDesc || this.getDescriptionText();

			if ( excerpt.length > this.config.descMaxLength ) {
				excerpt = excerpt
					.substring( 0, this.config.descMaxLength )
					.replace( /\s+\S*$/, '' );
			}

			return $.extend( {}, this.config.replacements, {
				title,
				excerpt,
				excerpt_only: shortDesc,
				category: categories.join( ', ' ),
				primary_category: categories[ 0 ] || '',
				tag: this.getSelectedLabels( '#product_tags' ).join( ', ' ),
				focuskw: ( this.$keyphrase.val() || '' ).trim(),
				id: $( 'input[name="product_id"]' ).val() || '',
			} );
		},

		/**
		 * Replace the variables the form can resolve. Unknown variables are
		 * left as typed; Yoast resolves them when the page is rendered.
		 *
		 * @param {string} text    Text containing %%variables%%.
		 * @param {Object} context Variable values keyed by name.
		 * @return {string} Text with the known variables replaced.
		 */
		replaceVariables( text, context ) {
			return text
				.replace( this.VARIABLE_PATTERN, function ( match, name ) {
					return Object.prototype.hasOwnProperty.call( context, name )
						? context[ name ]
						: match;
				} )
				.replace( /\s+/g, ' ' )
				.trim();
		},

		/**
		 * Yoast drops separators left dangling at either end when a variable
		 * resolves to nothing, e.g. "%%title%% %%sep%% %%sitename%%" with no title yet.
		 *
		 * @param {string} text Resolved title.
		 * @return {string} Title without leading or trailing separators.
		 */
		trimSeparators( text ) {
			const sep = this.config.replacements.sep;
			if ( ! sep ) {
				return text;
			}
			const escaped = sep.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
			return text
				.replace(
					new RegExp(
						'^(\\s*' +
							escaped +
							'\\s*)+|(\\s*' +
							escaped +
							'\\s*)+$',
						'g'
					),
					''
				)
				.trim();
		},

		/* ---------------------------------------------------------------
		 * Google preview + progress bars
		 * ------------------------------------------------------------- */

		bindPreview() {
			const self = this;
			const render = function () {
				self.render();
			};

			this.$card.on( 'input change', 'input, textarea', render );
			$( '#product_title, #product_slug, #product_short_description' ).on(
				'input change',
				render
			);
			$( '#product_category, #product_tags' ).on( 'change', render );

			// The description editor initialises after this script runs.
			$( window ).on( 'load', function () {
				const editor =
					typeof tinymce !== 'undefined'
						? tinymce.get( 'product_description' )
						: null;
				if ( editor ) {
					editor.on( 'keyup change', render );
				}
				render();
			} );
			$( '#product_description' ).on( 'input change', render );

			this.$card.on( 'click', '.storesuite-seo-mode-switch', function () {
				const desktop = self.$preview.attr( 'data-mode' ) !== 'desktop';
				self.$preview.attr(
					'data-mode',
					desktop ? 'desktop' : 'mobile'
				);
				$( this )
					.attr( 'aria-checked', desktop ? 'true' : 'false' )
					.attr(
						'aria-label',
						desktop
							? self.config.i18n.switchToMobile
							: self.config.i18n.switchToDesktop
					);
				self.render();
			} );
		},

		measureTitleWidth( text ) {
			if ( ! this.canvasContext ) {
				this.canvasContext = document
					.createElement( 'canvas' )
					.getContext( '2d' );
			}
			this.canvasContext.font = this.TITLE_FONT;
			return Math.round( this.canvasContext.measureText( text ).width );
		},

		getUrlParts() {
			const base =
				$( '.storesuite-permalink-prefix' ).first().text().trim() ||
				this.config.homeUrl;
			const slug =
				( $( '#product_slug' ).val() || '' ).trim() ||
				this.slugify( ( $( '#product_title' ).val() || '' ).trim() );
			const parts = base
				.replace( /^https?:\/\//, '' )
				.split( '/' )
				.filter( Boolean );

			if ( slug ) {
				parts.push( slug );
			}
			return parts;
		},

		escapeHtml( text ) {
			return $( '<div>' ).text( text ).html();
		},

		/**
		 * Bold the focus keyphrase words in the description, as Google does
		 * for the searcher's query.
		 *
		 * @param {string} text      Plain-text description.
		 * @param {string} keyphrase Focus keyphrase.
		 * @return {string} Escaped HTML with the keyphrase words wrapped in <strong>.
		 */
		highlightKeyphrase( text, keyphrase ) {
			const self = this;
			const words = keyphrase
				.split( /\s+/ )
				.filter( function ( word ) {
					return word.length > 1;
				} )
				.map( function ( word ) {
					return word.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
				} );

			if ( ! words.length ) {
				return this.escapeHtml( text );
			}

			// Split on the raw text and escape each piece, so a keyphrase can
			// never match inside an HTML entity.
			const pattern = new RegExp(
				'(?<![\\p{L}\\p{N}])(' +
					words.join( '|' ) +
					')(?![\\p{L}\\p{N}])',
				'giu'
			);

			return text
				.split( pattern )
				.map( function ( piece, index ) {
					const html = self.escapeHtml( piece );
					return index % 2 ? '<strong>' + html + '</strong>' : html;
				} )
				.join( '' );
		},

		setProgress( field, value, max, state ) {
			const $bar = this.$card.find(
				'[data-seo-field="' + field + '"] .storesuite-seo-progress'
			);
			$bar.attr( {
				'data-state': state,
				'aria-valuenow': value,
				'aria-valuemax': max,
			} );
			$bar.children( 'span' ).css(
				'width',
				Math.min( 100, ( value / max ) * 100 ) + '%'
			);
		},

		render() {
			const config = this.config;
			const context = this.getContext();
			const isCornerstone = $( '#storesuite_yoast_is_cornerstone' ).is(
				':checked'
			);
			let title = this.replaceVariables(
				( this.$title.val() || '' ).trim() || config.titleTemplate,
				context
			);
			title = this.trimSeparators( title );
			const desc = this.replaceVariables(
				( this.$desc.val() || '' ).trim() || config.descTemplate,
				context
			);
			const urlParts = this.getUrlParts();
			const isDesktop = this.$preview.attr( 'data-mode' ) === 'desktop';

			// Progress bars score what will be output, not the Google fallback below.
			const titleWidth = this.measureTitleWidth( title );
			this.setProgress(
				'title',
				titleWidth,
				config.titleMaxWidth,
				titleWidth > 0 && titleWidth <= config.titleMaxWidth
					? 'good'
					: 'bad'
			);

			let descState = 'good';
			if ( ! desc.length ) {
				descState = 'bad';
			} else if (
				desc.length < config.descMinLength ||
				desc.length > config.descMaxLength
			) {
				// Yoast holds cornerstone content to the stricter standard.
				descState = isCornerstone ? 'bad' : 'ok';
			}
			this.setProgress(
				'metadesc',
				desc.length,
				config.descMaxLength,
				descState
			);

			// Without a description Google picks text from the page itself.
			let shownDesc = desc || context.excerpt;
			const $desc = this.$preview.find( '.storesuite-seo-snippet-desc' );
			if ( shownDesc.length > config.descMaxLength ) {
				shownDesc =
					shownDesc
						.substring( 0, config.descMaxLength )
						.replace( /\s+\S*$/, '' ) + ' ...';
			}
			$desc.toggleClass( 'is-placeholder', ! shownDesc ).html(
				shownDesc
					? this.highlightKeyphrase(
							shownDesc,
							// Yoast only bolds the keyphrase in its desktop preview.
							isDesktop ? context.focuskw : ''
					  )
					: this.escapeHtml( config.i18n.descFallback )
			);

			this.$preview
				.find( '.storesuite-seo-snippet-title' )
				.toggleClass( 'is-placeholder', ! title )
				.text( title || config.i18n.titleFallback );
			this.$preview
				.find( '.storesuite-seo-snippet-sitename' )
				.text( config.replacements.sitename );
			this.$preview
				.find( '.storesuite-seo-snippet-url' )
				.text(
					isDesktop ? urlParts.join( ' › ' ) : urlParts[ 0 ] || ''
				);

			const $icon = this.$preview.find( '.storesuite-seo-snippet-icon' );
			if ( config.siteIcon && ! $icon.children().length ) {
				$icon.append( $( '<img>', { src: config.siteIcon, alt: '' } ) );
			}
		},

		/* ---------------------------------------------------------------
		 * "Insert variable" menu
		 * ------------------------------------------------------------- */

		bindVariableMenu() {
			const self = this;

			this.$card.on(
				'click',
				'.storesuite-seo-insert-variable',
				function ( event ) {
					event.preventDefault();
					const $input = $( this )
						.closest( '.storesuite-seo-field' )
						.find( 'input[type="text"], textarea' );

					if ( self.$menu && self.$menuField.is( $input ) ) {
						self.closeMenu();
						return;
					}
					// A field that wasn't being edited gets the variable at its end.
					if (
						$input[ 0 ].ownerDocument.activeElement !== $input[ 0 ]
					) {
						$input.trigger( 'focus' );
						$input[ 0 ].setSelectionRange(
							$input.val().length,
							$input.val().length
						);
					}
					self.openMenu( $input, '', null );
				}
			);

			// Keep the field focused (and its caret where it was) when the button is pressed.
			this.$card.on(
				'mousedown',
				'.storesuite-seo-insert-variable',
				function ( event ) {
					event.preventDefault();
				}
			);

			this.$card.on(
				'input',
				'.storesuite-seo-field input[type="text"], .storesuite-seo-field textarea',
				function () {
					const before = this.value.substring(
						0,
						this.selectionStart
					);
					const match = before.match( self.TRIGGER_PATTERN );

					if ( match ) {
						self.openMenu( $( this ), match[ 2 ], {
							start: before.length - match[ 2 ].length - 1,
							end: before.length,
						} );
					} else if ( self.menuTrigger ) {
						self.closeMenu();
					}
				}
			);

			this.$card.on(
				'keydown',
				'.storesuite-seo-field input[type="text"], .storesuite-seo-field textarea',
				function ( event ) {
					if ( ! self.$menu ) {
						return;
					}
					const $options = self.$menu.find( '[role="option"]' );
					const getActiveIndex = function () {
						return $options.index(
							$options.filter( '.is-active' )
						);
					};

					if (
						event.key === 'ArrowDown' ||
						event.key === 'ArrowUp'
					) {
						event.preventDefault();
						if ( $options.length ) {
							const step = event.key === 'ArrowDown' ? 1 : -1;
							self.setActiveOption(
								$options.eq(
									( getActiveIndex() +
										step +
										$options.length ) %
										$options.length
								)
							);
						}
					} else if ( event.key === 'Enter' || event.key === 'Tab' ) {
						const index = getActiveIndex();
						if ( index > -1 ) {
							event.preventDefault();
							self.insertVariable(
								$options.eq( index ).attr( 'data-variable' )
							);
						}
					} else if ( event.key === 'Escape' ) {
						event.preventDefault();
						event.stopPropagation();
						self.closeMenu();
					}
				}
			);

			// mousedown, so the field keeps focus and its caret position.
			this.$card.on(
				'mousedown',
				'.storesuite-seo-variable-menu [role="option"]',
				function ( event ) {
					event.preventDefault();
					self.insertVariable( $( this ).attr( 'data-variable' ) );
				}
			);

			$( document ).on( 'mousedown', function ( event ) {
				if (
					self.$menu &&
					! $( event.target ).closest(
						'.storesuite-seo-variable-menu, .storesuite-seo-insert-variable'
					).length &&
					! self.$menuField.is( event.target )
				) {
					self.closeMenu();
				}
			} );
		},

		setActiveOption( $option ) {
			this.$menu
				.find( '[role="option"]' )
				.removeClass( 'is-active' )
				.attr( 'aria-selected', 'false' );
			$option.addClass( 'is-active' ).attr( 'aria-selected', 'true' );

			if ( $option.length && $option[ 0 ].scrollIntoView ) {
				$option[ 0 ].scrollIntoView( { block: 'nearest' } );
			}
		},

		/**
		 * @param {Object}      $input  jQuery object of the field the variable goes into.
		 * @param {string}      filter  Partial variable name typed after %.
		 * @param {Object|null} trigger Range of the typed "%partial" to replace, null when opened from the button.
		 */
		openMenu( $input, filter, trigger ) {
			const self = this;
			const needle = filter.toLowerCase();
			const matches = this.config.variables.filter(
				function ( variable ) {
					return (
						! needle ||
						variable.name.indexOf( needle ) === 0 ||
						variable.label.toLowerCase().indexOf( needle ) > -1
					);
				}
			);

			this.closeMenu();

			const $menu = $( '<ul>', {
				class: 'storesuite-seo-variable-menu',
				role: 'listbox',
			} );

			if ( ! matches.length ) {
				$menu.append(
					$( '<li>', {
						class: 'storesuite-seo-variable-empty',
						text: this.config.i18n.noMatches,
					} )
				);
			}

			matches.forEach( function ( variable ) {
				$menu.append(
					$( '<li>', {
						role: 'option',
						'aria-selected': 'false',
						'data-variable': variable.name,
						class: variable.recommended ? 'is-recommended' : '',
					} )
						.append(
							$( '<span>', {
								class: 'storesuite-seo-variable-label',
								text: variable.label,
							} )
						)
						.append(
							$( '<code>', {
								text: '%%' + variable.name + '%%',
							} )
						)
				);
			} );

			this.$menu = $menu;
			this.$menuField = $input;
			this.menuTrigger = trigger;

			$input
				.closest( '.storesuite-seo-field' )
				.addClass( 'has-variable-menu' )
				.find( '.storesuite-seo-insert-variable' )
				.attr( 'aria-expanded', 'true' );
			$input.after( $menu );
			self.setActiveOption( $menu.find( '[role="option"]' ).first() );
		},

		closeMenu() {
			if ( ! this.$menu ) {
				return;
			}
			this.$menuField
				.closest( '.storesuite-seo-field' )
				.removeClass( 'has-variable-menu' )
				.find( '.storesuite-seo-insert-variable' )
				.attr( 'aria-expanded', 'false' );
			this.$menu.remove();
			this.$menu = null;
			this.$menuField = null;
			this.menuTrigger = null;
		},

		insertVariable( name ) {
			const input = this.$menuField[ 0 ];
			const trigger = this.menuTrigger;
			const start = trigger ? trigger.start : input.selectionStart;
			const end = trigger ? trigger.end : input.selectionEnd;
			const before = input.value.substring( 0, start );
			const after = input.value.substring( end );
			let token = '%%' + name + '%%';

			// Keep variables from running into neighbouring words.
			if ( before && ! /\s$/.test( before ) ) {
				token = ' ' + token;
			}
			if ( ! after || ! /^\s/.test( after ) ) {
				token += ' ';
			}

			input.value = before + token + after;
			input.selectionStart = input.selectionEnd =
				before.length + token.length;

			this.closeMenu();
			// Lets the preview and the form's unsaved-changes bar react.
			$( input )
				.trigger( 'input' )
				.trigger( 'change' )
				.trigger( 'focus' );
		},
	};

	$( function () {
		StoreSuiteProductSeo.init();
	} );
} )( jQuery );
