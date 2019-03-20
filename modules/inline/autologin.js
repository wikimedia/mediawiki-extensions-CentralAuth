( function () {
	mw.loader.using( 'mediawiki.Uri', function () {
		var current, login;

		// Set returnto and returntoquery so the logout link in the returned
		// html is correct.
		current = new mw.Uri();
		delete current.query.title;
		delete current.query.returnto;
		delete current.query.returntoquery;

		login = new mw.Uri(
			mw.config.get( 'wgArticlePath' ).replace( '$1', 'Special:CentralAutoLogin/toolslist' )
		);
		login.query.returnto = mw.config.get( 'wgPageName' );
		login.query.returntoquery = current.getQueryString();

		$.getJSON( login.toString() )
			.then( function ( data ) {
				if ( data.toolslist ) {
					// eslint-disable-next-line no-jquery/no-global-selector
					$( '#p-personal ul' ).html( data.toolslist );
					// eslint-disable-next-line no-jquery/no-global-selector
					$( '#p-personal' ).addClass( 'centralAuthPPersonalAnimation' );
					mw.hook( 'centralauth-p-personal-reset' ).fire();
				} else if ( data.notify ) {
					mw.notify(
						mw.message(
							'centralauth-centralautologin-logged-in',
							data.notify.username,
							data.notify.gender
						),
						{
							title: mw.message( 'centralautologin' ),
							autoHide: false,
							tag: 'CentralAutoLogin'
						}
					);
				}
			}, function () {
				// This happens if the user is logged in securely,
				// while also auto-loggedin from an http page.
				mw.notify(
					mw.message( 'centralauth-centralautologin-logged-in-nouser' ),
					{
						title: mw.message( 'centralautologin' ),
						autoHide: false,
						tag: 'CentralAutoLogin'
					}
				);
			} );
	} );
}() );
