<?php

namespace MediaWiki\Extension\CentralAuth\Tests\Phpunit\Integration;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Extension\CentralAuth\SharedDomainUtils;
use MediaWiki\HookContainer\HookRunner;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxRequest;
use MediaWiki\User\UserOptionsManager;
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
			static fn () => $services->getUserOptionsManager(),
			new HookRunner( $services->getHookContainer() ),
			null,
			false,
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
			static fn () => $services->getUserOptionsManager(),
			new HookRunner( $services->getHookContainer() ),
			null,
			false,
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

		$fauxRequest = new FauxRequest( $requestParams );
		if ( $cookies ) {
			$fauxRequest->setCookies( $cookies, '' );
		}
		$this->setRequest( $fauxRequest );

		/** @var SharedDomainUtils $sut */
		$sut = $this->getServiceContainer()->get( 'CentralAuth.SharedDomainUtils' );
		$actual = $sut->isSul3Enabled( $fauxRequest, $isUnset );
		if ( $isUnset ) {
			$this->assertNull( $expected );
		} else {
			$this->assertSame( $expected, $actual );
		}
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

		$fauxRequest = new FauxRequest( [ 'usesul3' => '1' ] );
		/** @var SharedDomainUtils $sut */
		$sut = $this->getMockBuilder( SharedDomainUtils::class )
			->setConstructorArgs( [
				$this->getServiceContainer()->getMainConfig(),
				$this->getServiceContainer()->getTitleFactory(),
				fn () => $this->getServiceContainer()->getUserOptionsManager(),
				new HookRunner( $this->getServiceContainer()->getHookContainer() ),
				null,
				$isAPiRequest,
				$this->getServiceContainer()->getTempUserConfig(),
			] )
			->onlyMethods( [ 'isSharedDomain' ] )
			->getMock();
		$sut->expects( $this->any() )->method( 'isSharedDomain' )->willReturn( $isSharedDomain );
		$actual = $sut->isSul3Enabled( $fauxRequest, $isUnset );
		if ( $isUnset ) {
			$this->assertNull( $expected );
		} else {
			$this->assertSame( $expected, $actual );
		}
	}

	public static function provideIsSul3EnabledWithGlobalPref() {
		$noCookies = [];
		$userNameCookieSet = [ 'UserName' => 'CentralAuthRolloutTestUser1' ];
		$noPrefs = [];
		$user1PrefTrue = [ 'CentralAuthRolloutTestUser1' => [ 'centralauth-use-sul3' => '1' ] ];
		$user1PrefFalse = [ 'CentralAuthRolloutTestUser1' => [ 'centralauth-use-sul3' => '0' ] ];
		$user2PrefTrue = [ 'CentralAuthRolloutTestUser2' => [ 'centralauth-use-sul3' => '1' ] ];
		$user2PrefFalse = [ 'CentralAuthRolloutTestUser2' => [ 'centralauth-use-sul3' => '0' ] ];
		$anonUser = '192.168.1.25';
		$namedUser = 'CentralAuthRolloutTestUser2';

		return [
			'IP user, no UserName cookie,' => [ $noCookies, $anonUser, $noPrefs, null ],
			'IP user, UserName cookie, no pref set' => [ $userNameCookieSet, $anonUser, $noPrefs, null ],
			'IP user, UserName cookie, pref true' => [ $userNameCookieSet, $anonUser, $user1PrefTrue, true ],
			'IP user, UserName cookie, pref false' => [ $userNameCookieSet, $anonUser, $user1PrefFalse, false ],

			'named user, pref not set' => [ $noCookies, $namedUser, $noPrefs, null ],
			'named user, pref true' => [ $noCookies, $namedUser, $user2PrefTrue, true ],
			'named user, pref false' => [ $noCookies, $namedUser, $user2PrefFalse, false ],
		];
	}

	/**
	 * @dataProvider provideIsSul3EnabledWithGlobalPref
	 * @return void
	 */
	public function testIsSul3EnabledWithGlobalPref( $cookies, $userOrIP, $prefValues, $expected ) {
		// note: this test will use a different code path when GlobalPreferences is enabled

		$this->overrideConfigValue( CAMainConfigNames::CentralAuthEnableSul3, [ 'global-pref' ] );

		if ( IPUtils::isIPAddress( $userOrIP ) ) {
			$user = $this->getServiceContainer()->getUserFactory()->newAnonymous( $userOrIP );
		} else {
			$user = ( new TestUser( $userOrIP ) )->getUser();
		}
		RequestContext::getMain()->setUser( $user );
		$fauxRequest = new FauxRequest();

		if ( $cookies ) {
			$fauxRequest->setCookies( $cookies );
		}
		$this->setRequest( $fauxRequest );

		if ( $prefValues ) {
			$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
			foreach ( $prefValues as $username => $values ) {
				$prefUser = $this->getServiceContainer()->getUserFactory()->newFromName( $username );
				foreach ( $values as $name => $value ) {
					$userOptionsManager->setOption( $prefUser, $name, $value, UserOptionsManager::GLOBAL_CREATE );
				}
				$userOptionsManager->saveOptions( $prefUser );
			}
		}

		$services = $this->getServiceContainer();
		$sharedDomainUtils = new SharedDomainUtils(
			$services->getMainConfig(),
			$services->getTitleFactory(),
			static fn () => $services->getUserOptionsManager(),
			new HookRunner( $services->getHookContainer() ),
			null,
			false,
			$services->getTempUserConfig()
		);
		$actual = $sharedDomainUtils->isSul3Enabled( $fauxRequest, $isUnset );
		if ( $isUnset ) {
			$this->assertNull( $expected );
		} else {
			$this->assertSame( $expected, $actual );
		}
	}

	public static function provideIsSul3EnabledWithPrefCookie() {
		$noCookies = [];
		$prefCookieSet = [ 'sul3OptIn' => '1' ];

		$anonUser = '192.168.1.25';
		$namedUser = 'CentralAuthRolloutTestUser2';

		return [
			'no cookie, IP user' => [ $noCookies, $anonUser, null ],
			'cookie, IP user' => [ $prefCookieSet, $anonUser, true ],
			'no cookie, named user' => [ $noCookies, $namedUser, null ],
			'cookie, named user' => [ $prefCookieSet, $namedUser, true ],
		];
	}

	/**
	 * @dataProvider provideIsSul3EnabledWithPrefCookie
	 * @return void
	 */
	public function testIsSul3EnabledWithPrefCookie( $cookies, $userOrIP, $expected ) {
		$this->overrideConfigValue( CAMainConfigNames::CentralAuthEnableSul3, [ 'cookie' ] );

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
			static fn () => $services->getUserOptionsManager(),
			new HookRunner( $services->getHookContainer() ),
			null,
			false,
			$services->getTempUserConfig()
		);
		$actual = $sharedDomainUtils->isSul3Enabled( $fauxRequest, $isUnset );
		if ( $isUnset ) {
			$this->assertNull( $expected );
		} else {
			$this->assertSame( $expected, $actual );
		}
	}
}
