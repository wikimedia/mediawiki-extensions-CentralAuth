( function ( mw, $, undefined ) {
	var $methodHint;

	function showMethodHint ( methodName, e ) {
		var cursorPosition = {
			x: e.clientX +
				( document.documentElement.scrollLeft || document.body.scrollLeft ) -
				document.documentElement.clientLeft,
			y: e.clientY +
				( document.documentElement.scrollTop || document.body.scrollTop ) -
				document.documentElement.clientTop
		};

		if ( !$methodHint ) {
			$methodHint = $( '<div>' )
				.addClass( 'merge-method-help-div' )
				.hide()
				.click( function () {
					$(this).fadeOut();
				} );
			mw.util.$content.append( $methodHint );
		}

		var hintHtml = mw.html.element(
			'p', {
				'class': 'merge-method-help-name'
			},
			mw.msg( 'centralauth-merge-method-' + methodName )
		) +
		mw.message( 'centralauth-merge-method-' + methodName + '-desc' ).escaped();

		$methodHint
			.html( hintHtml )
			.css( {
				left: cursorPosition.x + 'px',
				top: cursorPosition.y + 'px'
			} )
			.fadeIn();
	}

	$( document ).ready( function () {
		// OnClick event listener for the "(?)" tooltips on Special:CentralAuth
		$( '.mw-centralauth-wikislist .merge-method-help' ).on( 'click', function ( event ) {
			showMethodHint( $(this).data( 'centralauth-mergemethod' ), event );
		} );
		// Confirm account deletions
		$( '#mw-centralauth-delete input[type="submit"]' ).on( 'click', function () {
			return confirm( mw.msg( 'centralauth-admin-delete-confirm' ) );
		} );
	} );
}( mediaWiki, jQuery ) );
