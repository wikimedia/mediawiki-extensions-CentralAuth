<?php

namespace MediaWiki\Extension\CentralAuth\Tests\Phpunit\Integration\Api;

use CentralAuthApiSessionProvider;
use CentralAuthSessionProvider;
use MediaWiki\Api\ApiUsageException;
use MediaWiki\Config\HashConfig;
use MediaWiki\Config\SiteConfiguration;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Json\JwtCodec;
use MediaWiki\MainConfigNames;
use MediaWiki\Permissions\UltimateAuthority;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Session\Session;
use MediaWiki\Session\SessionProvider;
use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\Tests\Session\SessionProviderTestTrait;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\CentralAuth\Api\ApiCentralAuthToken
 * @group Database
 */
class ApiCentralAuthTokenTest extends ApiTestCase {
	use SessionProviderTestTrait;

	private Session $mockSession;

	public function setUp(): void {
		parent::setUp();

		$caSessionProvider = $this->createMock( CentralAuthSessionProvider::class );
		$mockSession = $this->createMock( Session::class );
		$mockSession->method( 'getProvider' )->willReturn( $caSessionProvider );
		$mockSession->method( 'getId' )->willReturn( 'testSessionId' );
		$this->mockSession = $mockSession;

		$this->patchStores();
	}

	private function patchStores(): void {
		$store = new HashBagOStuff();

		$wrappedSessionMgr = TestingAccessWrapper::newFromObject(
			CentralAuthServices::getSessionManager( $this->getServiceContainer() )
		);
		$wrappedSessionMgr->sessionStore = $store;

		$wrappedTokenMgr = TestingAccessWrapper::newFromObject(
			CentralAuthServices::getTokenManager( $this->getServiceContainer() )
		);
		$wrappedTokenMgr->tokenStore = $store;
	}

	/**
	 * Overwritten to force the mock session to be used.
	 * @inheritDoc
	 */
	protected function buildFauxRequest( $params, $session ): FauxRequest {
		return new FauxRequest( $params, true, $this->mockSession );
	}

	private function newSessionProvider(): CentralAuthApiSessionProvider {
		$config = new HashConfig( [ MainConfigNames::SecretKey => 'hunter2' ] );
		$services = $this->getServiceContainer();

		$provider = new CentralAuthApiSessionProvider(
			$services->getUserIdentityLookup(),
			CentralAuthServices::getApiTokenManager( $services ),
			CentralAuthServices::getSessionManager( $services ),
		);

		$this->initProvider(
			$provider,
			null,
			$config,
			$services->getSessionManager(),
			$this->createHookContainer(),
			$services->getUserNameUtils()
		);

		return $provider;
	}

	private function getAttachedTestUser() {
		$testUser = $this->getMutableTestUser();
		$user = $testUser->getUser();
		$centralUser = CentralAuthUser::getPrimaryInstance( $user );
		$centralUser->register( $testUser->getPassword(), null );
		$centralUser->attach( WikiMap::getCurrentWikiId() );
		return $user;
	}

	public function testBadSessionProvider() {
		$badProvider = $this->createMock( SessionProvider::class );
		$badSession = $this->createMock( Session::class );
		$badSession->method( 'getProvider' )->willReturn( $badProvider );
		$badSession->method( 'getId' )->willReturn( 'testSessionId' );
		$this->mockSession = $badSession;

		$user = $this->getAttachedTestUser();
		try {
			$this->doApiRequest( [ 'action' => 'centralauthtoken' ], null, false, $user );
			$this->fail( 'Expected ApiUsageException' );
		} catch ( ApiUsageException $e ) {
			$this->assertStatusError( 'apierror-centralauth-badsession', $e->getStatusValue() );
		}
	}

	public function testNotLoggedIn() {
		$anon = new UltimateAuthority( UserIdentityValue::newAnonymous( '127.0.0.1' ) );
		try {
			$this->doApiRequest( [ 'action' => 'centralauthtoken' ], null, false, $anon );
			$this->fail( 'Expected ApiUsageException' );
		} catch ( ApiUsageException $e ) {
			$this->assertStatusError( 'apierror-centralauth-notloggedin', $e->getStatusValue() );
		}
	}

	public function testNoGlobalUser() {
		$user = $this->getMutableTestUser()->getUser();
		try {
			$this->doApiRequest( [ 'action' => 'centralauthtoken' ], null, false, $user );
			$this->fail( 'Expected ApiUsageException' );
		} catch ( ApiUsageException $e ) {
			$this->assertStatusError( 'apierror-centralauth-notattached', $e->getStatusValue() );
		}
	}

	public function testTokenRoundtrip() {
		$user = $this->getAttachedTestUser();

		[ $result ] = $this->doApiRequest(
			[ 'action' => 'centralauthtoken' ],
			null,
			false,
			$user
		);

		$token = $result['centralauthtoken']['centralauthtoken'];
		$this->assertNotEmpty( $token );

		$provider = $this->newSessionProvider();
		$request = new FauxRequest( [ 'centralauthtoken' => $token ] );
		$sessionInfo = $provider->provideSessionInfo( $request );

		$this->assertNotNull( $sessionInfo );
		$this->assertNotNull( $sessionInfo->getUserInfo() );
		$this->assertSame( $user->getName(), $sessionInfo->getUserInfo()->getName() );
	}

	public function testTokenRoundtripJwt() {
		$loginToken = null;
		$jwtCodec = $this->createMock( JwtCodec::class );
		$jwtCodec->method( 'isEnabled' )->willReturn( true );
		$jwtCodec->expects( $this->once() )
			->method( 'create' )->willReturnCallback( static function ( $claims ) use ( &$loginToken ) {
				$loginToken = $claims['CAToken'];
				return 'FAKE.JWT';
			} );
		$jwtCodec->expects( $this->once() )
			->method( 'parse' )->with( 'FAKE.JWT' )->willReturnCallback(
				static function () use ( &$loginToken ) {
					return [ 'CAToken' => $loginToken ];
				} );
		$this->setService( 'JwtCodec', $jwtCodec );

		// Later: Once WikiMap is a service, this can be done more nicely.
		$conf = new SiteConfiguration();
		$conf->settings = [
			'wgServer' => [ 'examplewiki' => 'https://example.org' ],
			'wgArticlePath' => [ 'examplewiki' => '/wiki/$1' ],
		];
		$conf->suffixes = [ 'wiki' ];
		$this->setMwGlobals( 'wgConf', $conf );

		$this->overrideConfigValues( [
			MainConfigNames::UseSessionCookieJwt => true,
			CAMainConfigNames::CentralAuthCentralWiki => 'examplewiki',
		] );

		$this->patchStores();

		$user = $this->getAttachedTestUser();

		[ $result ] = $this->doApiRequest(
			[ 'action' => 'centralauthtoken' ],
			null,
			false,
			$user
		);

		$token = $result['centralauthtoken']['centralauthtoken'];
		$this->assertSame( 'FAKE.JWT', $token );

		$provider = $this->newSessionProvider();
		$request = new FauxRequest( [ 'centralauthtoken' => 'FAKE.JWT' ] );
		$sessionInfo = $provider->provideSessionInfo( $request );

		$this->assertNotNull( $sessionInfo );
		$this->assertNotNull( $sessionInfo->getUserInfo() );
		$this->assertSame( $user->getName(), $sessionInfo->getUserInfo()->getName() );
	}
}
