<?php
namespace MediaWiki\Extension\CentralAuth\Hooks;

use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;

/**
 * This is a hook handler interface, see docs/Hooks.md in core.
 * Use the hook name "CentralAuthUserVisibilityChanged" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 */
interface CentralAuthUserVisibilityChangedHook {

	/**
	 * This hook is fired whenenver a global user's visibility changes.
	 *
	 * @since 1.45
	 * @param CentralAuthUser $centralAuthUser The user whose visibility was changed.
	 * @param int $newVisibility One of the CentralAuthUser::HIDDEN_LEVEL_* constants
	 */
	public function onCentralAuthUserVisibilityChanged(
		CentralAuthUser $centralAuthUser,
		int $newVisibility
	);
}
