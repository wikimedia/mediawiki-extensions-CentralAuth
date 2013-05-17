( function ( mw, $ ) {
	var max = 0, count = 0, $progressbar, $tick;

	function edgeLoginDone() {
		$( '#centralauth-edge-login-info' )
			.text( mw.message( 'centralauth-edge-logged-in', mw.user ) )
			.css( 'display', 'block' );
		$tick.css( 'visibility', 'visible' );
	}

	mw.CentralAuth = {
		edgeLoginComplete: function ( frame ) {
			if ( frame ) {
				$( frame ).trigger( 'ca-done' );
			}
			$progressbar.progressbar( 'value', ++count );
			if ( count >= max ) {
				edgeLoginDone();
			}
		}
	};

	$( function () {
		var $urlSpans, $progressbarcontainer;

		$urlSpans = $( '.centralauth-edge-login-url' );
		if ( $urlSpans.length <= 0 ) {
			edgeLoginDone();
			return;
		}

		max = $urlSpans.length;

		$progressbarcontainer = $( '<div>' )
			.attr( 'id', 'centralauth-edge-progressbar' );

		$progressbar = $( '<div>' )
			.appendTo( $progressbarcontainer )
			.progressbar( { max: max, value: count } );
		$tick = $( '<img>' )
			.attr( {
				src: mw.config.get( 'stylepath' ) + '/common/images/tick-32.png',
				width: 32,
				height: 32
			} )
			.css( 'visibility', 'hidden' )
			.appendTo( $progressbarcontainer );

		$( '#centralauth-edge-login-info' )
			.text( mw.message( 'centralauth-edge-logging-in', mw.user ) )
			.after( $progressbarcontainer )
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
