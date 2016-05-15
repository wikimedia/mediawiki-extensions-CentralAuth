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
}
