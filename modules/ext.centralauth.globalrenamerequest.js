( function () {
	'use strict';
	/* eslint-disable no-jquery/no-global-selector */
	$( function () {
		$( '#mw-renamerequest-vanish>input' ).change( function () {
			if ( this.checked ) {
				$( '.mw-globalrenamerequest-newname' ).hide();
			} else {
				$( '.mw-globalrenamerequest-newname' ).show();
			}
		});
	} );
	/* eslint-enable no-alert, no-jquery/no-global-selector */
}() );
