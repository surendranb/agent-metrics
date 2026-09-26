(function () {
	'use strict';
	document.addEventListener( 'DOMContentLoaded', function () {
		document.addEventListener( 'click', function ( e ) {
			var b = e.target.closest( '.inplainsite-copy, .am-copy' );
			if ( b ) {
				navigator.clipboard.writeText( b.dataset.copy ).then( function () {
					var old = b.textContent;
					b.textContent = 'copied';
					setTimeout( function () {
						b.textContent = old;
					}, 1200 );
				} );
			}
		} );

		var box  = document.getElementById( 'inplainsite-mcp-config' ) || document.getElementById( 'am-mcp-config' );
		var copy = document.getElementById( 'inplainsite-mcp-copy' ) || document.getElementById( 'am-mcp-copy' );
		if ( box && copy ) {
			document.querySelectorAll( '.inplainsite-logo, .am-logo' ).forEach( function ( l ) {
				l.addEventListener( 'click', function () {
					box.value = l.dataset.config;
					copy.dataset.copy = box.value;
					box.focus();
				} );
			} );
			copy.dataset.copy = box.value;
		}
	} );
})();
