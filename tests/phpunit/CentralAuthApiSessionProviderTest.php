<?php

use MediaWiki\HookContainer\HookRunner;
use MediaWiki\Session\SessionInfo;
use MediaWiki\Session\SessionManager;
use Psr\Log\NullLogger;

/**
 * @covers CentralAuthApiSessionProvider
 * @group medium
 * @group Database
 */
class CentralAuthApiSessionProviderTest extends CentralAuthTokenSessionProviderTest {

	/** @var \MediaWiki\HookContainer\HookContainer */
	private $hookContainer;

	public function setUp() : void {
		parent::setUp();

		if ( !defined( 'MW_API' ) ) {
			define( 'MW_API', 'TEST' );
		}

		$this->hookContainer = $this->createHookContainer();
	}

	protected function makeRequest( $token ) {
		return new FauxRequest( [ 'centralauthtoken' => $token ] );
	}

	protected function assertSessionInfoError(
		WebRequest $request,
		?SessionInfo $result,
		string $error = null,
		string $code = null
	) {
		parent::assertSessionInfoError( $request, $result, $error, $code );
		$this->assertErrorFromApiBeforeMain( $request, $error );
	}

	private function assertErrorFromApiBeforeMain( WebRequest $request, $error ) {
		$context = new RequestContext();
		$context->setRequest( $request );
		$processor = new ApiMain( RequestContext::getMain(), true );

		try {
			( new HookRunner( $this->hookContainer ) )->onApiBeforeMain( $processor );
			$this->fail( 'Expected ApiUsageException' );
		} catch ( ApiUsageException $ex ) {
			$this->assertSame( $error, $ex->getMessageObject()->getKey() );
		}
	}

	protected function newSessionProvider() {
		$config = new HashConfig( [
			'SecretKey' => 'hunter2',
		] );

		$logger = new NullLogger();

		$manager = new SessionManager( [
			'config' => $config,
			'logger' => $logger,
			'store' => $this->sessionStore,
			'hookContainer' => $this->hookContainer
		] );

		$provider = new CentralAuthApiSessionProvider();
		$provider->setLogger( $logger );
		$provider->setConfig( $config );
		$provider->setManager( $manager );
		$provider->setHookContainer( $this->hookContainer );
		return $provider;
	}

	private function runApiCheckCanExecute( WebRequest $request ) {
		$user = $this->makeUser( 0, 'anon' );

		$context = new RequestContext();
		$context->setRequest( $request );
		$context->setUser( $user );

		$main = new ApiMain();
		$main->setContext( $context );

		$module = $this->getMockBuilder( 'ApiBase' )
			->setConstructorArgs( [ $main, 'test', '' ] )
			->onlyMethods( [ 'execute' ] )
			->getMock();

		$message = 'hookaborted';
		$ok = $this->hookContainer->run( 'ApiCheckCanExecute', [ $module, $user, &$message ] );

		$this->assertTrue( $ok );
	}

	/**
	 * Overridden to trigger deferred consumption of token
	 */
	public function testCannotReuseToken() {
		$user = $this->makeCentralAuthUser( 'Frank' );
		$token = $this->makeValidToken( [ 'userName' => $user->getName() ] );
		$provider = $this->newSessionProvider();

		$request = new FauxRequest( [ 'centralauthtoken' => $token ] );
		$result = $provider->provideSessionInfo( $request );
		$this->assertSessionInfoOk( $result );

		// consume token!
		$this->runApiCheckCanExecute( $request );

		// the token should now be unknown
		$result = $provider->provideSessionInfo( $request );
		$this->assertSessionInfoError( $request, $result, 'apierror-centralauth-badtoken', 'badtoken' );
	}

	/**
	 * @covers CentralAuthHooks::onAPIGetAllowedParams
	 */
	public function testApiParameterDeclared() {
		// hook is registered dynamically when creating the SessionProvider
		$this->newSessionProvider();

		$this->setMwGlobals( 'wgCentralAuthCookies', true );

		$main = new ApiMain();

		$params = $main->getFinalParams();
		$this->assertArrayHasKey( 'centralauthtoken', $params );
	}

}
