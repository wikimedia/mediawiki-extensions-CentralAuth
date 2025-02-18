$( () => {
	// Confirm renames
	mw.util.$content.find( 'form' ).first().on( 'submit',
		// TODO: Convert this to OOUI.
		// eslint-disable-next-line no-alert
		() => confirm( mw.msg( 'centralauth-rename-confirm' ) )
	);
} );
