QUnit.module( 'ext.centralauth.ForeignRest', QUnit.newMwEnvironment( {
	beforeEach: function () {
		this.server = this.sandbox.useFakeServer();
		this.server.respondImmediately = true;
	}
} ) );

QUnit.test( 'Anonymous users do not get centralauthtoken', function ( assert ) {
	mw.config.set( 'wgUserName', null );

	this.server.respond( function ( request ) {
		request.respond( 200, { 'Content-Type': 'application/json' }, '[]' );
	} );

	var actionApi = new mw.ForeignApi( '//localhost:4242/w/api.php' );
	var spy = this.sandbox.spy( actionApi, 'getCentralAuthToken' );

	var api = new mw.ForeignRest( '//localhost:4242/w/rest.php', actionApi );
	return api.get( '/hello' ).then( function () {
		assert.false( spy.called, 'Not called' );
	} );
} );

QUnit.test( 'Logged in users get centralauthtoken if not logged in remotely', function ( assert ) {
	mw.config.set( 'wgUserName', 'User' );

	this.server.respond( function ( request ) {
		assert.strictEqual( request.requestHeaders.Authorization, 'CentralAuthToken CENTRALAUTHTOKEN', 'Should pass Authorization header' );
		request.respond( 200, { 'Content-Type': 'application/json' }, '[]' );
	} );

	var loginSpy = this.sandbox.stub( mw.ForeignApi.prototype, 'checkForeignLogin' ).returns(
		$.Deferred().reject()
	);
	var tokenSpy = this.sandbox.stub( mw.ForeignApi.prototype, 'getCentralAuthToken' ).returns(
		$.Deferred().resolve( 'CENTRALAUTHTOKEN' )
	);
	var actionApi = new mw.ForeignApi( '//localhost:4242/w/api.php' );

	var api = new mw.ForeignRest( '//localhost:4242/w/rest.php', actionApi );
	return api.get( '/hello' ).then( function () {
		assert.true( loginSpy.called, 'Login spy called' );
		assert.true( tokenSpy.called, 'Token spy called' );
	} );
} );

QUnit.test( 'Logged in users do not get centralauthtoken if logged in remotely', function ( assert ) {
	mw.config.set( 'wgUserName', 'User' );

	this.server.respond( function ( request ) {
		request.respond( 200, { 'Content-Type': 'application/json' }, '[]' );
	} );

	var loginSpy = this.sandbox.stub( mw.ForeignApi.prototype, 'checkForeignLogin' ).returns(
		$.Deferred().resolve()
	);
	var actionApi = new mw.ForeignApi( '//localhost:4242/w/api.php' );
	var tokenSpy = this.sandbox.spy( actionApi, 'getCentralAuthToken' );

	var api = new mw.ForeignRest( '//localhost:4242/w/rest.php', actionApi );
	return api.get( {} ).then( function () {
		assert.true( loginSpy.called, 'Login called' );
		assert.false( tokenSpy.called, 'Token not called' );
	} );
} );
