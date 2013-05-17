( function ( mw, $ ) {
	$( function () {
		var i, l, urls = mw.config.get( 'wgCentralAuthEdgeAutoLoginEndpoints' );

		for ( i = 0, l = urls.length; i < l; i++ ) {
			$( '<iframe>' )
				.css( {
					position: 'absolute',
					top: 0,
					left: '-10px',
					width: '1px',
					height: '1px'
				} )
				.attr( 'src', urls[i] )
				.appendTo( document.body );
		}
	} );
}( mediaWiki, jQuery ) );
