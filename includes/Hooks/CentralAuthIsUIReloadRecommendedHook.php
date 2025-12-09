<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\Hooks;

use MediaWiki\User\User;

/**
 * This is a hook handler interface, see docs/Hooks.md in core.
 * Use the hook name "CentralAuthIsUIReloadRecommended" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 * @author Taavi "Majavah" Väänänen
 */
interface CentralAuthIsUIReloadRecommendedHook {

	/**
	 * This hook is called after a cross-wiki auto login to figure out
	 * if the users preferences are customized enough that they should
	 * refresh the page for the preferences to take effect.
	 *
	 * @param User $user The user logging in.
	 * @param bool &$recommendReload Set to true if the user should be recommended
	 * to reload the page.
	 *
	 * @return bool|void True or no return value to continue or false to abort
	 * @since 1.39
	 */
	public function onCentralAuthIsUIReloadRecommended( User $user, bool &$recommendReload );
}
