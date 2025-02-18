( function () {
	'use strict';
	/* eslint-disable no-alert, no-jquery/no-global-selector */
	$( () => {
		$( '#mw-vanishrequest-submit' ).on( 'click',
			// TODO: Convert this to OOUI.
			() => confirm( mw.msg( 'centralauth-vanish-confirm' ) )
		);
	} );
	/* eslint-enable no-alert, no-jquery/no-global-selector */
}() );
