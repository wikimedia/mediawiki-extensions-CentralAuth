( function () {
	'use strict';
	/* eslint-disable no-alert, no-jquery/no-global-selector */
	$( function () {
		// Confirm renames
		$( '#mw-renamequeue-approve' ).on( 'click', function () {
			// TODO: Convert this to OOUI.
			return confirm( mw.msg( 'centralauth-rename-confirm' ) );
		} );
		$( '#mw-renamequeue-approve-vanish' ).on( 'click', function () {
			// TODO: Convert this to OOUI.
			return confirm( mw.msg( 'centralauth-rename-confirm-vanish' ) );
		} );
		$( '#mw-renamequeue-deny' ).on( 'click', function () {
			// TODO: Convert this to OOUI.
			return confirm( mw.msg( 'centralauth-rename-deny' ) );
		} );
	} );
	/* eslint-enable no-alert, no-jquery/no-global-selector */
}() );
