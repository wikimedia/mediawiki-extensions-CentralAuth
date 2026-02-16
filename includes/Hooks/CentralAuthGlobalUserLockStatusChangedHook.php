<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\CentralAuth\Hooks;

use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;

/**
 * This is a hook handler interface, see docs/Hooks.md in core.
 * Use the hook name "CentralAuthGlobalUserLockStatusChanged" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 */
interface CentralAuthGlobalUserLockStatusChangedHook {

	/**
	 * This hook is fired whenever a global user account is locked or unlocked.
	 *
	 * @since 1.46
	 * @param CentralAuthUser $centralAuthUser The user whose lock status was changed.
	 * @param bool $isLocked Whether the user is now locked (true) or unlocked (false).
	 */
	public function onCentralAuthGlobalUserLockStatusChanged(
		CentralAuthUser $centralAuthUser,
		bool $isLocked
	): void;
}
