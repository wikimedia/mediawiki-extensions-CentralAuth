<?php

use MediaWiki\Api\ApiHookRunner;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Logger\NullSpi;
use MediaWiki\Session\Session;
use MediaWiki\Session\SessionInfo;
use MediaWiki\Session\SessionManager;
use MediaWiki\User\UserFactory;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;

/**
 * @covers CentralAuthTokenSessionProvider
 * @group medium
 * @group Database
 */
class CentralAuthTokenSessionProviderTest extends MediaWikiIntegrationTestCase {

	/** @var BagOStuff */
	private $sessionStore;

	/** @var int */
	private $idCounter = 0;

	/**
	 * @var User[]|null
	 */
	private $userObjects;

	public function setUp(): void {
		parent::setUp();

		$this->setUserLang( 'qqx' );
		LoggerFactory::registerProvider( new NullSpi() );

		if ( !defined( 'MW_API' ) ) {
			define( 'MW_API', 'test' );
		}

		$this->sessionStore = new HashBagOStuff();
		CentralAuthUtils::setSessionStore( $this->sessionStore );

		// CentralAuthTokenSessionProvider registers hooks dynamically.
		// Make sure the original hooks are restored before the next test.
		$this->setMwGlobals( 'wgHooks', $GLOBALS[ 'wgHooks' ] );
	}

	private function assertSessionInfoError(
		?SessionInfo $result,
		string $error = null,
		string $code = null
	) {
		$data = $result->getProviderMetadata();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'error', $data );
		$this->assertArrayHasKey( 'error-code', $data );

		if ( $code ) {
			$this->assertSame( $code, $data['error-code'] );
		}

