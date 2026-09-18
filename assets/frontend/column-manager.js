/* global StoreSuite_ColumnManager */
/**
 * "Columns" toolbar dropdown: show/hide list table columns per user.
 *
 * Toggling a checkbox hides or shows every header/cell tagged with the same
 * data-col key immediately, then saves the hidden set to user meta over AJAX.
 * The dropdown itself opens and closes through the shared .storesuite-dropdown
 * handler in global.js.
 */
( function ( $ ) {
	'use strict';

	var StoreSuiteColumnManager = {
		timers: {},

		config: function () {
			return typeof StoreSuite_ColumnManager !== 'undefined'
				? StoreSuite_ColumnManager
				: {};
		},

		init: function () {
			var self = this;

			$( document ).on(
				'change',
				'.storesuite-column-manager input[type="checkbox"]',
				function () {
					var $manager = $( this ).closest( '.storesuite-column-manager' );
					self.applyColumn( $( this ).val(), $( this ).is( ':checked' ) );
					self.updateCount( $manager );
					self.save( $manager );
				}
			);

			$( document ).on( 'click', '.storesuite-column-manager-reset', function ( event ) {
				event.preventDefault();
				var $manager = $( this ).closest( '.storesuite-column-manager' );
				$manager
					.find( 'input[type="checkbox"]:not(:disabled)' )
					.each( function () {
						$( this ).prop( 'checked', true );
						self.applyColumn( $( this ).val(), true );
					} );
				self.updateCount( $manager );
				self.save( $manager );
			} );

			// Keep the dropdown open while clicking inside it.
			$( document ).on( 'click', '.storesuite-column-manager-menu', function ( event ) {
				event.stopPropagation();
			} );

			$( document ).on( 'click', '.storesuite-column-manager-toggle', function () {
				var $toggle = $( this );
				setTimeout( function () {
					$toggle.attr(
						'aria-expanded',
						$toggle.siblings( '.storesuite-dropdown-menu' ).is( ':visible' ) ? 'true' : 'false'
					);
				}, 250 );
			} );
		},

		applyColumn: function ( key, visible ) {
			var $cells = $( 'th[data-col="' + key + '"], td[data-col="' + key + '"]' );
			if ( visible ) {
				$cells.removeAttr( 'hidden' );
			} else {
				$cells.attr( 'hidden', 'hidden' );
			}
		},

		hiddenKeys: function ( $manager ) {
			return $manager
				.find( 'input[type="checkbox"]:not(:disabled):not(:checked)' )
				.map( function () {
					return $( this ).val();
				} )
				.get();
		},

		updateCount: function ( $manager ) {
			var count = this.hiddenKeys( $manager ).length;
			var $badge = $manager.find( '.storesuite-column-manager-count' );

			if ( ! count ) {
				$badge.remove();
				return;
			}

			if ( ! $badge.length ) {
				$badge = $( '<span class="storesuite-filter-count storesuite-column-manager-count"></span>' );
				$manager.find( '.storesuite-column-manager-toggle' ).append( $badge );
			}
			$badge.text( String( count ) );
		},

		save: function ( $manager ) {
			var self = this;
			var table = $manager.data( 'table' );
			var config = this.config();

			if ( ! table || ! config.ajax_url ) {
				return;
			}

			// Coalesce rapid toggles into one request.
			clearTimeout( this.timers[ table ] );
			this.timers[ table ] = setTimeout( function () {
				$.ajax( {
					url: config.ajax_url,
					type: 'POST',
					dataType: 'json',
					data: {
						action: 'storesuite_save_hidden_columns',
						security: config.nonce,
						table: table,
						hidden: self.hiddenKeys( $manager ),
					},
				} );
			}, 300 );
		},
	};

	$( function () {
		StoreSuiteColumnManager.init();
	} );
} )( jQuery );
