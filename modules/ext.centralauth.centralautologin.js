( function () {
	var url, params, len, param, i;
	// Are we already logged in?
	if ( mw.config.get( 'wgUserName' ) !== null ) {
		return;
	}

	// Do we already know we're logged out centrally?
	if ( mw.config.get( 'wgCanonicalSpecialPageName' ) !== 'Userlogin' ) {
		try {
			if ( +localStorage.getItem( 'CentralAuthAnon' ) > Date.now() ) {
				return;
			}
		} catch ( e ) {}

		// Optimisation: Avoid $.cookie() to reduce depencency cost for all pages.
		if ( /(^|; )CentralAuthAnon=1/.test( document.cookie ) ) {
			return;
		}
	}

	// Perform the actual logged-in check via a <script> tag.
	// The referenced URL will redirect a few times (HTTP 302) and then respond
	// with JavaScript code that completes the login.
	url = require( './data.json' ).checkLoggedInURL;
	if ( url ) {
		url += '&proto=' + encodeURIComponent( location.protocol.replace( ':', '' ) );
		if ( mw.config.get( 'wgCentralAuthMobileDomain' ) === true ) {
			url += '&mobile=1';
		}
		if ( mw.config.get( 'wgCanonicalSpecialPageName' ) === 'Userlogin' ) {
			url += '&return=1';

			params = location.search.slice( 1 ).split( '&' );
			len = params.length;
			for ( i = 0; i < len; i++ ) {
				param = params[ i ].split( '=' );
				param = decodeURIComponent( param[ 0 ] );
				if ( param === 'returnto' || param === 'returntoquery' ) {
					url += '&' + params[ i ];
				}
			}
		}
		mw.loader.load( url );
	}
}() );
