<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupAssignmentService;
use MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupManager;
use MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalPermissionManager;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\CentralAuth\WikiSet;
use MediaWiki\User\RestrictedUserGroupConfigReader;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserGroupRestrictions;
use MediaWiki\User\UserRequirementsConditionChecker;
use MediaWiki\User\UserRequirementsConditionCheckerFactory;
use MediaWiki\WikiMap\WikiMap;

/**
 * @covers \MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalPermissionManager
 */
class GlobalPermissionManagerTest extends MediaWikiUnitTestCase {

	/** @dataProvider provideEffectiveGroups */
	public function testGetEffectiveGroups(
		array $groups,
		bool $isAttached,
		array $expectedGroups
	): void {
		$user = $this->createUser( 1, $groups, $isAttached );

		$wikiSets = [
			'global-sysop' => $this->createWikiSet( true ),
			'new-wiki-importer' => $this->createWikiSet( false ),
		];

		$permissionManager = $this->createPermissionManager( [], $wikiSets );
		$effectiveGroups = $permissionManager->getUserEffectiveGroups( $user );
		$this->assertArrayEquals( $expectedGroups, $effectiveGroups );
	}

	public static function provideEffectiveGroups(): iterable {
		yield 'Casual user without groups' => [
			'groups' => [],
			'isAttached' => true,
			'expectedGroups' => [],
		];
		yield 'Steward' => [
			'groups' => [ 'steward' ],
			'isAttached' => true,
			'expectedGroups' => [ 'steward' ],
		];
		yield 'Global sysop (group active on this wikiset)' => [
			'groups' => [ 'global-sysop' ],
			'isAttached' => true,
			'expectedGroups' => [ 'global-sysop' ],
		];
		yield 'New wiki importer (group not active on this wikiset)' => [
			'groups' => [ 'new-wiki-importer' ],
			'isAttached' => true,
			'expectedGroups' => [],
		];
		yield 'Steward (but unattached)' => [
			'groups' => [ 'steward' ],
			'isAttached' => false,
			'expectedGroups' => [],
		];
	}

	/** @dataProvider provideUserPermissions */
	public function testGetUserPermissions(
		array $groups,
		array $expectedRights
	): void {
		$user = $this->createUser( 1, $groups );

		$groupRights = [
			'steward' => [ 'read', 'delete' ],
			'global-sysop' => [ 'read', 'block' ],
		];

		$permissionManager = $this->createPermissionManager( $groupRights );
		$rights = $permissionManager->getUserPermissions( $user );
		$this->assertArrayEquals( $expectedRights, $rights );
	}

	public static function provideUserPermissions(): iterable {
		yield 'Casual user without groups' => [
			'groups' => [],
			'expectedRights' => [],
		];
		yield 'Steward' => [
			'groups' => [ 'steward' ],
			'expectedRights' => [ 'read', 'delete' ],
		];
		yield 'Global sysop' => [
			'groups' => [ 'global-sysop' ],
			'expectedRights' => [ 'read', 'block' ],
		];
		yield 'Both steward and global sysop' => [
			'groups' => [ 'steward', 'global-sysop' ],
			'expectedRights' => [ 'read', 'delete', 'block' ],
		];
	}

	public function testGetRightsAndEffectiveGroups_userNotExists(): void {
		// Normally, there would be no user such that it doesn't exist but is in groups and attached
		// But let's check that existence is also checked
		$user = $this->createUser( 0, [ 'steward' ] );

		$groupRights = [
			'steward' => [ 'read', 'delete' ],
		];

		$permissionManager = $this->createPermissionManager( $groupRights );
		$effectiveGroups = $permissionManager->getUserEffectiveGroups( $user );
		$rights = $permissionManager->getUserPermissions( $user );
		$this->assertSame( [], $effectiveGroups );
		$this->assertSame( [], $rights );
	}

	public function testGetUserPermissions_cacheIsPerUserId(): void {
		$groupRights = [
			'steward' => [ 'read', 'delete' ],
			'global-sysop' => [ 'read', 'block' ],
		];

		$userA = $this->createUser( 1, [ 'steward' ] );
		$userB = $this->createUser( 2, [ 'global-sysop' ] );
		$manager = $this->createPermissionManager( $groupRights );

		// Prepare cached values first
		$manager->getUserPermissions( $userA );
		$manager->getUserPermissions( $userB );

		$resultsA = $manager->getUserPermissions( $userA );
		$resultsB = $manager->getUserPermissions( $userB );

		$this->assertArrayEquals( [ 'read', 'delete' ], $resultsA );
		$this->assertArrayEquals( [ 'read', 'block' ], $resultsB );
	}

