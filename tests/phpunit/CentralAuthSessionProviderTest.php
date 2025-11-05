<?php

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Session\SessionBackend;
use MediaWiki\Session\SessionId;
use MediaWiki\Session\SessionInfo;
use MediaWiki\Session\UserInfo;
use MediaWiki\Tests\Mocks\Json\PlainJsonJwtCodec;
use MediaWiki\Tests\MockWikiMapTrait;
use MediaWiki\Tests\Session\SessionProviderTestTrait;
use MediaWiki\Tests\Session\TestBagOStuff;
use MediaWiki\Utils\UrlUtils;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use Wikimedia\LightweightObjectStore\ExpirationAwareness;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers CentralAuthSessionProvider
 * @group Database
 */
class CentralAuthSessionProviderTest extends MediaWikiIntegrationTestCase {
	use SessionProviderTestTrait;
	use MockWikiMapTrait;

	/** Value of `$request->response()->getCookie()` when the cookie was deleted. */
	private const DELETED = '';
	/** Value of `$request->response()->getCookie()` when the cookie was unchanged. */
	private const UNCHANGED = null;

	private function getConfig() {
		return new HashConfig( [
			CAMainConfigNames::CentralAuthCookies => true,
			CAMainConfigNames::CentralAuthCookiePrefix => 'central_',
			CAMainConfigNames::CentralAuthCookiePath => '/',
			CAMainConfigNames::CentralAuthCookieDomain => 'wiki.org',
			CAMainConfigNames::CentralAuthCentralWiki => 'issuer',
			MainConfigNames::CookieExpiration => 100,
			MainConfigNames::SessionCookieJwtExpiration => 10,
			MainConfigNames::ExtendedLoginCookieExpiration => 200,
			// these are needed by CookieSessionProvider::getConfig
			MainConfigNames::SessionName => null,
			MainConfigNames::CookiePrefix => 'local_',
			MainConfigNames::CookiePath => '',
			MainConfigNames::CookieDomain => 'local.wiki.org',
			MainConfigNames::CookieSecure => true,
			MainConfigNames::CookieHttpOnly => true,
			MainConfigNames::CookieSameSite => null,
			MainConfigNames::UseSessionCookieJwt => false,
		] );
	}

	/**
	 * @dataProvider provideSuggestLoginUsername
	 */
	public function testSuggestLoginUsername( $cookies, $expectedUsername ) {
		$services = $this->getServiceContainer();
		$provider = new CentralAuthSessionProvider(
			$services->getJwtCodec(),
			$services->getTempUserConfig(),
			$services->getUrlUtils(),
			$services->getUserIdentityLookup(),
			$services->get( 'CentralAuth.CentralAuthSessionManager' ),
			[
				'priority' => 42,
				'cookieOptions' => [ 'prefix' => '' ],
				'centralCookieOptions' => [ 'prefix' => '' ],
			]
		);
		$this->initProvider(
			$provider, null, $this->getConfig(), null, null, $services->getUserNameUtils()
		);
		$request = new FauxRequest();
		$request->setCookies( $cookies, '' );
		$this->assertSame( $expectedUsername, $provider->suggestLoginUsername( $request ) );
	}

	public static function provideSuggestLoginUsername() {
		return [
			[ [ 'Foo' => 'bar' ], null ],
			[ [ 'User' => 'Foo' ], 'Foo' ],
			[ [ 'User' => 'foo' ], 'Foo' ],
			[ [ 'User' => '#&@!' ], null ],
			[ [ 'UserName' => 'Foo' ], 'Foo' ],
			[ [ 'UserName' => 'foo' ], 'Foo' ],
			[ [ 'UserName' => '#&@!' ], null ],
			[ [ 'User' => 'Foo', 'UserName' => 'Bar' ], 'Foo' ],
			[ [ 'User' => '0' ], '0' ],
			[ [ 'UserName' => '0' ], '0' ],
		];
	}

