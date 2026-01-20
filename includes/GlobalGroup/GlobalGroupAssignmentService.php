<?php
/**
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\GlobalGroup;

use MediaWiki\Extension\CentralAuth\CentralAuthAutomaticGlobalGroupManager;
use MediaWiki\Extension\CentralAuth\Hooks\CentralAuthHookRunner;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\Permissions\Authority;
use MediaWiki\Title\Title;
use MediaWiki\User\UserGroupAssignmentService;
use MediaWiki\User\UserGroupMembership;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserNameUtils;
use MessageLocalizer;

/**
 * This class represents a service that provides high-level operations on user groups.
 * It's similar to {@see UserGroupAssignmentService} in core, but for global groups.
 * Contrary to the code in CentralAuthUser, this class is not interested in details of how
 * user groups are stored, but rather in the business logic of assigning and removing groups.
 *
 * Therefore, it combines group management with logging and provides permission checks.
 * Additionally, the method interfaces are designed to be suitable for calls from user-facing code.
 *
 * @since 1.45
 */
class GlobalGroupAssignmentService {

	public function __construct(
		private readonly UserNameUtils $userNameUtils,
		private readonly HookContainer $hookContainer,
		private readonly GlobalGroupLookup $globalGroupLookup,
		private readonly CentralAuthAutomaticGlobalGroupManager $automaticGroupManager,
		private readonly MessageLocalizer $messageLocalizer,
	) {
	}

