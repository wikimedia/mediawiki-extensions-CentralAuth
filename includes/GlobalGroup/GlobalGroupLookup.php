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

use DBAccessObjectUtils;
use IDBAccessObject;
use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;

/**
 * @since 1.37
 * @author Taavi "Majavah" Väänänen <hi@taavi.wtf>
 */
class GlobalGroupLookup implements IDBAccessObject {
	/** @var CentralAuthDatabaseManager */
	private $dbManager;

	/**
	 * @param CentralAuthDatabaseManager $dbManager
	 */
	public function __construct( CentralAuthDatabaseManager $dbManager ) {
		$this->dbManager = $dbManager;
	}

	/**
	 * Returns all defined global groups.
	 * @param int $flags One of the self::READ_* constants
	 * @return string[]
	 */
	public function getDefinedGroups( int $flags = self::READ_NORMAL ): array {
		[ $dbIndex, $dbOptions ] = DBAccessObjectUtils::getDBOptions( $flags );
		return $this->dbManager
			->getCentralDB( $dbIndex )
			->selectFieldValues(
				'global_group_permissions',
				'distinct ggp_group',
				[],
				__METHOD__,
				$dbOptions
			);
	}

	/**
	 * Returns all rights assigned to a specified global group.
	 * @param string $group
	 * @param int $flags One of the self::READ_* constants
	 * @return string[]
	 */
	public function getRightsForGroup( string $group, int $flags = self::READ_NORMAL ): array {
		[ $dbIndex, $dbOptions ] = DBAccessObjectUtils::getDBOptions( $flags );
		return $this->dbManager
			->getCentralDB( $dbIndex )
			->selectFieldValues(
				'global_group_permissions',
				'ggp_permission',
				[ 'ggp_group' => $group ],
				__METHOD__,
				$dbOptions
			);
	}
}
