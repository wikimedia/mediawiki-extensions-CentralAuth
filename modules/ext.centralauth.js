( function () {
	var methodHint;

	function showMethodHint( methodName, e ) {
		var hintHtml;

		e.preventDefault();

		if ( !methodHint ) {
			methodHint = new OO.ui.PopupWidget( {
				$content: $( '<p></p>' ),
				padded: true,
				anchor: false,
				label: mw.msg( 'centralauth-merge-method-' + methodName )
			} );
			mw.util.$content.append( methodHint.$element );
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
				class: 'merge-method-help-desc'
			}, mw.message( 'centralauth-merge-method-' + methodName + '-desc' ).escaped() );

		methodHint.$content = hintHtml;

		methodHint.toggle( true );
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
