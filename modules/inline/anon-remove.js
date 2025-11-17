/* eslint-disable mediawiki/no-storage, mediawiki/no-cookie -- Dependency-free inline script */

try {
	localStorage.removeItem( 'CentralAuthAnon' );
} catch ( e ) {}
try {
	if ( /(^|; )CentralAuthAnon=/.test( document.cookie ) ) {
		document.cookie = 'CentralAuthAnon=0; expires=Thu, 01 Jan 1970 00:00:01 GMT; path=/';
	}
} catch ( e ) {}
