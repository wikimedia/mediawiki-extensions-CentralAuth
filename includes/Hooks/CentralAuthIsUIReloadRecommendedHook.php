<?php

namespace MediaWiki\Extension\CentralAuth\Hooks;

use User;

/**
 * This is a hook handler interface, see docs/Hooks.md.
 * Use the hook name "CentralAuthLoginRedirectData" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 * @since 1.36
 */
interface CentralAuthIsUIReloadRecommendedHook {

	/**
	 * Allows an extension to intercept the decision if a UI reload should be recommended
	 * @param User $user
	 * @param bool &$recommendReload A bool that defaults to false.
	 * This value can be overridden by the extension calling this hook.
	 */
	public function onCentralAuthIsUIReloadRecommended( User $user, bool &$recommendReload ) : void;

}
