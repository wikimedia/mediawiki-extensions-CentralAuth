if ( 'localStorage' in window ) {
	localStorage.removeItem( 'CentralAuthAnon' );
}
if ( /(^|; )CentralAuthAnon=/.test( document.cookie ) ) {
	document.cookie = 'CentralAuthAnon=0; expires=Thu, 01 Jan 1970 00:00:01 GMT; path=/';
}
