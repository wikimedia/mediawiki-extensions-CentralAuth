<?php

namespace MediaWiki\Extension\CentralAuth\Hooks;

/**
 * This is a hook handler interface, see docs/Hooks.md in core.
 * Use the hook name "CentralAuthWikiList" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 * @author Taavi "Majavah" Väänänen
 */
interface CentralAuthWikiListHook {
	/**
	 * This hook is called when discovering the list of other wikis on this wiki farm.
	 *
	 * @param array|null &$wikiList List of wikis on this wiki farm, or null.
	 * If null after hook execution, value of $wgLocalDatabases will be used.
	 * @return bool|void True or no return value to continue or false to abort
	 * @since 1.37
	 */
	public function onCentralAuthWikiList( ?array &$wikiList );
}
