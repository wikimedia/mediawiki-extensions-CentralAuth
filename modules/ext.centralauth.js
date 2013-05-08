( function ( mw, $, undefined ) {
	var $methodHint;

	function showMethodHint( methodName, e ) {
		var hintHtml;

		if ( !$methodHint ) {
			$methodHint = $( '<div>' )
				.addClass( 'merge-method-help-div' )
				.hide()
				.click( function () {
					$(this).fadeOut();
				} );
			mw.util.$content.append( $methodHint );
		}

		// Give grep a chance to find the usages:
		// centralauth-merge-method-primary, centralauth-merge-method-empty,
		// centralauth-merge-method-mail, centralauth-merge-method-password,
		// centralauth-merge-method-admin, centralauth-merge-method-new,
		// centralauth-merge-method-login,
		// centralauth-merge-method-primary-desc, centralauth-merge-method-empty-desc,
		// centralauth-merge-method-mail-desc, centralauth-merge-method-password-desc,
		// centralauth-merge-method-admin-desc, centralauth-merge-method-new-desc,
		// centralauth-merge-method-login-desc
		hintHtml = mw.html.element(
			'p', {
				'class': 'merge-method-help-name'
			},
			mw.msg( 'centralauth-merge-method-' + methodName )
		) +
		mw.message( 'centralauth-merge-method-' + methodName + '-desc' ).escaped();

		$methodHint
			.html( hintHtml )
			.css( {
				left: e.pageX + 'px',
				top: e.pageY + 'px'
			} )
			.fadeIn();
	}

	$( document ).ready( function () {
		// Hidden field to indicate JS presence at Userlogin
		$( '#userloginForm form' ).append( $( '<input name="JSEnabled" value="1" type="hidden">' ) );
		// Automatic form submission on CentralLogin/start
		$( '#mw-centralloginform-spinnertext' ).after( $.createSpinner({ size: 'small', type: 'inline' }) );
		setTimeout( function () {
			$( '#mw-centralloginform' ).submit(); },
			2500
		);
		// Back link for CentralLogin/start
		$( '#centralauth-backlink-section' ).append(
			$( '<a href="javascript:void()">' + mw.msg( 'centralauth-completelogin-back' ) + '</a>' )
				.click( function() {
					parent.history.back();
					return false;
				})
		);
		// OnClick event listener for the "(?)" tooltips on Special:CentralAuth
		$( '.mw-centralauth-wikislist' ).on( 'click', '.merge-method-help', function ( event ) {
			showMethodHint( $(this).data( 'centralauth-mergemethod' ), event );
		} );
		// Confirm account deletions
		$( '#mw-centralauth-delete input[type="submit"]' ).on( 'click', function () {
			return confirm( mw.msg( 'centralauth-admin-delete-confirm' ) );
		} );
	} );
}( mediaWiki, jQuery ) );
