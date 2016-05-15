<?php

class CentralAuthSessionProviderTest extends PHPUnit_Framework_TestCase {
	/**
	 * @dataProvider provideSuggestLoginUsername
	 */
	public function testSuggestLoginUsername( $cookies, $expectedUsername ) {
		$provider = new CentralAuthSessionProvider( [
			'priority' => 42,
			'cookieOptions' => [ 'prefix' => '' ],
			'centralCookieOptions' => [ 'prefix' => '' ],
		] );
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
		$config = new HashConfig( [
			'CookieExpiration' => 100,
			'ExtendedLoginCookieExpiration' => 200,
			'ExtendedLoginCookies' => [ 'User', 'UserID', 'Token' ],
			// these are needed by CookieSessionProvider::getConfig
			'SessionName' => null,
			'CookiePrefix' => '',
			'CookiePath' => '',
			'CookieDomain' => 'example.com',
			'CookieSecure' => true,
			'CookieHttpOnly' => true,
		] );
		$provider = new CentralAuthSessionProvider( [ 'priority' => 42 ] );
		$provider->setConfig( $config );

		$this->assertSame( 200, $provider->getRememberUserDuration() );

		$config->set( 'ExtendedLoginCookies', [ 'UserID', 'Token' ] );

		$this->assertSame( 100, $provider->getRememberUserDuration() );

		$config->set( 'ExtendedLoginCookies', [ 'User', 'Token' ] );

		$this->assertSame( 100, $provider->getRememberUserDuration() );
	}
}
