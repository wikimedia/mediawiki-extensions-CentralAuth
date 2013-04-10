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

	var centralEndpoint = mw.config.get( 'wgCentralAuthCentralAutoLoginEndpoint' );
	var localEndpoint = mw.config.get( 'wgArticlePath' ).replace( '$1', 'Special:CentralAutoLogin/$1' );
	var wikiId = mw.config.get( 'wgCentralAuthWikiID' );

	if ( jQuery.support.cors ) {
		// We can do AJAX calls using CORS.
		// Documentation: [[mw:Auth systems/SUL2#Local wiki Javascript Auth check]]
		// Function names refer to states described in the documentation.

		function C1() {
			$.ajax( {
				url: centralEndpoint.replace( '$1', 'C1' ),
				dataType: 'json',
				type: 'POST',
				data: { wikiid: wikiId },
				xhrFields: {
					withCredentials: true
				}
			} ).done( function ( ret ) {
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
						L1( ret.gu_id );
					}
				}
			} );
		}

		function L1( gu_id ) {
			$.ajax( {
				url: localEndpoint.replace( '$1', 'L1' ),
				dataType: 'json',
				type: 'POST',
				data: { gu_id: gu_id }
			} ).done( function ( ret ) {
				if ( ret.status === 'ok' ) {
					C2( ret.token );
				}
			} );
		}

		function C2( token ) {
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
					L2();
				}
			} );
		}

		function L2() {
			$.ajax( {
				url: localEndpoint.replace( '$1', 'L2' ),
				dataType: 'json',
				type: 'POST',
				data: {}
			} ).done( function ( ret ) {
				if ( ret.status === 'ok' ) {
					if ( mw.config.get( 'wgCanonicalSpecialPageName' ) === 'Userlogin' && history.length > 1 ) {
						history.go( -1 );
					} else {
						location.reload( true );
					}
				}
			} );
		}

		C1();
	} else {
		// We have to do it with an iframe. State "L0" returns the first
		// auto-posting form for us, and the protocol proceeds from there
		// without our intervention.
		$( function () {
			var url = localEndpoint.replace( '$1', 'L0' );
			if ( mw.config.get( 'wgCanonicalSpecialPageName' ) === 'Userlogin' && history.length > 1 ) {
				url += ( url.indexOf( '?' ) < 0 ? '?' : '&' ) + 'back=1';
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
