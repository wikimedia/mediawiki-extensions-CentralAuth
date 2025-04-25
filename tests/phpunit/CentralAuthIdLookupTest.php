<?php

use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Extension\CentralAuth\User\CentralAuthIdLookup;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\MainConfigNames;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Rdbms\IDBAccessObject;
use Wikimedia\TestingAccessWrapper;

/**
 * Setup database tests for centralauth
 * @group CentralAuthDB
 * @group Database
 * @covers \MediaWiki\Extension\CentralAuth\User\CentralAuthIdLookup
 */
class CentralAuthIdLookupTest extends MediaWikiIntegrationTestCase {
	use MockAuthorityTrait;

	/** @var array<string,int>|null A map of user name to central ID */
	private static $centralIds;

	/**
	 * Users with these properties will be available in the database for all
	 * tests in this class
	 *
	 * @var array[]
	 */
	private static $userInfos = [
		'GlobalUser' => [
			'id' => 1001,
			'wikis' => [
				[ '**LOCAL**', 'primary' ],
				[ 'enwiki', 'primary' ],
				[ 'dewiki', 'login' ],
				[ 'metawiki', 'password' ],
			],
		],
		'GlobalLockedUser' => [
			'id' => 1003,
			'attrs' => [
				'gu_locked' => 1,
				'gu_hidden_level' => CentralAuthUser::HIDDEN_LEVEL_NONE,
				'gu_email' => 'testlocked@localhost',
				'gu_home_db' => 'metawiki',
			],
			'wikis' => [
				[ 'metawiki', 'primary' ],
			]
		],
		'GlobalSuppressedUser' => [
			'id' => 1004,
			'attrs' => [
				'gu_locked' => 1,
				'gu_hidden_level' => CentralAuthUser::HIDDEN_LEVEL_SUPPRESSED,
				'gu_email' => 'testsuppressed@localhost',
				'gu_home_db' => 'metawiki',
			],
			'wikis' => [
				[ 'metawiki', 'primary' ],
			]
		],
		'GlobalUserUnattached' => [
			'id' => 1007,
			'wikis' => [
				[ 'metawiki', 'primary' ],
			],
			'createLocal' => false
		],
		'GlobalUserConflict' => [
			'id' => 1008,
			'wikis' => [
				[ 'metawiki', 'primary' ],
			],
			'createLocal' => false
		],
	];

	public function addDBDataOnce() {
		$centralIds = [];
		foreach ( self::$userInfos as $userName => $userInfo ) {
			$id = $userInfo['id'];
			$centralIds[$userName] = $id;
			$wikis = $userInfo['wikis'];
			$wikis[0] = str_replace( '**LOCAL**', WikiMap::getCurrentWikiId(), $wikis[0] );
			$user = new CentralAuthTestUser(
				$userName,
				'GUCP@ssword',
				[ 'gu_id' => (string)$id ] + ( $userInfo['attrs'] ?? [] ),
				$wikis,
				$userInfo['createLocal'] ?? true
			);
			$user->save( $this->getDb() );
		}
		self::$centralIds = $centralIds;
	}

	private function newLookup( $strict = true ): CentralAuthIdLookup {
		$this->overrideConfigValues( [
			CAMainConfigNames::CentralAuthStrict => $strict,
			MainConfigNames::CentralIdLookupProviders => [
				'central' => [
					'class' => CentralAuthIdLookup::class,
					'services' => [
						'MainConfig',
						'CentralAuth.CentralAuthDatabaseManager',
						'CentralAuth.CentralAuthUserCache',
					],
				],
			],
			MainConfigNames::CentralIdLookupProvider => 'central',
		] );
		return $this->getServiceContainer()->getCentralIdLookupFactory()->getLookup();
	}

