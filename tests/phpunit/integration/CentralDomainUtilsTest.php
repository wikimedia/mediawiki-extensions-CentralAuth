<?php

namespace MediaWiki\Extension\CentralAuth\Tests\Phpunit\Integration;

use Generator;
use MediaWiki\Config\SiteConfiguration;
use MediaWiki\Extension\CentralAuth\CentralDomainUtils;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\Site\TestSites;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\CentralAuth\CentralDomainUtils
 * @group Database
 */
class CentralDomainUtilsTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		$conf = new SiteConfiguration();
		$conf->settings = [
			'wgServer' => [
				'foowiki' => 'http://foowiki.example.org',
			],
			'wgArticlePath' => [
				'foowiki' => '/w/$1',
			],
		];
		$conf->suffixes = [ 'wiki' ];
		$this->setMwGlobals( 'wgConf', $conf );
		$this->overrideConfigValues( [
			MainConfigNames::LocalDatabases => [ 'foowiki' ],
			MainConfigNames::CanonicalServer => '//foowiki.example.org',
			MainConfigNames::DBname => 'foowiki',
			MainConfigNames::DBprefix => ''
		] );

		TestSites::insertIntoDb();
	}

	private function getCentralDomainUtils(): CentralDomainUtils {
		$services = $this->getServiceContainer();
		return new CentralDomainUtils(
			$services->getMainConfig(),
			$services->getTitleFactory(),
			$services->get( 'CentralAuth.SharedDomainUtils' )
		);
	}

	public function testDefaultGetUrlForSul3Enabled(): void {
		$wikiId = CentralDomainUtils::CENTRAL_DOMAIN_ID;
		$page = 'Main_Page';
		$params = [];

		$this->overrideConfigValues( [
			CAMainConfigNames::CentralAuthEnableSul3 => [ 'always' ],
			CAMainConfigNames::CentralAuthSharedDomainPrefix => 'https://auth0.wikimedia.org',
		] );

		$centralDomainUtils = $this->getCentralDomainUtils();

		// Let's make sure the URL points to the right page.
		$this->assertStringContainsString(
			$page, $centralDomainUtils->getUrl( $wikiId, $page, new FauxRequest(), $params )
		);

		// The default behaviour is that we're on desktop.
		$this->assertStringContainsString(
			'useformat=desktop', $centralDomainUtils->getUrl( $wikiId, $page, new FauxRequest(), $params )
		);

		// Check that the URL has the shared domain prefix.
		$this->assertStringContainsString(
			'https://auth0.wikimedia.org',
			$centralDomainUtils->getUrl( $wikiId, $page, new FauxRequest(), $params )
		);
	}

	private function applyWikiConfigOverrides( string $wikiId, array $sul3Enabled ): void {
		$this->overrideConfigValues( [
			MainConfigNames::DBname => $wikiId,
			MainConfigNames::DBprefix => null,
			MainConfigNames::Server => "//$wikiId.wikimedia.org",
			MainConfigNames::CanonicalServer => "https://$wikiId.wikimedia.org",
			CAMainConfigNames::CentralAuthSharedDomainPrefix => 'https://auth0.wikimedia.org',
			CAMainConfigNames::CentralAuthEnableSul3 => $sul3Enabled,
		] );
	}

	public static function provideGetUrlDataForCentralDomain(): Generator {
		yield 'central domain' => [
			'wikiId' => CentralDomainUtils::CENTRAL_DOMAIN_ID,
			'page' => 'Main_Page',
			'params' => [],
			'sul3Enabled' => [ 'always' ],
		];

		yield 'central domain on Bar page with mobile param' => [
			'wikiId' => CentralDomainUtils::CENTRAL_DOMAIN_ID,
			'page' => 'Bar',
			'params' => [ 'useformat' => 'mobile' ],
			'sul3Enabled' => [ 'always' ],
		];

		yield 'central domain on Bar page with desktop param' => [
			'wikiId' => CentralDomainUtils::CENTRAL_DOMAIN_ID,
			'page' => 'Bar',
			'params' => [ 'useformat' => 'desktop' ],
			'sul3Enabled' => [ 'always' ],
		];

		yield 'central domain with special page Foo with desktop param' => [
			'wikiId' => CentralDomainUtils::CENTRAL_DOMAIN_ID,
			'page' => 'Special:Foo',
			'params' => [ 'useformat' => 'desktop' ],
			'sul3Enabled' => [ 'always' ],
		];
	}

	/**
	 * @dataProvider provideGetUrlDataForCentralDomain
	 */
	public function testGetUrlForCentralDomain( $wikiId, $page, $params, $sul3Enabled ): void {
		$this->applyWikiConfigOverrides( $wikiId, $sul3Enabled );
		$centralDomainUtils = $this->getCentralDomainUtils();

		// Check that the URL has the shared domain prefix.
		$this->assertStringContainsString(
			'auth0.wikimedia.org',
			$centralDomainUtils->getUrl( $wikiId, $page, new FauxRequest(), $params )
		);

		// Let's make sure the URL points to the right page.
		$this->assertStringContainsString(
			$page, $centralDomainUtils->getUrl( $wikiId, $page, new FauxRequest(), $params )
		);

		// The default behaviour is that we're on the correct device.
		$this->assertStringContainsString(
			$params['useformat'] ?? 'desktop', $centralDomainUtils->getUrl( $wikiId, $page, new FauxRequest(), $params )
		);
	}

	public static function provideGetUrlDataForNonCentralDomain(): Generator {
		yield 'non-central domain' => [
			'wikiId' => 'foowiki',
			'page' => 'Main_Page',
			'params' => [],
			'sul3Enabled' => [ 'always' ],
		];

		yield 'non-central domain on Bar page with mobile param' => [
			'wikiId' => 'foowiki',
			'page' => 'Bar',
			'params' => [ 'useformat' => 'mobile' ],
			'sul3Enabled' => [ 'always' ],
		];

		yield 'non-central domain on Bar page with desktop param' => [
			'wikiId' => 'foowiki',
			'page' => 'Bar',
			'params' => [ 'useformat' => 'desktop' ],
			'sul3Enabled' => [ 'always' ],
		];

		yield 'non-central domain with special page Foo with desktop param' => [
			'wikiId' => 'foowiki',
			'page' => 'Special:Foo',
			'params' => [ 'useformat' => 'desktop' ],
			'sul3Enabled' => [ 'always' ],
		];
	}

	/**
	 * @dataProvider provideGetUrlDataForNonCentralDomain
	 */
	public function testGetUrlForNonCentralDomain( $wikiId, $page, $params, $sul3Enabled ): void {
		$this->applyWikiConfigOverrides( $wikiId, $sul3Enabled );

		$centralDomainUtils = $this->getCentralDomainUtils();

		// Check that the URL has the shared domain prefix.
		$this->assertStringNotContainsString(
			'auth0.wikimedia.org',
			$centralDomainUtils->getUrl( $wikiId, $page, new FauxRequest(), $params )
		);

		// Let's make sure the URL points to the right page.
		$this->assertStringContainsString(
			$page, $centralDomainUtils->getUrl( $wikiId, $page, new FauxRequest(), $params )
		);

		// The default behaviour is that we're on the correct device.
		$this->assertStringContainsString(
			$params['useformat'] ?? 'desktop', $centralDomainUtils->getUrl( $wikiId, $page, new FauxRequest(), $params )
		);
	}

	public function testIsCentralDomain(): void {
		// SUL2 setup for central domain.
		$this->overrideConfigValues( [
			MainConfigNames::DBname => 'loginwiki',
			MainConfigNames::DBprefix => null,
			CAMainConfigNames::CentralAuthLoginWiki => 'loginwiki',
			CAMainConfigNames::CentralAuthEnableSul3 => [ 'query-flag' ],
		] );
		$centralDomainUtils = $this->getCentralDomainUtils();

		$this->assertTrue( $centralDomainUtils->isCentralDomain( new FauxRequest() ) );

		$centralDomainUtils = $this->getCentralDomainUtils();
		// Trigger SUL2 with a query flag in the URL instead.
		$this->assertTrue( $centralDomainUtils->isCentralDomain( new FauxRequest( [ 'usesul3' => '0' ] ) ) );
	}

	// TODO: testCentralDomainExists()
	// TODO: testGetCentralDomainHost()
	// TODO: testWithFallbackLoginWikiId()

}
