( function ( mw, $, OO ) {
	'use strict';
	$( function () {
		/*global confirm */

		// Confirm renames
		OO.ui.infuse( 'mw-renamequeue-approve' ).on( 'click', function () {
			return confirm( mw.msg( 'centralauth-rename-confirm' ) );
		} );
		OO.ui.infuse( 'mw-renamequeue-deny' ).on( 'click', function () {
			return confirm( mw.msg( 'centralauth-rename-deny' ) );
		} );
	} );
}( mediaWiki, jQuery, OO ) );
