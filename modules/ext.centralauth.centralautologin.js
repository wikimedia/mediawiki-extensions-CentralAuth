( function ( mw, $ ) {
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

	var centralEndpoint = mw.config.get( 'wgCentralAuthCentralAutoLoginEndpoint' ),
		localEndpoint = mw.config.get( 'wgArticlePath' ).replace( '$1', 'Special:CentralAutoLogin/$1' ),
		wikiId = mw.config.get( 'wgCentralAuthWikiID' );

	function getReturnToParams() {
		var params = location.search.slice( 1 ).split( '&' ),
			len = params.length,
			param, i,
			ret = [];
		for ( i = 0; i < len; i++ ) {
			param = params[i].split( '=' );
			param = decodeURIComponent( param[0] );
			if ( param === 'returnto' || param === 'returntoquery' ) {
				ret.push( params[i] );
			}
		}
		return ret.join( '&' );
	}

	// These functions should be declared in the "if ( jQuery.support.cors )"
	// where they're actually needed, but jshint insists they be placed up here
	// instead.
	// Documentation: [[mw:Auth systems/SUL2#Local wiki Javascript Auth check]]

	// State C1
	function fetchCentralUserId() {
		// Also, disable this brain-damaged warning. We're not going to rewrite
		// all the PHP code because it doesn't like "ret.gu_id".
		/*jshint camelcase: false */
		$.ajax( {
			url: centralEndpoint.replace( '$1', 'C1' ),
			dataType: 'json',
			type: 'POST',
			data: { wikiid: wikiId },
			xhrFields: {
				withCredentials: true
			}
		} ).done( function ( ret ) {
			/*jshint camelcase: false */
			if ( ret.status === 'ok' ) {
				if ( +ret.gu_id <= 0 ) {
					var t = new Date();
					t.setTime( t.getTime() + 86400000 );
					if ( 'localStorage' in window ) {
						localStorage.setItem( 'CentralAuthAnon', t.getTime() );
					} else {
						document.cookie = 'CentralAuthAnon=1; expires=' + t.toGMTString() + '; path=/';
					}
				} else {
					if ( 'localStorage' in window ) {
						localStorage.removeItem( 'CentralAuthAnon' );
					}
					if ( /(^|; )CentralAuthAnon=/.test( document.cookie ) ) {
						document.cookie = 'CentralAuthAnon=0; expires=Thu, 01 Jan 1970 00:00:01 GMT; path=/';
					}
					createLocalSessionForCentralLogin( ret.gu_id );
				}
			}
		} );
	}

	// State L1
	function createLocalSessionForCentralLogin( guid ) {
		/*jshint camelcase: false */
		$.ajax( {
			url: localEndpoint.replace( '$1', 'L1' ),
			dataType: 'json',
			type: 'POST',
			data: { gu_id: guid }
		} ).done( function ( ret ) {
			if ( ret.status === 'ok' ) {
				haveCentralWikiPutLoginDataInMemcached( ret.token );
			}
		} );
	}

	// State C2
	function haveCentralWikiPutLoginDataInMemcached( token ) {
		$.ajax( {
			url: centralEndpoint.replace( '$1', 'C2' ),
			dataType: 'json',
			type: 'POST',
			data: { token: token, wikiid: wikiId },
			xhrFields: {
				withCredentials: true
			}
		} ).done( function ( ret ) {
			if ( ret.status === 'ok' ) {
				checkMemcachedDataAndSetCentralAuthCookies();
			}
		} );
	}

	// State L2
	function checkMemcachedDataAndSetCentralAuthCookies() {
		$.ajax( {
			url: localEndpoint.replace( '$1', 'L2' ),
			dataType: 'json',
			type: 'POST',
			data: {
				oncomplete: 'rewrite-p-personal'
			}
		} ).done( function ( ret ) {
			if ( ret.status === 'ok' ) {
				if ( mw.config.get( 'wgCanonicalSpecialPageName' ) === 'Userlogin' ) {
					var url = mw.config.get( 'wgArticlePath' ).replace( '$1', 'Special:CentralLogin/Status' );
					url += ( url.indexOf( '?' ) < 0 ? '?' : '&' ) + getReturnToParams();
					location.href = url;
				} else {
					$( '#p-personal ul' ).html( ret.personal );
				}
			}
		} );
	}

	if ( jQuery.support.cors ) {
		// We can do AJAX calls using CORS.

		// Start by calling state C1
		fetchCentralUserId();
	} else {
		// We have to do it with an iframe. State "L0" returns the first
		// auto-posting form for us, and the protocol proceeds from there
		// without our intervention.
		$( function () {
			var url = localEndpoint.replace( '$1', 'L0' );
			url += ( url.indexOf( '?' ) < 0 ? '?' : '&' );
			if ( mw.config.get( 'wgCanonicalSpecialPageName' ) === 'Userlogin' ) {
				url += 'oncomplete=status';
				url += '&' + getReturnToParams();
			} else {
				url += 'oncomplete=rewrite-p-personal';
			}
			$( '<iframe>' )
				.css( {
					position: 'absolute',
					top: 0,
					left: '-10px',
					width: '1px',
					height: '1px'
				} )
				.attr( 'src', url )
				.appendTo( document.body );
		} );
	}

}( mediaWiki, jQuery ) );
