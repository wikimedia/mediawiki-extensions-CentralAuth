( function () {
	// Are we already logged in?
	if ( mw.config.get( 'wgUserName' ) !== null ) {
		return;
	}

	// If we are on Special:Userlogin and logged out, type=redirect autologin failed.
	// No point in trying the less reliable type=script version.
	if ( mw.config.get( 'wgCanonicalSpecialPageName' ) === 'Userlogin' ) {
		return;
	}

	// Do we already know we're logged out centrally?
	try {
		if ( +localStorage.getItem( 'CentralAuthAnon' ) > Date.now() ) {
			return;
		}
	} catch ( e ) {}

	// Optimisation: Avoid $.cookie() to reduce depencency cost for all pages.
	try {
		if ( /(^|; )CentralAuthAnon=1/.test( document.cookie ) ) {
			return;
		}
	} catch ( e ) {
		// If we can't read cookies, we can't write cookies either.
		// Don't even try autologin.
		return;
	}

	// Perform the actual logged-in check via a <script> tag.
	// The referenced URL will redirect a few times (HTTP 302) and then respond
	// with JavaScript code that completes the login.
	// The data comes from CentralAuthHooks::getCentralautologinJsData().
	let url = require( './data.json' ).startURL;

	if ( url ) {
		if ( mw.config.get( 'wgCanonicalSpecialPageName' ) === 'Userlogin' ) {
			url += '&return=1';

			const params = location.search.slice( 1 ).split( '&' );
			for ( let i = 0, len = params.length; i < len; i++ ) {
				const paramParts = params[ i ].split( '=' );
				const param = decodeURIComponent( paramParts[ 0 ] );
				if ( param === 'returnto' || param === 'returntoquery' ) {
					url += '&' + params[ i ];
				}
			}
		}
		mw.loader.load( url );
	}
}() );
