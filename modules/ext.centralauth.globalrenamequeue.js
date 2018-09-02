( function ( mw, $ ) {
	'use strict';
	$( function () {
		// Confirm renames
		$( '#mw-renamequeue-approve' ).click( function () {
			// TODO: Convert this to OOUI.
			// eslint-disable-next-line no-alert
			return confirm( mw.msg( 'centralauth-rename-confirm' ) );
		} );
		$( '#mw-renamequeue-deny' ).click( function () {
			// TODO: Convert this to OOUI.
			// eslint-disable-next-line no-alert
			return confirm( mw.msg( 'centralauth-rename-deny' ) );
		} );
	} );
}( mediaWiki, jQuery ) );
