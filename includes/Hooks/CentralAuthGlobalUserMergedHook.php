<?php

namespace MediaWiki\Extension\CentralAuth\Hooks;

/**
 * This is a hook handler interface, see docs/Hooks.md.
 * Use the hook name "CentralAuthGlobalUserMerged" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 * @since 1.36
 */
interface CentralAuthGlobalUserMergedHook {

	/**
	 * Called after two global users have been merged.
	 * @param string $oldName The old username of the central user (as provided by CentralAuthUser::getName())
	 * @param string $newName The new username of the central user (as provided by CentralAuthUser::getName())
	 * @param int $oldId The old ID of the central user (as provided by CentralAuthUser::getId())
	 * @param int $newId The new ID of the central user (as provided by CentralAuthUser::getId())
	 */
	public function onCentralAuthGlobalUserMerged( string $oldName, string $newName, int $oldId, int $newId ) : void;

}