	/**
	 * Disable database access in a given lookup object
	 *
	 * @param CentralAuthIdLookup|TestingAccessWrapper $lookup
	 */
	private function disableDatabase( $lookup ) {
		if ( !( $lookup instanceof TestingAccessWrapper ) ) {
			$lookup = TestingAccessWrapper::newFromObject( $lookup );
		}
		$mock = $this->createNoOpMock( CentralAuthDatabaseManager::class,
			[ 'centralLBHasRecentPrimaryChanges' ] );
		$mock
			->method( 'centralLBHasRecentPrimaryChanges' )
			->willReturn( false );
		$lookup->databaseManager = $mock;
	}

	public function testRegistration() {
		$this->assertInstanceOf(
			CentralAuthIdLookup::class,
			$this->getServiceContainer()
				->getCentralIdLookupFactory()
				->getLookup( 'CentralAuth' )
		);
	}

	public function testLookupCentralIds() {
		$lookup = $this->newLookup();
		$permitted = $this->mockAnonAuthorityWithPermissions( [ 'centralauth-suppress' ] );
		$nonPermitted = $this->mockAnonAuthorityWithoutPermissions( [ 'centralauth-suppress' ] );

		$this->assertSame( [], $lookup->lookupCentralIds( [] ) );

		$expect = array_flip( self::$centralIds );
		$expect[123] = 'X';
		ksort( $expect );

		$expect2 = $expect;
		$expect2[self::$centralIds['GlobalSuppressedUser']] = '';

		$arg = array_fill_keys( array_keys( $expect ), 'X' );

		$this->assertSame( $expect2, $lookup->lookupCentralIds( $arg ) );
		$this->assertSame( $expect, $lookup->lookupCentralIds( $arg, CentralIdLookup::AUDIENCE_RAW ) );
		$this->assertSame( $expect, $lookup->lookupCentralIds( $arg, $permitted ) );
		$this->assertSame( $expect2, $lookup->lookupCentralIds( $arg, $nonPermitted ) );
	}

	public function testLookupUserNames() {
		$lookup = $this->newLookup();
		$permitted = $this->mockAnonAuthorityWithPermissions( [ 'centralauth-suppress' ] );
		$nonPermitted = $this->mockAnonAuthorityWithoutPermissions( [ 'centralauth-suppress' ] );

		$this->assertSame( [], $lookup->lookupUserNames( [] ) );

		$expect = self::$centralIds;
		$expect['UTDoesNotExist'] = 'X';
		ksort( $expect );

		$expect2 = $expect;
		$expect2['GlobalSuppressedUser'] = 'X';

		$arg = array_fill_keys( array_keys( $expect ), 'X' );

		$this->assertSame( $expect2, $lookup->lookupUserNames( $arg ) );
		$this->assertSame( $expect, $lookup->lookupUserNames( $arg, CentralIdLookup::AUDIENCE_RAW ) );
		$this->assertSame( $expect, $lookup->lookupUserNames( $arg, $permitted ) );
		$this->assertSame( $expect2, $lookup->lookupUserNames( $arg, $nonPermitted ) );

		// test looking up a single name, which uses a different code path
		foreach ( $expect as $name => $id ) {
			$this->assertSame( [ $name => $id ], $lookup->lookupUserNames( [ $name => 'X' ],
				CentralIdLookup::AUDIENCE_RAW ) );
			$this->assertSame( [ $name => $id ], $lookup->lookupUserNames( [ $name => 'X' ], $permitted ) );
		}
		foreach ( $expect2 as $name => $id ) {
			$this->assertSame( [ $name => $id ], $lookup->lookupUserNames( [ $name => 'X' ] ) );
			$this->assertSame( [ $name => $id ], $lookup->lookupUserNames( [ $name => 'X' ], $nonPermitted ) );
		}
	}

