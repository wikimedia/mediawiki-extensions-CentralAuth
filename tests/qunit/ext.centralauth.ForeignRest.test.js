( function () {
	QUnit.module( 'ext.centralauth.ForeignRest', QUnit.newMwEnvironment( {
		setup: function () {
			this.server = this.sandbox.useFakeServer();
			this.server.respondImmediately = true;
		},
		config: {
			wgUserName: true
		}
	} ) );

	QUnit.test( 'Anonymous users do not get centralauthtoken', function ( assert ) {
		var api, actionApi, spy;

		mw.config.set( 'wgUserName', null );

		this.server.respond( function ( request ) {
			request.respond( 200, { 'Content-Type': 'application/json' }, '[]' );
		} );

		actionApi = new mw.ForeignApi( '//localhost:4242/w/api.php' );
		spy = this.sandbox.spy( actionApi, 'getCentralAuthToken' );

		api = new mw.ForeignRest( '//localhost:4242/w/rest.php', actionApi );
		return api.get( '/hello' ).then( function () {
			assert.notOk( spy.called, 'Anonymous users do not ask for centralauthtoken' );
		} );
	} );

	QUnit.test( 'Logged in users get centralauthtoken if not logged in remotely', function ( assert ) {
		var api, actionApi, loginSpy, tokenSpy;

		mw.config.set( 'wgUserName', 'User' );

		this.server.respond( function ( request ) {
			assert.strictEqual( request.requestHeaders.Authorization, 'CentralAuthToken CENTRALAUTHTOKEN', 'Should pass Authorization header' );
			request.respond( 200, { 'Content-Type': 'application/json' }, '[]' );
		} );

		loginSpy = this.sandbox.stub( mw.ForeignApi.prototype, 'checkForeignLogin' ).returns(
			$.Deferred().reject()
		);
		tokenSpy = this.sandbox.stub( mw.ForeignApi.prototype, 'getCentralAuthToken' ).returns(
			$.Deferred().resolve( 'CENTRALAUTHTOKEN' )
		);
		actionApi = new mw.ForeignApi( '//localhost:4242/w/api.php' );

		api = new mw.ForeignRest( '//localhost:4242/w/rest.php', actionApi );
		return api.get( '/hello' ).then( function () {
			assert.ok( loginSpy.called, 'Login spy called' );
			assert.ok( tokenSpy.called, 'Token spy called' );
		} );
	} );

	QUnit.test( 'Logged in users do not get centralauthtoken if logged in remotely', function ( assert ) {
		var api, actionApi, loginSpy, tokenSpy;

		mw.config.set( 'wgUserName', 'User' );

		this.server.respond( function ( request ) {
			request.respond( 200, { 'Content-Type': 'application/json' }, '[]' );
		} );

		loginSpy = this.sandbox.stub( mw.ForeignApi.prototype, 'checkForeignLogin' ).returns(
			$.Deferred().resolve()
		);
		actionApi = new mw.ForeignApi( '//localhost:4242/w/api.php' );
		tokenSpy = this.sandbox.spy( actionApi, 'getCentralAuthToken' );

		api = new mw.ForeignRest( '//localhost:4242/w/rest.php', actionApi );
		return api.get( {} ).then( function () {
			assert.ok( loginSpy.called, 'Login called' );
			assert.notOk( tokenSpy.called, 'Token not called' );
		} );
	} );

}() );
