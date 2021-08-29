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

use CentralAuthUser;
use MWNamespace;
use User;
use WikiMap;

class SecurePollHookHandler {

	/**
	 * @param mixed $auth Unused
	 * @param User $user
	 * @param array &$params
	 * @return bool
	 */
	public static function onSecurePoll_GetUserParams( $auth, $user, &$params ) {
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
			MWNamespace::getCanonicalName( NS_USER ) . ':' . $user->getTitleKey()
		);
		return true;
	}
}
