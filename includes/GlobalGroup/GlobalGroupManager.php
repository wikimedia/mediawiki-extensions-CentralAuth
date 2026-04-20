<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\GlobalGroup;

use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * This class is responsible for managing the global group configuration, such as assigned rights and
 * wiki sets the group is active on.
 *
 * This class doesn't manage what groups a user is assigned to.
 *
 * @since 1.37
 */
class GlobalGroupManager {

	public function __construct(
		private readonly CentralAuthDatabaseManager $dbManager
	) {
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

/**
 * Retain the old class name for backwards compatibility.
 * @deprecated since 1.46
 */
class_alias( GlobalGroupManager::class, 'MediaWiki\\Extension\\CentralAuth\\GlobalGroup\\GlobalGroupLookup' );
