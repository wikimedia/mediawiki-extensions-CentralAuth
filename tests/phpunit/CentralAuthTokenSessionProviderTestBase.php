<?php

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\MainConfigNames;
use MediaWiki\Output\OutputPage;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Request\WebRequest;
use MediaWiki\ResourceLoader\Module;
use MediaWiki\Session\Session;
use MediaWiki\Session\SessionId;
use MediaWiki\Session\SessionInfo;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use PHPUnit\Framework\MockObject\MockObject;
use Wikimedia\ObjectCache\BagOStuff;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\Rdbms\IDBAccessObject;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers CentralAuthTokenSessionProvider
 * @group medium
 * @group Database
 */
abstract class CentralAuthTokenSessionProviderTestBase extends MediaWikiIntegrationTestCase {

	protected BagOStuff $sessionStore;

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
		$this->patchStores();

		// CentralAuthTokenSessionProvider registers hooks dynamically.
		// Make sure the original hooks are restored before the next test.
		$this->overrideConfigValue( MainConfigNames::Hooks, $GLOBALS['wgHooks'] );
	}

	/**
	 * Patch our modified session store into CentralAuthSessionManager
	 */
	private function patchStores() {
		$sessionManager = CentralAuthServices::getSessionManager( $this->getServiceContainer() );
		$wrappedSessionManager = TestingAccessWrapper::newFromObject( $sessionManager );
		$wrappedSessionManager->sessionStore = $this->sessionStore;

		$tokenManager = CentralAuthServices::getTokenManager( $this->getServiceContainer() );
		$wrappedTokenManager = TestingAccessWrapper::newFromObject( $tokenManager );
		$wrappedTokenManager->tokenStore = $this->sessionStore;
	}

	protected function assertSessionInfoError(
		WebRequest $request,
		?SessionInfo $result,
		?string $error = null,
		?string $code = null
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
			// not the login token
			'token' => 'CATOKEN',
			'origin' => 'test',
			'originSessionId' => 1337,
		];

		$loginToken = 'testtoken' . ++$this->idCounter;

		$tokenManager = CentralAuthServices::getTokenManager( $this->getServiceContainer() );
		$tokenManager->tokenize(
			$data,
			'api-token',
			[ 'token' => $loginToken ]
		);
		return $loginToken;
	}

	/**
	 * @param int $id
	 * @param string $name
	 *
	 * @return MockObject|User
	 */
	protected function makeUser( $id, $name ) {
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

			$services = $this->getServiceContainer();
			$services->resetServiceForTesting( 'UserFactory' );
			$services->redefineService( 'UserFactory', static fn () => $userFactory );
		}

		$this->userObjects[ $name ] = $user;

		return $user;
	}

	/**
	 * @param string $name
	 * @param array<string,mixed> $return Array of methods to call, mapped to their expected values
	 * @param list<string> $methods List of extra methods that may be called during the test
	 *
	 * @return MockObject|CentralAuthUser
	 */
	protected function makeCentralAuthUser( $name, $return = [], $methods = [] ) {
		$id = ++$this->idCounter;

		$methods = [
			...$methods,
			'getId',
			'getName',
			'renameInProgress',
			'exists',
			'isAttached',
			'authenticateWithToken',
			'isFromPrimary'
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
			'isFromPrimary' => true,
		];

		foreach ( $return as $method => $value ) {
			$caUser->method( $method )->willReturn( $value );
		}

		CentralAuthServices::getUserCache()->set( $caUser );

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

	public static function provideBadUsers() {
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
			[ 'getProvider', 'getSessionId', 'getId', 'get', 'canSetUser', 'isPersistent', 'getUser' ]
		);
		$session->method( 'getProvider' )->willReturn( $provider );
		$session->method( 'getSessionId' )->willReturn( new SessionId( '23' ) );
		$session->method( 'getId' )->willReturn( '23' );
		$session->method( 'get' )->willReturn( null );
		$session->method( 'isPersistent' )->willReturn( true );
		$session->method( 'getUser' )->willReturn( $this->getTestUser()->getUser() );

		$request = new FauxRequest( [], false, $session );

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
