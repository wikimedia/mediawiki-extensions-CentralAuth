<?php

use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\CentralAuthSessionManager;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\ResourceLoader\Module;
use MediaWiki\Session\Session;
use MediaWiki\Session\SessionInfo;
use MediaWiki\User\UserFactory;
use PHPUnit\Framework\MockObject\MockObject;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers CentralAuthTokenSessionProvider
 * @group medium
 * @group Database
 */
abstract class CentralAuthTokenSessionProviderTest extends MediaWikiIntegrationTestCase {

	/** @var CentralAuthSessionManager */
	protected $sessionManager;

	/** @var BagOStuff */
	protected $sessionStore;

	/** @var int */
	private $idCounter = 0;

	/**
	 * @var User[]|null
	 */
	private $userObjects;

	public function setUp(): void {
		parent::setUp();

		$this->setUserLang( 'qqx' );

		$this->sessionStore = new HashBagOStuff();
		$this->patchSessionStore();

		// CentralAuthTokenSessionProvider registers hooks dynamically.
		// Make sure the original hooks are restored before the next test.
		$this->setMwGlobals( 'wgHooks', $GLOBALS[ 'wgHooks' ] );
	}

	/**
	 * Patch our modified session store into CentralAuthSessionManager
	 */
	private function patchSessionStore() {
		$manager = CentralAuthServices::getSessionManager( $this->getServiceContainer() );
		$this->sessionManager = TestingAccessWrapper::newFromObject( $manager );
		$this->sessionManager->sessionStore = $this->sessionStore;
		$this->sessionManager->tokenStore = $this->sessionStore;
	}

	protected function assertSessionInfoError(
		WebRequest $request,
		?SessionInfo $result,
		string $error = null,
		string $code = null
	) {
		$this->assertNotNull( $result );

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

	protected function assertSessionInfoOk( ?SessionInfo $result ) {
		$data = $result->getProviderMetadata();
		if ( $data !== null ) {
			$this->assertArrayNotHasKey( 'error', $data, $data['error'] ?? '' );
			$this->assertArrayNotHasKey( 'error-code', $data, $data['error-code'] ?? '' );
		}

		$this->assertNotNull( $result->getUserInfo() );
	}

	abstract protected function newSessionProvider();

	protected function makeValidToken( $data = [] ) {
		// NOTE: logic stolen from ApiCentralAuthToken

		$data += [
			'userName' => 'Frank',
			'token' => 'CATOKEN', // not the login token
			'origin' => 'test',
			'originSessionId' => 1337,
		];

		$loginToken = 'testtoken' . ++$this->idCounter;

		$key = CentralAuthServices::getSessionManager()->memcKey( 'api-token', $loginToken );
		$this->sessionStore->set( $key, $data, 60 * 60 );

		return $loginToken;
	}

	/**
	 * @param int $id
	 * @param string $name
	 *
	 * @return MockObject|User
	 */
	protected function makeUser( $id, $name ) {
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
			// setService resets existing objects, so make sure our sessionStore patch will be there
			$this->patchSessionStore();
		}

		$this->userObjects[ $name ] = $user;

		return $user;
	}

	/**
	 * @param string $name
	 * @param array $return
	 * @param array $methods
	 *
	 * @return MockObject|CentralAuthUser
	 */
	protected function makeCentralAuthUser( $name, $return = [], $methods = [] ) {
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

	abstract protected function makeRequest( $token );

	public function testProvideSessionInfo_noToken() {
		$provider = $this->newSessionProvider();
		$request = new FauxRequest();

		$result = $provider->provideSessionInfo( $request );
		$this->assertNull( $result );
	}

	public function testProvideSessionInfo_unknownToken() {
		$provider = $this->newSessionProvider();

		$request = $this->makeRequest( 'bogus' );
		$result = $provider->provideSessionInfo( $request );
		$this->assertSessionInfoError( $request, $result, 'apierror-centralauth-badtoken', 'badtoken' );
	}

	public function testProvideSessionInfo() {
		$user = $this->makeCentralAuthUser( 'Frank' );
		$token = $this->makeValidToken( [ 'userName' => $user->getName() ] );
		$provider = $this->newSessionProvider();

		$request = $this->makeRequest( $token );
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
	public function testProvideSessionInfo_badUser( $name, $return, $error, $code ) {
		// NOTE: the user gets registered in a fake service,
		//       we can't construct it in the data provider!
		$user = $this->makeCentralAuthUser( $name, $return );
		$token = $this->makeValidToken( [ 'userName' => $user->getName() ] );
		$provider = $this->newSessionProvider();

		$request = $this->makeRequest( $token );
		$result = $provider->provideSessionInfo( $request );
		$this->assertSessionInfoError( $request, $result, $error, $code );
	}

	public function testCannotReuseToken() {
		$user = $this->makeCentralAuthUser( 'Frank' );
		$token = $this->makeValidToken( [ 'userName' => $user->getName() ] );
		$provider = $this->newSessionProvider();

		$request = $this->makeRequest( $token );
		$result = $provider->provideSessionInfo( $request );
		$this->assertSessionInfoOk( $result );

		// the token should now be unknown
		$result = $provider->provideSessionInfo( $request );
		$this->assertSessionInfoError( $request, $result, 'apierror-centralauth-badtoken', 'badtoken' );
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

		$request = $this->makeRequest( $token );
		$result = $provider->provideSessionInfo( $request );

		$this->assertSessionInfoError( $request, $result, 'apierror-centralauth-badtoken', 'badtoken' );
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
			Module::ORIGIN_USER_SITEWIDE,
			$out->getAllowedModules( Module::TYPE_SCRIPTS )
		);
		$this->assertSame(
			Module::ORIGIN_USER_SITEWIDE,
			$out->getAllowedModules( Module::TYPE_STYLES )
		);
	}

}
