( function () {
	var $methodHint;

	function showMethodHint( methodName, e ) {
		var hintHtml;

		if ( !$methodHint ) {
			$methodHint = $( '<div>' )
				.addClass( 'merge-method-help-div' )
				.hide()
				.on( 'click', function () {
					// eslint-disable-next-line no-jquery/no-fade
					$( this ).fadeOut();
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
				class: 'merge-method-help-name'
			},
			mw.msg( 'centralauth-merge-method-' + methodName )
		) +
		mw.message( 'centralauth-merge-method-' + methodName + '-desc' ).escaped();

		// eslint-disable-next-line no-jquery/no-fade
		$methodHint
			.html( hintHtml )
			.css( {
				left: e.pageX + 'px',
				top: e.pageY + 'px'
			} )
			.fadeIn();
	}

	$( function () {
		// Back link for CentralLogin/start
		// eslint-disable-next-line no-jquery/no-global-selector
		$( '#centralauth-backlink-section' ).append(
			$( '<a>' )
				.attr( 'href', '#' )
				.text( mw.msg( 'centralauth-completelogin-back' ) )
				.on( 'click', function ( e ) {
					e.preventDefault();
					parent.history.back();
				} )
		);

		// OnClick event listener for the "(?)" tooltips on Special:CentralAuth
		// eslint-disable-next-line no-jquery/no-global-selector
		$( '.mw-centralauth-wikislist' ).on( 'click', '.merge-method-help', function ( event ) {
			showMethodHint( $( this ).data( 'centralauth-mergemethod' ), event );
		} );

		// Confirm account deletions
		// eslint-disable-next-line no-jquery/no-global-selector
		$( '#mw-centralauth-delete input[type="submit"]' ).on( 'click', function () {
			// TODO: Convert this to OOUI.
			// eslint-disable-next-line no-alert
			return confirm( mw.msg( 'centralauth-admin-delete-confirm' ) );
		} );
	} );
}() );
