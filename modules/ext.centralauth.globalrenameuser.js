( function ( mw, $ ) {
	$( document ).ready( function () {
		/*global confirm */

		// Confirm renames
		mw.util.$content.find( 'form' ).first().on( 'submit', function () {
			return confirm( mw.msg( 'centralauth-rename-confirm' ) );
		} );

	} );
}( mediaWiki, jQuery ) );
