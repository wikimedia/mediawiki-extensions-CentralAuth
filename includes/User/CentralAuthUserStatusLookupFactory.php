<?php
/**
 * @license GPL-2.0-or-later
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
