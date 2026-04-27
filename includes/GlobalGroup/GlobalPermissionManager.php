<?php
/**
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\GlobalGroup;

use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;

/**
 * This class is responsible for resolving a given {@see CentralAuthUser}'s state to permissions that they are
 * able to use, based on their global group membership and potentially other configurations.
 *
 * This class *does not* manage what permissions are assigned to which groups. For that, see {@see GlobalGroupManager}.
 *
 * @since 1.46
 */
class GlobalPermissionManager {

	private array $userPermissionCache = [];

	public function __construct(
		private readonly GlobalGroupManager $globalGroupManager
	) {
	}

	/**
	 * Clears the in-memory cache for the given user's global permissions.
	 */
	public function invalidateUserPermissionCache( CentralAuthUser $user ): void {
		unset( $this->userPermissionCache[$user->getId()] );
	}

	/**
	 * Returns a list of global groups that have impact on the user's rights on the current wiki.
	 * @return list<string>
	 */
	public function getUserEffectiveGroups( CentralAuthUser $user ): array {
		if ( !$user->exists() || !$user->isAttached() ) {
			return [];
		}

		$allGroups = $user->getGlobalGroups();
		$groupsActiveOnWiki = [];

		foreach ( $allGroups as $group ) {
			$wikiSet = $this->globalGroupManager->getGroupWikiSet( $group );
			if ( $wikiSet === null || $wikiSet->inSet() ) {
				$groupsActiveOnWiki[] = $group;
			}
		}
		return $groupsActiveOnWiki;
	}

	/**
	 * List all permissions that the specified user has due to their membership in global groups
	 * @return list<string>
	 */
	public function getUserPermissions( CentralAuthUser $user ): array {
		if ( !$user->exists() || !$user->isAttached() ) {
			return [];
		}

		$userId = $user->getId();
		if ( !isset( $this->userPermissionCache[$userId] ) ) {
			$this->userPermissionCache[$userId] = $this->getUserPermissionsInternal( $user );
		}
		return $this->userPermissionCache[$userId];
	}

	/**
	 * Backend for {@see getUserPermissions}, does the actual job of resolving user's effective groups into rights
	 * @return list<string>
	 */
	private function getUserPermissionsInternal( CentralAuthUser $user ): array {
		$groups = $this->getUserEffectiveGroups( $user );
		$permissions = [];
		foreach ( $groups as $group ) {
			$permissions = array_merge(
				$permissions,
				$this->globalGroupManager->getRightsForGroup( $group )
			);
		}

		return array_values( array_unique( $permissions ) );
	}
}
