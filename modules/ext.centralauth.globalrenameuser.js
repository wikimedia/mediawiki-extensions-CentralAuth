$( function () {
	// Confirm renames
	mw.util.$content.find( 'form' ).first().on( 'submit', function () {
		// TODO: Convert this to OOUI.
		// eslint-disable-next-line no-alert
		return confirm( mw.msg(
			mw.config.get( 'wgCanonicalSpecialPageName' ) === 'GlobalUserMerge' ?
				'centralauth-usermerge-confirm' :
				'centralauth-rename-confirm'
		) );
	} );
} );
