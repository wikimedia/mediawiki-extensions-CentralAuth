( function () {
	'use strict';
	/* eslint-disable no-alert, no-jquery/no-global-selector */
	$( function () {
		$( '#mw-vanishrequest-submit' ).on( 'click', function () {
			// TODO: Convert this to OOUI.
			return confirm( mw.msg( 'centralauth-vanish-confirm' ) );
		} );
	} );
	/* eslint-enable no-alert, no-jquery/no-global-selector */
}() );
