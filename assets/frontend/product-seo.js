/* global jQuery, StoreSuite_ProductSeo, StoreSuiteSeoPreview, tinymce */
/**
 * Yoast SEO card on the StoreSuite product form.
 *
 * Thin adapter over the shared snippet-preview engine (seo-preview.js): it
 * only knows where the product form keeps its title, slug, descriptions and
 * permalink. Reads the localized StoreSuite_ProductSeo global enqueued by
 * YoastSeoIntegration.
 *
 * @param {Function} $ jQuery.
 */
( function ( $ ) {
	const productSeo = {
		// The global's name is set by wp_localize_script() and follows the plugin's PHP naming.
		/* eslint-disable camelcase */
		config:
			typeof StoreSuite_ProductSeo !== 'undefined'
				? StoreSuite_ProductSeo
				: null,
		/* eslint-enable camelcase */
		cardSelector: '#storesuite-yoast-seo',
		titleSelector: '#storesuite_yoast_title',
		descSelector: '#storesuite_yoast_metadesc',
		keyphraseSelector: '#storesuite_yoast_focuskw',
		cornerstoneSelector: '#storesuite_yoast_is_cornerstone',

		/**
		 * Bind the product form fields that feed the preview.
		 *
		 * @param {Function} render Re-renders the preview.
		 */
		bindSources( render ) {
			$( '#product_title, #product_slug, #product_short_description' ).on(
				'input change',
				render
			);

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

		getContext() {
			const title = ( $( '#product_title' ).val() || '' ).trim();
			const shortDesc = this.stripTags(
				$( '#product_short_description' ).val() || ''
			);
			const excerpt = this.truncate(
				shortDesc || this.getDescriptionText(),
				this.config.descMaxLength
			);

			// category/primary_category/tag come resolved from the server:
			// Yoast reads them from the blog taxonomies, which the product's
			// own category and tag fields never touch.
			return $.extend( {}, this.config.replacements, {
				title,
				excerpt,
				excerpt_only: shortDesc,
				focuskw: ( this.$keyphrase.val() || '' ).trim(),
				id: $( 'input[name="product_id"]' ).val() || '',
			} );
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
	};

	$( function () {
		StoreSuiteSeoPreview.create( productSeo );
	} );
} )( jQuery );
