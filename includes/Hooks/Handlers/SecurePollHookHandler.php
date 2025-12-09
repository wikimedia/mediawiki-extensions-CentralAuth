<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\Hooks\Handlers;

use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\SecurePoll\Hooks\SecurePoll_GetUserParamsHook;
use MediaWiki\Extension\SecurePoll\User\LocalAuth;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\User\User;
use MediaWiki\WikiMap\WikiMap;

class SecurePollHookHandler implements SecurePoll_GetUserParamsHook {

	private NamespaceInfo $namespaceInfo;

	public function __construct( NamespaceInfo $namespaceInfo ) {
		$this->namespaceInfo = $namespaceInfo;
	}

	/**
	 * @param LocalAuth $localAuth Unused
	 * @param User $user
	 * @param array &$params
	 * @return bool
	 */
	public function onSecurePoll_GetUserParams( LocalAuth $localAuth, User $user, array &$params ) {
		if ( !$user->isRegistered() ) {
			return true;
		}

		$centralUser = CentralAuthUser::getInstance( $user );
		if ( !( $centralUser->exists() && $centralUser->isAttached() ) ) {
			return true;
		}

		$wikiID = $centralUser->getHomeWiki();
		if ( strval( $wikiID ) === '' ) {
			return true;
		}

		$wiki = WikiMap::getWiki( $wikiID );
		$wikiUrl = $wiki->getUrl( '' );
		$parts = explode( '/', $wikiUrl );
		if ( isset( $parts[2] ) ) {
			$params['properties']['ca-local-domain'] = $params['domain'];
			$params['domain'] = $parts[2];
		}

		$params['properties']['ca-local-url'] = $params['url'];
		$params['url'] = $wiki->getUrl(
			$this->namespaceInfo->getCanonicalName( NS_USER ) . ':' . $user->getTitleKey()
		);
		return true;
	}
}
