( function ( mw ) {
	// Are we already logged in?
	if ( mw.config.get( 'wgUserName' ) !== null ) {
		return;
	}

	// Do we already know we're logged out centrally?
	if ( mw.config.get( 'wgCanonicalSpecialPageName' ) !== 'Userlogin' ) {
		if ( 'localStorage' in window && +localStorage.getItem( 'CentralAuthAnon' ) > new Date().getTime() ) {
			return;
		}

		// Can't use $.cookie(), because we want to check this at the top of
		// the page and that isn't loaded until the bottom.
		if ( /(^|; )CentralAuthAnon=1/.test( document.cookie ) ) {
			return;
		}
	}

	// Ok, perform the acutal logged-in check via a <script> tag. The
	// referenced URL will 302 a few times and then return appropriate
	// JavaScript to complete the process.
	var url, params, len, param, i, returntoquery;

	url = mw.config.get( 'wgServer' ) +
		mw.config.get( 'wgArticlePath' ).replace( '$1', 'Special:CentralAutoLogin/start' );
	url += ( url.indexOf( '?' ) < 0 ? '?' : '&' ) + 'type=script';
	if ( mw.config.get( 'wgCanonicalSpecialPageName' ) === 'Userlogin' ) {
		url += '&return=1';

		params = location.search.slice( 1 ).split( '&' );
		len = params.length;
		for ( i = 0; i < len; i++ ) {
			param = params[i].split( '=' );
			param = decodeURIComponent( param[0] );
			if ( param === 'returnto' || param === 'returntoquery' ) {
				url += '&' + params[i];
			}
		}
	} else {
		returntoquery = [];
		params = location.search.slice( 1 ).split( '&' );
		len = params.length;
		for ( i = 0; i < len; i++ ) {
			param = params[i].split( '=' );
			param = decodeURIComponent( param[0] );
			if ( param !== 'title' && param !== 'returnto' && param !== 'returntoquery' ) {
				returntoquery.push( params[i] );
			}
		}
	}

	mw.loader.load( url );
}( mediaWiki ) );
