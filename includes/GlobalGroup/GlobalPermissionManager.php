<?php
/**
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\GlobalGroup;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\User\RestrictedUserGroupConfigReader;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserRequirementsConditionCheckerFactory;
use MediaWiki\WikiMap\WikiMap;

/**
 * This class is responsible for resolving a given {@see CentralAuthUser}'s state to permissions that they are
 * able to use, based on their global group membership and potentially other configurations.
 *
 * This class *does not* manage what permissions are assigned to which groups. For that, see {@see GlobalGroupManager}.
 *
 * @since 1.46
 */
class GlobalPermissionManager {

	/** @internal For use in ServiceWiring */
	public const CONSTRUCTOR_OPTIONS = [
		CAMainConfigNames::CentralAuthCentralWiki
	];

	private array $userPermissionCache = [];

	public function __construct(
		private readonly UserFactory $userFactory,
		private readonly ServiceOptions $options,
		private readonly UserGroupManager $userGroupManager,
		private readonly RestrictedUserGroupConfigReader $restrictedUserGroupConfigReader,
		private readonly UserRequirementsConditionCheckerFactory $userRequirementsConditionCheckerFactory,
		private readonly GlobalGroupManager $globalGroupManager
	) {
		$this->options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
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
		$activeGroups = $this->getUserGroupsActiveOnWiki( $user );
		if ( $activeGroups === [] ) {
			return [];
		}

		$disabledGroups = $this->getUserDisabledGroups( $user );

		return array_values( array_diff( $activeGroups, $disabledGroups ) );
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

	/**
	 * Returns a list of the user's global groups that are active on the current wiki. Groups are active,
	 * if the current wiki is in the wiki set configured for that group.
	 *
	 * Some of the groups returned by this method may be disabled for the user depending on additional
	 * per-user conditions. See {@see getUserDisabledGroups} for more details.
	 *
	 * @return list<string>
	 */
	public function getUserGroupsActiveOnWiki( CentralAuthUser $user ): array {
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
	 * Returns a list of global groups that the user is in, but don't affect the user's permissions.
	 * It's analogous to {@see UserGroupManager::getUserDisabledGroups()}.
	 *
	 * Groups returned by this method are disabled on per-user basis (for example because target is ineligible
	 * for membership, as configured in {@see $wgRestrictedGroups}). Groups from outside of this wiki's wiki sets
	 * are not included in the result, as they are not disabled per-user, but per-wiki.
	 *
	 * @return list<string>
	 */
	public function getUserDisabledGroups( CentralAuthUser $user ): array {
		$localId = $user->getLocalId( WikiMap::getCurrentWikiId() ) ?? 0;
		$localIdentity = new UserIdentityValue( $localId, $user->getName() );

		// Check if the user is system user. Given that such accounts cannot be logged in to and are controlled by
		// software, we can keep all their user groups enabled. These accounts may also ignore permission checks,
		// so in some cases the group membership is only declarative.
		if ( $this->userFactory->newFromUserIdentity( $localIdentity )->isSystemUser() ) {
			return [];
		}

		$groups = $this->getUserGroupsActiveOnWiki( $user );

		$centralWiki = $this->options->get( CAMainConfigNames::CentralAuthCentralWiki ) ?? false;
		$restrictedGroups = $this->restrictedUserGroupConfigReader
			->getConfig( $centralWiki, GlobalGroupAssignmentService::RESTRICTION_SCOPE );

		$checker = $this->userRequirementsConditionCheckerFactory
			->getUserRequirementsConditionChecker( $this->userGroupManager );

		$disabledGroups = [];
		foreach ( $groups as $group ) {
			if ( !array_key_exists( $group, $restrictedGroups ) ) {
				continue;
			}
			$restrictions = $restrictedGroups[$group];
			if ( !$restrictions->continuouslyEnforced() ) {
				continue;
			}

			if ( !$checker->recursivelyCheckCondition( $restrictions->getMemberConditions(), $localIdentity ) ) {
				$disabledGroups[] = $group;
			}
		}
		return $disabledGroups;
	}
}
