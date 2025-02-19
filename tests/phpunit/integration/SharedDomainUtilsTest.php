<?php

namespace MediaWiki\Extension\CentralAuth\Tests\Phpunit\Integration;

use GlobalPreferences\GlobalPreferencesFactory;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Extension\CentralAuth\SharedDomainUtils;
use MediaWiki\HookContainer\HookRunner;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxRequest;
use MediaWikiIntegrationTestCase;
use TestUser;
use Wikimedia\IPUtils;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\CentralAuth\SharedDomainUtils
 * @group Database
 */
class SharedDomainUtilsTest extends MediaWikiIntegrationTestCase {

	public function testIsSharedDomain() {
		$this->overrideConfigValues( [
			MainConfigNames::Server => '//auth.wikimedia.org',
			MainConfigNames::CanonicalServer => 'https://auth.wikimedia.org',
			CAMainConfigNames::CentralAuthSharedDomainPrefix => 'https://auth.wikimedia.org',
		] );
		$services = $this->getServiceContainer();
		$sharedDomainUtils = new SharedDomainUtils(
			$services->getMainConfig(),
			$services->getTitleFactory(),
			new HookRunner( $services->getHookContainer() ),
			null,
			false,
			static fn () => $services->getPreferencesFactory(),
			$services->getTempUserConfig(),
		);
		$wrappedHandler = TestingAccessWrapper::newFromObject( $sharedDomainUtils );
		$this->assertTrue( $wrappedHandler->isSharedDomain() );
		$this->assertTrue( $wrappedHandler->isSharedDomain() );

		$this->overrideConfigValues( [
			MainConfigNames::Server => '//en.wikipedia.org',
			MainConfigNames::CanonicalServer => 'https://en.wikipedia.org',
			CAMainConfigNames::CentralAuthSharedDomainPrefix => 'https://auth.wikimedia.org',
		] );
		$services = $this->getServiceContainer();
		$sharedDomainUtils = new SharedDomainUtils(
			$services->getMainConfig(),
			$services->getTitleFactory(),
			new HookRunner( $services->getHookContainer() ),
			null,
			false,
			static fn () => $services->getPreferencesFactory(),
			$services->getTempUserConfig()
		);
		$wrappedHandler = TestingAccessWrapper::newFromObject( $sharedDomainUtils );
		$this->assertFalse( $wrappedHandler->isSharedDomain() );
		$this->assertFalse( $wrappedHandler->isSharedDomain() );
	}

	public static function provideIsSul3Enabled() {
		$noCookies = [];
		$noParams = [];
		$paramSetToZero = [ 'usesul3' => '0' ];
		$paramSetToOne = [ 'usesul3' => '1' ];
		$cookieSet = [ 'sul3OptIn' => '1' ];

		return [
			'config disabled, no params' => [ [], $noParams, $noCookies, null ],
			'config disabled, param present' => [ [], $paramSetToOne, $noCookies, null ],
			'config disabled, cookie present' => [ [], $noParams, $cookieSet, null ],

			// config flag set to always, should always enable SUL3 mode.
			'config always, no params' => [ [ 'always' ], $noParams, $noCookies, true ],
			'config always and no query-flag, param set to zero' => [
				[ 'always' ], $paramSetToZero, $noCookies, true
			],

			// Enable SUL3 if config flag set to query-param, and query param is set to 1
			'queryFlag, no params' => [ [ 'query-flag' ], $noParams, $noCookies, null ],
			'queryFlag, param set' => [ [ 'query-flag' ], $paramSetToOne, $noCookies, true ],
			'queryFlag, cookie set' => [ [ 'query-flag' ], $noParams, $cookieSet, null ],

			// config flag set to cookie, only when cookie is present
			'cookie, no params' => [ [ 'cookie' ], $noParams, $noCookies, null ],
			'cookie and no query-flag, params set' => [ [ 'cookie' ], $paramSetToOne, $noCookies, null ],
			'cookie, cookie set' => [ [ 'cookie' ], $noParams, $cookieSet, true ],

			// multiple configs
			'both, no params' => [ [ 'cookie', 'query-flag' ], $noParams, $noCookies, null ],
			'both, param set' => [ [ 'cookie', 'query-flag' ], $paramSetToOne, $noCookies, true ],
			'both, param set to 0' => [
				[ 'cookie', 'query-flag' ], $paramSetToZero, $noCookies, false
			],
			'cookie and query-flag, cookie set, param set to 1' => [
				[ 'cookie', 'query-flag' ], $paramSetToOne, $cookieSet, true
			],
			'cookie and query-flag, cookie set, param set to 0' => [
				[ 'cookie', 'query-flag' ], $paramSetToZero, $cookieSet, false
			],
			'config always and query-flag, param set to zero' => [
				[ 'always', 'query-flag' ], $paramSetToZero, $noCookies, false
			],
		];
	}

