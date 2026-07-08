<?php

namespace MediaWiki\Extension\CentralAuth\Tests\Phpunit\Integration\Api;

use CentralAuthTestUser;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\Utils\MWTimestamp;
use MediaWiki\WikiMap\WikiMap;
use TestUserRegistry;

/**
 * @covers \MediaWiki\Extension\CentralAuth\Api\ApiQueryGlobalAllUsers
 * @group Database
 */
class ApiQueryGlobalAllUsersTest extends ApiTestCase {
	use TempUserTestTrait;

	private const MODULE_NAME = 'globalallusers';

	private const USER_PREFIX = 'GlobalTestUser ';
	private const GROUP_PREFIX = 'globalgroup-';

	private static array $baseParams = [
		'action' => 'query',
		'list' => self::MODULE_NAME,
	];

	/** @var list<callable> */
	private array $tearDownCallbacks = [];

	protected function setUp(): void {
		$this->overrideConfigValue( CAMainConfigNames::CentralAuthCentralWiki, null );
		parent::setUp();
	}

	/** @inheritDoc */
	public function addDBDataOnce() {
		$this->overrideConfigValue( CAMainConfigNames::CentralAuthCentralWiki, null );
		$centralDb = CentralAuthServices::getDatabaseManager( $this->getServiceContainer() )->getCentralPrimaryDB();

		// Create 3 global users
		for ( $i = 1; $i <= 3; $i++ ) {
			// Create a central user
			$user = new CentralAuthTestUser(
				self::USER_PREFIX . TestUserRegistry::getNextId(),
				'GUP@ssword',
				[ 'gu_id' => $i ],
				[ [ WikiMap::getCurrentWikiId(), 'primary' ] ]
			);
			$user->save( $this->getDb() );

			// Define the group
			$group = self::GROUP_PREFIX . $i;
			$centralDb->newInsertQueryBuilder()
				->insertInto( 'global_group_permissions' )
				->row( [
					'ggp_group' => $group,
					'ggp_permission' => 'autoconfirmed',
				] )
				->caller( __METHOD__ )
				->execute();

			// Register the user into the group
			$user->getCentralUser()->addToGlobalGroup( $group );
		}
	}

	protected function tearDown(): void {
		foreach ( $this->tearDownCallbacks as $fn ) {
			$fn();
		}
		$this->tearDownCallbacks = [];

		parent::tearDown();
	}

	/**
	 * @param int $userId 1~3
	 * @return array{0:CentralAuthUser,1:string} [ $user, $group ]
	 */
	private static function getCentralUser( int $userId ) {
		$centralAuthUser = CentralAuthUser::newPrimaryInstanceFromId( $userId );
		$group = self::GROUP_PREFIX . $centralAuthUser->getId();
		return [ $centralAuthUser, $group ];
	}

	public function testExecute() {
		[ $res ] = $this->doApiRequest( self::$baseParams );

		$this->assertArrayHasKey( self::MODULE_NAME, $res['query'] );
		$this->assertCount( 3, $res['query'][self::MODULE_NAME] );
	}

	/**
	 * @dataProvider provideExecuteWithRange
	 */
	public function testExecuteWithRange( ?int $fromIndex, ?int $toIndex ) {
		$userFrom = $fromIndex !== null ? $this->getCentralUser( $fromIndex )[0] : null;
		$userTo = $toIndex !== null ? $this->getCentralUser( $toIndex )[0] : null;

		[ $res ] = $this->doApiRequest( self::$baseParams + [
			'agufrom' => $userFrom?->getName(),
			'aguto' => $userTo?->getName(),
		] );

		if ( $fromIndex !== null && $toIndex !== null ) {
			$expectedCount = abs( $fromIndex - $toIndex ) + 1;
		} elseif ( $fromIndex !== null ) {
			$expectedCount = 3 - ( $fromIndex - 1 );
		} elseif ( $toIndex !== null ) {
			$expectedCount = $toIndex;
		} else {
			$expectedCount = 3;
		}
		$this->assertCount( $expectedCount, $res['query'][self::MODULE_NAME] );
	}

	public static function provideExecuteWithRange() {
		return [
			'from user 2' => [ 'fromIndex' => 2, 'toIndex' => null ],
			'to user 2' => [ 'fromIndex' => null, 'toIndex' => 2 ],
			'from user 1 to 3' => [ 'fromIndex' => 1, 'toIndex' => 3 ],
		];
	}

	/**
	 * @dataProvider provideExecuteWithPrefix
	 */
	public function testExecuteWithPrefix( string $prefix ) {
		[ $res ] = $this->doApiRequest( self::$baseParams + [
			'aguprefix' => $prefix,
		] );

		$expectedCount = $prefix === self::USER_PREFIX ? 3 : 0;
		$this->assertCount( $expectedCount, $res['query'][self::MODULE_NAME] );
	}

	public static function provideExecuteWithPrefix() {
		return [
			'Shared user prefix' => [ self::USER_PREFIX ],
			'Nonexisting user prefix' => [ self::USER_PREFIX . ' ' ],
		];
	}

