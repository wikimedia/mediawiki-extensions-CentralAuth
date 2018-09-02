/* eslint-disable no-alert */
( function ( mw, $ ) {
	$( function () {
		/* global confirm */

		// Confirm renames
		mw.util.$content.find( 'form' ).first().on( 'submit', function () {
			var msgKey;
			if ( mw.config.get( 'wgCanonicalSpecialPageName' ) === 'GlobalUserMerge' ) {
				msgKey = 'centralauth-usermerge-confirm';
			} else {
				msgKey = 'centralauth-rename-confirm';
			}
			return confirm( mw.msg( msgKey ) );
		} );

	} );
}( mediaWiki, jQuery ) );
