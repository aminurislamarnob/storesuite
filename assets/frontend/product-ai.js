/* global jQuery, StoreSuite_Product, storeSuiteFrontScript, Swal, tinymce */
/**
 * AI copy generation for the StoreSuite product form.
 *
 * Two modals, each with one job:
 *   - #storesuite-ai-prompt-modal: collects seed keywords when generating a
 *     title from an empty form (title + description + short description blank).
 *   - #storesuite-ai-modal: shows the editable suggestion with Regenerate, a
 *     history pager, and Insert.
 *
 * Reads the localized StoreSuite_Product global enqueued on the product script.
 *
 * @param {Function} $ jQuery.
 */
/* eslint-disable camelcase -- StoreSuite_Product is named by wp_localize_script(). */
( function ( $ ) {
	const StoreFrontProductAI = {
		SPINNER_ICON: '<i class="las la-spinner la-spin"></i> ',
		MODAL_CLOSE_SELECTOR:
			'.storesuite-product-bulk-modal-cancel, .storesuite-product-bulk-modal-close',
		// Every on-form AI launcher. While one generation request is pending
		// these are disabled together so a second one can't be started
		// mid-flight (which would corrupt the shared activeField / suggestion
		// state, or stack a second modal over the form).
		LAUNCHER_SELECTOR:
			'.storesuite-ai-generate, .storesuite-ai-bundle-launch, .storesuite-ai-image-generate',

		init() {
			this.aiConfig =
				( typeof StoreSuite_Product !== 'undefined' &&
					StoreSuite_Product.ai ) ||
				null;
			const imageEnabled = !! (
				this.aiConfig &&
				this.aiConfig.image &&
				this.aiConfig.image.enabled
			);
			if (
				! this.aiConfig ||
				( ! this.aiConfig.enabled && ! imageEnabled )
			) {
				return;
			}

			this.strings = this.aiConfig.i18n || {};
			// Field definitions from the registry: label, target, rows, length.
			this.fields = this.aiConfig.fields || {};
			this.commonStrings =
				( typeof StoreSuite_Product !== 'undefined' &&
					StoreSuite_Product.i18n ) ||
				{};
			this.sharedModal =
				( window.StoreSuite && window.StoreSuite.storeSuiteModal ) ||
				null;

			// Shared suggestion state.
			this.activeField = null; // Field currently being generated.
			this.activeTarget = ''; // Where its result is inserted (from the button).
			this.suggestions = []; // Suggestions generated this session.
			this.currentIndex = -1; // Index of the suggestion on screen.
			this.promptSeed = ''; // Prompt-entry keywords (kept for regeneration).

			// Per-field bundle suggestion history.
			this.bundleHistory = {
				title: [],
				short_description: [],
				description: [],
			};
			this.bundleIndex = {
				title: -1,
				short_description: -1,
				description: -1,
			};

			this.initTextModals();
			this.initBundleModal();
			this.initImageModal();
		},

		// --- Helpers -------------------------------------------------------

		// POST to admin-ajax and normalize the WP JSON envelope: the returned
		// promise resolves with response.data on success and rejects with a
		// human-readable message on failure (server error message, or the generic
		// fallback). Throwing inside the .then() handlers rejects the chained
		// jQuery promise under the Promises/A+ semantics of jQuery 3.x, which WP
		// 7.0 ships and this AI feature requires.
		aiPost( data ) {
			const commonStrings = this.commonStrings;
			return $.post( StoreSuite_Product.ajax_url, data ).then(
				function ( response ) {
					if ( response && response.success && response.data ) {
						return response.data;
					}
					throw (
						( response &&
							response.data &&
							response.data.message ) ||
						commonStrings.unexpected_error
					);
				},
				function () {
					throw commonStrings.unexpected_error;
				}
			);
		},

		trimmedValue( selector ) {
			return $.trim( $( selector ).val() || '' );
		},

		// Split a comma-separated string into a clean list (trimmed, no blanks).
		splitCsv( value ) {
			return $.map( ( value || '' ).split( ',' ), function ( item ) {
				item = $.trim( item );
				return item ? item : null;
			} );
		},

		// Active visual TinyMCE editor for the description, or null.
		getDescriptionEditor() {
			const editor =
				window.tinymce && tinymce.get( 'product_description' );
			return editor && ! editor.isHidden() ? editor : null;
		},

		getDescription() {
			const editor = this.getDescriptionEditor();
			return editor
				? editor.getContent()
				: $( '#product_description' ).val() || '';
		},

		setDescription( html ) {
			const editor = this.getDescriptionEditor();
			if ( editor ) {
				editor.setContent( html );
			}
			// Keep the underlying textarea in sync for the Text tab / submit.
			$( '#product_description' ).val( html );
		},

		// Extra context posted with every request: any form field an
		// integration flags with data-ai-context (e.g. the SEO focus keyphrase).
		getExtraContext() {
			const extra = {};
			$( '[data-ai-context]' ).each( function () {
				if ( this.name ) {
					extra[ this.name ] = $( this ).val() || '';
				}
			} );
			return extra;
		},

		getSelectedCategories() {
			return $( '#product_category option:selected' )
				.map( function () {
					return $.trim( $( this ).text() );
				} )
				.get()
				.filter( Boolean );
		},

		// Lock or release every AI launcher button on the form at once, so only
		// the in-flight generation can run until it settles.
		setLaunchersBusy( isBusy ) {
			$( this.LAUNCHER_SELECTOR ).prop( 'disabled', !! isBusy );
		},

		showAlert( icon, message ) {
			Swal.fire( {
				icon,
				title: this.strings.error_title,
				text: message || this.commonStrings.unexpected_error,
				confirmButtonText: this.commonStrings.ok_button,
			} );
		},

		// Definition of a field from the registry (empty object when unknown).
		fieldDefinition( fieldName ) {
			return this.fields[ fieldName ] || {};
		},

		modalTitleFor( fieldName ) {
			return (
				this.fieldDefinition( fieldName ).label ||
				( this.strings.modal_titles || {} )[ fieldName ] ||
				this.$modalTitle.text()
			);
		},

		// Most fields need a title or keywords to work from; the server decides
		// which (a field's `needs_context`), so only the title is exempt here.
		hasContext( fieldName ) {
			// Generating the title itself never needs prior context.
			if ( fieldName === 'title' ) {
				return true;
			}

			const hasTitle = this.trimmedValue( '#product_title' )
				? true
				: false;
			const hasShortDescription = this.trimmedValue(
				'#product_short_description'
			)
				? true
				: false;

			return hasTitle || hasShortDescription;
		},

		// True when title, description and short description are all empty.
		isTitleFormEmpty() {
			return (
				! this.trimmedValue( '#product_title' ) &&
				! this.trimmedValue( '#product_short_description' ) &&
				! $.trim( this.getDescription() )
			);
		},

		// Resolves with the generated content, or rejects with a message.
		generateSuggestion( fieldName, previousSuggestion ) {
			return this.aiPost( {
				action: this.aiConfig.action,
				nonce: this.aiConfig.nonce,
				field: fieldName,
				previous: previousSuggestion || '',
				// Fall back to the typed seed when the title field is empty
				// (prompt flow), so the seed survives regeneration.
				product_title:
					this.trimmedValue( '#product_title' ) || this.promptSeed,
				product_short_description: this.trimmedValue(
					'#product_short_description'
				),
				product_description: this.getDescription(),
				categories: this.getSelectedCategories(),
				...this.getExtraContext(),
			} ).then( function ( data ) {
				return data.content || '';
			} );
		},

		// Show "n / target" under the suggestion and flag it when over length.
		refreshCounter() {
			const target = this.fieldDefinition( this.activeField ).length || 0;
			if ( ! target || ! this.$modalCounter.length ) {
				this.$modalCounter.prop( 'hidden', true );
				return;
			}
			const length = ( this.$modalText.val() || '' ).trim().length;
			this.$modalCounter
				.prop( 'hidden', false )
				.attr( 'data-state', length > target ? 'over' : 'ok' )
				.text( length + ' / ' + target );
		},

		// Persist edits to the visible suggestion so they survive navigation.
		saveCurrentEdit() {
			if ( this.currentIndex > -1 ) {
				this.suggestions[ this.currentIndex ] =
					this.$modalText.val() || '';
			}
		},

		// Show the suggestion at the index and render the "‹ n/m ›" pager.
		showSuggestion( suggestionIndex ) {
			if (
				suggestionIndex < 0 ||
				suggestionIndex >= this.suggestions.length
			) {
				return;
			}
			this.currentIndex = suggestionIndex;
			this.$modalText.val( this.suggestions[ suggestionIndex ] );
			this.$pagerStatus.text(
				suggestionIndex + 1 + '/' + this.suggestions.length
			);
			this.$previousButton.prop( 'disabled', suggestionIndex === 0 );
			this.$nextButton.prop(
				'disabled',
				suggestionIndex === this.suggestions.length - 1
			);
			this.$pager.prop( 'hidden', this.suggestions.length < 2 );
			this.refreshCounter();
		},

		// Show a fresh suggestion in the suggestion modal (or insert directly
		// when the shared modal is unavailable).
		openSuggestionModal( content ) {
			if ( ! this.sharedModal || ! this.$modal.length ) {
				this.insertIntoField(
					this.activeField,
					content,
					this.activeTarget
				);
				return;
			}
			this.suggestions = [ content ];
			this.currentIndex = -1;
			this.$modalTitle.text( this.modalTitleFor( this.activeField ) );
			this.$modalText.attr(
				'rows',
				this.fieldDefinition( this.activeField ).rows || 3
			);
			this.showSuggestion( 0 );
			this.sharedModal.open( this.$modal );
		},

		// Open the prompt-input modal to collect seed keywords for a title.
		openPromptModal() {
			this.$promptText.val( '' );
			this.sharedModal.open( this.$promptModal );
		},

		// Insert into the field's declared target (or an explicit override from
		// the button that started the generation). The long description goes
		// through its rich-text editor; everything else is a plain form control.
		insertIntoField( fieldName, content, target ) {
			target = target || this.fieldDefinition( fieldName ).target;

			if ( ! target ) {
				return;
			}
			if ( target === '#product_description' ) {
				this.setDescription( content );
				return;
			}
			// input + change: the SEO preview listens to input, the
			// unsaved-changes bar to change.
			$( target ).val( content ).trigger( 'input' ).trigger( 'change' );
		},

		// --- Suggestion + prompt modals ------------------------------------

		initTextModals() {
			const self = this;

			// Suggestion modal.
			this.$modal = $( '#storesuite-ai-modal' );
			this.$modalText = this.$modal.find( '#storesuite-ai-modal-text' );
			this.$modalTitle = this.$modal.find( '#storesuite-ai-modal-title' );
			this.$insertButton = this.$modal.find( '.storesuite-ai-insert' );
			this.$regenerateButton = this.$modal.find(
				'.storesuite-ai-regenerate'
			);
			this.$pager = this.$modal.find( '.storesuite-ai-modal-pager' );
			this.$previousButton = this.$modal.find( '.storesuite-ai-prev' );
			this.$nextButton = this.$modal.find( '.storesuite-ai-next' );
			this.$pagerStatus = this.$modal.find(
				'.storesuite-ai-pager-status'
			);
			this.$modalSkeleton = this.$modal.find( '.storesuite-ai-skeleton' );
			this.$modalCounter = this.$modal.find(
				'.storesuite-ai-modal-counter'
			);

			// Live length counter for fields that declare a target length.
			this.$modalText.on( 'input', () => this.refreshCounter() );

			// Prompt-input modal.
			this.$promptModal = $( '#storesuite-ai-prompt-modal' );
			this.$promptText = this.$promptModal.find(
				'#storesuite-ai-prompt-text'
			);
			this.$promptGenerateButton = this.$promptModal.find(
				'.storesuite-ai-prompt-generate'
			);
			this.$promptDismissButtons = this.$promptModal.find(
				this.MODAL_CLOSE_SELECTOR
			);

			if ( this.sharedModal && this.$modal.length ) {
				this.sharedModal.initOverlay( this.$modal, {
					fade: true,
					closeSelector: this.MODAL_CLOSE_SELECTOR,
					closeOnOverlayClick: false,
				} );
			}
			if ( this.sharedModal && this.$promptModal.length ) {
				this.sharedModal.initOverlay( this.$promptModal, {
					fade: true,
					closeSelector: this.MODAL_CLOSE_SELECTOR,
					closeOnOverlayClick: false,
				} );
			}

			// Field buttons: generate, then open the suggestion modal.
			$( document ).on(
				'click',
				'.storesuite-ai-generate',
				function ( event ) {
					event.preventDefault();
					const $button = $( this );
					if ( $button.prop( 'disabled' ) ) {
						return;
					}
					self.activeField = $button.data( 'field' );
					self.activeTarget = $button.data( 'target' ) || '';

					// Title on an empty form: collect seed keywords first.
					if (
						self.activeField === 'title' &&
						self.isTitleFormEmpty() &&
						self.sharedModal &&
						self.$promptModal.length
					) {
						self.openPromptModal();
						return;
					}

					if ( ! self.hasContext( self.activeField ) ) {
						self.showAlert( 'info', self.strings.no_context );
						return;
					}

					self.promptSeed = ''; // Real form context drives this request.
					const originalHtml = $button.html();
					$button
						.prop( 'disabled', true )
						.html( self.SPINNER_ICON + self.strings.generating );
					// Lock every other AI launcher until this request settles.
					self.setLaunchersBusy( true );

					self.generateSuggestion( self.activeField, '' )
						.done( function ( content ) {
							self.openSuggestionModal( content );
						} )
						.fail( function ( message ) {
							self.showAlert( 'error', message );
						} )
						.always( function () {
							self.setLaunchersBusy( false );
							$button.html( originalHtml );
						} );
				}
			);

			// Prompt modal: generate a title from the typed keywords.
			this.$promptGenerateButton.on( 'click', function ( event ) {
				event.preventDefault();
				if ( self.$promptGenerateButton.prop( 'disabled' ) ) {
					return;
				}

				self.promptSeed = $.trim( self.$promptText.val() || '' );
				if ( ! self.promptSeed ) {
					self.showAlert( 'error', self.strings.prompt_required );
					return;
				}

				self.activeField = 'title';
				self.activeTarget = '';
				const originalText = self.$promptGenerateButton.text();
				self.$promptGenerateButton
					.prop( 'disabled', true )
					.text( self.strings.generating );
				// Lock the prompt and dismiss controls while the title is generated.
				self.$promptText.prop( 'readonly', true );
				self.$promptDismissButtons.prop( 'disabled', true );

				self.generateSuggestion( 'title', '' )
					.done( function ( content ) {
						self.sharedModal.close( self.$promptModal );
						self.openSuggestionModal( content );
					} )
					.fail( function ( message ) {
						self.showAlert( 'error', message );
					} )
					.always( function () {
						self.$promptText.prop( 'readonly', false );
						self.$promptDismissButtons.prop( 'disabled', false );
						self.$promptGenerateButton
							.prop( 'disabled', false )
							.text( originalText );
					} );
			} );

			// Regenerate: append a fresh suggestion, steering away from the current.
			this.$regenerateButton.on( 'click', function ( event ) {
				event.preventDefault();
				if (
					! self.activeField ||
					self.$regenerateButton.prop( 'disabled' )
				) {
					return;
				}

				self.saveCurrentEdit();
				const previousSuggestion = self.$modalText.val() || '';
				self.$regenerateButton
					.prop( 'disabled', true )
					.text( self.strings.regenerating );
				self.$insertButton.prop( 'disabled', true );
				// Swap the current text for a skeleton while the new one is generated.
				self.$modalText.prop( 'hidden', true );
				self.$pager.prop( 'hidden', true );
				self.$modalSkeleton.prop( 'hidden', false );

				self.generateSuggestion( self.activeField, previousSuggestion )
					.done( function ( content ) {
						self.suggestions.push( content );
						self.showSuggestion( self.suggestions.length - 1 );
					} )
					.fail( function ( message ) {
						self.showAlert( 'error', message );
					} )
					.always( function () {
						self.$modalSkeleton.prop( 'hidden', true );
						self.$modalText.prop( 'hidden', false );
						self.$pager.prop(
							'hidden',
							self.suggestions.length < 2
						);
						self.$regenerateButton
							.prop( 'disabled', false )
							.text( self.strings.regenerate );
						self.$insertButton.prop( 'disabled', false );
					} );
			} );

			// Pager: navigate between previously generated suggestions.
			this.$previousButton.on( 'click', function ( event ) {
				event.preventDefault();
				self.saveCurrentEdit();
				self.showSuggestion( self.currentIndex - 1 );
			} );
			this.$nextButton.on( 'click', function ( event ) {
				event.preventDefault();
				self.saveCurrentEdit();
				self.showSuggestion( self.currentIndex + 1 );
			} );

			// Insert the (possibly edited) modal text into the field.
			this.$insertButton.on( 'click', function ( event ) {
				event.preventDefault();
				if ( ! self.activeField ) {
					return;
				}
				self.insertIntoField(
					self.activeField,
					self.$modalText.val() || '',
					self.activeTarget
				);
				if ( self.sharedModal && self.$modal.length ) {
					self.sharedModal.close( self.$modal );
				}
			} );
		},

		// --- Bundle modal (global "Generate with AI") ----------------------
		//
		// Launched from the page header on Add New Product: the merchant types
		// one hint and AI drafts title, short description and long description
		// together for review before they're inserted into the form.

		// Lock every field and control in the bundle modal while a request is in
		// flight. The calling handler restores the busy button's own label.
		setBundleBusy( isBusy ) {
			this.$bundleFields.prop( 'readonly', isBusy );
			this.$bundleDismissButtons.prop( 'disabled', isBusy );
			this.$bundleGenerate.prop( 'disabled', isBusy );
			this.$bundleRegenerate.prop( 'disabled', isBusy );
			this.$bundleInsert.prop( 'disabled', isBusy );
			this.$bundleModal
				.find( '.storesuite-ai-field-regenerate' )
				.prop( 'disabled', isBusy );
		},

		// The editable result control for a field key.
		bundleField( fieldName ) {
			if ( fieldName === 'title' ) {
				return this.$bundleTitle;
			}
			if ( fieldName === 'short_description' ) {
				return this.$bundleShort;
			}
			return this.$bundleDescription;
		},

		// Swap a result field for its shimmer skeleton (or back) while it
		// regenerates, so the placeholder appears exactly where the new copy
		// will land.
		toggleBundleFieldSkeleton( fieldName, show ) {
			this.bundleField( fieldName ).prop( 'hidden', show );
			this.$bundleResultStep
				.find(
					'.storesuite-ai-skeleton[data-field="' + fieldName + '"]'
				)
				.prop( 'hidden', ! show );
		},

		toggleBundleSkeletons( show ) {
			this.toggleBundleFieldSkeleton( 'title', show );
			this.toggleBundleFieldSkeleton( 'short_description', show );
			this.toggleBundleFieldSkeleton( 'description', show );
		},

		// Reflect a field's history into its pager (count, position, arrows).
		renderFieldPager( fieldName ) {
			const history = this.bundleHistory[ fieldName ];
			const index = this.bundleIndex[ fieldName ];
			const $pager = this.$bundleResultStep.find(
				'.storesuite-ai-field-pager[data-field="' + fieldName + '"]'
			);
			if ( ! history.length ) {
				$pager.prop( 'hidden', true );
				return;
			}
			$pager.prop( 'hidden', false );
			$pager
				.find( '.storesuite-ai-field-pager-status' )
				.text( index + 1 + '/' + history.length );
			$pager
				.find( '.storesuite-ai-field-prev' )
				.prop( 'disabled', index <= 0 );
			$pager
				.find( '.storesuite-ai-field-next' )
				.prop( 'disabled', index >= history.length - 1 );
		},

		// Persist any manual edit to the visible value before navigating away.
		saveFieldEdit( fieldName ) {
			const index = this.bundleIndex[ fieldName ];
			if ( index > -1 ) {
				this.bundleHistory[ fieldName ][ index ] =
					this.bundleField( fieldName ).val() || '';
			}
		},

		// Append a freshly generated value and jump the pager to it.
		pushFieldSuggestion( fieldName, value ) {
			this.bundleHistory[ fieldName ].push( value );
			this.bundleIndex[ fieldName ] =
				this.bundleHistory[ fieldName ].length - 1;
			this.renderFieldPager( fieldName );
		},

		// Show the suggestion at the given index in its field.
		showFieldSuggestion( fieldName, index ) {
			const history = this.bundleHistory[ fieldName ];
			if ( index < 0 || index >= history.length ) {
				return;
			}
			this.bundleIndex[ fieldName ] = index;
			this.bundleField( fieldName ).val( history[ index ] );
			this.renderFieldPager( fieldName );
		},

		// Clear all per-field history and hide every pager.
		resetFieldHistory() {
			this.bundleHistory = {
				title: [],
				short_description: [],
				description: [],
			};
			this.bundleIndex = {
				title: -1,
				short_description: -1,
				description: -1,
			};
			this.renderFieldPager( 'title' );
			this.renderFieldPager( 'short_description' );
			this.renderFieldPager( 'description' );
		},

		// Return the modal to the hint-entry step with everything cleared.
		resetBundleModal() {
			this.setBundleBusy( false );
			this.toggleBundleSkeletons( false );
			this.resetFieldHistory();
			this.$bundleHint.val( '' );
			this.$bundleTitle.val( '' );
			this.$bundleShort.val( '' );
			this.$bundleDescription.val( '' );
			this.$bundleResultStep.prop( 'hidden', true );
			this.$bundleHintStep.prop( 'hidden', false );
			this.$bundleRegenerate.prop( 'hidden', true );
			this.$bundleInsert.prop( 'hidden', true );
			this.$bundleGenerate.prop( 'hidden', false );
		},

		// Request all three fields for the typed hint. Resolves with the data
		// object { title, short_description, description }, or rejects with a
		// message.
		generateBundle( previousTitle ) {
			return this.aiPost( {
				action: this.aiConfig.bundle_action,
				nonce: this.aiConfig.nonce,
				hint: $.trim( this.$bundleHint.val() || '' ),
				previous_title: previousTitle || '',
			} );
		},

		// Fill the editable result fields and reveal the result step. Each value
		// is appended to its field's history so the pager advances on every
		// (re)generation of the full set.
		showBundleResults( data ) {
			this.$bundleTitle.val( data.title || '' );
			this.$bundleShort.val( data.short_description || '' );
			this.$bundleDescription.val( data.description || '' );
			this.pushFieldSuggestion( 'title', data.title || '' );
			this.pushFieldSuggestion(
				'short_description',
				data.short_description || ''
			);
			this.pushFieldSuggestion( 'description', data.description || '' );
			this.$bundleHintStep.prop( 'hidden', true );
			this.$bundleResultStep.prop( 'hidden', false );
			this.$bundleGenerate.prop( 'hidden', true );
			this.$bundleRegenerate.prop( 'hidden', false );
			this.$bundleInsert.prop( 'hidden', false );
		},

		// Shared runner for Generate and Regenerate.
		runBundle( $button, busyLabel, previousTitle ) {
			const self = this;
			if ( ! $.trim( this.$bundleHint.val() || '' ) ) {
				this.showAlert( 'error', this.strings.hint_required );
				return;
			}
			const originalText = $button.text();
			// Regenerating means the result step is already on screen; show
			// skeletons over the fields. First generation has nothing to cover.
			const isRegenerate = ! this.$bundleResultStep.prop( 'hidden' );
			this.setBundleBusy( true );
			if ( isRegenerate ) {
				// Preserve any manual edits at their current history positions
				// before the new set is appended.
				this.saveFieldEdit( 'title' );
				this.saveFieldEdit( 'short_description' );
				this.saveFieldEdit( 'description' );
				this.toggleBundleSkeletons( true );
			}
			$button.text( busyLabel );

			this.generateBundle( previousTitle )
				.done( function ( data ) {
					self.showBundleResults( data );
				} )
				.fail( function ( message ) {
					self.showAlert( 'error', message );
				} )
				.always( function () {
					self.setBundleBusy( false );
					if ( isRegenerate ) {
						self.toggleBundleSkeletons( false );
					}
					$button.text( originalText );
				} );
		},

		// Current value of a single bundle result field.
		bundleFieldValue( fieldName ) {
			const value = this.bundleField( fieldName ).val() || '';
			// Only the title is trimmed; descriptions keep their whitespace.
			return fieldName === 'title' ? $.trim( value ) : value;
		},

		// Write a regenerated value back into its bundle result field.
		setBundleField( fieldName, content ) {
			this.bundleField( fieldName ).val( content );
		},

		initBundleModal() {
			const self = this;

			this.$bundleModal = $( '#storesuite-ai-bundle-modal' );
			this.$bundleHint = this.$bundleModal.find(
				'#storesuite-ai-bundle-hint'
			);
			this.$bundleHintStep = this.$bundleModal.find(
				'.storesuite-ai-bundle-hint-step'
			);
			this.$bundleResultStep = this.$bundleModal.find(
				'.storesuite-ai-bundle-result-step'
			);
			this.$bundleTitle = this.$bundleModal.find(
				'#storesuite-ai-bundle-result-title'
			);
			this.$bundleShort = this.$bundleModal.find(
				'#storesuite-ai-bundle-result-short'
			);
			this.$bundleDescription = this.$bundleModal.find(
				'#storesuite-ai-bundle-result-description'
			);
			this.$bundleGenerate = this.$bundleModal.find(
				'.storesuite-ai-bundle-generate'
			);
			this.$bundleRegenerate = this.$bundleModal.find(
				'.storesuite-ai-bundle-regenerate'
			);
			this.$bundleInsert = this.$bundleModal.find(
				'.storesuite-ai-bundle-insert'
			);
			this.$bundleFields = this.$bundleHint
				.add( this.$bundleTitle )
				.add( this.$bundleShort )
				.add( this.$bundleDescription );
			this.$bundleDismissButtons = this.$bundleModal.find(
				this.MODAL_CLOSE_SELECTOR
			);

			if ( this.sharedModal && this.$bundleModal.length ) {
				this.sharedModal.initOverlay( this.$bundleModal, {
					fade: true,
					closeSelector: this.MODAL_CLOSE_SELECTOR,
					closeOnOverlayClick: false,
				} );
			}

			// Header launcher: if the form already has any copy, skip the hint
			// step and open straight to the editable fields pre-filled with it.
			// Otherwise start on the hint step.
			$( document ).on(
				'click',
				'.storesuite-ai-bundle-launch',
				function ( event ) {
					event.preventDefault();
					if ( ! self.sharedModal || ! self.$bundleModal.length ) {
						return;
					}
					self.resetBundleModal();

					const title = self.trimmedValue( '#product_title' );
					const short = $.trim(
						$( '#product_short_description' ).val() || ''
					);
					const description = $.trim( self.getDescription() );

					if ( title || short || description ) {
						// Seed the (hidden) hint so Regenerate has something to work from.
						self.$bundleHint.val( title || short );
						self.showBundleResults( {
							title,
							short_description: short,
							description,
						} );
					}

					self.sharedModal.open( self.$bundleModal );
				}
			);

			this.$bundleGenerate.on( 'click', function ( event ) {
				event.preventDefault();
				self.runBundle(
					self.$bundleGenerate,
					self.strings.generating,
					''
				);
			} );

			this.$bundleRegenerate.on( 'click', function ( event ) {
				event.preventDefault();
				self.runBundle(
					self.$bundleRegenerate,
					self.strings.regenerating,
					$.trim( self.$bundleTitle.val() || '' )
				);
			} );

			// Regenerate a single field from the other fields' current values,
			// steering away from the value on screen.
			this.$bundleModal.on(
				'click',
				'.storesuite-ai-field-regenerate',
				function ( event ) {
					event.preventDefault();
					const $button = $( this );
					if ( $button.prop( 'disabled' ) ) {
						return;
					}
					const fieldName = $button.data( 'field' );
					const hint = $.trim( self.$bundleHint.val() || '' );
					const title = $.trim( self.$bundleTitle.val() || '' );
					const short = self.$bundleShort.val() || '';
					// Keywords for a title: the hint, else fall back to what we have.
					const titleSeed = hint || title || $.trim( short );
					const originalHtml = $button.html();
					// Keep any manual edit in history before the new one is appended.
					self.saveFieldEdit( fieldName );
					self.setBundleBusy( true );
					self.toggleBundleFieldSkeleton( fieldName, true );
					$button.html(
						self.SPINNER_ICON + self.strings.regenerating
					);

					self.aiPost( {
						action: self.aiConfig.action,
						nonce: self.aiConfig.nonce,
						field: fieldName,
						previous: self.bundleFieldValue( fieldName ),
						// Title leans on its seed; the others lean on the title.
						product_title:
							fieldName === 'title' ? titleSeed : title,
						product_short_description:
							fieldName === 'short_description'
								? ''
								: self.$bundleShort.val() || '',
						product_description:
							fieldName === 'description'
								? ''
								: self.$bundleDescription.val() || '',
						categories: [],
					} )
						.done( function ( data ) {
							const content = data.content || '';
							self.setBundleField( fieldName, content );
							self.pushFieldSuggestion( fieldName, content );
						} )
						.fail( function ( message ) {
							self.showAlert( 'error', message );
						} )
						.always( function () {
							self.setBundleBusy( false );
							self.toggleBundleFieldSkeleton( fieldName, false );
							$button.html( originalHtml );
						} );
				}
			);

			// Per-field pager: step through that field's generated suggestions.
			this.$bundleResultStep.on(
				'click',
				'.storesuite-ai-field-prev, .storesuite-ai-field-next',
				function ( event ) {
					event.preventDefault();
					const $button = $( this );
					if ( $button.prop( 'disabled' ) ) {
						return;
					}
					const fieldName = $button
						.closest( '.storesuite-ai-field-pager' )
						.data( 'field' );
					// Ignore while this field is mid-regeneration (skeleton shown).
					if ( self.bundleField( fieldName ).prop( 'hidden' ) ) {
						return;
					}
					self.saveFieldEdit( fieldName );
					const delta = $button.hasClass( 'storesuite-ai-field-next' )
						? 1
						: -1;
					self.showFieldSuggestion(
						fieldName,
						self.bundleIndex[ fieldName ] + delta
					);
				}
			);

			// Insert all three drafted fields into the product form.
			this.$bundleInsert.on( 'click', function ( event ) {
				event.preventDefault();
				self.insertIntoField(
					'title',
					$.trim( self.$bundleTitle.val() || '' )
				);
				self.insertIntoField(
					'short_description',
					self.$bundleShort.val() || ''
				);
				self.insertIntoField(
					'description',
					self.$bundleDescription.val() || ''
				);
				if ( self.sharedModal && self.$bundleModal.length ) {
					self.sharedModal.close( self.$bundleModal );
				}
			} );
		},

		// --- Product image generation --------------------------------------
		//
		// A small button beside the Product Image label opens a modal: describe
		// the image, Generate a preview, then Insert it (which side-loads it into
		// the media library and sets it as the product image).

		// Back to the prompt-entry state with no preview.
		resetImageModal() {
			this.$imagePrompt.val( '' ).prop( 'readonly', false );
			this.$imagePreviewImg.attr( 'src', '' );
			this.$imagePreview.prop( 'hidden', true );
			this.$imageSkeleton.prop( 'hidden', true );
			this.$imageRegenerate.prop( 'hidden', true );
			this.$imageInsert.prop( 'hidden', true );
			this.$imageSubmit.prop( 'hidden', false );
			this.imageToken = '';
		},

		// Generate (or regenerate) an image preview from the typed prompt.
		generateImage( $button, busyLabel ) {
			const self = this;
			const prompt = $.trim( this.$imagePrompt.val() || '' );
			if ( ! prompt ) {
				this.showAlert( 'error', this.imageConfig.prompt_required );
				return;
			}
			const originalText = $button.text();
			this.$imageSubmit.prop( 'disabled', true );
			this.$imageRegenerate.prop( 'disabled', true );
			this.$imageInsert.prop( 'disabled', true );
			$button.text( busyLabel );
			// Lock the prompt and show a skeleton while the image is generated.
			this.$imagePrompt.prop( 'readonly', true );
			this.$imagePreview.prop( 'hidden', true );
			this.$imageSkeleton.prop( 'hidden', false );

			this.aiPost( {
				action: this.imageConfig.generate_action,
				nonce: this.aiConfig.nonce,
				prompt,
			} )
				.done( function ( data ) {
					self.imageToken = data.token || '';
					self.$imagePreviewImg.attr( 'src', data.preview || '' );
					self.$imagePreview.prop( 'hidden', false );
					self.$imageSubmit.prop( 'hidden', true );
					self.$imageRegenerate.prop( 'hidden', false );
					self.$imageInsert.prop( 'hidden', false );
				} )
				.fail( function ( message ) {
					self.showAlert( 'error', message );
				} )
				.always( function () {
					self.$imageSkeleton.prop( 'hidden', true );
					self.$imagePrompt.prop( 'readonly', false );
					self.$imageSubmit.prop( 'disabled', false );
					self.$imageRegenerate.prop( 'disabled', false );
					self.$imageInsert.prop( 'disabled', false );
					$button.text( originalText );
				} );
		},

		// Wire the inserted attachment into the product image fields,
		// mirroring the manual media-library upload flow.
		applyProductImage( attachmentId, url ) {
			$( '#product_thumbnail_id' ).val( attachmentId );
			$( '#product_thumbnail_url' ).val( url );
			$( '#product_thumb_img' ).html(
				'<img src="' + url + '" alt="" />'
			);
			const $container = $( '#product-single-image' );
			$container.addClass( 'image-drop-bg' );
			$container
				.find( '.image-drop-text span' )
				.text(
					( typeof storeSuiteFrontScript !== 'undefined' &&
						storeSuiteFrontScript.remove_image_text ) ||
						''
				);
			// Notify the dirty-state tracker (sticky "Unsaved Changes" bar).
			$( '#product_thumbnail_id' ).trigger( 'change' );
		},

		// Append the inserted attachment to the product gallery, mirroring the
		// manual gallery upload flow.
		appendGalleryImage( attachmentId, url ) {
			const idStr = String( attachmentId );
			const ids = this.splitCsv( $( '#product_image_gallery' ).val() );
			if ( $.inArray( idStr, ids ) !== -1 ) {
				return;
			}
			const urls = this.splitCsv(
				$( '#product_image_gallery_url' ).val()
			);
			ids.push( idStr );
			urls.push( url );
			$( '#product_gallery_img' ).append(
				'<div class="preview-image-box"><a href="#" class="remove-gallery-image" data-id="' +
					idStr +
					'">×</a><img src="' +
					url +
					'" alt="" /></div>'
			);
			$( '#product_image_gallery' ).val( ids.join( ',' ) );
			$( '#product_image_gallery_url' ).val( urls.join( ',' ) );
			$( '#product_image_gallery' ).trigger( 'change' );
			$( '#product-gallery-images' ).addClass(
				'sm-gallery-image-uploader'
			);
			$( '.product-gallery-images-wrapper' ).removeClass(
				'gallery-has-no-image'
			);
		},

		initImageModal() {
			const self = this;

			this.imageConfig = this.aiConfig.image || null;
			if ( ! this.imageConfig || ! this.imageConfig.enabled ) {
				return;
			}

			this.$imageModal = $( '#storesuite-ai-image-modal' );
			this.$imagePrompt = this.$imageModal.find(
				'#storesuite-ai-image-prompt'
			);
			this.$imagePreview = this.$imageModal.find(
				'.storesuite-ai-image-preview'
			);
			this.$imagePreviewImg = this.$imagePreview.find( 'img' );
			this.$imageSkeleton = this.$imageModal.find(
				'.storesuite-ai-image-skeleton'
			);
			this.$imageSubmit = this.$imageModal.find(
				'.storesuite-ai-image-submit'
			);
			this.$imageRegenerate = this.$imageModal.find(
				'.storesuite-ai-image-regenerate'
			);
			this.$imageInsert = this.$imageModal.find(
				'.storesuite-ai-image-insert'
			);
			this.$imageDismissButtons = this.$imageModal.find(
				this.MODAL_CLOSE_SELECTOR
			);
			this.imageToken = ''; // Server-side handle to the last generated image.
			this.imageTarget = 'featured'; // Where Insert puts it: featured or gallery.

			if ( this.sharedModal && this.$imageModal.length ) {
				this.sharedModal.initOverlay( this.$imageModal, {
					fade: true,
					closeSelector: this.MODAL_CLOSE_SELECTOR,
				} );
			}

			// Label button: open the image modal fresh for the clicked target.
			$( document ).on(
				'click',
				'.storesuite-ai-image-generate',
				function ( event ) {
					event.preventDefault();
					event.stopPropagation();
					if ( ! self.sharedModal || ! self.$imageModal.length ) {
						return;
					}
					self.imageTarget =
						$( this ).data( 'target' ) === 'gallery'
							? 'gallery'
							: 'featured';
					self.resetImageModal();
					self.sharedModal.open( self.$imageModal );
				}
			);

			this.$imageSubmit.on( 'click', function ( event ) {
				event.preventDefault();
				self.generateImage(
					self.$imageSubmit,
					self.strings.generating
				);
			} );

			this.$imageRegenerate.on( 'click', function ( event ) {
				event.preventDefault();
				self.generateImage(
					self.$imageRegenerate,
					self.strings.regenerating
				);
			} );

			// Side-load the generated image and set it as the product image.
			this.$imageInsert.on( 'click', function ( event ) {
				event.preventDefault();
				if ( ! self.imageToken ) {
					return;
				}
				const originalText = self.$imageInsert.text();
				self.$imageInsert
					.prop( 'disabled', true )
					.text( self.imageConfig.inserting );
				self.$imageRegenerate.prop( 'disabled', true );
				// Lock the prompt and dismiss controls while the image is inserted.
				self.$imagePrompt.prop( 'readonly', true );
				self.$imageDismissButtons.prop( 'disabled', true );

				self.aiPost( {
					action: self.imageConfig.insert_action,
					nonce: self.aiConfig.nonce,
					token: self.imageToken,
				} )
					.done( function ( data ) {
						if ( 'gallery' === self.imageTarget ) {
							self.appendGalleryImage( data.id, data.url );
						} else {
							self.applyProductImage( data.id, data.url );
						}
						if ( self.sharedModal && self.$imageModal.length ) {
							self.sharedModal.close( self.$imageModal );
						}
					} )
					.fail( function ( message ) {
						self.showAlert( 'error', message );
					} )
					.always( function () {
						self.$imagePrompt.prop( 'readonly', false );
						self.$imageDismissButtons.prop( 'disabled', false );
						self.$imageInsert
							.prop( 'disabled', false )
							.text( originalText );
						self.$imageRegenerate.prop( 'disabled', false );
					} );
			} );
		},
	};
	StoreFrontProductAI.init();
} )( jQuery );
