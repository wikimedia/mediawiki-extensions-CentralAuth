<?php

namespace MediaWiki\Extension\CentralAuth\Tests\Phpunit\Integration;

use MediaWiki\Config\SiteConfiguration;
use MediaWiki\Extension\CentralAuth\CentralDomainUtils;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxRequest;
use MediaWikiIntegrationTestCase;
use MobileContext;

/**
 * @covers \MediaWiki\Extension\CentralAuth\CentralDomainUtils
 */
class CentralDomainUtilsTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		$conf = new SiteConfiguration();
		$conf->settings = [
			'wgServer' => [
				'foowiki' => 'https://foowiki.example.org',
				'loginwiki' => 'https://login.example.org',
				'enwiktionary' => 'https://en.wiktionary.org',
			],
			'wgArticlePath' => [
				'foowiki' => '/wiki/$1',
				'loginwiki' => '/wiki/$1',
				'enwiktionary' => '/wiki/$1',
			],
		];
		$conf->suffixes = [ 'wiki', 'wiktionary' ];
		$this->setMwGlobals( 'wgConf', $conf );
		$this->overrideConfigValues( [
			MainConfigNames::LocalDatabases => [ 'foowiki', 'loginwiki', 'enwiktionary' ],
			MainConfigNames::CanonicalServer => 'https://foowiki.example.org',
			MainConfigNames::DBname => 'foowiki',
			MainConfigNames::DBprefix => null,
			CAMainConfigNames::CentralAuthLoginWiki => 'loginwiki',
			CAMainConfigNames::CentralAuthSharedDomainCallback
				=> static fn ( $wikiId ) => "https://auth.example.org/$wikiId",
		] );
	}

	private function getCentralDomainUtils(): CentralDomainUtils {
		$services = $this->getServiceContainer();
		return new CentralDomainUtils(
			$services->getMainConfig(),
			$services->getTitleFactory(),
			$services->get( 'CentralAuth.SharedDomainUtils' )
		);
	}

	/**
	 * @dataProvider provideGetUrlData
	 */
	public function testGetUrl(
		bool $isSul3Enabled,
		string $wikiId,
		string $page,
		array $params,
		string $expectedUrl
	) {
		$this->markTestSkippedIfExtensionNotLoaded( 'MobileFrontend' );
		$this->overrideConfigValues( [
			MainConfigNames::LanguageCode => 'de',
			CAMainConfigNames::CentralAuthEnableSul3 => $isSul3Enabled,
		] );

		// Test that test actually tests not localizing URLs.
		$specialPageFactory = $this->getServiceContainer()->getSpecialPageFactory();
		$this->assertSame( 'Benutzerkonto_anlegen', $specialPageFactory->getLocalNameFor( 'CreateAccount' ) );

		$centralDomainUtils = $this->getCentralDomainUtils();
		$actualUrl = $centralDomainUtils->getUrl( $wikiId, $page, new FauxRequest(), $params );
		$this->assertSame( $expectedUrl, $actualUrl );
	}

	public static function provideGetUrlData() {
		$centralDomain = CentralDomainUtils::CENTRAL_DOMAIN_ID;
		$autologinDomain = CentralDomainUtils::AUTOLOGIN_CENTRAL_DOMAIN_ID;
		$sul2Domain = CentralDomainUtils::SUL2_CENTRAL_DOMAIN_ID;
		$sul3Domain = CentralDomainUtils::SUL3_CENTRAL_DOMAIN_ID;
		return [
			// $isSul3Enabled, $wikiId, $page, $params, $expectedUrl
			[ false, $centralDomain, 'Main Page', [],
				'https://login.example.org/wiki/Main_Page?useformat=desktop' ],
			[ false, $centralDomain, 'Special:UserLogin', [ 'foo' => 'bar' ],
				'https://login.example.org/wiki/Special:UserLogin?useformat=desktop&foo=bar' ],
			[ false, $autologinDomain, 'Special:CentralAutoLogin/start', [ 'foo' => 'bar' ],
				'https://login.example.org/wiki/Special:CentralAutoLogin/start?useformat=desktop&foo=bar' ],
			[ false, $sul2Domain, 'Special:CentralAutoLogin/start', [ 'foo' => 'bar' ],
				'https://login.example.org/wiki/Special:CentralAutoLogin/start?useformat=desktop&foo=bar' ],
			[ false, $sul3Domain, 'Special:CentralAutoLogin/start', [ 'foo' => 'bar' ],
				'https://auth.example.org/loginwiki/wiki/Special:CentralAutoLogin/start?useformat=desktop&foo=bar' ],
			[ false, 'enwiktionary', 'Special:UserLogin', [ 'foo' => 'bar' ],
				'https://en.wiktionary.org/wiki/Special:UserLogin?useformat=desktop&foo=bar' ],
			[ true, $centralDomain, 'Special:UserLogin', [ 'foo' => 'bar' ],
				'https://auth.example.org/foowiki/wiki/Spezial:UserLogin?useformat=desktop&foo=bar' ],
			[ true, $autologinDomain, 'Special:CentralAutoLogin/start', [ 'foo' => 'bar' ],
				'https://auth.example.org/loginwiki/wiki/Special:CentralAutoLogin/start?useformat=desktop&foo=bar' ],
			[ true, $sul2Domain, 'Special:CentralAutoLogin/start', [ 'foo' => 'bar' ],
				'https://login.example.org/wiki/Special:CentralAutoLogin/start?useformat=desktop&foo=bar' ],
			[ true, $sul3Domain, 'Special:CentralAutoLogin/start', [ 'foo' => 'bar' ],
				'https://auth.example.org/loginwiki/wiki/Special:CentralAutoLogin/start?useformat=desktop&foo=bar' ],
			[ true, 'enwiktionary', 'Special:UserLogin', [ 'foo' => 'bar' ],
				'https://en.wiktionary.org/wiki/Special:UserLogin?useformat=desktop&foo=bar' ],
		];
	}

	public function testGetUrl_mobile() {
		$this->markTestSkippedIfExtensionNotLoaded( 'MobileFrontend' );
		$this->overrideConfigValue( CAMainConfigNames::CentralAuthEnableSul3, true );
		$this->setRequest( new FauxRequest( [ 'useformat' => 'mobile' ] ) );
		MobileContext::resetInstanceForTesting();

		$centralDomainUtils = $this->getCentralDomainUtils();
		$actualUrl = $centralDomainUtils->getUrl( CentralDomainUtils::CENTRAL_DOMAIN_ID,
			'Special:CentralAutoLogin', new FauxRequest(), [] );
		$expectedUrl = 'https://auth.example.org/foowiki/wiki/Special:CentralAutoLogin?useformat=mobile';
		$this->assertSame( $expectedUrl, $actualUrl );
	}

	public function testIsCentralDomain(): void {
		// SUL2 setup for central domain.
		$this->overrideConfigValues( [
			MainConfigNames::DBname => 'loginwiki',
			CAMainConfigNames::CentralAuthSharedDomainCallback => false,
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
