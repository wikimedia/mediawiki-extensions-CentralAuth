<?php

namespace MediaWiki\Extension\CentralAuth\Tests\Phpunit\Integration;

use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Extension\CentralAuth\SharedDomainUtils;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxRequest;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\CentralAuth\SharedDomainUtils
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
			null,
			false
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
			null,
			false
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
			'config disabled, no params' => [ [], $noParams, $noCookies, false ],
			'config disabled, param present' => [ [], $paramSetToOne, $noCookies, false ],
			'config disabled, cookie present' => [ [], $noParams, $cookieSet, false ],

			// config flag set to always, should always enable SUL3 mode.
			'config always, no params' => [ [ 'always' ], $noParams, $noCookies, true ],
			'config always and no query-flag, param set to zero' => [
				[ 'always' ], $paramSetToZero, $noCookies, true
			],

			// Enable SUL3 if config flag set to query-param, and query param is set to 1
			'queryFlag, no params' => [ [ 'query-flag' ], $noParams, $noCookies, false ],
			'queryFlag, param set' => [ [ 'query-flag' ], $paramSetToOne, $noCookies, true ],
			'queryFlag, cookie set' => [ [ 'query-flag' ], $noParams, $cookieSet, false ],

			// config flag set to cookie, only when cookie is present
			'cookie, no params' => [ [ 'cookie' ], $noParams, $noCookies, false ],
			'cookie and no query-flag, params set' => [ [ 'cookie' ], $paramSetToOne, $noCookies, false ],
			'cookie, cookie set' => [ [ 'cookie' ], $noParams, $cookieSet, true ],

			// multiple configs
			'both, no params' => [ [ 'cookie', 'query-flag' ], $noParams, $noCookies, false ],
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
		$fauxRequest = new FauxRequest( [ 'usesul3' => '1' ] );
		/** @var SharedDomainUtils $sut */
		$sut = $this->getMockBuilder( SharedDomainUtils::class )
			->setConstructorArgs( [
				$this->getServiceContainer()->getMainConfig(),
				$this->getServiceContainer()->getTitleFactory(),
				null,
				$isAPiRequest
			] )
			->onlyMethods( [ 'isSharedDomain' ] )
			->getMock();
		$sut->expects( $this->any() )->method( 'isSharedDomain' )->willReturn( $isSharedDomain );
		$this->assertSame( $expected, $sut->isSul3Enabled( $fauxRequest ) );
	}
}
