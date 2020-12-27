<?php

namespace MediaWiki\Extension\CentralAuth\Hooks;

/**
 * This is a hook handler interface, see docs/Hooks.md.
 * Use the hook name "CentralAuthPostLoginRedirect" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 * @since 1.36
 */
interface CentralAuthPostLoginRedirectHook {

	/**
	 * Allows extensions to modify the redirect location after the user has finished logging in to their account.
	 * @param string &$returnTo Modifiable string representing the page name to redirect to
	 * @param string &$returnToQuery Modifiable string representing the URL parameters to use for the returning query
	 * @param bool $stickHttps Whether or not to make the redirect link continue to use HTTPS
	 * @param string $type The login redirect condition
	 * @param string &$injectHtml Modifiable string representing the HTML to inject
	 */
	public function onCentralAuthPostLoginRedirect(
		string &$returnTo,
		string &$returnToQuery,
		bool $stickHttps,
		string $type,
		string &$injectHtml
	) : void;

}
