/**
 * A2Z Widgets - auto height.
 *
 * Loaded only when Settings > A2Z Widgets > "Auto height" is on, and only on
 * pages that contain an A2Z widget. Bundled with the plugin; no third-party
 * script is loaded.
 *
 * Each A2Z widget frame posts its content height to the parent page:
 *   { a2z: 'embed', t: 'height', h: <px>, collapsed: <bool> }
 * This script accepts a message only when its origin is exactly the A2Z
 * origin AND it comes from one of the A2Z iframes printed by this plugin,
 * then sets that iframe's height (clamped to 80-5000 px). It sends nothing
 * about the page to the frame except a "ping" asking it to report its height
 * again, sets no cookies and stores nothing.
 */
( function ( win, doc ) {
	'use strict';

	if ( ! win || ! doc || ! win.addEventListener || win.a2zWidgetsAutoHeight ) {
		return;
	}
	win.a2zWidgetsAutoHeight = true;

	var ORIGIN = 'https://a2z.tools';
	var MIN_H = 80;
	var MAX_H = 5000;

	function frames() {
		return doc.querySelectorAll( '.a2z-widgets-embed iframe' );
	}

	function findFrame( source ) {
		var list = frames();
		for ( var i = 0; i < list.length; i++ ) {
			if ( list[ i ].contentWindow === source ) {
				return list[ i ];
			}
		}
		return null;
	}

	function ping( frame ) {
		try {
			if ( frame.contentWindow ) {
				frame.contentWindow.postMessage( { a2z: 'embed', t: 'ping' }, ORIGIN );
			}
		} catch ( e ) {
			// Frame not ready yet; it reports again on its own load.
		}
	}

	win.addEventListener( 'message', function ( ev ) {
		if ( ev.origin !== ORIGIN || ! ev.data || 'embed' !== ev.data.a2z || 'height' !== ev.data.t ) {
			return;
		}
		var frame = findFrame( ev.source );
		if ( ! frame ) {
			return;
		}
		var h = Math.round( Number( ev.data.h ) );
		if ( true === ev.data.collapsed && 0 === h ) {
			frame.style.height = '0px';
			return;
		}
		if ( isFinite( h ) ) {
			frame.style.height = Math.max( MIN_H, Math.min( MAX_H, h ) ) + 'px';
		}
	} );

	function start() {
		var list = frames();
		for ( var i = 0; i < list.length; i++ ) {
			( function ( frame ) {
				// A frame that loaded before this script reported its height to
				// nobody: ask it again, now and after its next load.
				frame.addEventListener( 'load', function () {
					ping( frame );
				} );
				ping( frame );
			}( list[ i ] ) );
		}
	}

	if ( 'loading' === doc.readyState ) {
		doc.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}( typeof window !== 'undefined' ? window : null, typeof document !== 'undefined' ? document : null ) );
