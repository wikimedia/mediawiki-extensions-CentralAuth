QUnit.module( 'ext.centralauth.ForeignRest', QUnit.newMwEnvironment( {
	beforeEach: function () {
		this.server = this.sandbox.useFakeServer();
		this.server.respondImmediately = true;
	}
} ) );

QUnit.test( 'Anonymous users do not get centralauthtoken', function ( assert ) {
	mw.config.set( 'wgUserName', null );

	this.server.respond( ( request ) => {
		request.respond( 200, { 'Content-Type': 'application/json' }, '[]' );
	} );

	const actionApi = new mw.ForeignApi( '//localhost:4242/w/api.php' );
	const spy = this.sandbox.spy( actionApi, 'getCentralAuthToken' );

	const api = new mw.ForeignRest( '//localhost:4242/w/rest.php', actionApi );
	return api.get( '/hello' ).then( () => {
		assert.false( spy.called, 'Not called' );
	} );
} );

QUnit.test( 'Logged in users get centralauthtoken if not logged in remotely', function ( assert ) {
	mw.config.set( 'wgUserName', 'User' );

	this.server.respond( ( request ) => {
		assert.strictEqual( request.requestHeaders.Authorization, 'CentralAuthToken CENTRALAUTHTOKEN', 'Should pass Authorization header' );
		assert.strictEqual( request.withCredentials, false, 'Should not pass browser credentials' );
		request.respond( 200, { 'Content-Type': 'application/json' }, '[]' );
	} );

	const loginSpy = this.sandbox.stub( mw.ForeignApi.prototype, 'checkForeignLogin' ).returns(
		$.Deferred().reject()
	);
	const tokenSpy = this.sandbox.stub( mw.ForeignApi.prototype, 'getCentralAuthToken' ).returns(
		$.Deferred().resolve( 'CENTRALAUTHTOKEN' )
	);
	const actionApi = new mw.ForeignApi( '//localhost:4242/w/api.php' );

	const api = new mw.ForeignRest( '//localhost:4242/w/rest.php', actionApi );
	return api.get( '/hello' ).then( () => {
		assert.true( loginSpy.called, 'Login spy called' );
		assert.true( tokenSpy.called, 'Token spy called' );
	} );
} );

QUnit.test( 'Logged in users do not get centralauthtoken if logged in remotely', function ( assert ) {
	mw.config.set( 'wgUserName', 'User' );

	this.server.respond( ( request ) => {
		assert.strictEqual( request.withCredentials, true, 'Should pass browser credentials' );
		request.respond( 200, { 'Content-Type': 'application/json' }, '[]' );
	} );

	const loginSpy = this.sandbox.stub( mw.ForeignApi.prototype, 'checkForeignLogin' ).returns(
		$.Deferred().resolve()
	);
	const actionApi = new mw.ForeignApi( '//localhost:4242/w/api.php' );
	const tokenSpy = this.sandbox.spy( actionApi, 'getCentralAuthToken' );

	const api = new mw.ForeignRest( '//localhost:4242/w/rest.php', actionApi );
	return api.get( {} ).then( () => {
		assert.true( loginSpy.called, 'Login called' );
		assert.false( tokenSpy.called, 'Token not called' );
	} );
} );