	public function testInvalidateUserPermissionCache(): void {
		$groupManager = $this->createMock( GlobalGroupManager::class );
		$groupManager->method( 'getRightsForGroup' )
			->willReturn( [ 'right1' ], [ 'right2' ] );
		$groupManager->method( 'getGroupWikiSet' )
			->willReturn( null );

		$permissionManager = new GlobalPermissionManager(
			$this->createStub( UserFactory::class ),
			new ServiceOptions(
				GlobalPermissionManager::CONSTRUCTOR_OPTIONS,
				[ CAMainConfigNames::CentralAuthCentralWiki => WikiMap::getCurrentWikiId() ]
			),
			$this->createStub( UserGroupManager::class ),
			$this->createStub( RestrictedUserGroupConfigReader::class ),
			$this->createStub( UserRequirementsConditionCheckerFactory::class ),
			$groupManager
		);

		$user = $this->createUser( 1, [ 'steward' ] );

		// $rights1 and $rights2 should be the same, as cache is used
		$rights1 = $permissionManager->getUserPermissions( $user );
		$this->assertSame( [ 'right1' ], $rights1 );

		$rights2 = $permissionManager->getUserPermissions( $user );
		$this->assertSame( [ 'right1' ], $rights2 );

		// Only now getRightsForGroup should be invoked again, yielding 'right2'
		$permissionManager->invalidateUserPermissionCache( $user );
		$rights3 = $permissionManager->getUserPermissions( $user );
		$this->assertSame( [ 'right2' ], $rights3 );
	}

	public function testGetUserDisabledGroups_systemUser(): void {
		$user = $this->createUser( 1, [ 'steward' ] );

		$localUser = $this->createMock( User::class );
		$localUser->method( 'isSystemUser' )->willReturn( true );
		$userFactory = $this->createMock( UserFactory::class );
		$userFactory->method( 'newFromUserIdentity' )->willReturn( $localUser );

		// System user should never attempt to read restrictions whatever they are
		$configReader = $this->createMock( RestrictedUserGroupConfigReader::class );
		$configReader->expects( $this->never() )->method( 'getConfig' );

		$permissionManager = $this->createPermissionManager(
			userFactory: $userFactory,
			configReader: $configReader
		);

		$this->assertSame( [], $permissionManager->getUserDisabledGroups( $user ) );
	}

	public function testGetUserDisabledGroups_usesCorrectScopeAndWiki(): void {
		$user = $this->createUser( 1, [] );

		$configReader = $this->createMock( RestrictedUserGroupConfigReader::class );
		$configReader->expects( $this->once() )
			->method( 'getConfig' )
			->with( WikiMap::getCurrentWikiId(), GlobalGroupAssignmentService::RESTRICTION_SCOPE )
			->willReturn( [] );

		$permissionManager = $this->createPermissionManager(
			userFactory: $this->createNonSystemUserFactory(),
			configReader: $configReader
		);

		$permissionManager->getUserDisabledGroups( $user );
	}

	/** @dataProvider provideDisabledGroups */
	public function testGetUserDisabledGroups(
		array $groups,
		array $restrictedGroupsConfig,
		bool $conditionResult,
		array $expectedDisabledGroups
	): void {
		$user = $this->createUser( 1, $groups );

		$checker = $this->createMock( UserRequirementsConditionChecker::class );
		$checker->method( 'recursivelyCheckCondition' )->willReturn( $conditionResult );
		$checkerFactory = $this->createMock( UserRequirementsConditionCheckerFactory::class );
		$checkerFactory->method( 'getUserRequirementsConditionChecker' )->willReturn( $checker );

		$configReader = $this->createMock( RestrictedUserGroupConfigReader::class );
		$configReader->method( 'getConfig' )->willReturn( $restrictedGroupsConfig );

		$permissionManager = $this->createPermissionManager(
			userFactory: $this->createNonSystemUserFactory(),
			configReader: $configReader,
			checkerFactory: $checkerFactory
		);

		$this->assertArrayEquals(
			$expectedDisabledGroups,
			$permissionManager->getUserDisabledGroups( $user )
		);
	}

