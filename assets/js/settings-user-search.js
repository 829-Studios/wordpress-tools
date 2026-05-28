/* globals wptUserSearch */
(function () {
	'use strict';

	var activeRequest = null;

	function getSelectedIds( idsContainer ) {
		return Array.from( idsContainer.querySelectorAll( 'input[type="hidden"]' ) ).map( function ( input ) {
			return parseInt( input.value, 10 );
		} );
	}

	function attachTagRemoveHandlers( tagsContainer, idsContainer ) {
		tagsContainer.querySelectorAll( '.wpt-user-tag-remove' ).forEach( function ( btn ) {
			if ( btn.dataset.handlerAttached ) {
				return;
			}
			btn.dataset.handlerAttached = 'true';
			var userId = btn.dataset.userId;
			btn.addEventListener( 'click', function () {
				var hidden = idsContainer.querySelector( 'input[value="' + userId + '"]' );
				if ( hidden ) {
					hidden.remove();
				}
				btn.closest( '.wpt-user-tag' ).remove();
			} );
		} );
	}

	function addUserTag( user, tagsContainer, idsContainer, settingKey ) {
		var id = parseInt( user.id, 10 );

		if ( idsContainer.querySelector( 'input[value="' + id + '"]' ) ) {
			return;
		}

		var tag       = document.createElement( 'span' );
		tag.className = 'wpt-user-tag';

		var label         = document.createElement( 'span' );
		label.className   = 'wpt-user-tag-label';
		label.textContent = user.label;

		var removeBtn = document.createElement( 'button' );
		removeBtn.type            = 'button';
		removeBtn.className       = 'wpt-user-tag-remove';
		removeBtn.dataset.userId  = id;
		removeBtn.setAttribute( 'aria-label', wptUserSearch.removeLabel );
		removeBtn.textContent     = '×';

		tag.appendChild( label );
		tag.appendChild( removeBtn );
		tagsContainer.appendChild( tag );

		var hidden  = document.createElement( 'input' );
		hidden.type  = 'hidden';
		hidden.name  = 'wpt_settings[' + settingKey + '][]';
		hidden.value = id;
		idsContainer.appendChild( hidden );

		attachTagRemoveHandlers( tagsContainer, idsContainer );
	}

	function initAutocomplete( input, options ) {
		var minLength = options.minLength || 1;

		var inputWrap       = document.createElement( 'div' );
		inputWrap.className = 'wpt-user-search-input-wrap';
		input.parentNode.insertBefore( inputWrap, input );
		inputWrap.appendChild( input );

		var listEl       = document.createElement( 'ul' );
		listEl.className = 'wpt-autocomplete-list';
		listEl.hidden    = true;
		inputWrap.appendChild( listEl );

		var activeIndex  = -1;
		var currentItems = [];

		function closeList() {
			listEl.hidden    = true;
			listEl.innerHTML = '';
			activeIndex      = -1;
			currentItems     = [];
		}

		function setActive( index ) {
			listEl.querySelectorAll( '.wpt-autocomplete-item' ).forEach( function ( li ) {
				li.classList.remove( 'is-active' );
			} );
			if ( index >= 0 && index < currentItems.length ) {
				listEl.querySelectorAll( '.wpt-autocomplete-item' )[ index ].classList.add( 'is-active' );
				activeIndex = index;
			}
		}

		function renderItems( items ) {
			listEl.innerHTML = '';
			currentItems     = items;

			if ( ! items.length ) {
				closeList();
				return;
			}

			items.forEach( function ( item, i ) {
				var li       = document.createElement( 'li' );
				li.className = 'wpt-autocomplete-item';
				li.textContent = item.label;
				li.addEventListener( 'mouseenter', function () {
					setActive( i );
				} );
				li.addEventListener( 'mousedown', function ( e ) {
					e.preventDefault();
					options.select( item );
					closeList();
				} );
				listEl.appendChild( li );
			} );

			listEl.hidden = false;
			activeIndex   = -1;
		}

		input.addEventListener( 'input', function () {
			var term = input.value;
			if ( term.length < minLength ) {
				closeList();
				return;
			}
			options.source( { term: term }, renderItems );
		} );

		input.addEventListener( 'keydown', function ( e ) {
			if ( listEl.hidden ) {
				return;
			}
			if ( 'ArrowDown' === e.key ) {
				e.preventDefault();
				setActive( Math.min( activeIndex + 1, currentItems.length - 1 ) );
			} else if ( 'ArrowUp' === e.key ) {
				e.preventDefault();
				setActive( Math.max( activeIndex - 1, 0 ) );
			} else if ( 'Enter' === e.key && activeIndex >= 0 ) {
				e.preventDefault();
				options.select( currentItems[ activeIndex ] );
				closeList();
			} else if ( 'Escape' === e.key ) {
				closeList();
			}
		} );

		document.addEventListener( 'click', function ( e ) {
			if ( ! inputWrap.contains( e.target ) ) {
				closeList();
			}
		} );
	}

	function initUserSearch( wrapper ) {
		var settingKey    = wrapper.dataset.settingKey;
		var input         = wrapper.querySelector( '.wpt-user-search-input' );
		var tagsContainer = wrapper.querySelector( '.wpt-user-tags' );
		var idsContainer  = wrapper.querySelector( '.wpt-user-ids' );

		attachTagRemoveHandlers( tagsContainer, idsContainer );

		initAutocomplete( input, {
			minLength: 2,
			source: function ( request, response ) {
				if ( activeRequest ) {
					activeRequest.abort();
				}

				var controller = new AbortController();
				activeRequest  = controller;

				var formData = new FormData();
				formData.append( 'action', 'wpt_search_users' );
				formData.append( 'nonce', wptUserSearch.nonce );
				formData.append( 'search', request.term );
				getSelectedIds( idsContainer ).forEach( function ( id ) {
					formData.append( 'exclude[]', id );
				} );

				fetch( wptUserSearch.ajaxUrl, {
					method: 'POST',
					body:   formData,
					signal: controller.signal,
				} )
					.then( function ( res ) { return res.json(); } )
					.then( function ( data ) {
						activeRequest = null;
						response( data.success ? data.data : [] );
					} )
					.catch( function ( err ) {
						if ( 'AbortError' !== err.name ) {
							activeRequest = null;
							response( [] );
						}
					} );
			},
			select: function ( item ) {
				input.value = '';
				addUserTag( item, tagsContainer, idsContainer, settingKey );
			},
		} );
	}

	function initRestrictionToggle( name ) {
		var radios = document.querySelectorAll( 'input[name="wpt_settings[' + name + ']"]' );

		function updateVisibility( value ) {
			document.querySelectorAll( '[data-restriction="' + name + '"]' ).forEach( function ( el ) {
				el.style.display = '1' === value ? '' : 'none';
			} );
		}

		radios.forEach( function ( radio ) {
			if ( radio.checked ) {
				updateVisibility( radio.value );
			}
			radio.addEventListener( 'change', function () {
				updateVisibility( radio.value );
			} );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.wpt-user-search' ).forEach( function ( wrapper ) {
			initUserSearch( wrapper );
		} );

		initRestrictionToggle( 'restrict_plugin_management' );
		initRestrictionToggle( 'restrict_theme_management' );
		initRestrictionToggle( 'restrict_829_credential_login' );
	} );
} )();
