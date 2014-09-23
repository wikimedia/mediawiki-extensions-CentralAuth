( function ( mw, $, oo ) {
	$( document ).ready( function () {
		/* Explain why we are requiring an email address */
		$( 'label[for=mw-renamerequest-email]' ).after(
			new oo.ui.PopupButtonWidget( {
				icon: 'help',
				label: mw.msg( 'globalrenamerequest-email-why-label' ),
				framed: false,
				popup: {
					$content: $( '<div>' ).html(
						mw.msg( 'globalrenamerequest-email-why-explain' )
					),
					padded: true,
					align: 'left'
				}
			} ).$element.addClass( 'mw-ui-flush-right' )
		);
	} );
}( mediaWiki, jQuery, OO ) );

