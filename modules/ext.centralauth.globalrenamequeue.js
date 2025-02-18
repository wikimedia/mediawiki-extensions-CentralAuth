( function () {
	'use strict';
	/* eslint-disable no-alert, no-jquery/no-global-selector */
	$( () => {
		// Confirm renames
		$( '#mw-renamequeue-approve' ).on( 'click',
			// TODO: Convert this to OOUI.
			() => confirm( mw.msg( 'centralauth-rename-confirm' ) )
		);
		$( '#mw-renamequeue-approve-vanish' ).on( 'click',
			// TODO: Convert this to OOUI.
			() => confirm( mw.msg( 'centralauth-rename-confirm-vanish' ) )
		);
		$( '#mw-renamequeue-deny' ).on( 'click',
			// TODO: Convert this to OOUI.
			() => confirm( mw.msg( 'centralauth-rename-deny' ) )
		);
	} );
	/* eslint-enable no-alert, no-jquery/no-global-selector */
}() );
