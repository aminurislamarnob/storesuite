/* global StoreSuite_AiSearch, Swal */
/**
 * Natural-language AI search on the Products list.
 *
 * Sends the typed request to `storesuite_ai_product_search`, which returns a
 * validated filter set and the matching list URL; the browser then navigates
 * there so the result is an ordinary, bookmarkable filtered list.
 */
( function ( $ ) {
	'use strict';

	var StoreSuiteAiSearch = {
		busy: false,

		config: function () {
			return typeof StoreSuite_AiSearch !== 'undefined' ? StoreSuite_AiSearch : {};
		},

		i18n: function () {
			return this.config().i18n || {};
		},

		init: function () {
			var self = this;
			this.$form = $( '#storesuite-ai-search' );

			if ( ! this.$form.length ) {
				return;
			}

			this.$input = this.$form.find( '#storesuite-ai-search-query' );
			this.$submit = this.$form.find( '.storesuite-ai-search-submit' );
			this.$hint = this.$form.find( '.storesuite-ai-search-hint' );

			this.$form.on( 'submit', function ( event ) {
				event.preventDefault();
				self.submit();
			} );
		},

		setBusy: function ( busy ) {
			this.busy = busy;
			this.$submit.prop( 'disabled', busy );
			this.$input.prop( 'disabled', busy );
			this.$form.toggleClass( 'is-busy', busy );
			this.$submit.find( '.storesuite-ai-search-spinner' ).attr( 'hidden', busy ? null : 'hidden' );
			this.$submit.find( '.storesuite-ai-search-submit-label' ).attr( 'hidden', busy ? 'hidden' : null );
		},

		showHint: function ( text, isError ) {
			if ( ! text ) {
				this.$hint.attr( 'hidden', 'hidden' ).text( '' );
				return;
			}
			this.$hint
				.toggleClass( 'is-error', !! isError )
				.text( text )
				.removeAttr( 'hidden' );
		},

		submit: function () {
			var self = this;
			var query = $.trim( this.$input.val() || '' );
			var i18n = this.i18n();

			if ( this.busy ) {
				return;
			}

			if ( ! query ) {
				this.showHint( i18n.empty_query, true );
				this.$input.trigger( 'focus' );
				return;
			}

			this.setBusy( true );
			this.showHint( i18n.thinking, false );

			$.ajax( {
				url: this.config().ajax_url,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'storesuite_ai_product_search',
					nonce: this.config().nonce,
					query: query,
				},
			} )
				.done( function ( response ) {
					if ( response && response.success && response.data && response.data.url ) {
						self.showHint( i18n.applying, false );
						window.location.href = response.data.url;
						return;
					}

					self.setBusy( false );
					self.showHint(
						( response && response.data && response.data.message ) || i18n.unexpected_error,
						true
					);
				} )
				.fail( function ( xhr ) {
					self.setBusy( false );
					self.showHint(
						xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
							? xhr.responseJSON.data.message
							: i18n.unexpected_error,
						true
					);
				} );
		},
	};

	$( function () {
		StoreSuiteAiSearch.init();
	} );
} )( jQuery );
