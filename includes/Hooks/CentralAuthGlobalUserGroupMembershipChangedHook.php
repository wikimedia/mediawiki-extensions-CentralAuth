<?php
namespace MediaWiki\Extension\CentralAuth\Hooks;

use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;

/**
 * This is a hook handler interface, see docs/Hooks.md in core.
 * Use the hook name "CentralAuthGlobalUserGroupMembershipChanged" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 */
interface CentralAuthGlobalUserGroupMembershipChangedHook {

	/**
	 * This hook fires whenenver a user's global group membership changes, allowing other extensions to respond
	 * to the event. The hook itself is purely declarative.
	 *
	 * @since 1.45
	 * @param CentralAuthUser $centralAuthUser The user whose groups are being changed.
	 * @param array $oldGroups The user's original group memberships
	 * @param array $newGroups The user's new group memberships
	 *
	 * @return void
	 */
	public function onCentralAuthGlobalUserGroupMembershipChanged(
		CentralAuthUser $centralAuthUser,
		array $oldGroups,
		array $newGroups
	);
}
