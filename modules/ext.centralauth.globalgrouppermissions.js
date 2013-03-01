( function ( $ ) {
	$( document ).ready( function () {
		$( '#ca-ggp-name-edit' ).click( function () {
			$( '#ca-ggp-name' ).html( $( '<input>' ).attr( { 'type': 'text', 'name': 'newname' } ).val( $( '#ca-ggp-name-current' ).text() ) );
		} );
	} );
}( jQuery ) );
