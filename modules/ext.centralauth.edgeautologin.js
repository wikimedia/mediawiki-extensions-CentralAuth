( function ( mw, $ ) {
	var max = 0, count = 0, loggedInto = [],
		$progressbar;

	function edgeLoginDone() {
		var msg, list;
		if ( loggedInto.length ) {
			list = loggedInto.slice();
			if ( list.length > 1 ) {
				list[list.length - 1] = mw.message( 'and' ).text() +
					mw.message( 'word-separator' ).text() +
					list[list.length - 1];
			}
			list = list.join( mw.message( list.length > 2 ? 'comma-separator' : 'word-separator' ).text() );
			msg = mw.message(
				'centralauth-edge-logged-in-list',
				mw.user,
				loggedInto.length,
				list
			);
		} else {
			msg = mw.message( 'centralauth-edge-logged-in', mw.user );
		}
		$( '#centralauth-edge-login-info' )
			.text( msg.text() )
			.css( 'display', 'block' );
	}

	mw.CentralAuth = {
		edgeLoginComplete: function ( frame, domain ) {
			if ( frame ) {
				$( frame ).trigger( 'ca-done' );
			}
			if ( domain ) {
				loggedInto.push( domain );
			}
			$progressbar.progressbar( 'value', ++count );
			if ( count >= max ) {
				edgeLoginDone();
			}
		}
	};

	$( function () {
		var $urlSpans;

		$urlSpans = $( '.centralauth-edge-login-url' );
		if ( $urlSpans.length <= 0 ) {
			edgeLoginDone();
			return;
		}

		max = $urlSpans.length;
		$progressbar = $( '<div>' );
		$progressbar.progressbar( { max: max, value: count } );

		$( '#centralauth-edge-login-info' )
			.text( mw.message( 'centralauth-edge-logging-in', mw.user ) )
			.append( $progressbar )
			.css( 'display', 'block' );

		$urlSpans.each( function () {
			var timer, $iframe;

			function giveUp() {
				timer = null;
				mw.CentralAuth.edgeLoginComplete( $iframe, null, [ 'timed-out' ] );
			}
			timer = setTimeout( giveUp, 10000 );

			$iframe = $( '<iframe>' )
				.css( {
					position: 'absolute',
					top: 0,
					left: '-10px',
					width: '1px',
					height: '1px'
				} )
				.on( 'load', function () {
					// Reset timeout
					if ( timer ) {
						clearTimeout( timer );
						timer = setTimeout( giveUp, 10000 );
					}
				} )
				.on( 'ca-done', function () {
					if ( timer ) {
						clearTimeout( timer );
						timer = null;
					}
				} )
				.attr( 'src', $( this ).data( 'src' ) )
				.appendTo( document.body );
		} );
	} );
}( mediaWiki, jQuery ) );
