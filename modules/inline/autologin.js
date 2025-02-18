( function () {
	mw.loader.using( 'web2017-polyfills', () => {
		// Set returnto and returntoquery so the logout link in the returned
		// html is correct.
		const current = new URL( location.href );
		current.searchParams.delete( 'title' );
		current.searchParams.delete( 'returnto' );
		current.searchParams.delete( 'returntoquery' );

		const login = new URL(
			mw.config.get( 'wgArticlePath' ).replace( '$1', 'Special:CentralAutoLogin/toolslist' ),
			location.href
		);
		login.searchParams.set( 'returnto', mw.config.get( 'wgPageName' ) );
		login.searchParams.set( 'returntoquery', current.search.slice( 1 ) );

		$.getJSON( login.toString() )
			.then( ( data ) => {
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
							title: mw.msg( 'centralautologin' ),
							autoHide: false,
							tag: 'CentralAutoLogin'
						}
					);
				}
			} );
	} );
}() );
