$( function () {
	// Confirm renames
	mw.util.$content.find( 'form' ).first().on( 'submit', function () {
		var msgKey;
		if ( mw.config.get( 'wgCanonicalSpecialPageName' ) === 'GlobalUserMerge' ) {
			msgKey = 'centralauth-usermerge-confirm';
		} else {
			msgKey = 'centralauth-rename-confirm';
		}
		// TODO: Convert this to OOUI.
		// eslint-disable-next-line no-alert
		return confirm( mw.msg( msgKey ) );
	} );
} );
