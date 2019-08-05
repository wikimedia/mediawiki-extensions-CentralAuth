( function () {
	/* eslint-disable no-jquery/no-global-selector */
	'use strict';
	if ( mw.config.get('wgCanonicalSpecialPageName') === 'GlobalRenameRequest' ) {
		$( function () {
			$( '#mw-renamerequest-vanish>input' ).on( 'change', function () {
				if ( this.checked ) {
					$( '.mw-globalrenamerequest-newname' ).hide();
				} else {
					$( '.mw-globalrenamerequest-newname' ).show();
				}
			} );
		} );
	}
	/* eslint-enable no-alert, no-jquery/no-global-selector */
}() );
