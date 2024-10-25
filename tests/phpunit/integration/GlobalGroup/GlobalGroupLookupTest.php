<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupLookup;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * @covers MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupLookup
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
