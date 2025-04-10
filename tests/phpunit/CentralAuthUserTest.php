<?php

use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Password\Password;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\User\User;
use MediaWiki\User\UserGroupMembership;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Only for tests that do not require the database to be
 * set up.
 *
 * @covers MediaWiki\Extension\CentralAuth\User\CentralAuthUser
 * @group CentralAuth
 */
class CentralAuthUserTest extends MediaWikiIntegrationTestCase {

	use TempUserTestTrait;

	public function testGetInstance() {
		$cache = CentralAuthServices::getUserCache();

		$user = User::newFromName( 'FooBarBaz' );
		$cache->delete( $user->getName() );
		$caUser = CentralAuthUser::getInstance( $user );
		$this->assertInstanceOf( CentralAuthUser::class, $caUser );
		$this->assertSame( $user->getName(), $caUser->getName() );
		$this->assertSame( $cache->get( $user->getName() ), $caUser );
		// Now test it just reads from the cache, no matter what
		$user2 = User::newFromName( 'BazBarFoo' );
		$centraluser2 = new CentralAuthUser( 'BazBarFoo' );
		$cache->set( $centraluser2 );
		$this->assertSame( $centraluser2, CentralAuthUser::getInstance( $user2 ) );
	}

	public function testGetInstanceByNameNonCanonicalForm() {
		$cache = CentralAuthServices::getUserCache();
		$userFactory = $this->getServiceContainer()->getUserFactory();

		// Username in non-canonical form
		$username = 'Example_user';
		$canonicalUsername = $this->getServiceContainer()->getUserNameUtils()->getCanonical( $username );
		$cache->delete( $username );
		$caUser = CentralAuthUser::getInstance( $userFactory->newFromName( $username ) );
		$this->assertInstanceOf( CentralAuthUser::class, $caUser );
		// Assert that the username is in canonical form
		$this->assertSame( 'Example user', $caUser->getName() );
		$this->assertSame( $cache->get( $canonicalUsername ), $caUser );
	}

	public function testNewUnattached() {
		$ca = CentralAuthUser::newUnattached( 'FooBar' );
		$this->assertInstanceOf( CentralAuthUser::class, $ca );
		$this->assertSame( 'FooBar', $ca->getName() );
		$this->assertFalse( $ca->isAttached() );
	}

	public function testAdminLockForTemporaryAccount() {
		$this->enableAutoCreateTempUser();
		$ca = CentralAuthUser::newUnattached( '~2024-1' );
		$this->assertInstanceOf( CentralAuthUser::class, $ca );
		$this->assertSame( '~2024-1', $ca->getName() );
		$this->assertStatusError( 'centralauth-admin-cannot-lock-temporary-account', $ca->adminLock() );
	}

	/**
	 * @dataProvider provideGetHomeWiki
	 */
	public function testGetHomeWiki( $attached, $expected ) {
		/** @var MockObject|CentralAuthUser $ca */
		$ca = $this->getMockBuilder( CentralAuthUser::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'queryAttachedBasic', 'queryAttached', 'loadState' ] )
			->getMock();

