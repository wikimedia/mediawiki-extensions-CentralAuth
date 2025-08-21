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

namespace MediaWiki\Extension\CentralAuth\User;

use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Rdbms\IConnectionProvider;

class CentralAuthUserStatusLookupFactory {

	public function __construct(
		private readonly IConnectionProvider $connectionProvider
	) {
	}

	/**
	 * @param string|false $remoteWikiId The ID of the remote wiki where the relevant log entries are stored.
	 * If set to false, the local wiki is queried.
	 */
	public function getLookupService( string|false $remoteWikiId = false ): CentralAuthUserStatusLookupService {
		if ( $remoteWikiId !== false ) {
			$wiki = WikiMap::getWiki( $remoteWikiId );
			if ( $wiki === null ) {
				throw new \InvalidArgumentException( "Unknown wiki ID: $remoteWikiId" );
			}
		}

		return new CentralAuthUserStatusLookupService(
			$this->connectionProvider,
			$remoteWikiId
		);
	}
}
