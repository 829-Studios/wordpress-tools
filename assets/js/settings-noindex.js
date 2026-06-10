/* globals wptNoIndex */
( function () {
	'use strict';

	function postAjax( action, data, callback ) {
		var body = new FormData();
		body.append( 'action', action );
		body.append( 'nonce', wptNoIndex.nonce );
		Object.keys( data ).forEach( function ( key ) {
			body.append( key, data[ key ] );
		} );
		fetch( wptNoIndex.ajaxUrl, { method: 'POST', body: body } )
			.then( function ( r ) { return r.json(); } )
			.then( callback )
			.catch( function () {} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var disableBtn = document.getElementById( 'wpt-noindex-disable-btn' );
		var enableBtn  = document.getElementById( 'wpt-noindex-enable-btn' );

		if ( disableBtn ) {
			disableBtn.addEventListener( 'click', function () {
				var durationEl = document.getElementById( 'wpt-noindex-duration' );
				var duration   = durationEl ? durationEl.value : '300';
				disableBtn.disabled    = true;
				disableBtn.textContent = disableBtn.getAttribute( 'data-loading' );
				postAjax( 'wpt_noindex_disable', { duration: duration }, function ( res ) {
					if ( res && res.success ) {
						window.location.reload();
					} else {
						disableBtn.disabled    = false;
						disableBtn.textContent = disableBtn.getAttribute( 'data-label' );
					}
				} );
			} );
		}

		if ( enableBtn ) {
			enableBtn.addEventListener( 'click', function () {
				enableBtn.disabled    = true;
				enableBtn.textContent = enableBtn.getAttribute( 'data-loading' );
				postAjax( 'wpt_noindex_enable', {}, function ( res ) {
					if ( res && res.success ) {
						window.location.reload();
					} else {
						enableBtn.disabled    = false;
						enableBtn.textContent = enableBtn.getAttribute( 'data-label' );
					}
				} );
			} );
		}
	} );
} )();
