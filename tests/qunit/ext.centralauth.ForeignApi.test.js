( function () {
	QUnit.module( 'ext.centralauth.ForeignApi', QUnit.newMwEnvironment( {
		setup: function () {
			this.server = this.sandbox.useFakeServer();
			this.server.respondImmediately = true;
		},
		config: {
			wgUserName: true
		}
	} ) );

	QUnit.test( 'Anonymous users do not get centralauthtoken', function ( assert ) {
		var api, spy;

		mw.config.set( 'wgUserName', null );

		this.server.respond( function ( request ) {
			request.respond( 200, { 'Content-Type': 'application/json' }, '[]' );
		} );

		api = new mw.ForeignApi( '//localhost:4242/w/api.php' );
		spy = this.sandbox.spy( api, 'getCentralAuthToken' );

		return api.get( {} ).then( function () {
			assert.false( spy.called, 'Anonymous users do not ask for centralauthtoken' );
		} );
	} );

	QUnit.test( 'Logged in users get centralauthtoken if not logged in remotely', function ( assert ) {
		var api, spy;

		mw.config.set( 'wgUserName', 'User' );

		this.sandbox.stub( mw.ForeignApi.prototype, 'checkForeignLogin' ).returns(
			$.Deferred().reject()
		);

		this.server.respond( function ( request ) {
			request.respond( 200, { 'Content-Type': 'application/json' }, '[]' );
		} );

		api = new mw.ForeignApi( '//localhost:4242/w/api.php' );
		spy = this.sandbox.stub( api, 'getCentralAuthToken' ).returns(
			$.Deferred().resolve( 'CENTRALAUTHTOKEN' )
		);

		return api.get( {} ).then( function () {
			assert.true( spy.called, 'Called' );
		} );
	} );

	QUnit.test( 'Logged in users do not get centralauthtoken if logged in remotely', function ( assert ) {
		var api, spy;

		mw.config.set( 'wgUserName', 'User' );

		this.sandbox.stub( mw.ForeignApi.prototype, 'checkForeignLogin' ).returns(
			$.Deferred().resolve()
		);

		this.server.respond( function ( request ) {
			request.respond( 200, { 'Content-Type': 'application/json' }, '[]' );
		} );

		api = new mw.ForeignApi( '//localhost:4242/w/api.php' );
		spy = this.sandbox.stub( api, 'getCentralAuthToken' ).returns(
			$.Deferred().resolve( 'CENTRALAUTHTOKEN' )
		);

		return api.get( {} ).then( function () {
			assert.false( spy.called, 'Called' );
		} );
	} );

}() );
