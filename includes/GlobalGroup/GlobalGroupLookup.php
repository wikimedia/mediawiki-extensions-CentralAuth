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

namespace MediaWiki\Extension\CentralAuth\GlobalGroup;

use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * @since 1.37
 * @author Taavi "Majavah" Väänänen <hi@taavi.wtf>
 */
class GlobalGroupLookup {

	private CentralAuthDatabaseManager $dbManager;

	public function __construct( CentralAuthDatabaseManager $dbManager ) {
		$this->dbManager = $dbManager;
	}

	/**
	 * Returns all defined global groups.
	 * @param int $flags One of the IDBAccessObject::READ_* constants
	 * @return string[]
	 */
	public function getDefinedGroups( int $flags = IDBAccessObject::READ_NORMAL ): array {
		$dbr = $this->dbManager->getCentralDBFromRecency( $flags );
		return $dbr->newSelectQueryBuilder()
			->select( 'ggp_group' )
			->distinct()
			->from( 'global_group_permissions' )
			->recency( $flags )
			->caller( __METHOD__ )
			->fetchFieldValues();
	}

	/**
	 * Returns all rights assigned to a specified global group.
	 * @param string $group
	 * @param int $flags One of the IDBAccessObject::READ_* constants
	 * @return string[]
	 */
	public function getRightsForGroup( string $group, int $flags = IDBAccessObject::READ_NORMAL ): array {
		$dbr = $this->dbManager->getCentralDBFromRecency( $flags );
		return $dbr->newSelectQueryBuilder()
			->select( 'ggp_permission' )
			->from( 'global_group_permissions' )
			->where( [ 'ggp_group' => $group ] )
			->recency( $flags )
			->caller( __METHOD__ )
			->fetchFieldValues();
	}

	/**
	 * Returns all global groups with a specified permission.
	 *
	 * @since 1.44
	 * @param string $permission
	 * @param int $flags {@link IDBAccessObject} flags
	 * @return string[] internal global group names with the given permission
	 */
	public function getGroupsWithPermission( string $permission, int $flags = IDBAccessObject::READ_NORMAL ): array {
		$dbr = $this->dbManager->getCentralDBFromRecency( $flags );
		return $dbr->newSelectQueryBuilder()
			->select( 'ggp_group' )
			->from( 'global_group_permissions' )
			->where( [ 'ggp_permission' => $permission ] )
			->recency( $flags )
			->caller( __METHOD__ )
			->fetchFieldValues();
	}
}