		if ( $error ) {
			$this->assertSame( $error, $data['error'] );
		}
	}

	private function assertSessionInfoOk( ?SessionInfo $result ) {
		$data = $result->getProviderMetadata();
		if ( $data !== null ) {
			$this->assertArrayNotHasKey( 'error', $data, $data['error'] ?? '' );
			$this->assertArrayNotHasKey( 'error-code', $data, $data['error-code'] ?? '' );
		}

		$this->assertNotNull( $result->getUserInfo() );
	}

	private function assertErrorFromApiBeforeMain( WebRequest $request, $error ) {
		$context = new RequestContext();
		$context->setRequest( $request );
		$processor = new ApiMain( RequestContext::getMain(), true );

		try {
			Hooks::runner()->onApiBeforeMain( $processor );
			$this->fail( 'Expected ApiUsageException' );
		} catch ( ApiUsageException $ex ) {
			$this->assertSame( $error, $ex->getMessageObject()->getKey() );
		}
	}

	private function newSessionProvider() {
		$config = new HashConfig( [
			'SecretKey' => 'hunter2',
		] );

		$logger = new NullLogger();

		$hookContainer = $this->getServiceContainer()->getHookContainer();

		$manager = new SessionManager( [
			'config' => $config,
			'logger' => $logger,
			'store' => $this->sessionStore,
			'hookContainer' => $hookContainer
		] );

		$provider = new CentralAuthTokenSessionProvider();
		$provider->setLogger( $logger );
		$provider->setConfig( $config );
		$provider->setManager( $manager );
		$provider->setHookContainer( $hookContainer );
		return $provider;
	}

	private function makeValidToken( $data = [] ) {
		// NOTE: logic stolen from ApiCentralAuthToken

		$data += [
			'userName' => 'Frank',
			'token' => 'CATOKEN', // not the login token
			'origin' => 'test',
			'originSessionId' => 1337,
		];

		$loginToken = 'testtoken' . ++$this->idCounter;

		$key = CentralAuthUtils::memcKey( 'api-token', $loginToken );
		$this->sessionStore->set( $key, $data, 60 * 60 );

		return $loginToken;
	}

	/**
	 * @param string $name
	 *
	 * @return MockObject|User
	 */
	private function makeUser( $id, $name ) {
		/** @var MockObject|CentralAuthUser $caUser */
		$user = $this->createNoOpMock( User::class, [
			'getName', 'getId', 'isAnon'
		] );

		$user->method( 'getId' )->willReturn( $id );
		$user->method( 'getName' )->willReturn( $name );
		$user->method( 'isAnon' )->willReturn( $id === 0 );

		if ( $this->userObjects === null ) {
			// set up a fake user factory
			$this->userObjects = [];

			$userFactory = $this->createNoOpMock( UserFactory::class, [
				'newFromName'
			] );

			$userFactory->method( 'newFromName' )->willReturnCallback( function ( $name ) {
				return $this->userObjects[ $name ] ?? null;
			} );

			$this->setService( 'UserFactory', $userFactory );
		}

		$this->userObjects[ $name ] = $user;

		return $user;
	}

	/**
	 * @param string $name
	 *
	 * @return MockObject|CentralAuthUser
	 */
	private function makeCentralAuthUser( $name, $return = [], $methods = [] ) {
		$id = ++$this->idCounter;

		$methods += [
			'getId',
			'getName',
			'renameInProgress',
			'exists',
			'isAttached',
			'authenticateWithToken'
		];

		/** @var MockObject|CentralAuthUser $caUser */
		$caUser = $this->getMockBuilder( CentralAuthUser::class )
			->setConstructorArgs( [ $name, IDBAccessObject::READ_LATEST ] )
			->getMock();

		$caUser->expects( $this->never() )->method( $this->anythingBut( '__destruct', ...$methods ) );

		$return += [
			'getId' => $id,
			'getName' => $name,
			'renameInProgress' => false,
			'exists' => true,
			'isAttached' => true,
			'authenticateWithToken' => 'ok',
		];

		foreach ( $return as $method => $value ) {
			$caUser->method( $method )->willReturn( $value );
		}

		CentralAuthUser::setInstanceByName( $name, $caUser );

		$this->makeUser( $id, $name );

		return $caUser;
	}

	public function testProvideSessinInfo_noToken() {
		$provider = $this->newSessionProvider();
		$request = new FauxRequest();

		$result = $provider->provideSessionInfo( $request );
		$this->assertNull( $result );
	}

	public function testProvideSessinInfo_unknownToken() {
		$provider = $this->newSessionProvider();

		$request = new FauxRequest( [ 'centralauthtoken' => 'bogus' ] );
		$result = $provider->provideSessionInfo( $request );
		$this->assertSessionInfoError( $result, 'apierror-centralauth-badtoken', 'badtoken' );
		$this->assertErrorFromApiBeforeMain( $request, 'apierror-centralauth-badtoken' );
	}

	public function testProvideSessinInfo() {
		$user = $this->makeCentralAuthUser( 'Frank' );
		$token = $this->makeValidToken( [ 'userName' => $user->getName() ] );
		$provider = $this->newSessionProvider();

		$request = new FauxRequest( [ 'centralauthtoken' => $token ] );
		$result = $provider->provideSessionInfo( $request );
		$this->assertSessionInfoOk( $result );

		$this->assertSame( $user->getName(), $result->getUserInfo()->getName() );
		$this->assertSame( $user->getId(), $result->getUserInfo()->getId() );
	}

	public function provideBadUsers() {
		yield 'bad user name' => [ '_', [], 'apierror-centralauth-badtoken', 'badtoken' ];

		yield 'rename in progress' => [
			'Fran',
			[ 'renameInProgress' => true ],
			'apierror-centralauth-renameinprogress',
			'renameinprogress'
		];

		yield 'nonexisting user' => [
			'Macy',
			[ 'exists' => false ],
			'apierror-centralauth-badtoken',
			'badtoken'
		];

		yield 'authentication failed' => [
			'Shlob',
			[ 'authenticateWithToken' => 'failed' ],
			'apierror-centralauth-badtoken',
			'badtoken'
		];
	}

	/**
	 * @dataProvider provideBadUsers
	 */
	public function testProvideSessinInfo_badUser( $name, $return, $error, $code ) {
		// NOTE: the user gets registered in a fake service,
		//       we can't construct it in teh data provider!
		$user = $this->makeCentralAuthUser( $name, $return );
		$token = $this->makeValidToken( [ 'userName' => $user->getName() ] );
		$provider = $this->newSessionProvider();

		$request = new FauxRequest( [ 'centralauthtoken' => $token ] );
		$result = $provider->provideSessionInfo( $request );
		$this->assertSessionInfoError( $result, $error, $code );
		$this->assertErrorFromApiBeforeMain( $request, $error );
	}

	private function runApiCheckCanExecute( WebRequest $request ) {
		$user = $this->makeUser( 0, 'anon' );

		$context = new RequestContext();
		$context->setRequest( $request );
		$context->setUser( $user );

		$main = new ApiMain();
		$main->setContext( $context );

		$module = new ApiHelp( $main, 'help' );

		$runner = new ApiHookRunner( $this->getServiceContainer()->getHookContainer() );

		$message = 'hookaborted';
		$ok = $runner->onApiCheckCanExecute( $module, $user, $message );

		$this->assertTrue( $ok );
	}

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
		$this->assertSessionInfoError( $result, 'apierror-centralauth-badtoken', 'badtoken' );
		$this->assertErrorFromApiBeforeMain( $request, 'apierror-centralauth-badtoken' );
	}

	public function testInvalidateSessionForUser() {
		$caUser = $this->makeCentralAuthUser( 'Frank', [], [ 'resetAuthToken' ] );
		$caUser->expects( $this->once() )->method( 'resetAuthToken' );

		$user = $this->getServiceContainer()->getUserFactory()->newFromName( $caUser->getName() );

		$provider = $this->newSessionProvider();
		$provider->invalidateSessionsForUser( $user );
	}

	public function testPreventSessionForUser() {
		$user = $this->makeCentralAuthUser( 'Donald' );
		$token = $this->makeValidToken( [ 'userName' => $user->getName() ] );
		$provider = $this->newSessionProvider();

		$provider->preventSessionsForUser( $user->getName() );

		$request = new FauxRequest( [ 'centralauthtoken' => $token ] );
		$result = $provider->provideSessionInfo( $request );

		$this->assertSessionInfoError( $result, 'apierror-centralauth-badtoken', 'badtoken' );
		$this->assertErrorFromApiBeforeMain( $request, 'apierror-centralauth-badtoken' );
	}

	public function testUserScriptsDisabled() {
		$provider = $this->newSessionProvider();

		$session = $this->createNoOpMock(
			Session::class,
			[ 'getProvider', 'getSessionId', 'get', 'canSetUser' ]
		);
		$session->method( 'getProvider' )->willReturn( $provider );
		$session->method( 'getSessionId' )->willReturn( 23 );
		$session->method( 'get' )->willReturn( null );

		/** @var MockObject|WebRequest $request */
		$request = $this->getMockBuilder( FauxRequest::class )
			->setConstructorArgs( [], false, $session )
			->onlyMethods( [ 'getSession' ] )
			->getMock();
		$request->method( 'getSession' )->willReturn( $session );

		$context = new RequestContext();
		$context->setRequest( $request );

		$context->setTitle( Title::newMainPage() );
		$context->setUser( $this->getTestUser()->getUser() );

		$out = new OutputPage( $context );

		$out->output( true );
		$this->assertSame(
			ResourceLoaderModule::ORIGIN_USER_SITEWIDE,
			$out->getAllowedModules( ResourceLoaderModule::TYPE_SCRIPTS )
		);
		$this->assertSame(
			ResourceLoaderModule::ORIGIN_USER_SITEWIDE,
			$out->getAllowedModules( ResourceLoaderModule::TYPE_STYLES )
		);
	}

	public function testApiParameterDeclared() {
		// hook is registered dynamically when creating the SessionProvider
		$this->newSessionProvider();

		$this->setMwGlobals( 'wgCentralAuthCookies', true );
		$main = new ApiMain();

		$params = $main->getFinalParams();
		$this->assertArrayHasKey( 'centralauthtoken', $params );
	}

}
