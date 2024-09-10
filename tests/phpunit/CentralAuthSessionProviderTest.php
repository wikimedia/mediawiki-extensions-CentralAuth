<?php

use MediaWiki\Config\HashConfig;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\Session\SessionProviderTestTrait;

/**
 * @covers CentralAuthSessionProvider
 */
class CentralAuthSessionProviderTest extends MediaWikiIntegrationTestCase {
	use SessionProviderTestTrait;

	private function getConfig() {
		return new HashConfig( [
			'CentralAuthCookies' => true,
			'CentralAuthCookiePrefix' => 'central_',
			'CentralAuthCookiePath' => '/',
			'CentralAuthCookieDomain' => '',
			MainConfigNames::CookieExpiration => 100,
			MainConfigNames::ExtendedLoginCookieExpiration => 200,
			// these are needed by CookieSessionProvider::getConfig
			MainConfigNames::SessionName => null,
			MainConfigNames::CookiePrefix => '',
			MainConfigNames::CookiePath => '',
			MainConfigNames::CookieDomain => 'example.com',
			MainConfigNames::CookieSecure => true,
			MainConfigNames::CookieHttpOnly => true,
			MainConfigNames::CookieSameSite => '',
		] );
	}

	/**
	 * @dataProvider provideSuggestLoginUsername
	 */
	public function testSuggestLoginUsername( $cookies, $expectedUsername ) {
		$services = $this->getServiceContainer();
		$provider = new CentralAuthSessionProvider(
			$services->getTempUserConfig(),
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
			$services->getTempUserConfig(),
			$services->getUserIdentityLookup(),
			$services->get( 'CentralAuth.CentralAuthSessionManager' ),
			[ 'priority' => 42 ]
		);
		$this->initProvider( $provider, null, $this->getConfig() );

		$this->assertSame( 200, $provider->getRememberUserDuration() );
	}
}
