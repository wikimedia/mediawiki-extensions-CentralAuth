<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

use MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupManager;
use MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalPermissionManager;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\CentralAuth\WikiSet;

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

	private function createPermissionManager(
		array $groupRights = [],
		array $groupWikiSets = []
	): GlobalPermissionManager {
		$groupManager = $this->createMock( GlobalGroupManager::class );
		$groupManager->method( 'getRightsForGroup' )
			->willReturnCallback( static fn ( $group ) => $groupRights[$group] ?? [] );
		// ?? null seems unneeded, but it silences warnings about key not present
		$groupManager->method( 'getGroupWikiSet' )
			->willReturnCallback( static fn ( $group ) => $groupWikiSets[$group] ?? null );

		return new GlobalPermissionManager( $groupManager );
	}

	private function createUser( int $id, array $groups, bool $isAttached = true ): CentralAuthUser {
		$user = $this->createMock( CentralAuthUser::class );
		$user->method( 'getId' )->willReturn( $id );
		$user->method( 'getGlobalGroups' )->willReturn( $groups );
		$user->method( 'isAttached' )->willReturn( $isAttached );
		$user->method( 'exists' )->willReturn( $id > 0 );
		return $user;
	}

	private function createWikiSet( bool $inSet ): WikiSet {
		$wikiSet = $this->createMock( WikiSet::class );
		$wikiSet->method( 'inSet' )->willReturn( $inSet );
		return $wikiSet;
	}
}
