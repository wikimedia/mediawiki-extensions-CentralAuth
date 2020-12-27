<?php

namespace MediaWiki\Extension\CentralAuth\Hooks;

use CentralAuthUser;

/**
 * This is a hook handler interface, see docs/Hooks.md.
 * Use the hook name "CentralAuthLoginRedirectData" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 * @since 1.36
 */
interface CentralAuthLoginRedirectDataHook {

	/**
	 * Allows an extension to intercept data before redirecting the user to the central login wiki
	 * @param CentralAuthUser $user
	 * @param array &$data An associative array of data, containing the following keys:
	 * * 'secret': A string of random data generated by MWCryptRand::generateHex()
	 * * 'name': A string representing the name of the central user (filled in by CentralAuthUser::getName())
	 * * 'guid': A int representing the global user ID of the central user (filled in by CentralAuthUser::getId())
	 * * 'wikiId': A string representing an ASCII string identifier of the wiki
	 *    (filled in by WikiMap::getCurrentWikiId())
	 * * 'secureCookies': A bool representing whether cookies are secure or not
	 * * 'finalProto': A string representing the final protocol of the page, either 'http' or 'https'
	 * * 'currentProto': A string representing the current protocol of the page, either 'http' or 'https'
	 */
	public function onCentralAuthLoginRedirectData( CentralAuthUser $user, array &$data ) : void;

}
