var t = new Date();
// Sets CentralAuthAnon for 1 day in the future
t.setTime( t.getTime() + 86400000 );
if ( 'localStorage' in window ) {
	localStorage.setItem( 'CentralAuthAnon', t.getTime() );
} else {
	document.cookie = 'CentralAuthAnon=1; expires=' + t.toGMTString() + '; path=/';
}