	/**
	 * Checks whether the target user can have groups assigned at all.
	 */
	public function targetCanHaveUserGroups( CentralAuthUser $target ): bool {
		// Don't assign groups to non-existing and temp. accounts
		if ( !$target->exists() ) {
			return false;
		}
		if ( $this->userNameUtils->isTemp( $target->getName() ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check whether the given user can change the target user's rights.
	 */
	public function userCanChangeRights( Authority $performer, CentralAuthUser $target ): bool {
		if ( !$this->targetCanHaveUserGroups( $target ) ) {
			return false;
		}

		$available = $this->getChangeableGroups( $performer, $target );
		if ( $available['add'] || $available['remove'] ) {
			return true;
		}
		return false;
	}

	/**
	 * Returns the groups that the performer can add or remove from the target user.
	 * @return array [
	 *   'add' => [ addablegroups ],
	 *   'remove' => [ removablegroups ],
	 *   'restricted' => [ groupname => [
	 *     'condition-met' => bool,
	 *     'ignore-condition' => bool,
	 *     'message' => string
	 *   ] ]
	 *  ]
	 * @phan-return array{add:list<string>,remove:list<string>,restricted:array<string,array>}
	 */
	public function getChangeableGroups( Authority $performer, CentralAuthUser $target ): array {
		if ( !$performer->isAllowed( 'globalgroupmembership' ) ) {
			return [
				'add' => [],
				'remove' => [],
				'restricted' => [],
			];
		}

		$allGroups = $this->globalGroupLookup->getDefinedGroups();
		$automaticGroups = $this->automaticGroupManager->getAutomaticGlobalGroups();
		$manualGroups = array_values( array_diff( $allGroups, $automaticGroups ) );

		$restrictedGroups = array_fill_keys( $automaticGroups, [
			// Assume the condition here is "the group is not automatic"
			'condition-met' => false,
			'ignore-condition' => false,
			'message' => 'centralauth-globalgroupperms-automatic-group-reason',
		] );

		return [
			'add' => $manualGroups,
			'remove' => $manualGroups,
			'restricted' => $restrictedGroups,
		];
	}

	/**
	 * Changes the user groups, ensuring that the performer has the necessary permissions
	 * and that the changes are logged.
	 *
	 * @param Authority $performer
	 * @param CentralAuthUser $target
	 * @param list<string> $addGroups The groups to add (or change expiry of)
	 * @param list<string> $removeGroups The groups to remove
	 * @param array<string, ?string> $newExpiries Map of group name to new expiry (string timestamp or null
	 *   for infinite). If a group is in $addGroups but not in this array, it won't expire.
	 * @param string $reason
	 * @param array $tags
	 * @return array{0:string[],1:string[]} The groups actually added and removed
	 */
	public function saveChangesToUserGroups(
		Authority $performer,
		CentralAuthUser $target,
		array $addGroups,
		array $removeGroups,
		array $newExpiries,
		string $reason = '',
		array $tags = []
	): array {
		$oldGroups = $target->getGlobalGroupsWithExpiration();
		$oldGroupMemberships = [];
		foreach ( $oldGroups as $group => $expiration ) {
			$oldGroupMemberships[$group] = new UserGroupMembership( $target->getId(), $group, $expiration );
		}
		$changeable = $this->getChangeableGroups( $performer, $target );
		UserGroupAssignmentService::enforceChangeGroupPermissions( $addGroups, $removeGroups, $newExpiries,
			$oldGroupMemberships, $changeable );

		$this->adjustForAutomaticGlobalGroups( $target, $oldGroups, $addGroups, $removeGroups, $newExpiries );

		// Remove groups, then add new ones/update expiries of existing ones
		foreach ( $removeGroups as $group ) {
			$target->removeFromGlobalGroups( $group );
		}
		foreach ( $addGroups as $group ) {
			$expiry = $newExpiries[$group] ?? null;
			$target->addToGlobalGroup( $group, $expiry );
		}
		$newGroups = $target->getGlobalGroupsWithExpiration();
		$newGroupMemberships = [];
		foreach ( $newGroups as $group => $expiration ) {
			$newGroupMemberships[$group] = new UserGroupMembership( $target->getId(), $group, $expiration );
		}

		// Ensure that caches are cleared
		$target->invalidateCache();

		$reason = $this->getLogReason( $reason, $addGroups, $removeGroups );

		// Only add a log entry if something actually changed
		if ( $oldGroups != $newGroups ) {
			// Allow other extensions to respond to changes in global group membership
			$caHookRunner = new CentralAuthHookRunner( $this->hookContainer );
			$caHookRunner->onCentralAuthGlobalUserGroupMembershipChanged( $target, $oldGroups, $newGroups );
			$this->addLogEntry(
				$performer->getUser(),
				$target,
				$reason,
				$tags,
				$oldGroupMemberships,
				$newGroupMemberships,
			);
		}

		return [ $addGroups, $removeGroups ];
	}

	/**
	 * Add a rights log entry for an action.
	 * @param UserIdentity $performer
	 * @param CentralAuthUser $target
	 * @param string $reason
	 * @param string[] $tags Change tags for the log entry
	 * @param array<string,UserGroupMembership> $oldUGMs Associative array of (group name => UserGroupMembership)
	 * @param array<string,UserGroupMembership> $newUGMs Associative array of (group name => UserGroupMembership)
	 */
	private function addLogEntry( UserIdentity $performer, CentralAuthUser $target, string $reason,
		array $tags, array $oldUGMs, array $newUGMs
	) {
		ksort( $oldUGMs );
		ksort( $newUGMs );
		$oldUGMs = array_map( self::serialiseUgmForLog( ... ), $oldUGMs );
		$oldGroups = array_keys( $oldUGMs );
		$oldUGMs = array_values( $oldUGMs );
		$newUGMs = array_map( self::serialiseUgmForLog( ... ), $newUGMs );
		$newGroups = array_keys( $newUGMs );
		$newUGMs = array_values( $newUGMs );

		$logEntry = new ManualLogEntry( 'gblrights', 'usergroups' );
		$logEntry->setPerformer( $performer );
		$logEntry->setTarget( Title::makeTitle( NS_USER, $target->getName() ) );
		$logEntry->setComment( $reason );
		$logEntry->setParameters( [
			'oldGroups' => $oldGroups,
			'newGroups' => $newGroups,
			'oldMetadata' => $oldUGMs,
			'newMetadata' => $newUGMs,
		] );
		$logId = $logEntry->insert();
		$logEntry->addTags( $tags );
		$logEntry->publish( $logId );
	}

	/**
	 * Update the reason if any automatic global groups were changed, unless the
	 * reason already explains an automatic update due to a local group change.
	 *
	 * @param string $reason The given reason
	 * @param string[] $addedGroups
	 * @param string[] $removedGroups
	 * @return string The updated reason
	 */
	private function getLogReason(
		string $reason,
		array $addedGroups,
		array $removedGroups
	) {
		$automaticGroups = $this->automaticGroupManager->getAutomaticGlobalGroups();
		$localReason = $this->messageLocalizer->msg( 'centralauth-automatic-global-groups-reason-local' )
			->inContentLanguage()
			->text();

		if ( $reason !== $localReason ) {
			foreach ( $automaticGroups as $automaticGroup ) {
				if (
					in_array( $automaticGroup, $addedGroups ) ||
					in_array( $automaticGroup, $removedGroups )
				) {
					if ( $reason ) {
						$reason .= $this->messageLocalizer
							->msg( 'semicolon-separator' )
							->inContentLanguage()->text();
					}
					$reason .= $this->messageLocalizer
						// The parameter is unused, but declared for compatibility with old translations
						->msg( 'centralauth-automatic-global-groups-reason-global', '' )
						->inContentLanguage()->text();
					break;
				}
			}
		}

		return $reason;
	}

	/**
	 * Serialise a UserGroupMembership object for storage in the log_params section
	 * of the logging table. Only keeps essential data, removing redundant fields.
	 */
	private static function serialiseUgmForLog( UserGroupMembership $ugm ): array {
		return [ 'expiry' => $ugm->getExpiry() ];
	}

	/**
	 * Add or remove automatic global groups, or update expiries, based on:
	 * - existing global groups
	 * - existing local groups
	 * - groups we are about to add
	 * - groups we are about to remove
	 * - groups whose expiries we are about to change
	 *
	 * @param CentralAuthUser $user
	 * @param array<string,?string> $globalGroups Associative array of (group name => expiry),
	 *   representing global groups that $user already has
	 * @param string[] &$add Array of groups to add
	 * @param string[] &$remove Array of groups to remove
	 * @param array<string,?string> &$groupExpiries Associative array of (group name => expiry),
	 *   containing only those groups that are to have new expiry values set
	 */
	private function adjustForAutomaticGlobalGroups(
		CentralAuthUser $user,
		array $globalGroups,
		array &$add,
		array &$remove,
		array &$groupExpiries
	) {
		// Get the user's local groups and their expiries. If the user has the same group on
		// multiple wikis, add the latest expiry (with null representing no expiry).
		$userInfo = $user->queryAttached();
		$localGroups = [];
		foreach ( $userInfo as $info ) {
			foreach ( $info['groupMemberships'] as $groupMembership ) {
				$group = $groupMembership->getGroup();
				$expiry = $groupMembership->getExpiry();
				if ( $expiry === null ) {
					$localGroups[$group] = null;
				} elseif (
					!array_key_exists( $group, $localGroups ) ||
					( $localGroups[$group] !== null && $localGroups[$group] < $expiry )
				) {
					$localGroups[$group] = $expiry;
				}
			}
		}

		$addGroupsWithExpiries = array_intersect_key(
			$groupExpiries,
			array_fill_keys( $add, null )
		);
		$assignedGroups = array_diff_key(
			array_merge( $globalGroups, $localGroups, $addGroupsWithExpiries ),
			array_fill_keys( $remove, null )
		);

		$this->automaticGroupManager->handleAutomaticGlobalGroups(
			$assignedGroups,
			$add,
			$remove,
			$groupExpiries
		);
	}
}
