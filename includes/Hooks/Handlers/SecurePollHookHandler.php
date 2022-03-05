<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\Hooks\Handlers;

use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\SecurePoll\Hooks\SecurePoll_GetUserParamsHook;
use MediaWiki\Extension\SecurePoll\User\LocalAuth;
use NamespaceInfo;
use User;
use WikiMap;

class SecurePollHookHandler implements SecurePoll_GetUserParamsHook {
	/** @var NamespaceInfo */
	private $namespaceInfo;

	/**
	 * @param NamespaceInfo $namespaceInfo
	 */
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