	/**
	 * @dataProvider provideIsSul3Enabled
	 * @return void
	 */
	public function testIsSul3Enabled( $configFlag, $requestParams, $cookies, $expected ) {
		$this->overrideConfigValue( CAMainConfigNames::CentralAuthEnableSul3, $configFlag );
		$this->overrideConfigValue( CAMainConfigNames::Sul3RolloutUserPercentage, '0' );
		$this->overrideConfigValue( CAMainConfigNames::Sul3RolloutAnonSignupPercentage, '0' );
		$this->overrideConfigValue( CAMainConfigNames::Sul3RolloutSignupCookie, false );

		$fauxRequest = new FauxRequest( $requestParams );
		if ( $cookies ) {
			$fauxRequest->setCookies( $cookies, '' );
		}
		$this->setRequest( $fauxRequest );

		/** @var SharedDomainUtils $sut */
		$sut = $this->getServiceContainer()->get( 'CentralAuth.SharedDomainUtils' );
		$this->assertSame( $expected, $sut->isSul3Enabled( $fauxRequest ) );
	}

	public function provideIsSul3Enabled_Api() {
		return [
			// are we on the shared domain?, is this an API request?, should SUL3 be enabled?
			[ true, true, true ],
			[ true, false, true ],
			[ false, true, false ],
			[ false, false, true ],
		];
	}

	/**
	 * @dataProvider provideIsSul3Enabled_Api
	 * @return void
	 */
	public function testIsSul3Enabled_Api( $isSharedDomain, $isAPiRequest, $expected ) {
		$this->overrideConfigValue( CAMainConfigNames::CentralAuthEnableSul3, [ 'query-flag' ] );
		$this->overrideConfigValue( CAMainConfigNames::Sul3RolloutUserPercentage, '0' );
		$this->overrideConfigValue( CAMainConfigNames::Sul3RolloutAnonSignupPercentage, '0' );
		$this->overrideConfigValue( CAMainConfigNames::Sul3RolloutSignupCookie, false );
		$fauxRequest = new FauxRequest( [ 'usesul3' => '1' ] );
		/** @var SharedDomainUtils $sut */
		$sut = $this->getMockBuilder( SharedDomainUtils::class )
			->setConstructorArgs( [
				$this->getServiceContainer()->getMainConfig(),
				$this->getServiceContainer()->getTitleFactory(),
				new HookRunner( $this->getServiceContainer()->getHookContainer() ),
				null,
				$isAPiRequest,
				fn () => $this->getServiceContainer()->getPreferencesFactory(),
				$this->getServiceContainer()->getTempUserConfig(),
			] )
			->onlyMethods( [ 'isSharedDomain' ] )
			->getMock();
		$sut->expects( $this->any() )->method( 'isSharedDomain' )->willReturn( $isSharedDomain );
		$this->assertSame( $expected, $sut->isSul3Enabled( $fauxRequest ) );
	}

	public static function provideIsSul3EnabledWithGlobalPref() {
		$noCookies = [];
		$prefix = RequestContext::getMain()->getConfig()->get( CAMainConfigNames::CentralAuthCookiePrefix );
		$prefCookieSet = [ $prefix . 'sul3wanted' => '1' ];

		$anonUser = '192.168.1.25';
		$namedUser = 'CentralAuthRolloutTestUser2';

		return [
			'no cookie, IP user, no pref set' => [
				$noCookies, $anonUser, false, null ],
			'pref cookie, IP user, no pref set' => [
				$prefCookieSet, $anonUser, false, true ],
			'pref cookie, IP user, pref set' => [
				$prefCookieSet, $anonUser, true, true ],

			'no cookie, named user, pref not set' => [
				$noCookies, $namedUser, false, false ],
			'pref cookie, named user, pref not set' => [
				$prefCookieSet, $namedUser, false, true ],
			'no cookie, named user, pref set' => [
				$noCookies, $namedUser, true, true ],

		];
	}

	/**
	 * @dataProvider provideIsSul3EnabledWithGlobalPref
	 * @return void
	 */
	public function testIsSul3EnabledWithGlobalPref( $cookies, $userOrIP, $prefValue, $expected ) {
		$this->markTestSkippedIfExtensionNotLoaded( 'GlobalPreferences' );

		$configFlag = [];
		$this->overrideConfigValue( CAMainConfigNames::CentralAuthEnableSul3, $configFlag );
		$this->overrideConfigValue( CAMainConfigNames::Sul3RolloutSignupCookie, true );

		$globalPreferencesFactory = $this->createMock( GlobalPreferencesFactory::class );
		$globalPreferencesFactory->method( 'getGlobalPreferencesValues' )
			->willReturn( [ 'centralauth-use-sul3' => $prefValue ] );
		$this->setService( 'PreferencesFactory', $globalPreferencesFactory );

		if ( IPUtils::isIPAddress( $userOrIP ) ) {
			$user = $this->getServiceContainer()->getUserFactory()->newAnonymous( $userOrIP );
		} else {
			$user = ( new TestUser( $userOrIP ) )->getUser();
		}
		RequestContext::getMain()->setUser( $user );
		$fauxRequest = new FauxRequest();

		if ( $cookies ) {
			$fauxRequest->setCookies( $cookies, '' );
		}
		$this->setRequest( $fauxRequest );

		$services = $this->getServiceContainer();
		$sharedDomainUtils = new SharedDomainUtils(
			$services->getMainConfig(),
			$services->getTitleFactory(),
			new HookRunner( $services->getHookContainer() ),
			null,
			false,
			static fn () => $services->getPreferencesFactory(),
			$services->getTempUserConfig()
		);
		$this->assertSame( $expected, $sharedDomainUtils->isSul3Enabled( $fauxRequest ) );
	}
}
