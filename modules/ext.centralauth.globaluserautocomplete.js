/*!
 * Add autocomplete suggestions for names of global accounts.
 * Lovingly stolen from core mediawiki.userSuggest
 */
( function () {
	var api, config;

	config = {
		fetch: function ( userInput ) {
			var $textbox = this,
				node = this[ 0 ];

			api = api || new mw.Api();

			$.data( node, 'request', api.get( {
				action: 'query',
				list: 'globalallusers',
				// Prefix of list=globalallusers is case sensitive. Normalise first
				// character to uppercase so that "fo" may yield "Foo".
				aguprefix: userInput[ 0 ].toUpperCase() + userInput.slice( 1 )
			} ).then( function ( data ) {
				var users = data.query.globalallusers.map( function ( userObj ) {
					return userObj.name;
				} );
				// Set the results as the autocomplete options
				$textbox.suggestions( 'suggestions', users );
			} ) );
		},
		cancel: function () {
			var node = this[ 0 ],
				request = $.data( node, 'request' );

			if ( request ) {
				request.abort();
				$.removeData( node, 'request' );
			}
		}
	};

	$( function () {
		// eslint-disable-next-line no-jquery/no-global-selector
		$( '.mw-autocomplete-global-user' ).suggestions( config );
	} );
}() );
