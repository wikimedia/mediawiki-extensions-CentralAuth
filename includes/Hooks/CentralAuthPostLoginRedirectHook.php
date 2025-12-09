<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\Hooks;

/**
 * This is a hook handler interface, see docs/Hooks.md in core.
 * Use the hook name "CentralAuthPostLoginRedirect" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 * @author Taavi "Majavah" Väänänen
 */
interface CentralAuthPostLoginRedirectHook {

	/**
	 * This hook is called after the user has been redirected back to
	 * the content wiki after completing the cross-wiki login process.
	 *
	 * @param string &$returnTo page name to redirect to
	 * @param string &$returnToQuery url parameters
	 * @param bool $stickHTTPS Deprecated since MW 1.42, do not use.
	 *   Previously indicated that the login session would force the HTTPS protocol.
	 * @param string $type 'signup' on signup, empty string otherwise.
	 * @param string &$injectedHtml Deprecated since MW 1.42, do not use.
	 *   Previously allowed setting HTML to show on the login success page.
	 *
	 * @return bool|void True or no return value to continue or false to abort
	 * @since 1.39
	 */
	public function onCentralAuthPostLoginRedirect(
		string &$returnTo,
		string &$returnToQuery,
		bool $stickHTTPS,
		string $type,
		string &$injectedHtml
	);
}
