var t = new Date();
// Set CentralAuthAnon to 1 day in the future
t.setTime( t.getTime() + 86400000 );
if ( 'localStorage' in window && window.localStorage ) {
	localStorage.setItem( 'CentralAuthAnon', t.getTime() );
} else {
	document.cookie = 'CentralAuthAnon=1; expires=' + t.toGMTString() + '; path=/';
}