	public static function provideLookupUserNamesWithFilter() {
		return [
			'none' => [
				CentralIdLookup::FILTER_NONE,
				false,
				'permitted',
				[
					'GlobalUser',
					'GlobalLockedUser',
					'GlobalSuppressedUser',
					'GlobalUserUnattached',
					'GlobalUserConflict',
				]
			],
			'none suppressed' => [
				CentralIdLookup::FILTER_NONE,
				false,
				'not-permitted',
				[
					'GlobalUser',
					'GlobalLockedUser',
					'GlobalUserUnattached',
					'GlobalUserConflict',
				]
			],
			'none raw' => [
				CentralIdLookup::FILTER_NONE,
				false,
				'raw',
				[
					'GlobalUser',
					'GlobalLockedUser',
					'GlobalSuppressedUser',
					'GlobalUserUnattached',
					'GlobalUserConflict',
				]
			],
			'local attached' => [
				CentralIdLookup::FILTER_ATTACHED,
				false,
				'permitted',
				[
					'GlobalUser',
				]
			],
			'foreign attached metawiki' => [
				CentralIdLookup::FILTER_ATTACHED,
				'metawiki',
				'permitted',
				[
					'GlobalUser',
					'GlobalLockedUser',
					'GlobalSuppressedUser',
					'GlobalUserUnattached',
					'GlobalUserConflict',
				]
			],
			'foreign attached dewiki' => [
				CentralIdLookup::FILTER_ATTACHED,
				'dewiki',
				'permitted',
				[
					'GlobalUser',
				]
			],
			'foreign attached suppressed' => [
				CentralIdLookup::FILTER_ATTACHED,
				'metawiki',
				'not-permitted',
				[
					'GlobalUser',
					'GlobalLockedUser',
					'GlobalUserUnattached',
					'GlobalUserConflict',
				]
			],
			'owned' => [
				CentralIdLookup::FILTER_OWNED,
				false,
				'permitted',
				[
					'GlobalUser',
					'GlobalLockedUser',
					'GlobalSuppressedUser',
					'GlobalUserUnattached',
					'GlobalUserConflict',
				]
			],
			'foreign owned' => [
				CentralIdLookup::FILTER_OWNED,
				'dewiki',
				'permitted',
				[
					'GlobalUser',
					'GlobalLockedUser',
					'GlobalSuppressedUser',
					'GlobalUserUnattached',
					'GlobalUserConflict',
				]
			]
		];
	}

	/**
	 * @dataProvider provideLookupUserNamesWithFilter
	 */
	public function testLookupUserNamesWithFilter( $filter, $wikiId, $auth, $expectedExistingUsers ) {
		/** @var CentralAuthIdLookup $lookup */
		$lookup = TestingAccessWrapper::newFromObject( $this->newLookup() );

		if ( $auth === 'permitted' ) {
			$audience = $this->mockAnonAuthorityWithPermissions( [ 'centralauth-suppress' ] );
		} elseif ( $auth === 'not-permitted' ) {
			$audience = $this->mockAnonAuthorityWithoutPermissions( [ 'centralauth-suppress' ] );
		} elseif ( $auth === 'raw' ) {
			$audience = CentralIdLookup::AUDIENCE_RAW;
		} else {
			throw new \InvalidArgumentException( 'auth must be permitted or not-permitted' );
		}

		$inputUsers = array_keys( self::$centralIds );
		$inputUsers[] = 'UTDoesNotExist';
		$inputUsers[] = '';
		$arg = array_fill_keys( $inputUsers, 'X' );

		$expect = [];
		foreach ( $inputUsers as $userName ) {
			$expect[$userName] = in_array( $userName, $expectedExistingUsers )
				? (string)self::$centralIds[$userName] : 'X';
		}

		// Test with an empty array
		$this->assertSame( [], $lookup->lookupUserNamesWithFilter(
			[], $filter, $audience, IDBAccessObject::READ_NORMAL, $wikiId ) );

		// Test with a variety of different kinds of users
		$result = $lookup->lookupUserNamesWithFilter(
			$arg, $filter, $audience, IDBAccessObject::READ_NORMAL, $wikiId );

		$this->assertArrayEquals( $expect, $result, false, true );

		// The local modes have caching, so it should be possible to load the same
		// data again without accessing the database
		if ( $wikiId === false ) {
			$this->disableDatabase( $lookup );
			$result = $lookup->lookupUserNamesWithFilter(
				$arg, $filter, $audience, IDBAccessObject::READ_NORMAL, $wikiId );
			$this->assertArrayEquals( $expect, $result, false, true );
		}
	}

