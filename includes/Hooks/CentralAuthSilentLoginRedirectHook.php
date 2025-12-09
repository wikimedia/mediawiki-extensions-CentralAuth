<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\Hooks;

use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;

/**
 * This is a hook handler interface, see docs/Hooks.md in core.
 * Use the hook name "CentralAuthPostLoginRedirect" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 * @author Taavi "Majavah" Väänänen
 */
interface CentralAuthSilentLoginRedirectHook {

	/**
	 * This hook is called during the cross-wiki login process when the user is redirected back
	 * to the original wiki.
	 *
	 * @param CentralAuthUser $centralAuthUser User logging in
	 * @param string &$url URL to redirect the user back to
	 * @param array $data Data retrieved from the token store set when the login process started
	 *
	 * @return bool|void True or no return value to continue or false to abort
	 * @since 1.39
	 */
	public function onCentralAuthSilentLoginRedirect( CentralAuthUser $centralAuthUser, string &$url, array $data );
}
