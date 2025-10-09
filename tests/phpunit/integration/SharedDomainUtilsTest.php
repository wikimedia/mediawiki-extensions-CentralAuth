<?php

namespace MediaWiki\Extension\CentralAuth\Tests\Phpunit\Integration\Hooks\Handlers;

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
			MainConfigNames::Server => '//sso.wikimedia.org',
			MainConfigNames::CanonicalServer => 'https://sso.wikimedia.org',
			'CentralAuthSsoUrlPrefix' => 'https://sso.wikimedia.org',
		] );
		$services = $this->getServiceContainer();
		$sharedDomainUtils = new SharedDomainUtils(
			$services->getMainConfig(),
			$services->getTitleFactory(),
			null
		);
		$wrappedHandler = TestingAccessWrapper::newFromObject( $sharedDomainUtils );
		$this->assertTrue( $wrappedHandler->isSharedDomain() );
		$this->assertTrue( $wrappedHandler->isSharedDomain() );

		$this->overrideConfigValues( [
			MainConfigNames::Server => '//en.wikipedia.org',
			MainConfigNames::CanonicalServer => 'https://en.wikipedia.org',
			'CentralAuthSsoUrlPrefix' => 'https://sso.wikimedia.org',
		] );
		$services = $this->getServiceContainer();
		$sharedDomainUtils = new SharedDomainUtils(
			$services->getMainConfig(),
			$services->getTitleFactory(),
			null
		);
		$wrappedHandler = TestingAccessWrapper::newFromObject( $sharedDomainUtils );
		$this->assertFalse( $wrappedHandler->isSharedDomain() );
		$this->assertFalse( $wrappedHandler->isSharedDomain() );
	}

	public static function provideTestData() {
		$noCookies = [];
		$noParams = [];
		$paramSet = [ 'usesul3' => '1' ];
		$cookieSet = [ 'sul3OptIn' => '1' ];

		return [
			'config disabled, no params' => [ null, $noParams, $noCookies, false ],
			'config disabled, param present' => [ null, $paramSet, $noCookies, false ],
			'config disabled, cookie present' => [ null, $noParams, $cookieSet, false ],

			// config flag set to always, should always log
			'config always, no params' => [ [ 'always' ], $noParams, $noCookies, true ],
			'config always, param zero' => [ [ 'always' ], [ 'usesul3' => 0 ], $noCookies, true ],

			// config flag set to url, only if param is set
			'queryFlag, no params' => [ [ 'query-flag' ], $noParams, $noCookies, false ],
			'queryFlag, param set' => [ [ 'query-flag' ], $paramSet, $noCookies, true ],
			'queryFlag, cookie set' => [ 'query-flag', $noParams, $cookieSet, false ],

			// config flag set to cookie, only when cookie is present
			'cookie, no params' => [ [ 'cookie' ], $noParams, $noCookies, false ],
			'cookie, params set' => [ [ 'cookie' ], $paramSet, $noCookies, false ],
			'cookie, cookie set' => [ [ 'cookie' ], $noParams, $cookieSet, true ],

			// multiple configs
			'both, no params' => [ [ 'cookie', 'query-flag' ], $noParams, $noCookies, false ],
			'both, param set' => [ [ 'cookie', 'query-flag' ], $paramSet, $noCookies, true ],
			'both, cookie set' => [ [ 'cookie', 'query-flag' ], $paramSet, $cookieSet, true ],
		];
	}

	/**
	 * @dataProvider provideTestData
	 * @return void
	 */
	public function testIsSul3Enabled( $configFlag, $requestParams, $cookies, $expected ) {
		$this->overrideConfigValue( 'CentralAuthEnableSul3', $configFlag );
		$fauxRequest = new FauxRequest( $requestParams );
		if ( $cookies ) {
			$fauxRequest->setCookies( $cookies, '' );
		}
		$this->setRequest( $fauxRequest );

		/** @var SharedDomainUtils $sut */
		$sut = $this->getServiceContainer()->get( 'CentralAuth.SharedDomainUtils' );
		$this->assertSame( $expected, $sut->isSul3Enabled( $fauxRequest ) );
	}
}
