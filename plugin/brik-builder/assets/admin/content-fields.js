/**
 * Brik content fields: interactive controls for field groups in wp-admin (post editor
 * meta boxes in the block and classic editor, term screens, user profiles, site options).
 *
 * Plain JavaScript on purpose: it runs on core screens without a build step.
 */
( function () {
	'use strict';

	const cfg = window.brikFields || { i18n: {} };
	const t = ( key ) => ( cfg.i18n && cfg.i18n[ key ] ) || key;
	const sprintf = ( text, ...args ) => {
		let i = 0;
		return text.replace( /%(\d\$)?[sd]/g, () => String( args[ i++ ] ) );
	};
	const $ = ( sel, root = document ) => root.querySelector( sel );
	const $$ = ( sel, root = document ) => Array.from( root.querySelectorAll( sel ) );
	const debounce = ( fn, ms ) => {
		let timer;
		return ( ...args ) => {
			clearTimeout( timer );
			timer = setTimeout( () => fn( ...args ), ms );
		};
	};
	const domId = ( name ) => name.replace( /[^a-zA-Z0-9_-]+/g, '-' ).replace( /^-+|-+$/g, '' );
	const esc = ( s ) =>
		String( s == null ? '' : s ).replace( /[&<>"']/g, ( c ) => ( { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ] ) );
	const fire = ( el ) => el && el.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	const inTemplate = ( el ) => !! el.closest( 'template' );

	const api = ( path ) => {
		if ( window.wp && window.wp.apiFetch ) {
			return window.wp.apiFetch( { path } );
		}
		return Promise.reject( new Error( 'apiFetch missing' ) );
	};
	const query = ( params ) =>
		Object.keys( params )
			.filter( ( k ) => params[ k ] !== '' && params[ k ] != null )
			.map( ( k ) => encodeURIComponent( k ) + '=' + encodeURIComponent( params[ k ] ) )
			.join( '&' );

	// The classic editor API lives on wp.oldEditor inside the block editor.
	const classicEditor = () => {
		const wp = window.wp || {};
		if ( wp.oldEditor && wp.oldEditor.initialize ) {
			return wp.oldEditor;
		}
		return wp.editor && wp.editor.initialize ? wp.editor : null;
	};

	/* ---------------------------------------------------------------------
	 * Field values (for conditions and validation).
	 * ------------------------------------------------------------------- */

	const SCOPE = '.brik-cf-row-body, .brik-cf-group, .brik-cf';

	const control = ( field ) => $( ':scope > .brik-cf-control', field );

	function readValue( field ) {
		const type = field.dataset.type;
		const box = control( field );
		if ( ! box ) {
			return '';
		}
		const own = ( sel ) => $$( sel, box ).filter( ( el ) => el.closest( '.brik-cf-field' ) === field );
		switch ( type ) {
			case 'toggle': {
				const cb = own( 'input[type=checkbox]' )[ 0 ];
				return cb && cb.checked ? '1' : '0';
			}
			case 'checkbox':
				return own( 'input[type=checkbox]:checked' ).map( ( i ) => i.value );
			case 'taxonomy': {
				const checks = own( '.brik-cf-terms input:checked' ).map( ( i ) => i.value );
				const chips = own( '.brik-cf-chip input' ).map( ( i ) => i.value );
				return checks.length ? checks : chips;
			}
			case 'radio':
			case 'button_group': {
				const r = own( 'input[type=radio]:checked' )[ 0 ];
				return r ? r.value : '';
			}
			case 'select': {
				const s = own( 'select' )[ 0 ];
				if ( ! s ) {
					return '';
				}
				return s.multiple ? Array.from( s.selectedOptions ).map( ( o ) => o.value ) : s.value;
			}
			case 'image':
			case 'file':
				return ( own( '.brik-cf-media-id' )[ 0 ] || {} ).value || '';
			case 'gallery':
				return own( '.brik-cf-gallery-item input' ).map( ( i ) => i.value ).filter( Boolean );
			case 'relationship':
			case 'post_object':
			case 'user':
				return own( '.brik-cf-chip input' ).map( ( i ) => i.value );
			case 'repeater':
				return $$( ':scope > .brik-cf-rows > .brik-cf-row', $( '.brik-cf-repeater', box ) );
			case 'link': {
				const u = own( 'input[name$="[url]"]' )[ 0 ];
				return u ? u.value.trim() : '';
			}
			case 'map': {
				const a = own( '.brik-cf-map-address' )[ 0 ];
				const lat = own( '.brik-cf-map-lat' )[ 0 ];
				return ( a && a.value.trim() ) || ( lat && lat.value.trim() ) || '';
			}
			case 'wysiwyg': {
				const ta = own( 'textarea' )[ 0 ];
				if ( ! ta ) {
					return '';
				}
				const mce = window.tinymce && window.tinymce.get( ta.id );
				const html = mce && ! mce.isHidden() ? mce.getContent() : ta.value;
				return html.replace( /<[^>]*>|&nbsp;/g, '' ).trim() || ( /<img|<iframe/i.test( html ) ? 'x' : '' );
			}
			case 'group':
			case 'message':
			case 'tab':
				return '';
			default: {
				const input = own( 'input:not([type=hidden]), textarea, select' )[ 0 ];
				return input ? input.value.trim() : '';
			}
		}
	}

	const isEmpty = ( v ) => v == null || v === '' || ( Array.isArray( v ) && v.length === 0 );

	function ruleMet( rule, actual ) {
		const expected = String( rule.value == null ? '' : rule.value );
		const list = Array.isArray( actual ) ? actual.map( String ) : null;
		switch ( rule.operator ) {
			case 'empty':
				return isEmpty( actual );
			case '!empty':
				return ! isEmpty( actual );
			case 'contains':
				return list ? list.includes( expected ) : expected !== '' && String( actual ).toLowerCase().includes( expected.toLowerCase() );
			case '!=':
				return ! equals( actual, expected );
			default:
				return equals( actual, expected );
		}
	}

	function equals( actual, expected ) {
		if ( Array.isArray( actual ) ) {
			return actual.map( String ).includes( expected );
		}
		if ( expected === 'true' || expected === 'false' ) {
			expected = expected === 'true' ? '1' : '0';
		}
		return String( actual ) === expected;
	}

	// Fields that belong directly to a scope (not to nested rows or groups).
	const scopeFields = ( scope ) => $$( '.brik-cf-field', scope ).filter( ( f ) => f.parentElement.closest( SCOPE ) === scope && ! inTemplate( f ) );

	function applyConditions( root = document ) {
		const scopes = new Set();
		$$( '.brik-cf-field[data-conditions]', root ).forEach( ( f ) => {
			if ( ! inTemplate( f ) ) {
				scopes.add( f.parentElement.closest( SCOPE ) );
			}
		} );
		scopes.forEach( ( scope ) => {
			if ( ! scope ) {
				return;
			}
			const fields = scopeFields( scope );
			const byKey = {};
			fields.forEach( ( f ) => ( byKey[ f.dataset.key ] = f ) );
			fields.forEach( ( f ) => {
				if ( ! f.dataset.conditions ) {
					return;
				}
				let groups;
				try {
					groups = JSON.parse( f.dataset.conditions );
				} catch ( e ) {
					return;
				}
				const shown = groups.some( ( and ) =>
					and.every( ( rule ) => {
						const other = byKey[ rule.field ];
						if ( ! other ) {
							return true;
						}
						// A field hidden by its own conditions counts as empty.
						const value = other.classList.contains( 'is-hidden' ) ? '' : readValue( other );
						return ruleMet( rule, value );
					} )
				);
				f.classList.toggle( 'is-hidden', ! shown );
				f.hidden = ! shown;
			} );
		} );
	}

	/* ---------------------------------------------------------------------
	 * Validation.
	 * ------------------------------------------------------------------- */

	const visible = ( f ) => ! f.closest( '.is-hidden' ) && ! inTemplate( f );

	function fieldError( f ) {
		const value = readValue( f );
		const type = f.dataset.type;
		if ( f.dataset.required && type !== 'toggle' && isEmpty( value ) ) {
			return t( 'required' );
		}
		const count = Array.isArray( value ) ? value.length : 0;
		if ( f.dataset.min && count && count < Number( f.dataset.min ) ) {
			return sprintf( t( 'min' ), f.dataset.min );
		}
		if ( f.dataset.max && count > Number( f.dataset.max ) ) {
			return sprintf( t( 'max' ), f.dataset.max );
		}
		if ( type === 'email' && value && ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( value ) ) {
			return t( 'invalidEmail' );
		}
		if ( type === 'url' && value && ! /^(https?:\/\/|mailto:|tel:|\/|#)/i.test( value ) ) {
			return t( 'invalidUrl' );
		}
		return '';
	}

	function showError( f, message ) {
		const box = $( ':scope > .brik-cf-error', f );
		f.classList.toggle( 'has-error', !! message );
		if ( box ) {
			box.textContent = message || '';
			box.hidden = ! message;
		}
	}

	/**
	 * Check every visible field under root. Returns the fields with problems.
	 */
	function validate( root, reveal ) {
		const bad = [];
		$$( '.brik-cf-field', root ).forEach( ( f ) => {
			if ( ! visible( f ) || [ 'message', 'tab', 'group' ].includes( f.dataset.type ) ) {
				showError( f, '' );
				return;
			}
			const message = fieldError( f );
			if ( message ) {
				bad.push( f );
			}
			if ( reveal || f.dataset.touched || ! message ) {
				showError( f, message );
			}
		} );
		return bad;
	}

	const labelsOf = ( fields ) =>
		Array.from(
			new Set(
				fields.map( ( f ) => {
					const row = f.closest( '.brik-cf-row' );
					const rep = row && row.closest( '.brik-cf-field' );
					return ( rep ? ( rep.dataset.label || '' ) + ' → ' : '' ) + ( f.dataset.label || f.dataset.name );
				} )
			)
		);

	/* Block editor: lock saving while required fields are empty. */
	let locked = false;
	function syncBlockEditor() {
		const wp = window.wp;
		if ( ! wp || ! wp.data || ! wp.data.dispatch( 'core/editor' ) ) {
			return;
		}
		const roots = $$( '.brik-cf' ).filter( ( r ) => ! inTemplate( r ) );
		const bad = roots.flatMap( ( r ) => validate( r, false ) );
		const editor = wp.data.dispatch( 'core/editor' );
		const notices = wp.data.dispatch( 'core/notices' );
		if ( bad.length ) {
			if ( ! locked ) {
				editor.lockPostSaving( 'brik-content-fields' );
				locked = true;
			}
			notices.createNotice( 'warning', sprintf( t( 'fillRequired' ), labelsOf( bad ).join( ', ' ) ), {
				id: 'brik-content-fields',
				isDismissible: false,
			} );
		} else if ( locked ) {
			editor.unlockPostSaving( 'brik-content-fields' );
			notices.removeNotice( 'brik-content-fields' );
			locked = false;
		}
	}
	const syncSoon = debounce( syncBlockEditor, 200 );

	/* Forms (classic editor, terms, users, options): stop the submit and point at the problem. */
	function guardForm( form ) {
		if ( form.dataset.brikGuard ) {
			return;
		}
		form.dataset.brikGuard = '1';
		const check = ( e ) => {
			if ( window.tinymce ) {
				window.tinymce.triggerSave();
			}
			// Saving a draft in the classic editor is allowed with empty required fields.
			const submitter = e.submitter || document.activeElement;
			if ( submitter && submitter.id === 'save-post' ) {
				return;
			}
			const bad = $$( '.brik-cf', form ).flatMap( ( r ) => validate( r, true ) );
			if ( ! bad.length ) {
				return;
			}
			e.preventDefault();
			e.stopImmediatePropagation();
			bad.forEach( ( f ) => ( f.dataset.touched = '1' ) );
			const first = bad[ 0 ];
			openTabOf( first );
			first.scrollIntoView( { behavior: 'smooth', block: 'center' } );
			const input = $( 'input:not([type=hidden]), textarea, select, button', control( first ) || first );
			if ( input ) {
				setTimeout( () => input.focus( { preventScroll: true } ), 300 );
			}
			// Core disables the buttons and shows a spinner as soon as submit starts.
			$$( '#publish, #save-post, #submit, .button-primary', document ).forEach( ( b ) => b.classList.remove( 'disabled' ) );
			$$( '#publishing-action .spinner, #submitpost .spinner', document ).forEach( ( s ) => s.classList.remove( 'is-active' ) );
		};
		form.addEventListener( 'submit', check, true );
		// The add-term form is sent with AJAX from a click handler, before any submit event.
		$$( 'input[type=submit], button[type=submit]', form ).forEach( ( b ) => b.addEventListener( 'click', check, true ) );
	}

	function openTabOf( field ) {
		let panel = field.closest( '.brik-cf-panel' );
		while ( panel ) {
			const tab = document.getElementById( panel.getAttribute( 'aria-labelledby' ) );
			if ( tab ) {
				selectTab( tab );
			}
			panel = panel.parentElement.closest( '.brik-cf-panel' );
		}
		const row = field.closest( '.brik-cf-row.is-collapsed' );
		if ( row ) {
			toggleRow( row, false );
		}
	}

	/* ---------------------------------------------------------------------
	 * Tabs.
	 * ------------------------------------------------------------------- */

	function selectTab( tab ) {
		const list = tab.closest( '.brik-cf-tablist' );
		const wrap = list.parentElement;
		$$( ':scope > .brik-cf-tab', list ).forEach( ( b ) => {
			const on = b === tab;
			b.setAttribute( 'aria-selected', on ? 'true' : 'false' );
			b.tabIndex = on ? 0 : -1;
			const panel = document.getElementById( b.getAttribute( 'aria-controls' ) );
			if ( panel && panel.parentElement.parentElement === wrap ) {
				panel.hidden = ! on;
			}
		} );
	}

	/* ---------------------------------------------------------------------
	 * Sortable lists (repeater rows, gallery, chips) with native drag and drop.
	 * ------------------------------------------------------------------- */

	let dragging = null;

	function itemOf( el ) {
		const handle = el.closest( '.brik-cf-row-handle' );
		if ( handle ) {
			return handle.closest( '.brik-cf-row' );
		}
		const item = el.closest( '.brik-cf-gallery-item, .brik-cf-chip' );
		return item && item.parentElement.classList.contains( 'brik-cf-sortable' ) ? item : null;
	}

	document.addEventListener( 'dragstart', ( e ) => {
		const item = e.target.closest && itemOf( e.target );
		if ( ! item || ! item.closest( '.brik-cf, .brik-cf-screen' ) ) {
			return;
		}
		dragging = item;
		item.classList.add( 'is-dragging' );
		e.dataTransfer.effectAllowed = 'move';
		e.dataTransfer.setData( 'text/plain', '' );
		if ( item.classList.contains( 'brik-cf-row' ) ) {
			e.dataTransfer.setDragImage( item, 24, 20 );
			removeEditors( item );
		}
	} );

	document.addEventListener( 'dragover', ( e ) => {
		if ( ! dragging ) {
			return;
		}
		const list = dragging.parentElement;
		if ( ! list.contains( e.target ) ) {
			return;
		}
		e.preventDefault();
		const siblings = Array.from( list.children ).filter( ( c ) => c !== dragging );
		const x = e.clientX;
		const y = e.clientY;
		const next = siblings.find( ( s ) => {
			const r = s.getBoundingClientRect();
			return y < r.top || ( y < r.bottom && x < r.left + r.width / 2 );
		} );
		if ( next ) {
			if ( dragging.nextElementSibling !== next ) {
				list.insertBefore( dragging, next );
			}
		} else if ( list.lastElementChild !== dragging ) {
			list.appendChild( dragging );
		}
	} );

	document.addEventListener( 'drop', ( e ) => {
		if ( dragging ) {
			e.preventDefault();
		}
	} );

	document.addEventListener( 'dragend', () => {
		if ( ! dragging ) {
			return;
		}
		const item = dragging;
		dragging = null;
		item.classList.remove( 'is-dragging' );
		if ( item.classList.contains( 'brik-cf-row' ) ) {
			initEditors( item );
			refreshRepeater( item.closest( '.brik-cf-repeater' ) );
		}
		fire( $( 'input', item ) || item );
	} );

	/* ---------------------------------------------------------------------
	 * Rich text.
	 * ------------------------------------------------------------------- */

	function editorSettings( box ) {
		let opts = {};
		try {
			opts = JSON.parse( box.dataset.settings || '{}' );
		} catch ( e ) {}
		const ed = classicEditor();
		const defaults = ed && ed.getDefaultSettings ? ed.getDefaultSettings() : {};
		const basic = opts.toolbar === 'basic';
		return {
			tinymce: Object.assign( {}, defaults.tinymce || {}, {
				wpautop: true,
				toolbar1: basic
					? 'bold,italic,underline,link,bullist,numlist,undo,redo'
					: 'formatselect,bold,italic,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,wp_more,fullscreen,wp_adv',
				toolbar2: basic ? '' : 'strikethrough,hr,forecolor,pastetext,removeformat,charmap,outdent,indent,undo,redo,wp_help',
				height: 220,
				setup( editor ) {
					editor.on( 'change keyup undo redo', () => {
						editor.save();
						const field = box.closest( '.brik-cf-field' );
						fire( $( 'textarea', box ) );
						if ( field && field.dataset.touched ) {
							validate( field.closest( '.brik-cf' ), false );
						}
					} );
				},
			} ),
			quicktags: { buttons: 'strong,em,link,block,del,ins,img,ul,ol,li,code,close' },
			mediaButtons: opts.media !== false,
		};
	}

	function initEditors( root ) {
		const ed = classicEditor();
		if ( ! ed ) {
			return;
		}
		$$( '.brik-cf-wysiwyg', root ).forEach( ( box ) => {
			if ( inTemplate( box ) || box.dataset.ready ) {
				return;
			}
			const ta = $( 'textarea', box );
			if ( ! ta || ! ta.id ) {
				return;
			}
			box.dataset.ready = '1';
			box.dataset.id = ta.id;
			box.dataset.name = ta.name;
			ed.initialize( ta.id, editorSettings( box ) );
		} );
	}

	/**
	 * Tear editors down (keeping their content in the textarea) before a row moves or is
	 * cloned: TinyMCE iframes don't survive being moved in the DOM.
	 */
	function removeEditors( root ) {
		const ed = classicEditor();
		$$( '.brik-cf-wysiwyg[data-ready]', root ).forEach( ( box ) => {
			const id = box.dataset.id;
			const mce = window.tinymce && window.tinymce.get( id );
			const content = mce ? mce.getContent() : ( $( 'textarea', box ) || {} ).value || '';
			if ( ed && ed.remove ) {
				ed.remove( id );
			}
			box.innerHTML = '<textarea class="brik-cf-wysiwyg-input wp-editor-area" rows="10"></textarea>';
			const ta = $( 'textarea', box );
			ta.id = id;
			ta.name = box.dataset.name;
			ta.value = content;
			delete box.dataset.ready;
		} );
	}

	/* ---------------------------------------------------------------------
	 * Repeaters.
	 * ------------------------------------------------------------------- */

	const rowsOf = ( rep ) => $$( ':scope > .brik-cf-rows > .brik-cf-row', rep );
	const fieldOf = ( el ) => el.closest( '.brik-cf-field' );

	function refreshRepeater( rep ) {
		if ( ! rep ) {
			return;
		}
		const rows = rowsOf( rep );
		const field = fieldOf( rep );
		const max = Number( field.dataset.max || 0 );
		const min = Number( field.dataset.min || 0 );
		rows.forEach( ( row, i ) => {
			const num = $( ':scope > .brik-cf-row-head .brik-cf-row-number', row );
			if ( num ) {
				num.textContent = String( i + 1 );
			}
			const remove = $( ':scope > .brik-cf-row-head .brik-cf-row-remove', row );
			if ( remove ) {
				remove.disabled = min > 0 && rows.length <= min;
			}
			const dup = $( ':scope > .brik-cf-row-head .brik-cf-row-duplicate', row );
			if ( dup ) {
				dup.disabled = max > 0 && rows.length >= max;
			}
			rowTitle( row );
		} );
		const add = $( ':scope > .brik-cf-repeater-bar .brik-cf-row-add', rep );
		if ( add ) {
			add.disabled = max > 0 && rows.length >= max;
		}
		const count = $( ':scope > .brik-cf-repeater-bar .brik-cf-count', rep );
		if ( count ) {
			count.textContent = max ? rows.length + ' / ' + max : '';
		}
		const empty = $( ':scope > .brik-cf-empty', rep );
		if ( empty ) {
			empty.hidden = rows.length > 0;
		}
	}

	function rowTitle( row ) {
		const body = $( ':scope > .brik-cf-row-body', row );
		const target = $( ':scope > .brik-cf-row-head .brik-cf-row-title', row );
		if ( ! body || ! target ) {
			return;
		}
		const input = $$( '.brik-cf-type-text input[type=text], .brik-cf-type-email input, .brik-cf-type-url input', body ).find(
			( i ) => i.closest( '.brik-cf-row' ) === row
		);
		target.textContent = input && input.value.trim() ? input.value.trim() : '';
	}

	function addRow( rep, after ) {
		const tpl = $( ':scope > .brik-cf-row-template', rep );
		const field = fieldOf( rep );
		const max = Number( field.dataset.max || 0 );
		if ( ! tpl || ( max && rowsOf( rep ).length >= max ) ) {
			return null;
		}
		const index = Number( rep.dataset.next || 0 );
		rep.dataset.next = String( index + 1 );
		const html = tpl.innerHTML.split( rep.dataset.token ).join( String( index ) );
		const holder = document.createElement( 'ol' );
		holder.innerHTML = html.trim();
		const row = holder.firstElementChild;
		const list = $( ':scope > .brik-cf-rows', rep );
		if ( after && after.parentElement === list ) {
			after.after( row );
		} else {
			list.appendChild( row );
		}
		setup( row );
		refreshRepeater( rep );
		applyConditions( row );
		return row;
	}

	function duplicateRow( row ) {
		const rep = row.closest( '.brik-cf-repeater' );
		const field = fieldOf( rep );
		const max = Number( field.dataset.max || 0 );
		if ( max && rowsOf( rep ).length >= max ) {
			return;
		}
		if ( window.tinymce ) {
			window.tinymce.triggerSave();
		}
		removeEditors( row );
		const copy = row.cloneNode( true );
		const index = Number( rep.dataset.next || 0 );
		rep.dataset.next = String( index + 1 );
		const oldPrefix = row.dataset.prefix;
		const newPrefix = oldPrefix.replace( /\[[^\[\]]*\]$/, '[' + index + ']' );
		renamePrefix( copy, oldPrefix, newPrefix );
		// Cloned nested repeaters need their own row counters.
		row.after( copy );
		copy.querySelectorAll( '[data-brik-init]' ).forEach( ( el ) => delete el.dataset.brikInit );
		delete copy.dataset.brikInit;
		initEditors( row );
		setup( copy );
		refreshRepeater( rep );
		applyConditions( copy );
		fire( $( 'input', copy ) || copy );
	}

	function renamePrefix( root, oldPrefix, newPrefix ) {
		const oldId = domId( oldPrefix );
		const newId = domId( newPrefix );
		const swap = ( value, from, to ) => ( value && value.indexOf( from ) === 0 ? to + value.slice( from.length ) : value );
		const walk = ( node ) => {
			[ node, ...node.querySelectorAll( '*' ) ].forEach( ( el ) => {
				if ( el.nodeType !== 1 ) {
					return;
				}
				if ( el.name ) {
					el.name = swap( el.name, oldPrefix, newPrefix );
				}
				[ 'data-prefix', 'data-name' ].forEach( ( attr ) => {
					if ( el.hasAttribute( attr ) ) {
						el.setAttribute( attr, swap( el.getAttribute( attr ), oldPrefix, newPrefix ) );
					}
				} );
				[ 'id', 'for', 'aria-controls', 'aria-labelledby', 'aria-describedby', 'data-id' ].forEach( ( attr ) => {
					if ( el.hasAttribute( attr ) ) {
						el.setAttribute(
							attr,
							el
								.getAttribute( attr )
								.split( ' ' )
								.map( ( v ) => swap( v, oldId, newId ) )
								.join( ' ' )
						);
					}
				} );
				if ( el.tagName === 'TEMPLATE' ) {
					el.innerHTML = el.innerHTML.split( oldPrefix ).join( newPrefix ).split( oldId ).join( newId );
				}
			} );
		};
		walk( root );
	}

	function toggleRow( row, collapse ) {
		const now = collapse === undefined ? ! row.classList.contains( 'is-collapsed' ) : collapse;
		row.classList.toggle( 'is-collapsed', now );
		const btn = $( ':scope > .brik-cf-row-head .brik-cf-row-toggle', row );
		if ( btn ) {
			btn.setAttribute( 'aria-expanded', now ? 'false' : 'true' );
		}
	}

	function rowHasContent( row ) {
		return $$( 'input:not([type=hidden]):not([type=checkbox]):not([type=radio]), textarea', row ).some( ( i ) => i.value.trim() ) ||
			$$( '.brik-cf-media-id, .brik-cf-chip input, .brik-cf-gallery-item input', row ).some( ( i ) => i.value );
	}

	/* ---------------------------------------------------------------------
	 * Media (image, file, gallery).
	 * ------------------------------------------------------------------- */

	const extOk = ( mimes, filename ) => {
		const allowed = String( mimes || '' )
			.toLowerCase()
			.split( ',' )
			.map( ( s ) => s.trim().replace( /^\./, '' ) )
			.filter( Boolean );
		if ( ! allowed.length ) {
			return true;
		}
		const ext = String( filename || '' ).split( '.' ).pop().toLowerCase();
		return allowed.includes( ext ) || ( ext === 'jpeg' && allowed.includes( 'jpg' ) ) || ( ext === 'jpg' && allowed.includes( 'jpeg' ) );
	};

	const sizeUrl = ( att, size ) => {
		const sizes = att.sizes || {};
		return ( sizes[ size ] || sizes.medium || sizes.thumbnail || sizes.full || {} ).url || att.url;
	};

	function pickMedia( box ) {
		if ( ! window.wp || ! window.wp.media ) {
			return;
		}
		const image = box.dataset.library === 'image';
		const frame = window.wp.media( {
			title: image ? t( 'chooseImage' ) : t( 'chooseFile' ),
			button: { text: t( 'use' ) },
			library: image ? { type: 'image' } : {},
			multiple: false,
		} );
		frame.on( 'open', () => {
			const id = $( '.brik-cf-media-id', box ).value;
			if ( id ) {
				const att = window.wp.media.attachment( id );
				att.fetch();
				frame.state().get( 'selection' ).reset( [ att ] );
			}
		} );
		frame.on( 'select', () => {
			const att = frame.state().get( 'selection' ).first().toJSON();
			const field = fieldOf( box );
			if ( ( image && att.type !== 'image' ) || ! extOk( box.dataset.mimes, att.filename ) ) {
				showError( field, t( 'badType' ) );
				return;
			}
			setMedia( box, att );
		} );
		frame.open();
	}

	function setMedia( box, att ) {
		const input = $( '.brik-cf-media-id', box );
		input.value = att ? String( att.id ) : '';
		box.classList.toggle( 'has-value', !! att );
		if ( box.classList.contains( 'is-image' ) ) {
			$( '.brik-cf-media-thumb', box ).innerHTML = att ? '<img src="' + esc( sizeUrl( att, box.dataset.size ) ) + '" alt="">' : '';
		} else {
			$( '.brik-cf-file-title', box ).textContent = att ? att.title || att.filename : '';
			$( '.brik-cf-file-name', box ).textContent = att ? att.filename + ( att.filesizeHumanReadable ? ' · ' + att.filesizeHumanReadable : '' ) : '';
		}
		const field = fieldOf( box );
		field.dataset.touched = '1';
		showError( field, '' );
		fire( input );
	}

	function galleryItems( gal ) {
		return $$( '.brik-cf-gallery-grid > .brik-cf-gallery-item', gal );
	}

	function refreshGallery( gal ) {
		const field = fieldOf( gal );
		const max = Number( field.dataset.max || 0 );
		const n = galleryItems( gal ).length;
		const count = $( '.brik-cf-count', gal );
		if ( count ) {
			count.textContent = max ? n + ' / ' + max : n ? String( n ) : '';
		}
		const add = $( '.brik-cf-gallery-add', gal );
		if ( add ) {
			add.disabled = max > 0 && n >= max;
		}
		gal.classList.toggle( 'is-empty', n === 0 );
	}

	function pickGallery( gal ) {
		if ( ! window.wp || ! window.wp.media ) {
			return;
		}
		const frame = window.wp.media( {
			title: t( 'chooseImages' ),
			button: { text: t( 'use' ) },
			library: { type: 'image' },
			multiple: 'add',
		} );
		frame.on( 'select', () => {
			const field = fieldOf( gal );
			const max = Number( field.dataset.max || 0 );
			const have = galleryItems( gal ).map( ( li ) => li.dataset.id );
			const tpl = $( '.brik-cf-gallery-template', gal );
			let skipped = false;
			frame
				.state()
				.get( 'selection' )
				.toJSON()
				.forEach( ( att ) => {
					if ( have.includes( String( att.id ) ) ) {
						return;
					}
					if ( att.type !== 'image' || ! extOk( gal.dataset.mimes, att.filename ) ) {
						skipped = true;
						return;
					}
					if ( max && galleryItems( gal ).length >= max ) {
						skipped = true;
						return;
					}
					const li = tpl.content.firstElementChild.cloneNode( true );
					li.dataset.id = String( att.id );
					$( 'input', li ).value = String( att.id );
					$( 'img', li ).src = sizeUrl( att, gal.dataset.size || 'thumbnail' );
					$( '.brik-cf-gallery-grid', gal ).appendChild( li );
					have.push( String( att.id ) );
				} );
			field.dataset.touched = '1';
			showError( field, skipped ? ( max ? sprintf( t( 'max' ), max ) : t( 'badType' ) ) : '' );
			refreshGallery( gal );
			fire( $( 'input', gal ) );
		} );
		frame.open();
	}

	/* ---------------------------------------------------------------------
	 * Pickers (post object, user, taxonomy select) and relationships.
	 * ------------------------------------------------------------------- */

	const endpoint = ( kind ) => ( kind === 'user' ? 'search-users' : kind === 'term' ? 'search-terms' : 'search-posts' );

	function search( kind, params ) {
		return api( 'brik/v1/content/' + endpoint( kind ) + '?' + query( params ) );
	}

	function chipHtml( item, name, sortable ) {
		return (
			'<li class="brik-cf-chip"' + ( sortable ? ' draggable="true"' : '' ) + ' data-id="' + esc( item.id ) + '">' +
			'<input type="hidden" name="' + esc( name ) + '" value="' + esc( item.id ) + '">' +
			( item.thumb ? '<img class="brik-cf-chip-thumb" src="' + esc( item.thumb ) + '" alt="">' : '' ) +
			'<span class="brik-cf-chip-text"><span class="brik-cf-chip-title">' + esc( item.title ) + '</span>' +
			( item.meta ? '<span class="brik-cf-chip-meta">' + esc( item.meta ) + '</span>' : '' ) +
			'</span><button type="button" class="brik-cf-chip-remove" aria-label="' + esc( t( 'remove' ) ) + '">' +
			'<svg class="brik-cf-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>' +
			'</button></li>'
		);
	}

	function optionHtml( item, selected ) {
		return (
			'<li role="option" class="brik-cf-option' + ( selected ? ' is-selected' : '' ) + '" data-id="' + esc( item.id ) + '" aria-selected="' + ( selected ? 'true' : 'false' ) + '">' +
			( item.thumb ? '<img src="' + esc( item.thumb ) + '" alt="">' : '<span class="brik-cf-option-dot"></span>' ) +
			'<span class="brik-cf-option-text"><span class="brik-cf-option-title">' + esc( item.title ) + '</span>' +
			( item.meta ? '<span class="brik-cf-option-meta">' + esc( item.meta ) + '</span>' : '' ) +
			'</span></li>'
		);
	}

	function pickerParams( el, s ) {
		let q = {};
		try {
			q = JSON.parse( el.dataset.query || '{}' );
		} catch ( e ) {}
		return Object.assign( {}, q, { s } );
	}

	function setupPicker( picker ) {
		const input = $( '.brik-cf-picker-search', picker );
		const list = $( '.brik-cf-listbox', picker );
		const chips = $( '.brik-cf-chips', picker );
		const multiple = !! picker.dataset.multiple;
		const field = fieldOf( picker );
		let items = [];
		let active = -1;
		let seq = 0;

		const selectedIds = () => $$( '.brik-cf-chip', chips ).map( ( c ) => c.dataset.id );
		const close = () => {
			list.hidden = true;
			input.setAttribute( 'aria-expanded', 'false' );
			active = -1;
		};
		const render = () => {
			const ids = selectedIds();
			if ( ! items.length ) {
				list.innerHTML = '<li class="brik-cf-option is-empty">' + esc( t( 'noResults' ) ) + '</li>';
			} else {
				list.innerHTML = items.map( ( it ) => optionHtml( it, ids.includes( String( it.id ) ) ) ).join( '' );
			}
			$$( '.brik-cf-option', list ).forEach( ( o, i ) => o.classList.toggle( 'is-active', i === active ) );
			list.hidden = false;
			input.setAttribute( 'aria-expanded', 'true' );
		};
		const load = debounce( () => {
			const mine = ++seq;
			list.innerHTML = '<li class="brik-cf-option is-empty">' + esc( t( 'loading' ) ) + '</li>';
			list.hidden = false;
			search( picker.dataset.kind, pickerParams( picker, input.value.trim() ) )
				.then( ( res ) => {
					if ( mine !== seq ) {
						return;
					}
					items = ( res && res.items ) || [];
					active = items.length ? 0 : -1;
					render();
				} )
				.catch( () => {
					items = [];
					render();
				} );
		}, 220 );
		const choose = ( item ) => {
			if ( ! item ) {
				return;
			}
			const max = Number( field.dataset.max || 0 );
			if ( selectedIds().includes( String( item.id ) ) ) {
				return;
			}
			if ( ! multiple ) {
				chips.innerHTML = '';
			} else if ( max && selectedIds().length >= max ) {
				showError( field, sprintf( t( 'max' ), max ) );
				return;
			}
			chips.insertAdjacentHTML( 'beforeend', chipHtml( item, picker.dataset.name, multiple ) );
			field.dataset.touched = '1';
			showError( field, '' );
			input.value = '';
			if ( ! multiple ) {
				close();
			} else {
				render();
			}
			fire( $( 'input', chips.lastElementChild ) );
		};

		input.addEventListener( 'focus', load );
		input.addEventListener( 'input', load );
		input.addEventListener( 'keydown', ( e ) => {
			if ( e.key === 'ArrowDown' || e.key === 'ArrowUp' ) {
				e.preventDefault();
				if ( list.hidden ) {
					load();
					return;
				}
				active = Math.max( 0, Math.min( items.length - 1, active + ( e.key === 'ArrowDown' ? 1 : -1 ) ) );
				render();
			} else if ( e.key === 'Enter' ) {
				// Never submit the post form from a search box.
				e.preventDefault();
				if ( ! list.hidden && items[ active ] ) {
					choose( items[ active ] );
				}
			} else if ( e.key === 'Escape' ) {
				close();
			}
		} );
		list.addEventListener( 'mousedown', ( e ) => {
			const opt = e.target.closest( '.brik-cf-option[data-id]' );
			if ( opt ) {
				e.preventDefault();
				choose( items.find( ( it ) => String( it.id ) === opt.dataset.id ) );
			}
		} );
		input.addEventListener( 'blur', () => setTimeout( close, 120 ) );
	}

	function setupRelationship( rel ) {
		const input = $( '.brik-cf-rel-search', rel );
		const results = $( '.brik-cf-rel-results', rel );
		const selected = $( '.brik-cf-rel-selected', rel );
		const field = fieldOf( rel );
		let items = [];
		let page = 1;
		let pages = 1;
		let seq = 0;

		const ids = () => $$( '.brik-cf-chip', selected ).map( ( c ) => c.dataset.id );
		const refresh = () => {
			const max = Number( field.dataset.max || 0 );
			const n = ids().length;
			$( '.brik-cf-count', rel ).textContent = max ? n + ' / ' + max : String( n );
			rel.classList.toggle( 'is-full', max > 0 && n >= max );
			const chosen = ids();
			$$( '.brik-cf-option[data-id]', results ).forEach( ( o ) => {
				const on = chosen.includes( o.dataset.id );
				o.classList.toggle( 'is-selected', on );
				o.setAttribute( 'aria-selected', on ? 'true' : 'false' );
			} );
		};
		const render = ( fresh ) => {
			const chosen = ids();
			const more = $( '.brik-cf-rel-more', results );
			if ( more ) {
				more.remove();
			}
			const html = fresh.map( ( it ) => optionHtml( it, chosen.includes( String( it.id ) ) ) ).join( '' );
			if ( page > 1 ) {
				results.insertAdjacentHTML( 'beforeend', html );
			} else {
				results.innerHTML = html || '<li class="brik-cf-option is-empty">' + esc( t( 'noResults' ) ) + '</li>';
			}
			if ( page < pages ) {
				results.insertAdjacentHTML( 'beforeend', '<li class="brik-cf-rel-more"><button type="button" class="brik-cf-btn brik-cf-btn-sm brik-cf-btn-ghost">' + esc( t( 'loadMore' ) ) + '</button></li>' );
			}
			refresh();
		};
		const load = ( more ) => {
			const mine = ++seq;
			if ( ! more ) {
				page = 1;
				results.innerHTML = '<li class="brik-cf-option is-empty">' + esc( t( 'loading' ) ) + '</li>';
			}
			search( 'post', Object.assign( pickerParams( rel, input.value.trim() ), { page } ) )
				.then( ( res ) => {
					if ( mine !== seq ) {
						return;
					}
					pages = res.pages || 1;
					const got = res.items || [];
					items = more ? items.concat( got ) : got;
					render( got );
				} )
				.catch( () => {
					results.innerHTML = '<li class="brik-cf-option is-empty">' + esc( t( 'noResults' ) ) + '</li>';
				} );
		};

		results.addEventListener( 'click', ( e ) => {
			if ( e.target.closest( '.brik-cf-rel-more' ) ) {
				page++;
				load( true );
				return;
			}
			const opt = e.target.closest( '.brik-cf-option[data-id]' );
			if ( ! opt ) {
				return;
			}
			const max = Number( field.dataset.max || 0 );
			if ( ids().includes( opt.dataset.id ) ) {
				const chip = $$( '.brik-cf-chip', selected ).find( ( c ) => c.dataset.id === opt.dataset.id );
				if ( chip ) {
					chip.remove();
				}
			} else if ( max && ids().length >= max ) {
				showError( field, sprintf( t( 'max' ), max ) );
				return;
			} else {
				const item = items.find( ( it ) => String( it.id ) === opt.dataset.id );
				selected.insertAdjacentHTML( 'beforeend', chipHtml( item, rel.dataset.name, true ) );
			}
			field.dataset.touched = '1';
			showError( field, '' );
			refresh();
			fire( $( 'input', rel ) );
		} );
		input.addEventListener( 'input', debounce( () => load( false ), 250 ) );
		input.addEventListener( 'keydown', ( e ) => {
			if ( e.key === 'Enter' ) {
				e.preventDefault();
			}
		} );
		rel.addEventListener( 'brik:refresh', refresh );
		load( false );
		refresh();
	}

	/* ---------------------------------------------------------------------
	 * Map, oEmbed, color, range, toggle.
	 * ------------------------------------------------------------------- */

	function mapSrc( q, zoom ) {
		return 'https://maps.google.com/maps?q=' + encodeURIComponent( q ) + '&z=' + ( Number( zoom ) || 14 ) + '&ie=UTF8&iwloc=&output=embed';
	}

	function updateMap( map ) {
		const lat = $( '.brik-cf-map-lat', map ).value.trim();
		const lng = $( '.brik-cf-map-lng', map ).value.trim();
		const addr = $( '.brik-cf-map-address', map ).value.trim();
		const q = lat && lng ? lat + ',' + lng : addr || ( map.dataset.lat && map.dataset.lng ? map.dataset.lat + ',' + map.dataset.lng : '' );
		const preview = $( '.brik-cf-map-preview', map );
		if ( ! q ) {
			preview.hidden = true;
			return;
		}
		const frame = $( 'iframe', preview );
		const src = mapSrc( q, $( '.brik-cf-map-zoom', map ).value || map.dataset.zoom );
		if ( frame.getAttribute( 'src' ) !== src ) {
			frame.setAttribute( 'src', src );
		}
		frame.style.height = ( Number( map.dataset.height ) || 320 ) + 'px';
		preview.hidden = false;
	}

	function geocode( map ) {
		const addr = $( '.brik-cf-map-address', map ).value.trim();
		const btn = $( '.brik-cf-map-find', map );
		if ( ! addr ) {
			return;
		}
		btn.disabled = true;
		btn.classList.add( 'is-busy' );
		fetch( 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent( addr ), {
			headers: { Accept: 'application/json' },
		} )
			.then( ( r ) => r.json() )
			.then( ( list ) => {
				const field = fieldOf( map );
				if ( ! list || ! list.length ) {
					showError( field, t( 'notFound' ) );
					return;
				}
				$( '.brik-cf-map-lat', map ).value = Number( list[ 0 ].lat ).toFixed( 7 );
				$( '.brik-cf-map-lng', map ).value = Number( list[ 0 ].lon ).toFixed( 7 );
				showError( field, '' );
				updateMap( map );
				fire( $( '.brik-cf-map-lat', map ) );
			} )
			.catch( () => showError( fieldOf( map ), t( 'notFound' ) ) )
			.finally( () => {
				btn.disabled = false;
				btn.classList.remove( 'is-busy' );
			} );
	}

	/**
	 * Only provider iframes (and images, for photo embeds) are shown: the preview is built
	 * from the proxy response rather than injected as-is.
	 */
	function oembedPreview( box ) {
		const input = $( '.brik-cf-oembed-url', box );
		const preview = $( '.brik-cf-oembed-preview', box );
		const url = input.value.trim();
		if ( ! /^https?:\/\//i.test( url ) ) {
			preview.hidden = true;
			preview.innerHTML = '';
			return;
		}
		if ( preview.dataset.url === url ) {
			return;
		}
		preview.dataset.url = url;
		preview.hidden = false;
		preview.innerHTML = '<div class="brik-cf-oembed-loading">' + esc( t( 'loading' ) ) + '</div>';
		api( 'oembed/1.0/proxy?' + query( { url, maxwidth: 640 } ) )
			.then( ( res ) => {
				const doc = new DOMParser().parseFromString( ( res && res.html ) || '', 'text/html' );
				const frame = doc.querySelector( 'iframe[src^="https://"]' );
				if ( frame ) {
					const f = document.createElement( 'iframe' );
					f.src = frame.getAttribute( 'src' );
					f.title = res.title || '';
					f.loading = 'lazy';
					f.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
					f.setAttribute( 'sandbox', 'allow-scripts allow-same-origin allow-popups allow-presentation' );
					preview.replaceChildren( f );
				} else if ( res && res.thumbnail_url ) {
					const img = document.createElement( 'img' );
					img.src = res.thumbnail_url;
					img.alt = res.title || '';
					preview.replaceChildren( img );
				} else {
					preview.innerHTML = '<div class="brik-cf-oembed-loading">' + esc( t( 'noPreview' ) ) + '</div>';
				}
			} )
			.catch( () => {
				preview.innerHTML = '<div class="brik-cf-oembed-loading">' + esc( t( 'noPreview' ) ) + '</div>';
			} );
	}

	function syncColor( wrap, fromPicker ) {
		const text = $( '.brik-cf-color-text', wrap );
		const picker = $( '.brik-cf-color-picker', wrap );
		if ( fromPicker ) {
			text.value = picker.value;
		} else if ( /^#[0-9a-f]{6}$/i.test( text.value.trim() ) ) {
			picker.value = text.value.trim();
		} else if ( /^#[0-9a-f]{3}$/i.test( text.value.trim() ) ) {
			const v = text.value.trim();
			picker.value = '#' + v[ 1 ] + v[ 1 ] + v[ 2 ] + v[ 2 ] + v[ 3 ] + v[ 3 ];
		}
		$( '.brik-cf-color-swatch', wrap ).style.setProperty( '--swatch', text.value.trim() || 'transparent' );
		wrap.classList.toggle( 'is-empty', ! text.value.trim() );
	}

	/* ---------------------------------------------------------------------
	 * Setup.
	 * ------------------------------------------------------------------- */

	function once( el ) {
		if ( el.dataset.brikInit || inTemplate( el ) ) {
			return false;
		}
		el.dataset.brikInit = '1';
		return true;
	}

	function setup( root ) {
		$$( '.brik-cf-picker', root ).forEach( ( p ) => once( p ) && setupPicker( p ) );
		$$( '.brik-cf-relationship', root ).forEach( ( r ) => once( r ) && setupRelationship( r ) );
		$$( '.brik-cf-map', root ).forEach( ( m ) => once( m ) && updateMap( m ) );
		$$( '.brik-cf-oembed', root ).forEach( ( o ) => once( o ) && oembedPreview( o ) );
		$$( '.brik-cf-gallery', root ).forEach( ( g ) => once( g ) && refreshGallery( g ) );
		$$( '.brik-cf-repeater', root ).forEach( ( r ) => {
			if ( once( r ) ) {
				// Rows rendered by PHP are numbered 0…n-1; new ones continue after the highest.
				r.dataset.next = String( Math.max( Number( r.dataset.next || 0 ), rowsOf( r ).length ) );
				refreshRepeater( r );
			}
		} );
		$$( '.brik-cf-color', root ).forEach( ( c ) => once( c ) && syncColor( c, false ) );
		initEditors( root );
		$$( 'form', document ).forEach( ( form ) => {
			if ( $( '.brik-cf', form ) ) {
				guardForm( form );
			}
		} );
	}

	/* Delegated events. */

	document.addEventListener( 'click', ( e ) => {
		const el = e.target.closest( 'button, .brik-cf-tab' );
		if ( ! el || ! el.closest( '.brik-cf, .brik-cf-screen' ) ) {
			return;
		}
		if ( el.classList.contains( 'brik-cf-tab' ) ) {
			selectTab( el );
			return;
		}
		if ( el.matches( '.brik-cf-media-add, .brik-cf-media-edit' ) ) {
			e.preventDefault();
			pickMedia( el.closest( '.brik-cf-media' ) );
		} else if ( el.matches( '.brik-cf-media-remove' ) ) {
			e.preventDefault();
			setMedia( el.closest( '.brik-cf-media' ), null );
		} else if ( el.matches( '.brik-cf-gallery-add' ) ) {
			e.preventDefault();
			pickGallery( el.closest( '.brik-cf-gallery' ) );
		} else if ( el.matches( '.brik-cf-gallery-remove' ) ) {
			e.preventDefault();
			const gal = el.closest( '.brik-cf-gallery' );
			el.closest( '.brik-cf-gallery-item' ).remove();
			refreshGallery( gal );
			fire( $( 'input', gal ) );
		} else if ( el.matches( '.brik-cf-chip-remove' ) ) {
			e.preventDefault();
			const chip = el.closest( '.brik-cf-chip' );
			const holder = chip.closest( '.brik-cf-picker, .brik-cf-relationship' );
			chip.remove();
			if ( holder ) {
				holder.dispatchEvent( new Event( 'brik:refresh' ) );
				fire( $( 'input', holder ) );
			}
		} else if ( el.matches( '.brik-cf-row-add' ) ) {
			e.preventDefault();
			const row = addRow( el.closest( '.brik-cf-repeater' ) );
			if ( row ) {
				const first = $( 'input:not([type=hidden]), textarea, select', row );
				if ( first ) {
					first.focus();
				}
				fire( first || row );
			}
		} else if ( el.matches( '.brik-cf-row-remove' ) ) {
			e.preventDefault();
			const row = el.closest( '.brik-cf-row' );
			if ( rowHasContent( row ) && ! window.confirm( t( 'confirmRemove' ) ) ) {
				return;
			}
			const rep = row.closest( '.brik-cf-repeater' );
			removeEditors( row );
			row.remove();
			refreshRepeater( rep );
			fire( $( 'input', rep ) );
		} else if ( el.matches( '.brik-cf-row-duplicate' ) ) {
			e.preventDefault();
			duplicateRow( el.closest( '.brik-cf-row' ) );
		} else if ( el.matches( '.brik-cf-row-toggle' ) ) {
			e.preventDefault();
			toggleRow( el.closest( '.brik-cf-row' ) );
		} else if ( el.matches( '.brik-cf-map-find' ) ) {
			e.preventDefault();
			geocode( el.closest( '.brik-cf-map' ) );
		}
	} );

	document.addEventListener( 'keydown', ( e ) => {
		const tab = e.target.closest && e.target.closest( '.brik-cf-tab' );
		if ( tab && [ 'ArrowRight', 'ArrowLeft', 'ArrowDown', 'ArrowUp', 'Home', 'End' ].includes( e.key ) ) {
			const tabs = $$( ':scope > .brik-cf-tab', tab.parentElement );
			const i = tabs.indexOf( tab );
			const next =
				e.key === 'Home' ? 0 : e.key === 'End' ? tabs.length - 1 : ( i + ( e.key === 'ArrowRight' || e.key === 'ArrowDown' ? 1 : -1 ) + tabs.length ) % tabs.length;
			e.preventDefault();
			tabs[ next ].focus();
			selectTab( tabs[ next ] );
			return;
		}
		if ( e.key === 'Enter' && e.target.matches && e.target.matches( '.brik-cf-map-address' ) ) {
			e.preventDefault();
			geocode( e.target.closest( '.brik-cf-map' ) );
		}
	} );

	// Clicking the selected segment of an optional button group clears it.
	document.addEventListener(
		'mousedown',
		( e ) => {
			const seg = e.target.closest && e.target.closest( '.brik-cf-button-group[data-allow-null] .brik-cf-segment' );
			if ( seg ) {
				const radio = $( 'input', seg );
				seg.dataset.wasChecked = radio.checked ? '1' : '';
			}
		},
		true
	);
	document.addEventListener( 'click', ( e ) => {
		const seg = e.target.closest && e.target.closest( '.brik-cf-button-group[data-allow-null] .brik-cf-segment' );
		if ( seg && seg.dataset.wasChecked && e.target.tagName === 'INPUT' ) {
			e.target.checked = false;
			seg.dataset.wasChecked = '';
			fire( e.target );
		}
	} );

	const onEdit = ( e ) => {
		const el = e.target;
		if ( ! el.closest || ! el.closest( '.brik-cf' ) ) {
			return;
		}
		const field = fieldOf( el );
		if ( el.matches( '.brik-cf-range' ) ) {
			const out = $( '.brik-cf-range-value span', el.closest( '.brik-cf-range-wrap' ) );
			if ( out ) {
				out.textContent = el.value;
			}
		} else if ( el.matches( '.brik-cf-color-picker' ) ) {
			syncColor( el.closest( '.brik-cf-color' ), true );
		} else if ( el.matches( '.brik-cf-color-text' ) ) {
			syncColor( el.closest( '.brik-cf-color' ), false );
		} else if ( el.matches( '.brik-cf-switch' ) ) {
			const text = $( '.brik-cf-switch-text', el.closest( '.brik-cf-switch-row' ) );
			if ( text ) {
				text.textContent = el.checked ? text.dataset.on : text.dataset.off;
			}
		} else if ( el.matches( '.brik-cf-map-lat, .brik-cf-map-lng' ) ) {
			updateMap( el.closest( '.brik-cf-map' ) );
		} else if ( el.matches( '.brik-cf-terms-filter input' ) ) {
			const q = el.value.trim().toLowerCase();
			$$( '.brik-cf-terms-list .brik-cf-choice', el.closest( '.brik-cf-terms' ) ).forEach( ( c ) => {
				c.hidden = q !== '' && ! c.textContent.toLowerCase().includes( q );
			} );
			return;
		}
		if ( e.type === 'change' && el.matches( '.brik-cf-oembed-url' ) ) {
			oembedPreview( el.closest( '.brik-cf-oembed' ) );
		}
		if ( el.matches( '.brik-cf-picker-search, .brik-cf-rel-search' ) ) {
			return;
		}
		const row = el.closest( '.brik-cf-row' );
		if ( row ) {
			rowTitle( row );
		}
		if ( field ) {
			field.dataset.touched = '1';
		}
		applyConditions( el.closest( '.brik-cf' ) );
		if ( field && field.dataset.touched ) {
			validate( field.closest( '.brik-cf' ), false );
		}
		if ( cfg.blockEditor ) {
			syncSoon();
		}
	};
	document.addEventListener( 'input', onEdit );
	document.addEventListener( 'change', onEdit );
	document.addEventListener(
		'blur',
		( e ) => {
			const el = e.target;
			if ( el.matches && el.matches( '.brik-cf-oembed-url' ) ) {
				oembedPreview( el.closest( '.brik-cf-oembed' ) );
			}
		},
		true
	);

	/* Block editor: server notices printed in a meta box become editor notices. */
	function liftNotices() {
		const wp = window.wp;
		if ( ! wp || ! wp.data || ! wp.data.dispatch( 'core/notices' ) ) {
			return;
		}
		$$( '.brik-cf-notice' ).forEach( ( n ) => {
			const items = $$( 'li', n ).map( ( li ) => li.textContent );
			wp.data.dispatch( 'core/notices' ).createNotice( 'error', $( 'strong', n ).textContent + ' ' + items.join( ' ' ), {
				id: 'brik-content-fields-server',
			} );
			n.remove();
		} );
	}

	function start() {
		$$( '.brik-cf' ).forEach( ( r ) => ( r.dataset.brikRoot = '1' ) );
		setup( document );
		applyConditions( document );
		if ( cfg.blockEditor ) {
			liftNotices();
			syncBlockEditor();
		}
	}

	if ( window.wp && window.wp.domReady ) {
		window.wp.domReady( start );
	} else if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}

	// Meta boxes of the block editor can mount after DOM ready.
	if ( cfg.blockEditor ) {
		const check = debounce( () => {
			const fresh = $$( '.brik-cf:not([data-brik-root])' ).filter( ( r ) => ! inTemplate( r ) );
			if ( fresh.length ) {
				setup( document );
				applyConditions( document );
				fresh.forEach( ( r ) => ( r.dataset.brikRoot = '1' ) );
				syncSoon();
			}
		}, 150 );
		new MutationObserver( check ).observe( document.body, { childList: true, subtree: true } );
	}

	window.brikContentFields = { setup, validate, applyConditions, readValue };
} )();
