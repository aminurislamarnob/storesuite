/* global StoreSuite_ProductSeo, tinymce */
/**
 * Yoast SEO card on the StoreSuite product form.
 *
 *   - "Insert variable": a suggestion menu for Yoast snippet variables, opened
 *     from the button or by typing % in the SEO title / meta description.
 *   - Google preview: a live mobile/desktop search snippet built from the SEO
 *     fields and the product's title, permalink, descriptions and terms.
 *   - Progress bars using Yoast's limits (title width in px, description length).
 *
 * Reads the localized StoreSuite_ProductSeo global enqueued by YoastSeoIntegration.
 */
( function ( $ ) {
	var StoreSuiteProductSeo = {
		VARIABLE_PATTERN: /%%([a-z_]+)%%/g,
		// A lone % (not part of a finished %%var%%) followed by the partial name being typed.
		TRIGGER_PATTERN: /(^|[^%])%([a-z_]*)$/,
		// Yoast measures the SEO title as Google renders it on desktop.
		TITLE_FONT: '20px Arial, sans-serif',

		init: function () {
			this.config =
				typeof StoreSuite_ProductSeo !== 'undefined'
					? StoreSuite_ProductSeo
					: null;
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

			this.bindPreview();
			this.bindVariableMenu();
			this.render();
		},

		/* ---------------------------------------------------------------
		 * Live values from the product form
		 * ------------------------------------------------------------- */

		stripTags: function ( html ) {
			var el = document.createElement( 'div' );
			el.innerHTML = html;
			return ( el.textContent || '' ).replace( /\s+/g, ' ' ).trim();
		},

		getDescriptionText: function () {
			var editor =
				typeof tinymce !== 'undefined'
					? tinymce.get( 'product_description' )
					: null;
			var html =
				editor && ! editor.isHidden()
					? editor.getContent()
					: $( '#product_description' ).val() || '';
			return this.stripTags( html );
		},

		getSelectedLabels: function ( selector ) {
			return $( selector )
				.find( 'option:selected' )
				.map( function () {
					return $( this ).text().trim();
				} )
				.get();
		},

		slugify: function ( text ) {
			return text
				.toLowerCase()
				.replace( /[^a-z0-9\s-]/g, '' )
				.trim()
				.replace( /[\s-]+/g, '-' );
		},

		getContext: function () {
			var title = ( $( '#product_title' ).val() || '' ).trim();
			var shortDesc = this.stripTags(
				$( '#product_short_description' ).val() || ''
			);
			var categories = this.getSelectedLabels( '#product_category' );
			var excerpt = shortDesc || this.getDescriptionText();

			if ( excerpt.length > this.config.descMaxLength ) {
				excerpt = excerpt
					.substring( 0, this.config.descMaxLength )
					.replace( /\s+\S*$/, '' );
			}

			return $.extend( {}, this.config.replacements, {
				title: title,
				excerpt: excerpt,
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
		 */
		replaceVariables: function ( text, context ) {
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
		 */
		trimSeparators: function ( text ) {
			var sep = this.config.replacements.sep;
			if ( ! sep ) {
				return text;
			}
			var escaped = sep.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
			return text
				.replace(
					new RegExp(
						'^(\\s*' + escaped + '\\s*)+|(\\s*' + escaped + '\\s*)+$',
						'g'
					),
					''
				)
				.trim();
		},

		/* ---------------------------------------------------------------
		 * Google preview + progress bars
		 * ------------------------------------------------------------- */

		bindPreview: function () {
			var self = this;
			var render = function () {
				self.render();
			};

			this.$card.on(
				'input change',
				'input, textarea',
				render
			);
			$( '#product_title, #product_slug, #product_short_description' ).on(
				'input change',
				render
			);
			$( '#product_category, #product_tags' ).on( 'change', render );

			// The description editor initialises after this script runs.
			$( window ).on( 'load', function () {
				var editor =
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
				var desktop = self.$preview.attr( 'data-mode' ) !== 'desktop';
				self.$preview.attr( 'data-mode', desktop ? 'desktop' : 'mobile' );
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

		measureTitleWidth: function ( text ) {
			if ( ! this.canvasContext ) {
				this.canvasContext = document
					.createElement( 'canvas' )
					.getContext( '2d' );
			}
			this.canvasContext.font = this.TITLE_FONT;
			return Math.round( this.canvasContext.measureText( text ).width );
		},

		getUrlParts: function () {
			var base =
				$( '.storesuite-permalink-prefix' ).first().text().trim() ||
				this.config.homeUrl;
			var slug =
				( $( '#product_slug' ).val() || '' ).trim() ||
				this.slugify( ( $( '#product_title' ).val() || '' ).trim() );
			var parts = base
				.replace( /^https?:\/\//, '' )
				.split( '/' )
				.filter( Boolean );

			if ( slug ) {
				parts.push( slug );
			}
			return parts;
		},

		escapeHtml: function ( text ) {
			return $( '<div>' ).text( text ).html();
		},

		/**
		 * Bold the focus keyphrase words in the description, as Google does
		 * for the searcher's query.
		 */
		highlightKeyphrase: function ( text, keyphrase ) {
			var self = this;
			var words = keyphrase
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
			var pattern = new RegExp(
				'(?<![\\p{L}\\p{N}])(' +
					words.join( '|' ) +
					')(?![\\p{L}\\p{N}])',
				'giu'
			);

			return text
				.split( pattern )
				.map( function ( piece, index ) {
					var html = self.escapeHtml( piece );
					return index % 2 ? '<strong>' + html + '</strong>' : html;
				} )
				.join( '' );
		},

		setProgress: function ( field, value, max, state ) {
			var $bar = this.$card.find(
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

		render: function () {
			var config = this.config;
			var context = this.getContext();
			var isCornerstone = $( '#storesuite_yoast_is_cornerstone' ).is(
				':checked'
			);
			var title = this.replaceVariables(
				( this.$title.val() || '' ).trim() || config.titleTemplate,
				context
			);
			title = this.trimSeparators( title );
			var desc = this.replaceVariables(
				( this.$desc.val() || '' ).trim() || config.descTemplate,
				context
			);
			var urlParts = this.getUrlParts();
			var isDesktop = this.$preview.attr( 'data-mode' ) === 'desktop';

			// Progress bars score what will be output, not the Google fallback below.
			var titleWidth = this.measureTitleWidth( title );
			this.setProgress(
				'title',
				titleWidth,
				config.titleMaxWidth,
				titleWidth > 0 && titleWidth <= config.titleMaxWidth
					? 'good'
					: 'bad'
			);

			var descState = 'good';
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
			var shownDesc = desc || context.excerpt;
			var $desc = this.$preview.find( '.storesuite-seo-snippet-desc' );
			if ( shownDesc.length > config.descMaxLength ) {
				shownDesc =
					shownDesc
						.substring( 0, config.descMaxLength )
						.replace( /\s+\S*$/, '' ) + ' ...';
			}
			$desc
				.toggleClass( 'is-placeholder', ! shownDesc )
				.html(
					shownDesc
						? this.highlightKeyphrase( shownDesc, context.focuskw )
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
				.text( isDesktop ? urlParts.join( ' › ' ) : urlParts[ 0 ] || '' );

			var $icon = this.$preview.find( '.storesuite-seo-snippet-icon' );
			if ( config.siteIcon && ! $icon.children().length ) {
				$icon.append(
					$( '<img>', { src: config.siteIcon, alt: '' } )
				);
			}
		},

		/* ---------------------------------------------------------------
		 * "Insert variable" menu
		 * ------------------------------------------------------------- */

		bindVariableMenu: function () {
			var self = this;

			this.$card.on(
				'click',
				'.storesuite-seo-insert-variable',
				function ( event ) {
					event.preventDefault();
					var $input = $( this )
						.closest( '.storesuite-seo-field' )
						.find( 'input[type="text"], textarea' );

					if ( self.$menu && self.$menuField.is( $input ) ) {
						self.closeMenu();
						return;
					}
					// A field that wasn't being edited gets the variable at its end.
					if ( document.activeElement !== $input[ 0 ] ) {
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
					var before = this.value.substring( 0, this.selectionStart );
					var match = before.match( self.TRIGGER_PATTERN );

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
					var $options = self.$menu.find( '[role="option"]' );
					var index = $options.index(
						$options.filter( '.is-active' )
					);

					if ( event.key === 'ArrowDown' || event.key === 'ArrowUp' ) {
						event.preventDefault();
						if ( ! $options.length ) {
							return;
						}
						index =
							( index +
								( event.key === 'ArrowDown' ? 1 : -1 ) +
								$options.length ) %
							$options.length;
						self.setActiveOption( $options.eq( index ) );
					} else if ( event.key === 'Enter' || event.key === 'Tab' ) {
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

		setActiveOption: function ( $option ) {
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
		 * @param {jQuery}      $input  Field the variable goes into.
		 * @param {string}      filter  Partial variable name typed after %.
		 * @param {Object|null} trigger Range of the typed "%partial" to replace, null when opened from the button.
		 */
		openMenu: function ( $input, filter, trigger ) {
			var self = this;
			var needle = filter.toLowerCase();
			var matches = this.config.variables.filter( function ( variable ) {
				return (
					! needle ||
					variable.name.indexOf( needle ) === 0 ||
					variable.label.toLowerCase().indexOf( needle ) > -1
				);
			} );

			this.closeMenu();

			var $menu = $( '<ul>', {
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

		closeMenu: function () {
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

		insertVariable: function ( name ) {
			var input = this.$menuField[ 0 ];
			var trigger = this.menuTrigger;
			var start = trigger ? trigger.start : input.selectionStart;
			var end = trigger ? trigger.end : input.selectionEnd;
			var before = input.value.substring( 0, start );
			var after = input.value.substring( end );
			var token = '%%' + name + '%%';

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
			$( input ).trigger( 'input' ).trigger( 'change' ).trigger( 'focus' );
		},
	};

	$( function () {
		StoreSuiteProductSeo.init();
	} );
} )( jQuery );
