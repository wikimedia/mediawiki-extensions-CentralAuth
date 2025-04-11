<?php

namespace MediaWiki\Extension\CentralAuth\Tests\Phpunit\Integration;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Extension\CentralAuth\SharedDomainUtils;
use MediaWiki\HookContainer\HookRunner;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxRequest;
use MediaWiki\WikiMap\WikiMap;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\CentralAuth\SharedDomainUtils
 * @group Database
 */
class SharedDomainUtilsTest extends MediaWikiIntegrationTestCase {

	public function testGetSharedDomainPrefix() {
		$getSharedDomainUtils = function ( $sharedDomainCallback ) {
			$services = $this->getServiceContainer();
			return new SharedDomainUtils(
				new HashConfig( [
					CAMainConfigNames::CentralAuthSharedDomainCallback => $sharedDomainCallback
				] ),
				$services->getSpecialPageFactory(),
				new HookRunner( $services->getHookContainer() ),
				null,
				false,
				$services->getTempUserConfig(),
			);
		};

		$currentWikiId = WikiMap::getCurrentWikiId();
		$sharedDomainUtils = $getSharedDomainUtils( static fn ( $wikiId ) => "https://auth.wikimedia.org/$wikiId" );
		$this->assertSame( "https://auth.wikimedia.org/$currentWikiId", $sharedDomainUtils->getSharedDomainPrefix() );
		$this->assertSame( 'https://auth.wikimedia.org/foowiki',
			$sharedDomainUtils->getSharedDomainPrefix( 'foowiki' ) );

		$sharedDomainUtils = $getSharedDomainUtils( false );
		$this->assertNull( $sharedDomainUtils->getSharedDomainPrefix() );
		$this->assertNull( $sharedDomainUtils->getSharedDomainPrefix( 'foowiki' ) );
	}

	public function testIsSharedDomain() {
		$this->overrideConfigValues( [
			MainConfigNames::Server => '//auth.wikimedia.org',
			MainConfigNames::CanonicalServer => 'https://auth.wikimedia.org',
			CAMainConfigNames::CentralAuthSharedDomainCallback => static fn () => 'https://auth.wikimedia.org/foowiki',
		] );
		$services = $this->getServiceContainer();
		$sharedDomainUtils = new SharedDomainUtils(
			$services->getMainConfig(),
			$services->getSpecialPageFactory(),
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
			CAMainConfigNames::CentralAuthSharedDomainCallback => static fn () => 'https://auth.wikimedia.org/foowiki',
		] );
		$services = $this->getServiceContainer();
		$sharedDomainUtils = new SharedDomainUtils(
			$services->getMainConfig(),
			$services->getSpecialPageFactory(),
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
		$noParams = [];
		$paramSetToZero = [ 'usesul3' => '0' ];
		$paramSetToOne = [ 'usesul3' => '1' ];

		return [
			'config disabled, no params' =>
				[ false, $noParams, false ],
			'config enabled, no params' =>
				[ true, $noParams, true ],
			'config disabled, param set' =>
				[ false, $paramSetToOne, true ],
			'config disabled, param set to zero' =>
				[ false, $paramSetToZero, false ],
			'config enabled, param set to zero' =>
				[ true, $paramSetToZero, false ],
		];
	}

	/**
	 * @dataProvider provideIsSul3Enabled
	 * @return void
	 */
	public function testIsSul3Enabled( $configFlag, $requestParams, $expected ) {
		$this->overrideConfigValues( [
			CAMainConfigNames::CentralAuthSharedDomainCallback => static fn () => 'https://auth.wikimedia.org/foowiki',
			CAMainConfigNames::CentralAuthEnableSul3 => $configFlag,
		] );

		$fauxRequest = new FauxRequest( $requestParams );
		$this->setRequest( $fauxRequest );

		/** @var SharedDomainUtils $sut */
		$sut = $this->getServiceContainer()->get( 'CentralAuth.SharedDomainUtils' );
		$actual = $sut->isSul3Enabled( $fauxRequest );
		$this->assertSame( $expected, $actual );
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
	public function testIsSul3Enabled_Api( $isSharedDomain, $isApiRequest, $expected ) {
		$this->overrideConfigValues( [
			CAMainConfigNames::CentralAuthSharedDomainCallback => static fn () => 'https://auth.wikimedia.org/foowiki',
			CAMainConfigNames::CentralAuthEnableSul3 => true,
		] );

		$fauxRequest = new FauxRequest();
		/** @var SharedDomainUtils $sut */
		$sut = $this->getMockBuilder( SharedDomainUtils::class )
			->setConstructorArgs( [
				$this->getServiceContainer()->getMainConfig(),
				$this->getServiceContainer()->getSpecialPageFactory(),
				new HookRunner( $this->getServiceContainer()->getHookContainer() ),
				null,
				$isApiRequest,
				$this->getServiceContainer()->getTempUserConfig(),
			] )
			->onlyMethods( [ 'isSharedDomain' ] )
			->getMock();
		$sut->expects( $this->any() )->method( 'isSharedDomain' )->willReturn( $isSharedDomain );
		$actual = $sut->isSul3Enabled( $fauxRequest );
		$this->assertSame( $expected, $actual );
	}
}
