QUnit.module( 'ext.centralauth.ForeignApi', QUnit.newMwEnvironment( {
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

	const api = new mw.ForeignApi( '//localhost:4242/w/api.php' );
	const spy = this.sandbox.spy( api, 'getCentralAuthToken' );

	return api.get( {} ).then( () => {
		assert.false( spy.called, 'Not called' );
	} );
} );

QUnit.test( 'Logged in users get centralauthtoken if not logged in remotely', function ( assert ) {
	mw.config.set( 'wgUserName', 'User' );

	this.sandbox.stub( mw.ForeignApi.prototype, 'checkForeignLogin' ).returns(
		$.Deferred().reject()
	);

	this.server.respond( ( request ) => {
		request.respond( 200, { 'Content-Type': 'application/json' }, '[]' );
	} );

	const api = new mw.ForeignApi( '//localhost:4242/w/api.php' );
	const spy = this.sandbox.stub( api, 'getCentralAuthToken' ).returns(
		$.Deferred().resolve( 'CENTRALAUTHTOKEN' )
	);

	return api.get( {} ).then( () => {
		assert.true( spy.called, 'Called' );
	} );
} );

QUnit.test( 'Logged in users do not get centralauthtoken if logged in remotely', function ( assert ) {
	mw.config.set( 'wgUserName', 'User' );

	this.sandbox.stub( mw.ForeignApi.prototype, 'checkForeignLogin' ).returns(
		$.Deferred().resolve()
	);

	this.server.respond( ( request ) => {
		request.respond( 200, { 'Content-Type': 'application/json' }, '[]' );
	} );

	const api = new mw.ForeignApi( '//localhost:4242/w/api.php' );
	const spy = this.sandbox.stub( api, 'getCentralAuthToken' ).returns(
		$.Deferred().resolve( 'CENTRALAUTHTOKEN' )
	);

	return api.get( {} ).then( () => {
		assert.false( spy.called, 'Called' );
	} );
} );
