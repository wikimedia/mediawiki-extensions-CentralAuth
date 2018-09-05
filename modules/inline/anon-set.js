( function () {

	var t = new Date();
	// Set CentralAuthAnon to 1 day in the future
	t.setTime( t.getTime() + 86400000 );
	try {
		localStorage.setItem( 'CentralAuthAnon', t.getTime() );
	} catch ( e ) {
		/* When no localStorage or when QUOTA reached */
		document.cookie = 'CentralAuthAnon=1; expires=' + t.toGMTString() + '; path=/';
	}

}() );
