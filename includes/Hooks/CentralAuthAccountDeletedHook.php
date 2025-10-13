<?php
namespace MediaWiki\Extension\CentralAuth\Hooks;

/**
 * This is a hook handler interface, see docs/Hooks.md in core.
 * Use the hook name "CentralAuthAccountDeleted" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 */
interface CentralAuthAccountDeletedHook {

	/**
	 * This hook is fired whenenver a global user is deleted. Note that when this hook runs, the CentralAuthUser
	 * no longer exists.
	 *
	 * @since 1.45
	 */
	public function onCentralAuthAccountDeleted( int $userID, string $userName );
}
