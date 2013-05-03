( function ( mw, $ ) {
	var loggedInto = [];

	mw.CentralAuth = {
		loggedIn: function ( domain ) {
			loggedInto.push( domain );
			$( '#centralauth-edge-login-info' ).text(
				mw.message(
					'centralauth-edge-logged-in',
					loggedInto.length,
					loggedInto.join( ' ' )
				).text()
			);
		}
	};
}( mediaWiki, jQuery ) );
