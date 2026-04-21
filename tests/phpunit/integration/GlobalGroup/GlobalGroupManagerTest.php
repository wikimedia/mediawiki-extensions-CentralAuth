<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupManager;
use MediaWiki\Extension\CentralAuth\WikiSet;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * @covers \MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupManager
 * @group Database
 * @author Taavi "Majavah" Väänänen <hi@taavi.wtf>
 */
class GlobalGroupManagerTest extends MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider provideFlags
	 */
	public function testGetDefinedGroups( int $flags ) {
		$manager = $this->createManager();
		$groups = $manager->getDefinedGroups( $flags );

		$this->assertArrayEquals( [ 'steward', 'global-sysop' ], $groups );
	}

	/**
	 * @dataProvider provideFlags
	 */
	public function testGetRightsForGroup( int $flags ) {
		$manager = $this->createManager();
		$stewardRights = $manager->getRightsForGroup( 'steward', $flags );
		$globalSysopRights = $manager->getRightsForGroup( 'global-sysop', $flags );
		$nonexistentGroupRights = $manager->getRightsForGroup( 'does-not-exist', $flags );

		$this->assertArrayEquals( [ 'read', 'delete' ], $stewardRights );
		$this->assertArrayEquals( [ 'read' ], $globalSysopRights );
		$this->assertArrayEquals( [], $nonexistentGroupRights );
	}

	/** @dataProvider provideFlags */
	public function testGetGroupsWithPermission( int $flags ) {
		$manager = $this->createManager();
		$groupsWithReadRight = $manager->getGroupsWithPermission( 'read', $flags );
		$groupsWithDeleteRight = $manager->getGroupsWithPermission( 'delete', $flags );
		$groupsWithNonExistentRight = $manager->getGroupsWithPermission( 'does-not-exist', $flags );

		$this->assertArrayEquals( [ 'steward', 'global-sysop' ], $groupsWithReadRight );
		$this->assertArrayEquals( [ 'steward' ], $groupsWithDeleteRight );
		$this->assertCount( 0, $groupsWithNonExistentRight );
	}

	/** @dataProvider provideFlags */
	public function testIsGroupEmpty( int $flags ) {
		$manager = $this->createManager();
		$this->assertTrue( $manager->isGroupEmpty( 'steward', $flags ) );

		$this->addGroupMember( 'steward' );
		$this->assertFalse( $manager->isGroupEmpty( 'steward', $flags ) );
	}

	public static function provideFlags(): array {
		return [
			'READ_NORMAL' => [ IDBAccessObject::READ_NORMAL ],
			'READ_LATEST' => [ IDBAccessObject::READ_LATEST ],
		];
	}

	/** @dataProvider provideAddRights */
	public function testAddRights(
		string $groupName,
		array $addRights,
		array $expectedRights
	): void {
		$manager = $this->createManager();
		$status = $manager->addRightsToGroup( $groupName, $addRights );

		$this->assertStatusGood( $status );
		$rights = $manager->getRightsForGroup( $groupName );
		$this->assertArrayEquals( $expectedRights, $rights );
	}

	public static function provideAddRights(): array {
		return [
			'Add rights to existing group' => [
				'groupName' => 'steward',
				'addRights' => [ 'block' ],
				'expectedRights' => [ 'read', 'delete', 'block' ],
			],
			'Add duplicated rights to existing group' => [
				'groupName' => 'steward',
				'addRights' => [ 'read', 'protect' ],
				'expectedRights' => [ 'read', 'delete', 'protect' ],
			],
			'Add rights to new group' => [
				'groupName' => 'new-group',
				'addRights' => [ 'block' ],
				'expectedRights' => [ 'block' ],
			],
			'Adding empty array of rights does not fail' => [
				'groupName' => 'steward',
				'addRights' => [],
				'expectedRights' => [ 'read', 'delete' ],
			],
		];
	}

	/** @dataProvider provideRemoveRights */
	public function testRemoveRights(
		string $groupName,
		array $removeRights,
		array $expectedRights
	): void {
		$manager = $this->createManager();
		$status = $manager->removeRightsFromGroup( $groupName, $removeRights );

		$this->assertStatusGood( $status );
		$remainingRights = $manager->getRightsForGroup( $groupName );
		$this->assertArrayEquals( $expectedRights, $remainingRights );
	}

	public static function provideRemoveRights(): array {
		return [
			'Remove rights from group' => [
				'groupName' => 'steward',
				'removeRights' => [ 'delete' ],
				'expectedRights' => [ 'read' ],
			],
			'Remove unassigned rights from group' => [
				'groupName' => 'steward',
				'removeRights' => [ 'block' ],
				'expectedRights' => [ 'read', 'delete' ],
			],
			'Remove rights from group that does not exist' => [
				'groupName' => 'ghost',
				'removeRights' => [ 'hideuser' ],
				'expectedRights' => [],
			],
			'Removing empty array of rights does not fail' => [
				'groupName' => 'steward',
				'removeRights' => [],
				'expectedRights' => [ 'read', 'delete' ],
			],
		];
	}

	public function testRemoveRights_failsWhenGroupHasMembers(): void {
		$this->addGroupMember( 'steward' );
		$manager = $this->createManager();
		$status = $manager->removeRightsFromGroup( 'steward', [ 'read', 'delete' ] );
		$this->assertStatusError( 'centralauth-editgroup-delete-removemembers', $status );
	}

	/** @dataProvider provideRemoveGroup */
	public function testRemoveGroup(
		string $groupName,
		int $memberCount,
		?string $expectedError,
		array $expectedRights
	): void {
		for ( $i = 1; $i <= $memberCount; $i++ ) {
			$this->addGroupMember( $groupName, $i );
		}

		$manager = $this->createManager();
		$status = $manager->removeGroup( $groupName );
		$remainingRights = $manager->getRightsForGroup( $groupName );

		if ( $expectedError === null ) {
			$this->assertStatusGood( $status );
			$this->newSelectQueryBuilder()
				->select( 'ggr_group' )
				->table( 'global_group_restrictions' )
				->where( [ 'ggr_group' => $groupName ] )
				->caller( __METHOD__ )
				->assertEmptyResult();
		} else {
			$this->assertStatusError( $expectedError, $status );
		}
		$this->assertArrayEquals( $expectedRights, $remainingRights );
	}

	public static function provideRemoveGroup(): array {
		return [
			'Group is empty' => [
				'groupName' => 'steward',
				'memberCount' => 0,
				'expectedError' => null,
				'expectedRights' => [],
			],
			'Group has a member' => [
				'groupName' => 'steward',
				'memberCount' => 1,
				'expectedError' => 'centralauth-editgroup-delete-removemembers',
				'expectedRights' => [ 'read', 'delete' ],
			],
			'Group has a wiki set, which should be unlinked as well' => [
				'groupName' => 'global-sysop',
				'memberCount' => 0,
				'expectedError' => null,
				'expectedRights' => [],
			],
			'Group has a wiki set, but it should not be unlinked, because the group is non-empty' => [
				'groupName' => 'global-sysop',
				'memberCount' => 1,
				'expectedError' => 'centralauth-editgroup-delete-removemembers',
				'expectedRights' => [ 'read' ],
			],
		];
	}

	/** @dataProvider provideRenameGroup */
	public function testRenameGroup(
		string $oldName,
		string $newName,
		?string $expectedError,
	): void {
		$this->addGroupMember( $oldName );

		$manager = $this->createManager();
		$rightsBeforeRename = $manager->getRightsForGroup( $oldName );
		$wikiSetBeforeRename = WikiSet::getWikiSetForGroup( $oldName );

		$status = $manager->renameGroup( $oldName, $newName );

		$oldGroupRights = $manager->getRightsForGroup( $oldName );
		$oldMemberCount = $this->getMemberCount( $oldName );
		$oldWikiSet = WikiSet::getWikiSetForGroup( $oldName );
		$newGroupRights = $manager->getRightsForGroup( $newName );
		$newMemberCount = $this->getMemberCount( $newName );
		$newWikiSet = WikiSet::getWikiSetForGroup( $newName );

		// Verify all three parts of group rename - rights, members and wiki set
		if ( $expectedError === null ) {
			$this->assertStatusGood( $status );
			$this->assertArrayEquals( $rightsBeforeRename, $newGroupRights );
			$this->assertCount( 0, $oldGroupRights );

			$this->assertSame( 1, $newMemberCount );
			$this->assertSame( 0, $oldMemberCount );

			$this->assertSame( $wikiSetBeforeRename, $newWikiSet );
			$this->assertSame( 0, $oldWikiSet );
		} else {
			$this->assertStatusError( $expectedError, $status );
			$this->assertArrayEquals( $rightsBeforeRename, $oldGroupRights );
			$this->assertSame( 1, $oldMemberCount );
			$this->assertSame( $wikiSetBeforeRename, $oldWikiSet );
		}
	}

	public static function provideRenameGroup(): array {
		return [
			'Rename to an untaken name' => [
				'oldName' => 'steward',
				'newName' => 'commander',
				'expectedError' => null,
			],
			'Rename to a taken name' => [
				'oldName' => 'steward',
				'newName' => 'global-sysop',
				'expectedError' => 'centralauth-editgroup-rename-taken',
			],
		];
	}

	/** @dataProvider provideSetWikiSet */
	public function testSetWikiSet(
		string $groupName,
		?int $wikiSetId,
		int $expectedWikiSet
	): void {
		$manager = $this->createManager();
		$status = $manager->setWikiSet( $groupName, $wikiSetId );

		$this->assertStatusGood( $status );
		$newWikiSetId = WikiSet::getWikiSetForGroup( $groupName );
		$this->assertSame( $expectedWikiSet, $newWikiSetId );
	}

	public static function provideSetWikiSet(): array {
		return [
			'Wiki set assigned to a group that did not have it' => [
				'groupName' => 'steward',
				'wikiSetId' => 1,
				'expectedWikiSet' => 1,
			],
			'Wiki set assigned to a group that had it already' => [
				'groupName' => 'global-sysop',
				'wikiSetId' => 2,
				'expectedWikiSet' => 2,
			],
			'Unassign wiki set by passing 0' => [
				'groupName' => 'global-sysop',
				'wikiSetId' => 0,
				'expectedWikiSet' => 0,
			],
			'Unassign wiki set by passing null' => [
				'groupName' => 'global-sysop',
				'wikiSetId' => null,
				'expectedWikiSet' => 0,
			],
			'Unassigning wiki set from group that did not have it should not fail' => [
				'groupName' => 'steward',
				'wikiSetId' => 0,
				'expectedWikiSet' => 0,
			]
		];
	}

	public function testSetWikiSet_failsForNonExistentGroup(): void {
		$manager = $this->createManager();
		$status = $manager->setWikiSet( 'does-not-exist', 7 );

		$this->assertStatusError( 'centralauth-editgroup-nonexistent', $status );
	}

	private function createManager(): GlobalGroupManager {
		$dbManager = $this->createMock( CentralAuthDatabaseManager::class );
		$dbManager->method( 'getCentralDBFromRecency' )->willReturn( $this->getDb() );
		$dbManager->method( 'getCentralPrimaryDB' )->willReturn( $this->getDb() );
		return new GlobalGroupManager( $dbManager );
	}

	private function addGroupMember( string $groupName, int $userId = 1 ): void {
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'global_user_groups' )
			->row( [ 'gug_user' => $userId, 'gug_group' => $groupName ] )
			->caller( __METHOD__ )
			->execute();
	}

	private function getMemberCount( string $groupName ): int {
		return $this->getDb()->newSelectQueryBuilder()
			->from( 'global_user_groups' )
			->where( [ 'gug_group' => $groupName ] )
			->caller( __METHOD__ )
			->fetchRowCount();
	}

	public function addDBData() {
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'global_group_permissions' )
			->rows( [
				[ 'ggp_group' => 'steward', 'ggp_permission' => 'read' ],
				[ 'ggp_group' => 'steward', 'ggp_permission' => 'delete' ],
				[ 'ggp_group' => 'global-sysop', 'ggp_permission' => 'read' ],
			] )
			->caller( __METHOD__ )
			->execute();
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'global_group_restrictions' )
			->row( [ 'ggr_group' => 'global-sysop', 'ggr_set' => 42 ] )
			->caller( __METHOD__ )
			->execute();
	}
}
