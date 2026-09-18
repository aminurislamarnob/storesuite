( function ( $ ) {
	var StoreSuiteGlobal = {
		init: function () {
			this.handleSidebarCollapseToggle();
			this.handleSubmenuToggle();
			this.handleDropdown();
			this.closeDropdownOutside();
			this.handleThemeToggle();
		},

		handleThemeToggle: function () {
			var themePreferenceStorageKey = 'storesuite_theme_mode';
			var $themeToggle = $( '.storesuite-theme-toggle' );
			var $root = $( document.documentElement );

			if ( ! $themeToggle.length ) {
				return;
			}

			function persistThemePreference( mode ) {
				try {
					localStorage.setItem( themePreferenceStorageKey, mode );
				} catch ( storageError ) {}
			}

			function currentMode() {
				return $root.attr( 'data-theme' ) === 'dark' ? 'dark' : 'light';
			}

			// Dark styles for the TinyMCE content iframe. WordPress bundles
			// TinyMCE 4 (lightgray skin) with no dark skin, and its init
			// serialization mangles a server-side content_style that contains
			// quotes — so we inject the stylesheet straight into the iframe and
			// gate it on a data-theme attribute we toggle here.
			function buildEditorDarkCss() {
				var s = 'html[data-theme=dark] body.mce-content-body';
				return (
					s + '{background-color:#243449;color:rgb(203 213 225);}' +
					s + ' h1,' + s + ' h2,' + s + ' h3,' + s + ' h4,' + s + ' h5,' + s + ' h6{color:#f1f5f9;}' +
					s + ' a{color:#3b6ce0;}' +
					s + ' blockquote{border-left-color:rgb(51 65 85);color:rgb(148 163 184);}' +
					s + ' hr{border-color:rgb(51 65 85);}' +
					s + ' table td,' + s + ' table th{border-color:rgb(51 65 85);}' +
					s + ' code,' + s + ' pre{background-color:#172033;color:rgb(203 213 225);}'
				);
			}

			// Mirror the dashboard theme onto a single TinyMCE content iframe.
			function themeEditor( editor ) {
				var doc = editor.getDoc && editor.getDoc();
				if ( ! doc || ! doc.documentElement ) {
					return;
				}
				if ( ! doc.getElementById( 'storesuite-editor-dark' ) ) {
					var style = doc.createElement( 'style' );
					style.id = 'storesuite-editor-dark';
					style.textContent = buildEditorDarkCss();
					( doc.head || doc.documentElement ).appendChild( style );
				}
				doc.documentElement.setAttribute( 'data-theme', currentMode() );
			}

			// Editors initialize asynchronously, so handle both editors that are
			// already up and ones that init later (including after a toggle).
			function syncEditorsTheme() {
				if ( ! window.tinymce || ! window.tinymce.editors ) {
					return;
				}
				window.tinymce.editors.forEach( function ( editor ) {
					if ( editor.initialized ) {
						themeEditor( editor );
					} else {
						editor.on( 'init', function () {
							themeEditor( editor );
						} );
					}
				} );
			}

			// The sidebar logo and icon can each have a dark variant. Both URLs
			// ride along on the image as data attributes so the swap is a src
			// change rather than a re-render.
			function applyBrandingImages( mode ) {
				$( '.storesuite-sidebar-logo [data-storesuite-dark-src]' ).attr(
					'src',
					function () {
						return (
							$( this ).data(
								mode === 'dark'
									? 'storesuiteDarkSrc'
									: 'storesuiteLightSrc'
							) || $( this ).attr( 'src' )
						);
					}
				);
			}

			function applyThemeMode( mode ) {
				$root.attr( 'data-theme', mode );
				$themeToggle.attr(
					'aria-pressed',
					mode === 'dark' ? 'true' : 'false'
				);
				applyBrandingImages( mode );
				syncEditorsTheme();
			}

			// TinyMCE (wp-tinymce.js) often loads after this script, so polling
			// avoids missing editors that initialize later — e.g. when the page
			// is reloaded while dark mode is active.
			function whenTinymceReady( onReady ) {
				if ( window.tinymce ) {
					onReady();
					return;
				}
				var attempts = 0;
				var poll = setInterval( function () {
					attempts++;
					if ( window.tinymce ) {
						clearInterval( poll );
						onReady();
					} else if ( attempts > 50 ) {
						clearInterval( poll );
					}
				}, 100 );
			}

			// Reflect the theme resolved by the inline head script on load.
			applyThemeMode( currentMode() );

			// Theme existing editors plus any added after TinyMCE is ready.
			whenTinymceReady( function () {
				syncEditorsTheme();
				window.tinymce.on( 'AddEditor', function ( event ) {
					event.editor.on( 'init', function () {
						themeEditor( event.editor );
					} );
				} );
			} );

			$themeToggle.on( 'click', function () {
				var nextMode = currentMode() === 'dark' ? 'light' : 'dark';
				applyThemeMode( nextMode );
				persistThemePreference( nextMode );
			} );
		},

		handleSubmenuToggle: function () {
			$( document ).on(
				'click',
				'ul.storesuite-dashboard-menu li.has-submenu > a',
				function ( e ) {
					// In collapsed sidebar submenus appear as CSS hover flyouts — don't intercept.
					if (
						$( '.my-storesuite-container' ).hasClass(
							'storesuite-sidebar-collapsed'
						)
					) {
						return;
					}
					e.preventDefault();
					var $li = $( this ).closest( 'li' );
					var $submenu = $li.children( '.submenu' );
					if ( $li.hasClass( 'is-open' ) ) {
						$submenu.stop( true, false ).slideUp( 250, function () {
							$li.removeClass( 'is-open' );
							$submenu.css( 'display', '' );
						} );
					} else {
						$li.addClass( 'is-open' );
						$submenu
							.stop( true, false )
							.hide()
							.slideDown( 250, function () {
								$submenu.css( 'display', '' );
							} );
					}
				}
			);
		},

		handleSidebarCollapseToggle: function () {
			var collapsedPreferenceStorageKey = 'storesuite_sidebar_collapsed';
			var minViewportWidthForCollapsedSidebar = 768;
			var maxTabletViewportWidth = 1024;
			var $dashboardContainer = $( '.my-storesuite-container' );
			var $sidebarCollapseToggle = $( '.storesuite-sidebar-trigger' );

			if (
				! $dashboardContainer.length ||
				! $sidebarCollapseToggle.length
			) {
				return;
			}

			function isViewportWideEnoughForCollapsedSidebar() {
				// Use the layout viewport (clientWidth) so this matches the CSS
				// media queries; window.innerWidth tracks the visual viewport and
				// can diverge under zoom / dev tools, desyncing JS from CSS.
				return (
					document.documentElement.clientWidth >=
					minViewportWidthForCollapsedSidebar
				);
			}

			function applySidebarCollapsedState( isCollapsed ) {
				$dashboardContainer.toggleClass(
					'storesuite-sidebar-collapsed',
					isCollapsed
				);
				$sidebarCollapseToggle.attr(
					'aria-expanded',
					isCollapsed ? 'false' : 'true'
				);
			}

			function isTabletViewport() {
				var viewportWidth = document.documentElement.clientWidth;
				return (
					viewportWidth >= minViewportWidthForCollapsedSidebar &&
					viewportWidth <= maxTabletViewportWidth
				);
			}

			function persistCollapsedPreference( isCollapsed ) {
				try {
					// '0' is stored explicitly (instead of removing the key) so an
					// expanded choice survives the collapsed-by-default tablet range.
					localStorage.setItem(
						collapsedPreferenceStorageKey,
						isCollapsed ? '1' : '0'
					);
				} catch ( storageError ) {}
			}

			function readCollapsedPreferenceFromStorage() {
				var storedPreference = null;
				try {
					storedPreference = localStorage.getItem(
						collapsedPreferenceStorageKey
					);
				} catch ( storageError ) {}
				if ( '1' === storedPreference ) {
					return true;
				}
				if ( '0' === storedPreference ) {
					return false;
				}
				// No explicit choice: collapse on tablets, expand on desktop.
				return isTabletViewport();
			}

			function applyMobileOpenState( isOpen ) {
				$dashboardContainer.toggleClass(
					'storesuite-sidebar-mobile-open',
					isOpen
				);
				$sidebarCollapseToggle.attr(
					'aria-expanded',
					isOpen ? 'true' : 'false'
				);
			}

			function syncSidebarCollapsedState() {
				if ( ! isViewportWideEnoughForCollapsedSidebar() ) {
					// Narrow viewport: drop the desktop collapse, start closed.
					applySidebarCollapsedState( false );
					applyMobileOpenState( false );
					return;
				}
				// Wide viewport: drop the off-canvas state, restore preference.
				$dashboardContainer.removeClass(
					'storesuite-sidebar-mobile-open'
				);
				applySidebarCollapsedState(
					readCollapsedPreferenceFromStorage()
				);
			}

			function handleSidebarToggleInteraction( event ) {
				event.preventDefault();

				// Narrow viewport: the trigger opens/closes the off-canvas drawer.
				if ( ! isViewportWideEnoughForCollapsedSidebar() ) {
					applyMobileOpenState(
						! $dashboardContainer.hasClass(
							'storesuite-sidebar-mobile-open'
						)
					);
					return;
				}

				var shouldBeCollapsed = ! $dashboardContainer.hasClass(
					'storesuite-sidebar-collapsed'
				);
				applySidebarCollapsedState( shouldBeCollapsed );
				persistCollapsedPreference( shouldBeCollapsed );
			}

			// Close the off-canvas drawer when tapping the backdrop (outside
			// the sidebar and away from the trigger).
			function handleOutsideClickToClose( event ) {
				if (
					isViewportWideEnoughForCollapsedSidebar() ||
					! $dashboardContainer.hasClass(
						'storesuite-sidebar-mobile-open'
					)
				) {
					return;
				}
				var $target = $( event.target );
				if (
					$target.closest( '.my-storesuite-sidebar' ).length ||
					$target.closest( '.storesuite-sidebar-trigger' ).length
				) {
					return;
				}
				applyMobileOpenState( false );
			}

			syncSidebarCollapsedState();
			$( window ).on( 'resize', syncSidebarCollapsedState );
			$sidebarCollapseToggle.on(
				'click',
				handleSidebarToggleInteraction
			);
			$( document ).on( 'click', handleOutsideClickToClose );
		},

		handleDropdown: function () {
			$( document ).on(
				'click',
				'.storesuite-dropdown-icon',
				function () {
					var $menu = $( this )
						.closest( '.storesuite-dropdown' )
						.find( '.storesuite-dropdown-menu' );
					$( '.storesuite-dropdown-menu' )
						.not( $menu )
						.stop( true, false )
						.slideUp( 200 );
					$menu.stop( true, false ).slideToggle( 200 );
				}
			);
		},

		closeDropdownOutside: function () {
			$( document ).on( 'click', function ( event ) {
				if (
					! $( event.target ).closest( '.storesuite-dropdown' ).length
				) {
					$( '.storesuite-dropdown-menu' )
						.stop( true, false )
						.slideUp( 200 );
				}
			} );
		},
	};

	StoreSuiteGlobal.init();
} )( jQuery );