	public function testGetRememberUserDuration() {
		$services = $this->getServiceContainer();
		$provider = new CentralAuthSessionProvider(
			$services->getJwtCodec(),
			$services->getTempUserConfig(),
			$services->getUrlUtils(),
			$services->getUserIdentityLookup(),
			$services->get( 'CentralAuth.CentralAuthSessionManager' ),
			[ 'priority' => 42 ]
		);
		$this->initProvider( $provider, null, $this->getConfig() );

		$this->assertSame( 200, $provider->getRememberUserDuration() );
	}

	public function testProvideSessionInfo() {
		$startTime = 1_000_000;
		ConvertibleTimestamp::setFakeTime( $startTime );
		$this->overrideConfigValues( [
			MainConfigNames::CentralIdLookupProvider => 'CentralAuth',
		] );
		$this->mockWikiMap( 'http://local.wiki.org', [
			[ 'wikiId' => 'issuer', 'server' => 'http://issuer.wiki.org' ],
		] );
		$logger = new TestLogger( true,
			static fn ( $message, $level ) => ( $level === LogLevel::DEBUG ) ? null : $message
		);

		$services = $this->getServiceContainer();
		$provider = new CentralAuthSessionProvider(
			new PlainJsonJwtCodec(),
			$services->getTempUserConfig(),
			$this->getMockUrlUtils( canonicalServer: 'http://local.wiki.org' ),
			$services->getUserIdentityLookup(),
			$services->get( 'CentralAuth.CentralAuthSessionManager' ),
			[
				'priority' => 42,
				'sessionName' => 'session',
			]
		);
		$config = $this->getConfig();
		$config->set( MainConfigNames::UseSessionCookieJwt, true );
		$this->initProvider( $provider, $logger, $config, $services->getSessionManager(),
			userNameUtils: $services->getUserNameUtils() );

		$testUser = $this->getTestUser();
		$user = $testUser->getUser();
		$centralUser = CentralAuthTestUser::newFromTestUser( $testUser )->save( $this->getDb() )->getCentralUser();

		$jwtCodec = new PlainJsonJwtCodec();
		$defaultClaims = [
			'iss' => 'http://issuer.wiki.org',
			'sxp' => $startTime + 100,
			'exp' => $startTime + 100 + ExpirationAwareness::TTL_DAY,
			'sub' => 'mw:CentralAuth::' . $centralUser->getId(),
		];

		$request = new FauxRequest();
		$request->setCookies( [
			'local_UserName' => $user->getName(),
			'local_UserID' => $user->getId(),
			'central_User' => $user->getName(),
			'central_Token' => $centralUser->getAuthToken(),
			'sessionJwt' => $jwtCodec->create( [
				'iss' => 'http://local.wiki.org',
			] + $defaultClaims ),
		], prefix: '' );
		$info = $provider->provideSessionInfo( $request );
		// avoid terrible built-in object dumping of PHPUnit
		$this->assertNull( $info?->__toString() );
		$this->assertSame( [ [ LogLevel::INFO, 'JWT validation failed: JWT error: wrong issuer' ] ], $logger->getBuffer() );
		$logger->clearBuffer();

		$request = new FauxRequest();
		$request->setCookies( [
			'local_UserName' => $user->getName(),
			'local_UserID' => $user->getId(),
			'central_User' => $user->getName(),
			'central_Token' => $centralUser->getAuthToken(),
			'sessionJwt' => $jwtCodec->create( $defaultClaims ),
		], prefix: '' );
		$info = $provider->provideSessionInfo( $request );
		$this->assertNotNull( $info );
		$this->assertSame( 42, $info->getPriority() );
		$this->assertSame( $provider, $info->getProvider() );
		$this->assertNotNull( $info->getUserInfo() );
		$this->assertSame( false, $info->getUserInfo()->isAnon() );
		$this->assertSame( true, $info->getUserInfo()->isVerified() );
		$this->assertSame( $user->getName(), $info->getUserInfo()->getName() );
		$this->assertSame( [ 'CentralAuthSource' => 'CentralAuth' ], $info->getProviderMetadata() );
		$this->assertSame( true, $info->isIdSafe() );
		$this->assertSame( true, $info->wasPersisted() );
		$this->assertSame( true, $info->wasRemembered() );
		$this->assertSame( [], $logger->getBuffer() );
		$logger->clearBuffer();
	}

