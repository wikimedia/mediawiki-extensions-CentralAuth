<?php

namespace MediaWiki\Extension\CentralAuth\Hooks;

use CentralAuthUser;

/**
 * This is a hook handler interface, see docs/Hooks.md.
 * Use the hook name "CentralAuthSilentLoginRedirect" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 * @since 1.36
 */
interface CentralAuthSilentLoginRedirectHook {

	/**
	 * @param CentralAuthUser $centralUser
	 * @param string &$url Modifiable string, representing a full URL to [[Special:CentralLogin/complete]],
	 * returns a protocol-relative URL if needed (as provided by WikiReference::getFullURL())
	 * @param array $info Array containing token information, or false if the value was not found or an error
	 * occurred (as provided by CentralAuthUtils::getKeyValueUponExistence())
	 */
	public function onCentralAuthSilentLoginRedirect(
		CentralAuthUser $centralUser,
		string &$url,
		array $info
	) : void;

}