		$ca->method( 'queryAttachedBasic' )->willReturn(
			array_map( static function ( $data ) {
				return array_intersect_key( $data, [ 'attachedMethod' => true ] );
			}, $attached )
		);
		$ca->method( 'queryAttached' )
			->willReturn( $attached );
		$this->assertSame( $expected, $ca->getHomeWiki() );
	}

	public static function provideGetHomeWiki() {
		return [
			[
				[],
				null
			],
			[
				[
					'foowiki' => [ 'attachedMethod' => 'new' ]
				],
				'foowiki'
			],
			[
				[
					'foowiki' => [ 'attachedMethod' => 'primary' ]
				],
				'foowiki'
			],
			[
				[
					'foowiki' => [ 'attachedMethod' => 'password' ],
					'bazwiki' => [ 'attachedMethod' => 'new' ]
				],
				'bazwiki'
			],
			[
				[
					'foowiki' => [ 'attachedMethod' => 'password' ]
				],
				'foowiki'
			],
			[
				[
					'foowiki' => [ 'attachedMethod' => 'primary', 'editCount' => 4 ],
					'barwiki' => [ 'attachedMethod' => 'password', 'editCount' => 6 ]
				],
				// Primary account "wins" over edit count
				'foowiki'
			],
			[
				[
					'foowiki' => [ 'attachedMethod' => 'password', 'editCount' => 4 ],
					'barwiki' => [ 'attachedMethod' => 'password', 'editCount' => 6 ]
				],
				'barwiki'
			]
		];
	}

	/**
	 * @dataProvider provideChooseHomeWiki
	 */
	public function testChooseHomeWiki( $expected, $attached ) {
		$ca = new CentralAuthUser( 'FooBar' );
		$this->assertSame( $expected, $ca->chooseHomeWiki( $attached ) );
	}

	public static function provideChooseHomeWiki() {
		return [
			// Groups win
			[ 'barwiki', [
				'foowiki' => [
					'groupMemberships' => [
						'sysop' => new UserGroupMembership( 123, 'sysop' ),
					],
				],
				'barwiki' => [
					'groupMemberships' => [
						'checkuser' => new UserGroupMembership( 321, 'checkuser' ),
					],
				],
			] ],
			// Groups tie, editcount wins
			[ 'barwiki', [
				'foowiki' => [
					'groupMemberships' => [
						'sysop' => new UserGroupMembership( 123, 'sysop' ),
						'checkuser' => new UserGroupMembership( 123, 'checkuser' ),
					],
					'editCount' => '100',
				],
				'barwiki' => [
					'groupMemberships' => [
						'checkuser' => new UserGroupMembership( 321, 'checkuser' ),
					],
					'editCount' => '100000000',
				],
			] ],
			// No groups, Editcount wins
			[ 'barwiki', [
				'foowiki' => [
					'groupMemberships' => [],
					'editCount' => '100'
				],
				'barwiki' => [
					'groupMemberships' => [],
					'editCount' => '1000'
				],
			] ],
			// Edit count ties, super old registration (null) wins
			[ 'foowiki', [
				'foowiki' => [
					'groupMemberships' => [],
					'editCount' => '5',
					'registration' => null
				],
				'barwiki' => [
					'groupMemberships' => [],
					'editCount' => '5',
					'registration' => '20150305220251',
				],
			] ],
			// Edit count ties, registration wins
			[ 'foowiki', [
				'foowiki' => [
					'groupMemberships' => [],
					'editCount' => '5',
					'registration' => '20100305220251'
				],
				'barwiki' => [
					'groupMemberships' => [],
					'editCount' => '5',
					'registration' => '20150305220251',
				],
			] ]

		];
	}

	/**
	 * @dataProvider provideGetPasswordFromString
	 */
	public function testGetPasswordFromString( $pass, $salt, $type ) {
		$class = new ReflectionClass( CentralAuthUser::class );
		$method = $class->getMethod( 'getPasswordFromString' );
		$method->setAccessible( true );
		$ca = new CentralAuthUser( 'DoesNotExist' );
		$password = $method->invokeArgs( $ca, [ $pass, $salt ] );
		$this->assertInstanceOf( Password::class, $password );
		$this->assertInstanceOf( $type, $password );
	}

	public static function provideGetPasswordFromString() {
		return [
			[
				':pbkdf2:sha256:10000:128:Oin6/F737E41pY7dza46Dw==:f6LNAySaUdEnjI2omuj+CX1aPDnt5b' .
					'zgZcdLsEcLWqF7vG0CcMyviqWaq8smXCj2HBY0sV/w2kxpsTXXOgUrJJTEjuXmEsxHTtpMO4fCfZ' .
					'5nb3a1kCYA44owCzKu96i8I6VrmGYu3waxmVAzlXld3bNIxrhGUjra/Y0TmWOe1q0=',
				'',
				'AbstractPbkdf2Password'
			],
			[
				':B:6540e6ad:b02a3700be1eec9488a46b042a831646',
				'',
				'MWSaltedPassword'
			],
			[
				'b02a3700be1eec9488a46b042a831646',
				'6540e6ad',
				'MWSaltedPassword',
			],
		];
	}

	/**
	 * @dataProvider provideOnPasswordPoliciesForUser
	 */
	public function testGetLocalGroups( $attached, $expected ) {
		/** @var MockObject|CentralAuthUser $ca */
		$ca = $this->getMockBuilder( CentralAuthUser::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'queryAttached' ] )
			->getMock();
		$ca->method( 'queryAttached' )
			->willReturn( $attached );

		$this->assertSame( $expected, $ca->getLocalGroups() );
	}

	public static function provideOnPasswordPoliciesForUser() {
		return [
			[
				[
					'enwiki' => [
						'wiki' => 'enwiki',
						'attachedTimestamp' => '20130627183725',
						'attachedMethod' => 'login',
						'id' => '1234',
						'groupMemberships' => [],
					],
					'commonswiki' => [
						'wiki' => 'commonswiki',
						'attachedTimestamp' => '20130627183726',
						'attachedMethod' => 'login',
						'id' => '4321',
						'groupMemberships' => [],
					],
				],
				[]
			],
			[
				[
					'enwiki' => [
						'wiki' => 'enwiki',
						'attachedTimestamp' => '20130627183727',
						'attachedMethod' => 'login',
						'id' => '12345',
						'groupMemberships' => [
							'sysop' => new UserGroupMembership( 12345, 'sysop' ),
						],
					],
					'commonswiki' => [
						'wiki' => 'commonswiki',
						'attachedTimestamp' => '20130627183728',
						'attachedMethod' => 'login',
						'id' => '54321',
						'groupMemberships' => [
							'sysop' => new UserGroupMembership( 54321, 'sysop' ),
						],
					],
				],
				[ 'sysop' ]
			],
			[
				[
					'enwiki' => [
						'wiki' => 'enwiki',
						'attachedTimestamp' => '20130627183729',
						'attachedMethod' => 'login',
						'id' => '123456',
						'groupMemberships' => [
							'bureaucrat' => new UserGroupMembership( 123456, 'bureaucrat' ),
						],
					],
					'commonswiki' => [
						'wiki' => 'commonswiki',
						'attachedTimestamp' => '20130627183720',
						'attachedMethod' => 'login',
						'id' => '654321',
						'groupMemberships' => [],
					],
				],
				[ 'bureaucrat' ]
			],
		];
	}
}
