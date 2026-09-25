/* globals wptNoIndex */
( function () {
	'use strict';

	function postAjax( action, data, callback ) {
		const body = new FormData();
		body.append( 'action', action );
		body.append( 'nonce', wptNoIndex.nonce );

		Object.keys( data ).forEach( function ( key ) {
			body.append( key, data[ key ] );
		} );

		fetch( wptNoIndex.ajaxUrl, { method: 'POST', body } )
			.then( function ( r ) {
				return r.json();
			} )
			.then( callback )
			.catch( function () {} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		const disableBtn = document.getElementById( 'wpt-noindex-disable-btn' );
		const enableBtn = document.getElementById( 'wpt-noindex-enable-btn' );

		if ( disableBtn ) {
			disableBtn.addEventListener( 'click', function () {
				const durationEl = document.getElementById(
					'wpt-noindex-duration'
				);

				const duration = durationEl ? durationEl.value : '300';
				disableBtn.disabled = true;

				disableBtn.textContent =
					disableBtn.getAttribute( 'data-loading' );

				postAjax(
					'wpt_noindex_disable',
					{ duration },
					function ( res ) {
						if ( res && res.success ) {
							window.location.reload();
						} else {
							disableBtn.disabled = false;

							disableBtn.textContent =
								disableBtn.getAttribute( 'data-label' );
						}
					}
				);
			} );
		}

		if ( enableBtn ) {
			enableBtn.addEventListener( 'click', function () {
				enableBtn.disabled = true;

				enableBtn.textContent =
					enableBtn.getAttribute( 'data-loading' );

				postAjax( 'wpt_noindex_enable', {}, function ( res ) {
					if ( res && res.success ) {
						window.location.reload();
					} else {
						enableBtn.disabled = false;

						enableBtn.textContent =
							enableBtn.getAttribute( 'data-label' );
					}
				} );
			} );
		}
	} );
} )();
