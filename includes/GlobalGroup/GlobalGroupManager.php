<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\GlobalGroup;

use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use MediaWiki\Extension\CentralAuth\WikiSet;
use StatusValue;
use Wikimedia\ObjectCache\WANObjectCache;
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

	/**
	 * @var list<string> List of groups that were updated in this instance. They will be read bypassing the
	 *     WANObjectCache, which is to ensure that after changing group settings, this request won't be bound
	 *     to reading its data from (now outdated) cache
	 */
	private array $updatedGroups = [];

	public function __construct(
		private readonly WANObjectCache $wanCache,
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
	 * Returns all rights assigned to a specified global group. This method uses cache.
	 * @param string $group
	 * @param int $flags One of the IDBAccessObject::READ_* constants
	 * @return string[]
	 */
	public function getRightsForGroup( string $group, int $flags = IDBAccessObject::READ_NORMAL ): array {
		if ( in_array( $group, $this->updatedGroups ) && $flags === IDBAccessObject::READ_NORMAL ) {
			$flags = IDBAccessObject::READ_LATEST;
		}
		if ( $flags !== IDBAccessObject::READ_NORMAL ) {
			return $this->getRightsForGroupInternal( $group, $flags );
		}

		$rights = $this->wanCache->getWithSetCallback(
			$this->makePermissionsCacheKey( $group ),
			WANObjectCache::TTL_MONTH,
			function () use ( $group ) {
				$rights = $this->getRightsForGroupInternal( $group, IDBAccessObject::READ_NORMAL );
				if ( $rights === [] ) {
					// Don't cache inexistent groups
					return false;
				}
				return $rights;
			},
			[ 'pcTTL' => WANObjectCache::TTL_PROC_LONG ]
		);
		if ( $rights === false ) {
			return [];
		}
		return $rights;
	}

	/**
	 * Backend for {@see getRightsForGroup}
	 */
	private function getRightsForGroupInternal( string $group, int $flags ): array {
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

	/**
	 * Checks if the specified global group is empty (i.e. has no members)
	 *
	 * @since 1.46
	 */
	public function isGroupEmpty( string $groupName, int $flags = IDBAccessObject::READ_NORMAL ): bool {
		$dbr = $this->dbManager->getCentralDBFromRecency( $flags );
		$memberCount = $dbr->newSelectQueryBuilder()
			->select( 'gug_group' )
			->from( 'global_user_groups' )
			->where( [ 'gug_group' => $groupName ] )
			->caller( __METHOD__ )
			->fetchRowCount();
		return $memberCount === 0;
	}

	/**
	 * Returns the wiki set associated with the group. This method uses cache
	 *
	 * @since 1.46
	 */
	public function getGroupWikiSet( string $groupName ): ?WikiSet {
		if ( !in_array( $groupName, $this->updatedGroups ) ) {
			$wikiSetId = $this->wanCache->getWithSetCallback(
				$this->makeWikiSetCacheKey( $groupName ),
				WANObjectCache::TTL_MONTH,
				static function () use ( $groupName ) {
					return WikiSet::getWikiSetForGroup( $groupName );
				},
				[ 'pcTTL' => WANObjectCache::TTL_PROC_LONG ]
			);
		} else {
			$wikiSetId = WikiSet::getWikiSetForGroup( $groupName );
		}

		if ( $wikiSetId === 0 ) {
			return null;
		}
		return WikiSet::newFromID( $wikiSetId );
	}

	/**
	 * Adds the specified permissions to the group. It's safe to add a right that the group already has,
	 * as it'll be deduplicated on write.
	 *
	 * If the group doesn't exist, it's created.
	 *
	 * @since 1.46
	 * @param string $groupName
	 * @param list<string> $rights
	 */
	public function addRightsToGroup( string $groupName, array $rights ): StatusValue {
		if ( $rights === [] ) {
			return StatusValue::newGood();
		}

		$dbw = $this->dbManager->getCentralPrimaryDB();

		$insertRows = [];
		foreach ( $rights as $right ) {
			$insertRows[] = [ 'ggp_group' => $groupName, 'ggp_permission' => $right ];
		}

		// Replace into the DB, to ensure that a (group, right) pair exists only once in the DB
		$dbw->newReplaceQueryBuilder()
			->replaceInto( 'global_group_permissions' )
			->uniqueIndexFields( [ 'ggp_group', 'ggp_permission' ] )
			->rows( $insertRows )
			->caller( __METHOD__ )
			->execute();

		$this->invalidateGroupCache( $groupName );
		return StatusValue::newGood();
	}

	/**
	 * Removes the specified permissions from the group. If removing all remaining permissions
	 * from the group, an attempt will be made to remove the group instead ({@see removeGroup}).
	 *
	 * It's safe to remove a right that the group doesn't have currently; such rights will be ignored.
	 *
	 * @since 1.46
	 * @param string $groupName
	 * @param list<string> $rights
	 */
	public function removeRightsFromGroup( string $groupName, array $rights ): StatusValue {
		if ( $rights === [] ) {
			return StatusValue::newGood();
		}

		$oldRights = $this->getRightsForGroup( $groupName, IDBAccessObject::READ_LATEST );
		if ( $oldRights === [] ) {
			return StatusValue::newGood();
		}

		$rightsToRemain = array_diff( $oldRights, $rights );
		if ( count( $rightsToRemain ) === 0 ) {
			// If we're trying to remove all remaining rights, defer to removeGroup(), which will
			// check for group members
			return $this->removeGroup( $groupName );
		}

		$dbw = $this->dbManager->getCentralPrimaryDB();

		// Delete the rights from DB
		$dbw->newDeleteQueryBuilder()
			->deleteFrom( 'global_group_permissions' )
			->where( [ 'ggp_group' => $groupName, 'ggp_permission' => $rights ] )
			->caller( __METHOD__ )
			->execute();

		$this->invalidateGroupCache( $groupName );
		return StatusValue::newGood();
	}

	/**
	 * Removes group from the database.
	 *
	 * A group can be removed only if it has no members. Otherwise, members have to
	 * be removed from the group first.
	 *
	 * @since 1.46
	 */
	public function removeGroup( string $groupName ): StatusValue {
		$dbw = $this->dbManager->getCentralPrimaryDB();

		// First, ensure that the group has no members
		if ( !$this->isGroupEmpty( $groupName, IDBAccessObject::READ_LATEST ) ) {
			return StatusValue::newFatal( 'centralauth-editgroup-delete-removemembers' );
		}

		// Ensure that we don't leave any stray rows related to wiki sets for this group
		$this->setWikiSet( $groupName, 0 );

		// Delete all the group's permissions, effectively removing it
		$dbw->newDeleteQueryBuilder()
			->deleteFrom( 'global_group_permissions' )
			->where( [ 'ggp_group' => $groupName ] )
			->caller( __METHOD__ )
			->execute();

		$this->invalidateGroupCache( $groupName );
		return StatusValue::newGood();
	}

	/**
	 * Changes the internal name of a group. This operation on itself has no functional impact on the wiki.
	 * All the group's configuration, as well as its members are retained.
	 *
	 * Renaming a group is possible only if the new name is not used by any group.
	 *
	 * After calling this method, ensure that you invalidate the cache of users that are members of this group
	 * ({@see CentralAuthUser::invalidateCache()}). Otherwise, users may not be able to use their rights until
	 * the cache reloads.
	 *
	 * @since 1.46
	 */
	public function renameGroup( string $groupName, string $newName ): StatusValue {
		if ( in_array( $newName, $this->getDefinedGroups( DB_PRIMARY ) ) ) {
			return StatusValue::newFatal( 'centralauth-editgroup-rename-taken', $newName );
		}

		$dbw = $this->dbManager->getCentralPrimaryDB();
		$updates = [
			'global_group_permissions' => 'ggp_group',
			'global_group_restrictions' => 'ggr_group',
			'global_user_groups' => 'gug_group'
		];

		foreach ( $updates as $table => $field ) {
			$dbw->newUpdateQueryBuilder()
				->update( $table )
				->set( [ $field => $newName ] )
				->where( [ $field => $groupName ] )
				->caller( __METHOD__ )
				->execute();
		}

		// In theory, it's sufficient to invalidate only the cache for old group, but for safety let's do both
		$this->invalidateGroupCache( $groupName );
		$this->invalidateGroupCache( $newName );
		return StatusValue::newGood();
	}

	/**
	 * Specifies the wiki set on which the group is active.
	 *
	 * If the group is to be active on all wikis, use wiki set id of `0` or `null` (both have the same effect).
	 *
	 * @since 1.46
	 */
	public function setWikiSet( string $groupName, ?int $wikiSetId ): StatusValue {
		if ( !in_array( $groupName, $this->getDefinedGroups( DB_PRIMARY ) ) ) {
			return StatusValue::newFatal( 'centralauth-editgroup-nonexistent', $groupName );
		}

		$dbw = $this->dbManager->getCentralPrimaryDB();

		if ( $wikiSetId === 0 || $wikiSetId === null ) {
			$dbw->newDeleteQueryBuilder()
				->deleteFrom( 'global_group_restrictions' )
				->where( [ 'ggr_group' => $groupName ] )
				->caller( __METHOD__ )
				->execute();
		} else {
			$dbw->newReplaceQueryBuilder()
				->replaceInto( 'global_group_restrictions' )
				->uniqueIndexFields( 'ggr_group' )
				->row( [ 'ggr_group' => $groupName, 'ggr_set' => $wikiSetId, ] )
				->caller( __METHOD__ )
				->execute();
		}

		$this->invalidateGroupCache( $groupName );
		return StatusValue::newGood();
	}

	private function invalidateGroupCache( string $groupName ): void {
		$this->updatedGroups[] = $groupName;
		$this->wanCache->delete( $this->makePermissionsCacheKey( $groupName ) );
		$this->wanCache->delete( $this->makeWikiSetCacheKey( $groupName ) );
	}

	private function makePermissionsCacheKey( string $groupName ): string {
		return $this->wanCache->makeGlobalKey( 'global_group_permissions', $groupName );
	}

	private function makeWikiSetCacheKey( string $groupName ): string {
		return $this->wanCache->makeGlobalKey( 'global_group_wikiset', $groupName );
	}
}

/**
 * Retain the old class name for backwards compatibility.
 * @deprecated since 1.46
 */
class_alias( GlobalGroupManager::class, 'MediaWiki\\Extension\\CentralAuth\\GlobalGroup\\GlobalGroupLookup' );
