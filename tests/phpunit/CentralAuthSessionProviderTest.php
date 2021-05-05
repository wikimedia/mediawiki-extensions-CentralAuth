<?php

use MediaWiki\Session\SessionManager;
use MediaWiki\User\UserNameUtils;

/**
 * @covers CentralAuthSessionProvider
 */
class CentralAuthSessionProviderTest extends MediaWikiIntegrationTestCase {

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
		$provider->init(
			new TestLogger(),
			$this->getConfig(),
			$this->createNoOpMock( SessionManager::class ),
			$this->createHookContainer(),
			$this->getServiceContainer()->getUserNameUtils()
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
		$provider->init(
			new TestLogger(),
			$this->getConfig(),
			$this->createNoOpMock( SessionManager::class ),
			$this->createHookContainer(),
			$this->createNoOpMock( UserNameUtils::class )
		);

		$this->assertSame( 200, $provider->getRememberUserDuration() );
	}
}
