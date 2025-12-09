<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupLookup;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * @covers \MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupLookup
 * @group Database
 * @author Taavi "Majavah" Väänänen <hi@taavi.wtf>
 */
class GlobalGroupLookupTest extends MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider provideFlags
	 */
	public function testGetDefinedGroups( int $flags ) {
		$dbManager = $this->createMock( CentralAuthDatabaseManager::class );
		$dbManager->method( 'getCentralDBFromRecency' )->willReturn( $this->getDb() );

		$lookup = new GlobalGroupLookup( $dbManager );
		$groups = $lookup->getDefinedGroups( $flags );

		$this->assertArrayEquals( [ 'steward', 'global-sysop' ], $groups );
	}

	/**
	 * @dataProvider provideFlags
	 */
	public function testGetRightsForGroup( int $flags ) {
		$dbManager = $this->createMock( CentralAuthDatabaseManager::class );
		$dbManager->method( 'getCentralDBFromRecency' )->willReturn( $this->getDb() );

		$lookup = new GlobalGroupLookup( $dbManager );
		$stewardRights = $lookup->getRightsForGroup( 'steward', $flags );
		$globalSysopRights = $lookup->getRightsForGroup( 'global-sysop', $flags );
		$nonexistentGroupRights = $lookup->getRightsForGroup( 'does-not-exist', $flags );

		$this->assertArrayEquals( [ 'read', 'delete' ], $stewardRights );
		$this->assertArrayEquals( [ 'read' ], $globalSysopRights );
		$this->assertArrayEquals( [], $nonexistentGroupRights );
	}

	/** @dataProvider provideFlags */
	public function testGetGroupsWithPermission( int $flags ) {
		$dbManager = $this->createMock( CentralAuthDatabaseManager::class );
		$dbManager->method( 'getCentralDBFromRecency' )
			->with( $flags )
			->willReturn( $this->getDb() );

		$lookup = new GlobalGroupLookup( $dbManager );
		$groupsWithReadRight = $lookup->getGroupsWithPermission( 'read', $flags );
		$groupsWithDeleteRight = $lookup->getGroupsWithPermission( 'delete', $flags );
		$groupsWithNonExistentRight = $lookup->getGroupsWithPermission( 'does-not-exist', $flags );

		$this->assertArrayEquals( [ 'steward', 'global-sysop' ], $groupsWithReadRight );
		$this->assertArrayEquals( [ 'steward' ], $groupsWithDeleteRight );
		$this->assertCount( 0, $groupsWithNonExistentRight );
	}

	public static function provideFlags(): array {
		return [
			'READ_NORMAL' => [ IDBAccessObject::READ_NORMAL ],
			'READ_LATEST' => [ IDBAccessObject::READ_LATEST ],
		];
	}

	public function addDBDataOnce() {
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'global_group_permissions' )
			->rows( [
				[ 'ggp_group' => 'steward', 'ggp_permission' => 'read' ],
				[ 'ggp_group' => 'steward', 'ggp_permission' => 'delete' ],
				[ 'ggp_group' => 'global-sysop', 'ggp_permission' => 'read' ],
			] )
			->caller( __METHOD__ )
			->execute();
	}
}