	public static function provideDisabledGroups(): iterable {
		$restrictions = new UserGroupRestrictions( [
			'memberConditions' => [ 'some-condition' ],
		] );
		$noConditionsRestriction = new UserGroupRestrictions( [] );

		yield 'No active groups' => [
			'groups' => [],
			'restrictedGroupsConfig' => [ 'steward' => $restrictions ],
			'conditionResult' => false,
			'expectedDisabledGroups' => [],
		];
		yield 'Group not in restricted config' => [
			'groups' => [ 'steward' ],
			'restrictedGroupsConfig' => [],
			'conditionResult' => false,
			'expectedDisabledGroups' => [],
		];
		yield 'Restriction has no member conditions' => [
			'groups' => [ 'steward' ],
			'restrictedGroupsConfig' => [ 'steward' => $noConditionsRestriction ],
			'conditionResult' => false,
			'expectedDisabledGroups' => [],
		];
		yield 'User meets conditions' => [
			'groups' => [ 'steward' ],
			'restrictedGroupsConfig' => [ 'steward' => $restrictions ],
			'conditionResult' => true,
			'expectedDisabledGroups' => [],
		];
		yield 'User fails conditions' => [
			'groups' => [ 'steward' ],
			'restrictedGroupsConfig' => [ 'steward' => $restrictions ],
			'conditionResult' => false,
			'expectedDisabledGroups' => [ 'steward' ],
		];
	}

	public function testGetUserDisabledGroups_multipleGroupsMixed(): void {
		// steward fails conditions → disabled; global-sysop meets conditions → not disabled
		$user = $this->createUser( 1, [ 'steward', 'global-sysop' ] );

		$restrictionMet = new UserGroupRestrictions( [ 'memberConditions' => [ 'cond-met' ] ] );
		$restrictionFailed = new UserGroupRestrictions( [ 'memberConditions' => [ 'cond-failed' ] ] );

		$checker = $this->createMock( UserRequirementsConditionChecker::class );
		$checker->method( 'recursivelyCheckCondition' )
			->willReturnCallback( static fn ( $cond, $user ) => $cond[0] === 'cond-met' );
		$checkerFactory = $this->createMock( UserRequirementsConditionCheckerFactory::class );
		$checkerFactory->method( 'getUserRequirementsConditionChecker' )->willReturn( $checker );

		$configReader = $this->createMock( RestrictedUserGroupConfigReader::class );
		$configReader->method( 'getConfig' )
			->willReturn( [ 'steward' => $restrictionFailed, 'global-sysop' => $restrictionMet ] );

		$permissionManager = $this->createPermissionManager(
			userFactory: $this->createNonSystemUserFactory(),
			configReader: $configReader,
			checkerFactory: $checkerFactory
		);

		$this->assertArrayEquals( [ 'steward' ], $permissionManager->getUserDisabledGroups( $user ) );
	}

	private function createPermissionManager(
		array $groupRights = [],
		array $groupWikiSets = [],
		?UserFactory $userFactory = null,
		?RestrictedUserGroupConfigReader $configReader = null,
		?UserRequirementsConditionCheckerFactory $checkerFactory = null
	): GlobalPermissionManager {
		$groupManager = $this->createMock( GlobalGroupManager::class );
		$groupManager->method( 'getRightsForGroup' )
			->willReturnCallback( static fn ( $group ) => $groupRights[$group] ?? [] );
		// ?? null seems unneeded, but it silences warnings about key not present
		$groupManager->method( 'getGroupWikiSet' )
			->willReturnCallback( static fn ( $group ) => $groupWikiSets[$group] ?? null );

		return new GlobalPermissionManager(
			$userFactory ?? $this->createStub( UserFactory::class ),
			new ServiceOptions(
				GlobalPermissionManager::CONSTRUCTOR_OPTIONS,
				[ CAMainConfigNames::CentralAuthCentralWiki => WikiMap::getCurrentWikiId() ]
			),
			$this->createStub( UserGroupManager::class ),
			$configReader ?? $this->createStub( RestrictedUserGroupConfigReader::class ),
			$checkerFactory ?? $this->createStub( UserRequirementsConditionCheckerFactory::class ),
			$groupManager
		);
	}

	private function createUser( int $id, array $groups, bool $isAttached = true ): CentralAuthUser {
		$user = $this->createMock( CentralAuthUser::class );
		$user->method( 'getId' )->willReturn( $id );
		$user->method( 'getName' )->willReturn( "User $id" );
		$user->method( 'getGlobalGroups' )->willReturn( $groups );
		$user->method( 'isAttached' )->willReturn( $isAttached );
		$user->method( 'exists' )->willReturn( $id > 0 );
		$user->method( 'getLocalId' )->willReturn( $id );
		return $user;
	}

	private function createNonSystemUserFactory(): UserFactory {
		$localUser = $this->createMock( User::class );
		$localUser->method( 'isSystemUser' )->willReturn( false );
		$userFactory = $this->createMock( UserFactory::class );
		$userFactory->method( 'newFromUserIdentity' )->willReturn( $localUser );
		return $userFactory;
	}

	private function createWikiSet( bool $inSet ): WikiSet {
		$wikiSet = $this->createMock( WikiSet::class );
		$wikiSet->method( 'inSet' )->willReturn( $inSet );
		return $wikiSet;
	}
}