	public function testPersistSession() {
		$startTime = 1_000_000;
		ConvertibleTimestamp::setFakeTime( $startTime );
		$this->overrideConfigValues( [
			MainConfigNames::CentralIdLookupProvider => 'CentralAuth',
		] );
		$this->mockWikiMap( 'http://local.wiki.org', [
			[ 'wikiId' => 'issuer', 'server' => 'http://issuer.wiki.org' ],
		] );

		$services = $this->getServiceContainer();
		$hookContainer = $this->createHookContainer();
		$provider = new CentralAuthSessionProvider(
			new PlainJsonJwtCodec(),
			$services->getTempUserConfig(),
			$this->getMockUrlUtils( canonicalServer: 'http://local.wiki.org' ),
			$services->getUserIdentityLookup(),
			$services->get( 'CentralAuth.CentralAuthSessionManager' ),
			[
				'priority' => 42,
				'sessionName' => 'session',
			]
		);
		$config = $this->getConfig();
		$config->set( MainConfigNames::UseSessionCookieJwt, true );
		$config->set( MainConfigNames::AnonSessionCacheType, false );
		$this->initProvider( $provider, null, $config, $services->getSessionManager() );

		$jwtCodec = new PlainJsonJwtCodec();
		$testUser = $this->getTestUser();
		$user = $testUser->getUser();
		$centralUser = CentralAuthTestUser::newFromTestUser( $testUser )->save( $this->getDb() )->getCentralUser();
		$request = new FauxRequest();

		$sessionId = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
		$centralSessionId = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
		$store = new TestBagOStuff();
		$backend = new SessionBackend(
			new SessionId( $sessionId ),
			new SessionInfo( SessionInfo::MIN_PRIORITY, [
				'provider' => $provider,
				'id' => $sessionId,
				'persisted' => true,
				'remembered' => false,
				'idIsSafe' => true,
				'userInfo' => UserInfo::newFromUser( $user, true ),
			] ),
			$this->getServiceContainer()->getSessionStore(),
			new NullLogger(),
			$hookContainer,
			10
		);
		$backend->addData( [ 'CentralAuth::centralSessionId' => $centralSessionId ] );

		$provider->persistSession( $backend, $request );
		$this->assertSame( $sessionId, $request->response()->getCookie( 'session' ) );
		$this->assertSame( (string)$user->getId(), $request->response()->getCookie( 'local_UserID' ) );
		$this->assertSame( $user->getName(), $request->response()->getCookie( 'local_UserName' ) );
		$this->assertSame( self::DELETED, $request->response()->getCookie( 'local_Token' ) );
		$this->assertSame( $centralSessionId, $request->response()->getCookie( 'central_Session' ) );
		$this->assertSame( $user->getName(), $request->response()->getCookie( 'central_User' ) );
		$this->assertSame( self::DELETED, $request->response()->getCookie( 'central_Token' ) );

		$sessionJwtData = $request->response()->getCookieData( 'sessionJwt' );
		$this->assertNotNull( $sessionJwtData );
		$this->assertSame( 'local.wiki.org', $sessionJwtData['domain'] );
		$this->assertSame( $startTime + 10, $sessionJwtData['expire'] );
		$claims = $jwtCodec->parse( $sessionJwtData['value'] );
		$this->assertSame( 'http://issuer.wiki.org', $claims['iss'] );
		$this->assertSame( 'mw:CentralAuth::' . $centralUser->getId(), $claims['sub'] );
	}

	private function getMockUrlUtils( string $canonicalServer ): UrlUtils {
		$urlUtils = $this->createNoOpMock( UrlUtils::class, [ 'getCanonicalServer' ] );
		$urlUtils->method( 'getCanonicalServer' )->willReturn( $canonicalServer );
		return $urlUtils;
	}
}
