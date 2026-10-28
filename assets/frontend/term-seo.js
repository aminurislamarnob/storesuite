/* global jQuery, StoreSuite_TermSeo, StoreSuiteSeoPreview */
/**
 * Yoast SEO card on the StoreSuite category, tag and brand forms.
 *
 * Thin adapter over the shared snippet-preview engine (seo-preview.js). The
 * term's name, description and slug come live from the form, so typing in
 * them updates the preview; the localized values only fill in what the form
 * cannot supply. Reads the StoreSuite_TermSeo global enqueued by
 * YoastSeoTermIntegration.
 *
 * @param {Function} $ jQuery.
 */
( function ( $ ) {
	// The global's name is set by wp_localize_script() and follows the plugin's PHP naming.
	/* eslint-disable camelcase */
	const config =
		typeof StoreSuite_TermSeo !== 'undefined' ? StoreSuite_TermSeo : null;
	/* eslint-enable camelcase */
	const fields = ( config && config.fields ) || {};
	const PREFIX = '#storesuite_yoast_term_';

	const termSeo = {
		config,
		cardSelector: '#storesuite-yoast-term-seo',
		titleSelector: PREFIX + 'title',
		descSelector: PREFIX + 'metadesc',
		keyphraseSelector: PREFIX + 'focuskw',
		cornerstoneSelector: PREFIX + 'is_cornerstone',

		/**
		 * Bind the term form fields that feed the preview.
		 *
		 * @param {Function} render Re-renders the preview.
		 */
		bindSources( render ) {
			$(
				[ fields.name, fields.slug, fields.description ]
					.filter( Boolean )
					.join( ',' )
			).on( 'input change', render );
		},

		/**
		 * A live form value, or the localized one when the form has no such field.
		 *
		 * @param {string} selector Field selector.
		 * @param {string} fallback Value used when the field is absent.
		 * @return {string} Trimmed value.
		 */
		getFieldValue( selector, fallback ) {
			const $field = selector ? $( selector ) : $();
			return (
				$field.length ? $field.val() || '' : fallback || ''
			).trim();
		},

		getContext() {
			const data = this.config;
			const name = this.getFieldValue(
				fields.name,
				data.replacements.term_title
			);
			const description = this.stripTags(
				this.getFieldValue(
					fields.description,
					data.replacements.term_description
				)
			);
			const context = $.extend( {}, data.replacements, {
				term_title: name,
				term_description: description,
				// Google's fallback text when there is no meta description.
				excerpt: this.truncate( description, data.descMaxLength ),
				focuskw: ( this.$keyphrase.val() || '' ).trim(),
			} );

			context[ data.descriptionAlias ] = description;

			if ( data.hierarchical ) {
				// Ancestors come from the server; the term's own name stays live.
				context.term_hierarchy = [ data.hierarchyBase, name ]
					.filter( Boolean )
					.join( ' ' + data.replacements.sep + ' ' );
			}

			return context;
		},

		getUrlParts() {
			const name = this.getFieldValue( fields.name, '' );
			const slug =
				this.getFieldValue( fields.slug, '' ) || this.slugify( name );
			const parts = ( this.config.urlBase || this.config.homeUrl )
				.replace( /^https?:\/\//, '' )
				.split( '/' )
				.filter( Boolean );

			if ( slug ) {
				parts.push( slug );
			}
			return parts;
		},
	};

	$( function () {
		StoreSuiteSeoPreview.create( termSeo );
	} );
} )( jQuery );
