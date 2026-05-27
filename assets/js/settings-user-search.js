/* globals wptUserSearch, jQuery */
(function ( $ ) {
	'use strict';

	var activeRequest = null;

	function getSelectedIds( $idsContainer ) {
		return $idsContainer.find( 'input[type="hidden"]' ).map( function () {
			return parseInt( this.value, 10 );
		} ).get();
	}

	function attachTagRemoveHandlers( $tags, $ids ) {
		$tags.find( '.wpt-user-tag-remove' ).each( function () {
			var $btn = $( this );
			if ( $btn.data( 'handlerAttached' ) ) {
				return;
			}
			$btn.data( 'handlerAttached', true );
			var userId = $btn.data( 'userId' );
			$btn.on( 'click', function () {
				$ids.find( 'input[value="' + userId + '"]' ).remove();
				$btn.closest( '.wpt-user-tag' ).remove();
			} );
		} );
	}

	function addUserTag( user, $tags, $ids, settingKey ) {
		var id = parseInt( user.id, 10 );

		if ( $ids.find( 'input[value="' + id + '"]' ).length ) {
			return;
		}

		var $tag = $(
			'<span class="wpt-user-tag">' +
				'<span class="wpt-user-tag-label"></span>' +
				'<button type="button" class="wpt-user-tag-remove" data-user-id="' + id + '" aria-label="' + wptUserSearch.removeLabel + '">&times;</button>' +
			'</span>'
		);

		$tag.find( '.wpt-user-tag-label' ).text( user.label );
		$tags.append( $tag );

		$ids.append(
			$( '<input>' ).attr( {
				type:  'hidden',
				name:  'wpt_settings[' + settingKey + '][]',
				value: id,
			} )
		);

		attachTagRemoveHandlers( $tags, $ids );
	}

	function initUserSearch( $wrapper ) {
		var settingKey = $wrapper.data( 'settingKey' );
		var $input     = $wrapper.find( '.wpt-user-search-input' );
		var $tags      = $wrapper.find( '.wpt-user-tags' );
		var $ids       = $wrapper.find( '.wpt-user-ids' );

		attachTagRemoveHandlers( $tags, $ids );

		$input.autocomplete( {
			minLength: 2,
			source: function ( request, response ) {
				if ( activeRequest ) {
					activeRequest.abort();
				}

				activeRequest = $.ajax( {
					url:  wptUserSearch.ajaxUrl,
					type: 'POST',
					data: {
						action:  'wpt_search_users',
						nonce:   wptUserSearch.nonce,
						search:  request.term,
						exclude: getSelectedIds( $ids ),
					},
					success: function ( data ) {
						activeRequest = null;
						response( data.success ? data.data : [] );
					},
					error: function () {
						activeRequest = null;
						response( [] );
					},
				} );
			},
			select: function ( event, ui ) {
				event.preventDefault();
				$input.val( '' );
				addUserTag( ui.item, $tags, $ids, settingKey );
			},
			focus: function ( event ) {
				event.preventDefault();
			},
		} );
	}

	function initRestrictionToggle( name ) {
		var $radios = $( 'input[name="wpt_settings[' + name + ']"]' );

		function updateVisibility( value ) {
			$( '[data-restriction="' + name + '"]' ).toggle( '1' === value );
		}

		$radios.filter( ':checked' ).each( function () {
			updateVisibility( $( this ).val() );
		} );

		$radios.on( 'change', function () {
			updateVisibility( $( this ).val() );
		} );
	}

	$( function () {
		$( '.wpt-user-search' ).each( function () {
			initUserSearch( $( this ) );
		} );

		initRestrictionToggle( 'restrict_plugin_management' );
		initRestrictionToggle( 'restrict_theme_management' );
		initRestrictionToggle( 'restrict_829_credential_login' );
	} );

} )( jQuery );
