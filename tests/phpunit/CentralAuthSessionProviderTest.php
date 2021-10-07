<?php

use MediaWiki\Session\SessionProviderTestTrait;

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
			'CookieExpiration' => 100,
			'ExtendedLoginCookieExpiration' => 200,
			// these are needed by CookieSessionProvider::getConfig
			'SessionName' => null,
			'CookiePrefix' => '',
			'CookiePath' => '',
			'CookieDomain' => 'example.com',
			'CookieSecure' => true,
			'CookieHttpOnly' => true,
			'CookieSameSite' => '',
		] );
	}

	/**
	 * @dataProvider provideSuggestLoginUsername
	 */
	public function testSuggestLoginUsername( $cookies, $expectedUsername ) {
		$provider = new CentralAuthSessionProvider( [
			'priority' => 42,
			'cookieOptions' => [ 'prefix' => '' ],
			'centralCookieOptions' => [ 'prefix' => '' ],
		] );
		$this->initProvider(
			$provider, null, $this->getConfig(), null, null, $this->getServiceContainer()->getUserNameUtils()
		);
		$request = new FauxRequest();
		$request->setCookies( $cookies, '' );
		$this->assertSame( $expectedUsername, $provider->suggestLoginUsername( $request ) );
	}

	public function provideSuggestLoginUsername() {
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
		$provider = new CentralAuthSessionProvider( [ 'priority' => 42 ] );
		$this->initProvider( $provider, null, $this->getConfig() );

		$this->assertSame( 200, $provider->getRememberUserDuration() );
	}
}
