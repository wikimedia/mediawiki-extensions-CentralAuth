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

/**
 * @coversDefaultClass MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupLookup
 * @group Database
 * @author Taavi "Majavah" Väänänen <hi@taavi.wtf>
 */
class GlobalGroupLookupTest extends CentralAuthUsingDatabaseTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->db->insert(
			'global_group_permissions',
			[
				[ 'ggp_group' => 'steward', 'ggp_permission' => 'read' ],
				[ 'ggp_group' => 'steward', 'ggp_permission' => 'delete' ],
				[ 'ggp_group' => 'global-sysop', 'ggp_permission' => 'read' ],
			]
		);
	}

	/**
	 * @covers ::__construct
	 */
	public function testConstructor() {
		$this->assertInstanceOf(
			GlobalGroupLookup::class,
			new GlobalGroupLookup(
				$this->createMock( CentralAuthDatabaseManager::class )
			)
		);
	}

	/**
	 * @covers ::getDefinedGroups
	 * @dataProvider provideFlags
	 */
	public function testGetDefinedGroups( int $flags, int $readIndex ) {
		$dbManager = $this->createMock( CentralAuthDatabaseManager::class );
		$dbManager->method( 'getCentralDB' )
			->with( $readIndex )
			->willReturn( $this->db );

		$lookup = new GlobalGroupLookup( $dbManager );
		$groups = $lookup->getDefinedGroups( $flags );

		$this->assertArrayEquals( [ 'steward', 'global-sysop' ], $groups );
	}

	/**
	 * @covers ::getRightsForGroup
	 * @dataProvider provideFlags
	 */
	public function testGetRightsForGroup( int $flags, int $readIndex ) {
		$dbManager = $this->createMock( CentralAuthDatabaseManager::class );
		$dbManager->method( 'getCentralDB' )
			->with( $readIndex )
			->willReturn( $this->db );

		$lookup = new GlobalGroupLookup( $dbManager );
		$stewardRights = $lookup->getRightsForGroup( 'steward', $flags );
		$globalSysopRights = $lookup->getRightsForGroup( 'global-sysop', $flags );
		$nonexistentGroupRights = $lookup->getRightsForGroup( 'does-not-exist', $flags );

		$this->assertArrayEquals( [ 'read', 'delete' ], $stewardRights );
		$this->assertArrayEquals( [ 'read' ], $globalSysopRights );
		$this->assertArrayEquals( [], $nonexistentGroupRights );
	}

	public function provideFlags(): array {
		return [
			'READ_NORMAL' => [ GlobalGroupLookup::READ_NORMAL, DB_REPLICA ],
			'READ_LATEST' => [ GlobalGroupLookup::READ_LATEST, DB_PRIMARY ],
		];
	}
}
