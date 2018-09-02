/* eslint-disable no-alert */
( function ( mw, $ ) {
	'use strict';
	$( function () {
		/* global confirm */

		// Confirm renames
		$( '#mw-renamequeue-approve' ).click( function () {
			return confirm( mw.msg( 'centralauth-rename-confirm' ) );
		} );
		$( '#mw-renamequeue-deny' ).click( function () {
			return confirm( mw.msg( 'centralauth-rename-deny' ) );
		} );
	} );
}( mediaWiki, jQuery ) );
