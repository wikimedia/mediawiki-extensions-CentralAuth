<?php

namespace MediaWiki\Extension\CentralAuth\Hooks;

/**
 * This is a hook handler interface, see docs/Hooks.md.
 * Use the hook name "CentralAuthWikiList" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 * @since 1.36
 */
interface CentralAuthWikiListHook {

	/**
	 * Allows extensions to provide the list of known wikis.
	 * @param array|null &$wikiList Array of strings (string[]) representing a list of wikis, or null if not available.
	 * Defaults to null.
	 *
	 * @return bool|void True or no return value to continue or false to abort running remaining hook handlers.
	 */
	public function onCentralAuthWikiList( ?array &$wikiList );

}
