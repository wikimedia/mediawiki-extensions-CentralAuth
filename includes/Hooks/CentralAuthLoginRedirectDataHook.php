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
 * Use the hook name "CentralAuthLoginRedirectData" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 * @author Taavi "Majavah" Väänänen
 */
interface CentralAuthLoginRedirectDataHook {

	/**
	 * This hook is called after a user has logged in to a single wiki during the login process,
	 * when saving any required data to the token store before redirecting to the login wiki.
	 *
	 * @param CentralAuthUser $centralAuthUser User logging in
	 * @param array &$data Data stored on the token store that can be used on the login wiki.
	 *
	 * @return bool|void True or no return value to continue or false to abort
	 * @since 1.39
	 */
	public function onCentralAuthLoginRedirectData( CentralAuthUser $centralAuthUser, array &$data );
}
