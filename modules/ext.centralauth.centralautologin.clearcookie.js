( function () {
	if ( mw.config.get( 'wgUserName' ) !== null ) {
		try {
			localStorage.removeItem( 'CentralAuthAnon' );
		} catch ( e ) {}
		if ( /(^|; )CentralAuthAnon=1/.test( document.cookie ) ) {
			document.cookie = 'CentralAuthAnon=0; expires=Thu, 01 Jan 1970 00:00:01 GMT; path=/';
		}
	}
}() );
