/**
 * Brik theme: color mode, mobile navigation and submenus.
 */
( function () {
	'use strict';

	var root = document.documentElement;
	var i18n = ( window.brikTheme && window.brikTheme.i18n ) || {};
	var KEY = 'brik-theme'; // Shared with Brik Builder's theme toggle module.
	var media = window.matchMedia ? window.matchMedia( '(prefers-color-scheme: dark)' ) : null;

	/* Color mode ---------------------------------------------------------- */

	function stored() {
		try {
			return localStorage.getItem( KEY ) || 'system';
		} catch ( e ) {
			return 'system';
		}
	}

	function isDark( mode ) {
		return 'dark' === mode || ( 'light' !== mode && !! media && media.matches );
	}

	function syncButtons() {
		var dark = root.classList.contains( 'dark' );
		document.querySelectorAll( '.mode-toggle' ).forEach( function ( button ) {
			button.setAttribute( 'aria-pressed', dark ? 'true' : 'false' );
			if ( i18n.dark && i18n.light ) {
				button.setAttribute( 'aria-label', dark ? i18n.light : i18n.dark );
			}
		} );
	}

	function apply( mode ) {
		var dark = isDark( mode );
		root.classList.toggle( 'dark', dark );
		root.style.colorScheme = dark ? 'dark' : 'light';
		syncButtons();
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.mode-toggle' );
		if ( ! button ) {
			return;
		}
		var next = root.classList.contains( 'dark' ) ? 'light' : 'dark';
		// Picking the mode the OS already uses means "follow the system" again.
		var value = isDark( 'system' ) === ( 'dark' === next ) ? 'system' : next;
		try {
			localStorage.setItem( KEY, value );
		} catch ( e ) {}
		apply( next );
	} );

	if ( media && media.addEventListener ) {
		media.addEventListener( 'change', function () {
			if ( 'system' === stored() ) {
				apply( 'system' );
			}
		} );
	}

	window.addEventListener( 'storage', function ( event ) {
		if ( KEY === event.key ) {
			apply( stored() );
		}
	} );

	syncButtons();

	/* Submenus ------------------------------------------------------------ */

	function setSubmenu( toggle, open ) {
		var item = toggle.closest( 'li' );
		toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		item.classList.toggle( 'is-open', open );
	}

	function closeSubmenus( except ) {
		document.querySelectorAll( '.site-nav .submenu-toggle[aria-expanded="true"]' ).forEach( function ( toggle ) {
			if ( ! except || ! toggle.closest( 'li' ).contains( except ) ) {
				setSubmenu( toggle, false );
			}
		} );
	}

	document.addEventListener( 'click', function ( event ) {
		var toggle = event.target.closest( '.submenu-toggle' );
		if ( toggle ) {
			var open = 'true' !== toggle.getAttribute( 'aria-expanded' );
			closeSubmenus( toggle );
			setSubmenu( toggle, open );
			return;
		}
		if ( ! event.target.closest( '.site-nav' ) ) {
			closeSubmenus();
		}
	} );

	// Moving focus out of an open submenu closes it, like a native disclosure menu.
	document.addEventListener( 'focusin', function ( event ) {
		if ( event.target.closest && event.target.closest( '.site-nav' ) ) {
			closeSubmenus( event.target );
		} else {
			closeSubmenus();
		}
	} );

	/* Mobile panel -------------------------------------------------------- */

	var body = document.body;
	var menuToggle = document.querySelector( '.menu-toggle' );
	var panel = document.getElementById( 'site-nav-panel' );
	var backdrop = document.querySelector( '.site-nav-backdrop' );
	var desktop = window.matchMedia( '(min-width: 960px)' );

	function focusable() {
		return Array.prototype.filter.call(
			panel.querySelectorAll( 'a[href], button:not([disabled])' ),
			function ( el ) {
				return el.offsetParent !== null;
			}
		);
	}

	function setPanel( open ) {
		if ( ! menuToggle || ! panel ) {
			return;
		}
		body.classList.toggle( 'nav-open', open );
		menuToggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		if ( backdrop ) {
			backdrop.hidden = ! open;
		}
		if ( open ) {
			var items = focusable();
			( items[ 1 ] || items[ 0 ] || panel ).focus();
		} else if ( panel.contains( document.activeElement ) ) {
			menuToggle.focus();
		}
	}

	if ( menuToggle && panel ) {
		menuToggle.addEventListener( 'click', function () {
			setPanel( ! body.classList.contains( 'nav-open' ) );
		} );

		panel.querySelectorAll( '.menu-close' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				setPanel( false );
			} );
		} );

		if ( backdrop ) {
			backdrop.addEventListener( 'click', function () {
				setPanel( false );
			} );
		}

		panel.addEventListener( 'keydown', function ( event ) {
			if ( 'Tab' !== event.key || ! body.classList.contains( 'nav-open' ) ) {
				return;
			}
			var items = focusable();
			var first = items[ 0 ];
			var last = items[ items.length - 1 ];
			if ( event.shiftKey && document.activeElement === first ) {
				event.preventDefault();
				last.focus();
			} else if ( ! event.shiftKey && document.activeElement === last ) {
				event.preventDefault();
				first.focus();
			}
		} );

		var onBreakpoint = function ( event ) {
			if ( event.matches ) {
				setPanel( false );
			}
		};
		if ( desktop.addEventListener ) {
			desktop.addEventListener( 'change', onBreakpoint );
		}
	}

	document.addEventListener( 'keydown', function ( event ) {
		if ( 'Escape' !== event.key ) {
			return;
		}
		// Innermost open submenu first, so Escape backs out one level at a time.
		var opened = document.querySelectorAll( '.site-nav .submenu-toggle[aria-expanded="true"]' );
		var open = opened[ opened.length - 1 ];
		if ( open ) {
			setSubmenu( open, false );
			open.focus();
			return;
		}
		if ( body.classList.contains( 'nav-open' ) ) {
			setPanel( false );
		}
	} );
}() );