	/**
	 * Confirm that lookupUserNames shares its cache with CentralAuth::getInstance()
	 */
	public function testLookupCacheSharing() {
		$lookup = $this->newLookup();
		$this->disableDatabase( $lookup );

		$expectedId = self::$centralIds['GlobalUser'];
		$id = CentralAuthUser::getInstance( new UserIdentityValue( 1, 'GlobalUser' ) )
			->getId();
		$this->assertSame( $expectedId, $id );
		$result = $lookup->lookupUserNames( [ 'GlobalUser' => 'X' ] );
		$this->assertSame( [ 'GlobalUser' => $expectedId ], $result );
	}

	public static function provideLocalUsers() {
		return [
			[ 'GlobalUser', 1001, true ],
			[ 'UTSysop', 1, false ],
			[ 'DoesNotExist', 123, false ],
		];
	}

	/**
	 * @dataProvider provideLocalUsers
	 * @param string $username
	 * @param int $id
	 * @param bool $succeed
	 */
	public function testCentralIdFromLocalUser( $username, $id, $succeed ) {
		$user = User::newFromName( $username );
		$lookup = $this->newLookup();
		$ret = $lookup->centralIdFromLocalUser( $user );
		if ( $succeed ) {
			$this->assertSame( $id, $ret );
		} else {
			$this->assertSame( 0, $ret );
		}
	}

	/**
	 * @dataProvider provideLocalUsers
	 * @param string $username
	 * @param int $id
	 * @param bool $succeed
	 */
	public function testLocalUserFromCentralId( $username, $id, $succeed ) {
		$lookup = $this->newLookup();
		$user = $lookup->localUserFromCentralId( $id );
		if ( $succeed ) {
			$this->assertInstanceOf( UserIdentity::class, $user );
			$this->assertSame( $username, $user->getName() );
		} else {
			$this->assertNull( $user );
		}
	}

	public static function provideIsAttached() {
		return [
			[
				1, 'GlobalUser', 'enwiki',
				true
			],
			[
				1, 'GlobalUser', 'foowiki',
				false
			],

			'Local user exists and is attached to the global user' => [
				1, 'GlobalUser', UserIdentityValue::LOCAL,
				true
			],
			'Local user exists and IS NOT attached to the global user' => [
				1, 'GlobalUserConflict', UserIdentityValue::LOCAL,
				false
			],
			'Local user DOES NOT exist, so can not be attached, but is owned by the global user' => [
				0, 'GlobalUserUnattached', UserIdentityValue::LOCAL,
				false, true, true
			],
			'Local user DOES NOT exist, so can not be attached, and IS NOT owned by the global user' => [
				0, 'GlobalUserUnattached', UserIdentityValue::LOCAL,
				false, false, false
			],

			[
				1, 'UTSysop', UserIdentityValue::LOCAL,
				false
			],
			[
				0, 'DoesNotExist', UserIdentityValue::LOCAL,
				false
			],
		];
	}

	/**
	 * @dataProvider provideIsAttached
	 * @param int $id
	 * @param string $username
	 * @param string $wikiId
	 * @param bool $isAttached
	 * @param bool|null $isOwned
	 * @param bool $strict
	 */
	public function testIsAttached( $id, $username, $wikiId, $isAttached, $isOwned = null, $strict = true ) {
		$user = new UserIdentityValue( $id, $username, $wikiId );
		$lookup = $this->newLookup( $strict );
		$this->assertSame( $isAttached, $lookup->isAttached( $user, $wikiId ) );
		$this->assertSame( $isOwned ?? $isAttached, $lookup->isOwned( $user, $wikiId ) );
	}

}
