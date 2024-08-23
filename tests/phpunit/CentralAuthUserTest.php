<?php

use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Password\Password;
use MediaWiki\User\User;
use MediaWiki\User\UserGroupMembership;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\WikiMap\WikiMap;
use PHPUnit\Framework\MockObject\MockObject;
use Wikimedia\TestingAccessWrapper;

/**
 * Only for tests that do not require the database to be
 * set up.
 *
 * @covers MediaWiki\Extension\CentralAuth\User\CentralAuthUser
 * @group CentralAuth
 * @group Database
 */
class CentralAuthUserTest extends MediaWikiIntegrationTestCase {

	public function testGetInstance() {
		$cache = TestingAccessWrapper::newFromClass( CentralAuthUser::class )->getUserCache();

		$user = User::newFromName( 'FooBarBaz' );
		$cache->clear( $user->getName() );
		$caUser = CentralAuthUser::getInstance( $user );
		$this->assertInstanceOf( CentralAuthUser::class, $caUser );
		$this->assertSame( $user->getName(), $caUser->getName() );
		$this->assertSame( $cache->get( $user->getName() ), $caUser );
		// Now test it just reads from the cache, no matter what
		$user2 = User::newFromName( 'BazBarFoo' );
		$centraluser2 = new CentralAuthUser( 'BazBarFoo' );
		$cache->set( $user2->getName(), $centraluser2 );
		$this->assertSame( $centraluser2, CentralAuthUser::getInstance( $user2 ) );
	}

	public function testGetInstanceByNameNonCanonicalForm() {
		$cache = TestingAccessWrapper::newFromClass( CentralAuthUser::class )->getUserCache();
		$userFactory = $this->getServiceContainer()->getUserFactory();

		// Username in non-canonical form
		$username = 'Example_user';
		$canonicalUsername = $this->getServiceContainer()->getUserNameUtils()->getCanonical( $username );
		$cache->clear( $username );
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

	public function testGetEmail() {
		$user = new CentralAuthUser( __METHOD__ );
		$this->assertSame( '', $user->getEmail() );
		$user->setEmail( 'test@test.test' );
		$this->assertSame( 'test@test.test', $user->getEmail() );

		$user->register( 'blabla', 'test@test.test' );
		$this->assertSame( 'test@test.test', $user->getEmail() );

		// reload
		$user->getStateHash( true );
		$this->assertSame( 'test@test.test', $user->getEmail() );
	}

	/** T370779 */
	public function testShouldHandleDuplicatesDuringMigration(): void {
		$testUserOne = $this->getMutableTestUser()->getUserIdentity();
		$testUserTwo = $this->getMutableTestUser()->getUserIdentity();

		$firstBatch = [
			$testUserOne,
			$testUserTwo
		];

		$secondBatch = [
			$testUserOne,
			new UserIdentityValue( $testUserTwo->getId(), 'OtherUser' )
		];

		$firstBatchResults = $this->simulateMigration( $firstBatch );
		$secondBatchResults = $this->simulateMigration( $secondBatch );

		foreach ( $firstBatchResults as $result ) {
			$this->assertTrue(
				$result,
				'Pass 1 migration should have succeeded for all users in the first batch'
			);
		}

		$this->assertFalse(
			$secondBatchResults[$testUserOne->getId()],
			"Pass 1 migration should have failed for \"{$testUserOne->getName()}\" as a corresponding global user exists"
		);
		$this->assertTrue(
			$secondBatchResults[$testUserTwo->getId()],
			"Pass 1 migration should have succeeded for \"{$testUserTwo->getName()}\""
		);
	}

	/**
	 * Simulate Pass 0 and Pass 1 migrations for a batch of users.
	 *
	 * @param UserIdentity[] $users Users to simulate migrations for.
	 * @return bool[] Pass 1 migration statuses by user ID.
	 */
	private function simulateMigration( array $users ): array {
		// A more realistic test would run each migration for a separate wiki ID,
		// but this is not really feasible in CI as there's no mechanism to setup
		// multiple test databases.
		$wikiId = WikiMap::getCurrentWikiId();

		// Pass 0 migration
		CentralAuthUser::storeMigrationData( $wikiId, $users );

		// Pass 1 migration
		$results = [];
		foreach ( $users as $user ) {
			$userId = $user->getId();
			$userName = $user->getName();

			$cu = $this->getMockBuilder( CentralAuthUser::class )
				->onlyMethods( [ 'queryUnattached' ] )
				->setConstructorArgs( [ $userName ] )
				->getMock();

			$cu->method( 'queryUnattached' )
				->willReturn( [
					$wikiId => [
						'wiki' => $wikiId,
						'id' => $userId,
						'name' => $userName,
						'email' => 'test@example.com',
						'emailAuthenticated' => wfTimestampNow(),
						'registration' => wfTimestampNow(),
						'password' => '',
						'editCount' => 1,
						'groupMemberships' => [],
						'blocked' => false,
					]
				] );

			$results[$userId] = $cu->storeAndMigrate();
		}

		return $results;
	}
}