	/**
	 * @dataProvider provideExecuteWithGroupAndLimit
	 */
	public function testExecuteWithGroupAndLimit( array $userIds, int $limit ) {
		$groups = [];
		$expected = [];
		foreach ( $userIds as $id ) {
			[ $user, $group ] = $this->getCentralUser( $id );
			$groups[] = $group;
			$expected[] = [
				'id' => $id,
				'name' => $user->getName(),
				'groups' => [ $group ],
			];
		}
		$expected = array_slice( $expected, 0, $limit );

		[ $res ] = $this->doApiRequest( self::$baseParams + [
			'agugroup' => implode( '|', $groups ),
			'aguprop' => 'groups',
			'agulimit' => $limit,
		] );

		$this->assertSame( $expected, $res['query'][self::MODULE_NAME] );
		if ( count( $userIds ) > $limit ) {
			$this->assertArrayHasKey( 'continue', $res );
		}
	}

	public static function provideExecuteWithGroupAndLimit() {
		return [
			'one user, limit 1' => [
				'userIds' => [ 1 ],
				'limit' => 1,
			],
			'two users, limit 1' => [
				'userIds' => [ 1, 2 ],
				'limit' => 1,
			],
			'two users, limit 2' => [
				'userIds' => [ 1, 2 ],
				'limit' => 2,
			],
			'three users, limit 2' => [
				'userIds' => [ 1, 2, 3 ],
				'limit' => 2,
			],
		];
	}

	/**
	 * @dataProvider provideExecuteWithGroupForExpiredGroups
	 */
	public function testExecuteWithGroupForExpiredGroups(
		array $groupIndexes,
		int $limit,
		int $expectedEntryCount,
		bool $expectContinuation
	) {
		// Expire user #1's group
		$dbw = CentralAuthServices::getDatabaseManager()->getCentralPrimaryDB();
		$dbw->newUpdateQueryBuilder()
			->update( 'global_user_groups' )
			->set( [ 'gug_expiry' => $dbw->timestamp( MWTimestamp::now( TS_UNIX ) - 1 ) ] )
			->where( [ 'gug_user' => 1 ] )
			->caller( __METHOD__ )
			->execute();

		// Schedule cleanup
		$this->tearDownCallbacks[] = static function () use ( $dbw ) {
			$dbw->newUpdateQueryBuilder()
				->update( 'global_user_groups' )
				->set( [ 'gug_expiry' => null ] )
				->where( [ 'gug_user' => 1 ] )
				->execute();
		};

		// Build group filter
		$groups = [];
		foreach ( $groupIndexes as $idx ) {
			[ , $group ] = $this->getCentralUser( $idx );
			$groups[] = $group;
		}

		[ $res ] = $this->doApiRequest( self::$baseParams + [
			'agugroup' => implode( '|', $groups ),
			'aguprop' => 'groups',
			'agulimit' => $limit,
		] );

		// Build expected results: user #1 is expired, so only #2 and #3 can appear
		$expected = [];
		foreach ( [ 2, 3 ] as $idx ) {
			if ( count( $expected ) >= $expectedEntryCount ) {
				break;
			}
			[ $user ] = $this->getCentralUser( $idx );
			$expected[] = [
				'id' => $user->getId(),
				'name' => $user->getName(),
				'groups' => $user->getActiveGlobalGroups(),
			];
		}

		$this->assertSame( $expected, $res['query'][self::MODULE_NAME] );

		if ( $expectContinuation ) {
			$this->assertArrayHasKey( 'continue', $res );
		} else {
			$this->assertArrayNotHasKey( 'continue', $res );
		}
	}

	public static function provideExecuteWithGroupForExpiredGroups() {
		return [
			'Group 1, limit 1, no entry expected' => [
				'groupIndexes' => [ 1 ],
				'limit' => 1,
				'expectedEntryCount' => 0,
				'expectContinuation' => false,
			],
			'Group 1&2, limit 1, one entry for user #2 expected' => [
				'groupIndexes' => [ 1, 2 ],
				'limit' => 1,
				'expectedEntryCount' => 1,
				'expectContinuation' => false,
			],
			'Group 1&2, limit 2, one entry for user #2 expected' => [
				'groupIndexes' => [ 1, 2 ],
				'limit' => 2,
				'expectedEntryCount' => 1,
				'expectContinuation' => false,
			],
			'Group 1&2&3, limit 1, one entry for user #2 expected with continuation' => [
				'groupIndexes' => [ 1, 2, 3 ],
				'limit' => 1,
				'expectedEntryCount' => 1,
				'expectContinuation' => true,
			],
			'Group 1&2&3, limit 2, two entries for user #2 and #3 expected' => [
				'groupIndexes' => [ 1, 2, 3 ],
				'limit' => 2,
				'expectedEntryCount' => 2,
				'expectContinuation' => false,
			],
		];
	}

