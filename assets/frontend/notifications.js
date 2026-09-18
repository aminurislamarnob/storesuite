/**
 * Dashboard notifications: bell badge polling + seen handling.
 *
 * Polls the REST endpoint with the last-known notification id (cursor).
 * When nothing changed the server answers from cache without touching the
 * notifications table, so idle polls stay near-free. Polling pauses while
 * the tab is hidden and resumes with an immediate poll on focus.
 *
 * @param {Object} $ jQuery.
 */
( function ( $ ) {
	'use strict';

	const cfg = window.StoreSuite_Notifications;

	if ( ! cfg ) {
		return;
	}

	let cursor = parseInt( cfg.cursor, 10 ) || 0;
	const intervalMs = ( parseInt( cfg.interval, 10 ) || 60 ) * 1000;
	let timer = null;
	const baseTitle = document.title.replace( /^\(\d+\+?\)\s*/, '' );

	const $bell = $( '.storesuite-notifications-dropdown' );
	const $badge = $bell.find( '.storesuite-notification-badge' );
	const $list = $bell.find( '.storesuite-notifications-menu-list' );

	function updateBadge( count ) {
		if ( count > 0 ) {
			$badge
				.text( count > 9 ? '9+' : String( count ) )
				.removeAttr( 'hidden' );
			document.title =
				'(' + ( count > 9 ? '9+' : count ) + ') ' + baseTitle;
		} else {
			$badge.attr( 'hidden', 'hidden' );
			document.title = baseTitle;
		}
	}

	function renderItems( items ) {
		if ( ! items || ! items.length ) {
			return;
		}

		$list.empty();

		items.forEach( function ( item ) {
			const $link = $( '<a>', {
				class: 'dropdown-link',
				href: item.url ? item.url : cfg.notifications_url,
			} );
			$link.append(
				$( '<span>', {
					class: 'storesuite-notification-title',
					text: item.title,
				} )
			);
			$link.append(
				$( '<span>', {
					class: 'storesuite-notification-message',
					text: item.message,
				} )
			);
			$link.append(
				$( '<span>', {
					class: 'storesuite-notification-time',
					text: item.time_ago,
				} )
			);

			$list.append(
				$( '<li>', {
					class:
						'storesuite-notification-item' +
						( item.is_seen ? '' : ' is-unseen' ),
				} ).append( $link )
			);
		} );
	}

	function poll() {
		window
			.fetch( cfg.rest_url + '/poll?cursor=' + cursor, {
				headers: { 'X-WP-Nonce': cfg.nonce },
				credentials: 'same-origin',
			} )
			.then( function ( response ) {
				return response.ok ? response.json() : null;
			} )
			.then( function ( data ) {
				if ( ! data ) {
					return;
				}

				cursor = data.latest_id;

				if ( data.changed ) {
					updateBadge( data.unseen_count );
					renderItems( data.items );
				}
			} )
			.catch( function () {
				// Network hiccup — the next tick retries.
			} );
	}

	function markAllSeen() {
		window
			.fetch( cfg.rest_url + '/seen', {
				method: 'POST',
				headers: {
					'X-WP-Nonce': cfg.nonce,
					'Content-Type': 'application/json',
				},
				credentials: 'same-origin',
				body: JSON.stringify( { all: true } ),
			} )
			.then( function ( response ) {
				return response.ok ? response.json() : null;
			} )
			.then( function ( data ) {
				if ( ! data ) {
					return;
				}

				updateBadge( data.unseen_count );
				$( '.storesuite-notification-item' ).removeClass( 'is-unseen' );
				$(
					'.storesuite-notification-unseen-dot, .storesuite-notifications-mark-all'
				).remove();
			} );
	}

	function startPolling() {
		if ( ! timer ) {
			timer = window.setInterval( poll, intervalMs );
		}
	}

	function stopPolling() {
		if ( timer ) {
			window.clearInterval( timer );
			timer = null;
		}
	}

	// Opening the bell dropdown marks everything seen. The shared dropdown
	// toggle in global.js runs first, so check the menu state shortly after
	// the slide animation kicks off.
	$( document ).on(
		'click',
		'.storesuite-notifications-dropdown .storesuite-dropdown-icon',
		function () {
			const $menu = $bell.find( '.storesuite-dropdown-menu' );

			window.setTimeout( function () {
				if ( $menu.is( ':visible' ) && ! $badge.attr( 'hidden' ) ) {
					markAllSeen();
				}
			}, 250 );
		}
	);

	// "Mark all as read" on the notifications page.
	$( document ).on(
		'click',
		'.storesuite-notifications-mark-all',
		function () {
			markAllSeen();
		}
	);

	function clearAll() {
		window
			.fetch( cfg.rest_url + '/clear', {
				method: 'POST',
				headers: { 'X-WP-Nonce': cfg.nonce },
				credentials: 'same-origin',
			} )
			.then( function ( response ) {
				return response.ok ? response.json() : null;
			} )
			.then( function ( data ) {
				if ( data ) {
					window.location.reload();
				}
			} );
	}

	// "Clear all" on the notifications page: deletes every notification for
	// the current user after a SweetAlert2 confirmation, then reloads so
	// the empty state renders.
	$( document ).on(
		'click',
		'.storesuite-notifications-clear-all',
		function () {
			// Fallback for the edge case where SweetAlert2 did not load.
			if ( ! window.Swal ) {
				// eslint-disable-next-line no-alert
				if ( window.confirm( cfg.i18n.confirm_clear_all ) ) {
					clearAll();
				}
				return;
			}

			window.Swal.fire( {
				title: cfg.i18n.are_you_sure,
				text: cfg.i18n.confirm_clear_all,
				icon: 'warning',
				showCancelButton: true,
				confirmButtonColor: '#d63638',
				confirmButtonText: cfg.i18n.yes_clear,
				cancelButtonText: cfg.i18n.cancel_button,
			} ).then( function ( result ) {
				if ( result.isConfirmed ) {
					clearAll();
				}
			} );
		}
	);

	// Pause while hidden; poll immediately on return.
	document.addEventListener( 'visibilitychange', function () {
		if ( document.hidden ) {
			stopPolling();
		} else {
			poll();
			startPolling();
		}
	} );

	startPolling();
} )( window.jQuery );
