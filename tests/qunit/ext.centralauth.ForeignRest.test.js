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

	const spy = this.sandbox.spy( mw.Api.prototype, 'getCentralAuthToken' );

	const api = new mw.ForeignRest( '//localhost:4242/w/rest.php', null );
	return api.get( '/hello' ).then( () => {
		assert.false( spy.called, 'Not called' );
	} );
} );

QUnit.test( 'Logged in users always get centralauthtoken', function ( assert ) {
	mw.config.set( 'wgUserName', 'User' );

	this.server.respond( ( request ) => {
		assert.strictEqual( request.requestHeaders.Authorization, 'CentralAuthToken CENTRALAUTHTOKEN', 'Should pass Authorization header' );
		assert.strictEqual( request.withCredentials, false, 'Should not pass browser credentials' );
		request.respond( 200, { 'Content-Type': 'application/json' }, '[]' );
	} );

	const loginSpy = this.sandbox.stub( mw.ForeignApi.prototype, 'checkForeignLogin' ).returns(
		$.Deferred().reject()
	);
	const tokenSpy = this.sandbox.stub( mw.Api.prototype, 'getCentralAuthToken' ).returns(
		$.Deferred().resolve( 'CENTRALAUTHTOKEN' )
	);

	const api = new mw.ForeignRest( '//localhost:4242/w/rest.php', null );
	return api.get( '/hello' ).then( () => {
		// We used to call this method unnecessarily, check for regressions
		assert.false( loginSpy.called, 'Login spy called' );
		assert.true( tokenSpy.called, 'Token spy called' );
	} );
} );