	/**
	 * @dataProvider provideExecuteWithExcludeGroup
	 */
	public function testExecuteWithExcludeGroup( array $excludeGroupIndexes, array $expectedUserIndexes ) {
		$excludeGroups = [];
		foreach ( $excludeGroupIndexes as $idx ) {
			if ( $idx > 3 ) {
				$excludeGroups[] = 'nonexistinggroup';
				continue;
			}
			[ , $group ] = $this->getCentralUser( $idx );
			$excludeGroups[] = $group;
		}

		[ $res ] = $this->doApiRequest( self::$baseParams + [
			'aguexcludegroup' => implode( '|', $excludeGroups ),
		] );

		$expected = [];
		foreach ( $expectedUserIndexes as $idx ) {
			[ $user ] = $this->getCentralUser( $idx );
			$expected[] = [
				'id' => $user->getId(),
				'name' => $user->getName(),
			];
		}

		$this->assertSame( $expected, $res['query'][self::MODULE_NAME] );
	}

	public static function provideExecuteWithExcludeGroup() {
		return [
			'Exclude group of user #1' => [
				'excludeGroupIndexes' => [ 1 ],
				'expectedUserIndexes' => [ 2, 3 ],
			],
			'Exclude groups of user #1 and #2' => [
				'excludeGroupIndexes' => [ 1, 2 ],
				'expectedUserIndexes' => [ 3 ],
			],
			'Exclude non-matching group (no effect)' => [
				// Group not mapped, should be ignored
				'excludeGroupIndexes' => [ 999 ],
				'expectedUserIndexes' => [ 1, 2, 3 ],
			],
		];
	}

	public function testExecuteWithPropLockinfo() {
		// Lock user #1
		[ $user ] = $this->getCentralUser( 1 );
		$status = $user->adminLock();
		$this->assertStatusGood( $status );

		[ $res ] = $this->doApiRequest( self::$baseParams + [
			'agufrom' => $user->getName(),
			'aguto' => $this->getCentralUser( 2 )[0]->getName(),
			'aguprop' => 'lockinfo',
			'agudir' => 'ascending',
		] );

		$entries = $res['query'][self::MODULE_NAME];
		$this->assertSame( '', $entries[0]['locked'] );
		$this->assertArrayNotHasKey( 'locked', $entries[1] );
	}

	public function testExecuteWithPropExistsLocally() {
		[ $user ] = $this->getCentralUser( 1 );

		[ $res ] = $this->doApiRequest( self::$baseParams + [
			'agufrom' => $user->getName(),
			'aguto' => $user->getName(),
			'aguprop' => 'existslocally',
		] );

		$this->assertSame( '', $res['query'][self::MODULE_NAME][0]['existslocally'] );
	}

	public function testExecuteWithExcludeNamed() {
		[ $res ] = $this->doApiRequest( self::$baseParams + [
			'aguexcludenamed' => 1,
		] );

		$this->assertSame( [], $res['query'][self::MODULE_NAME] );
	}

	public function testExecuteWithExcludeTemp() {
		$this->enableAutoCreateTempUser();
		$status = $this->getServiceContainer()->getTempUserCreator()->create( null, new FauxRequest() );
		$this->assertStatusGood( $status );
		$tempUser = $status->getValue();

		// Schedule manual cleanup of the temp user for tables touched in ::addDBDataOnce
		$this->tearDownCallbacks[] = function () use ( $tempUser ) {
			$centralDbw = CentralAuthServices::getDatabaseManager()->getCentralPrimaryDB();
			$centralDbw->newDeleteQueryBuilder()
				->delete( 'globaluser' )
				->where( [ 'gu_name' => $tempUser->getName() ] )
				->execute();
			$centralDbw->newDeleteQueryBuilder()
				->deleteFrom( 'localuser' )
				->where( [ 'lu_name' => $tempUser->getName() ] )
				->execute();
			$this->getDb()->newDeleteQueryBuilder()
				->deleteFrom( 'user' )
				->where( [ 'user_id' => $tempUser->getId() ] )
				->execute();
		};

		// Ensure the temp user exists in the central DB
		$status = CentralAuthServices::getUserHelper()->getCentralAuthUserByName( $tempUser->getName() );
		$this->assertStatusGood( $status );

		[ $res ] = $this->doApiRequest( self::$baseParams + [
			'agufrom' => $tempUser->getName(),
			'aguto' => $tempUser->getName(),
		] );

		$this->assertSame( $tempUser->getName(), $res['query'][self::MODULE_NAME][0]['name'] );

		[ $res ] = $this->doApiRequest( self::$baseParams + [
			'aguexcludetemp' => 1,
		] );

		$count = 0;
		$includesTempUser = false;
		foreach ( $res['query'][self::MODULE_NAME] as $entry ) {
			$count++;
			if ( $entry['name'] === $tempUser->getName() ) {
				$includesTempUser = true;
			}
		}

		$this->assertSame( 3, $count );
		$this->assertFalse( $includesTempUser );
	}

}
