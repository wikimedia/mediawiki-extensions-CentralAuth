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

use DBAccessObjectUtils;
use IDBAccessObject;
use MediaWiki\Config\Config;
use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\UserIdentity;
use MediaWiki\WikiMap\WikiMap;

/**
 * Look up central IDs using CentralAuth
 */
class CentralAuthIdLookup extends CentralIdLookup {

	private Config $config;
	private CentralAuthDatabaseManager $databaseManager;

	public function __construct( Config $config, CentralAuthDatabaseManager $databaseManager ) {
		$this->config = $config;
		$this->databaseManager = $databaseManager;
	}

	/** @inheritDoc */
	public function lookupCentralIds(
		array $idToName, $audience = self::AUDIENCE_PUBLIC, $flags = IDBAccessObject::READ_NORMAL
	): array {
		if ( !$idToName ) {
			return [];
		}

		$audience = $this->checkAudience( $audience );
		$fromPrimaryDb = DBAccessObjectUtils::hasFlags( $flags, IDBAccessObject::READ_LATEST );
		$db = $this->databaseManager->getCentralDBFromRecency( $flags );

		$res = $db->newSelectQueryBuilder()
			->queryInfo( CentralAuthUser::selectQueryInfo() )
			->where( [ 'gu_id' => array_map( 'intval', array_keys( $idToName ) ) ] )
			->caller( __METHOD__ )
			->fetchResultSet();
		foreach ( $res as $row ) {
			$centralUser = CentralAuthUser::newFromRow( $row, [], $fromPrimaryDb );
			if ( $centralUser->getHiddenLevelInt() === CentralAuthUser::HIDDEN_LEVEL_NONE
				|| $audience === null || $audience->isAllowed( 'centralauth-suppress' )
			) {
				$idToName[$centralUser->getId()] = $centralUser->getName();
			} else {
				$idToName[$centralUser->getId()] = '';
			}
		}

		return $idToName;
	}

	/** @inheritDoc */
	public function lookupUserNames(
		array $nameToId, $audience = self::AUDIENCE_PUBLIC, $flags = IDBAccessObject::READ_NORMAL
	): array {
		if ( !$nameToId ) {
			return [];
		}

		$audience = $this->checkAudience( $audience );
		$fromPrimaryDb = DBAccessObjectUtils::hasFlags( $flags, IDBAccessObject::READ_LATEST );
		$db = $this->databaseManager->getCentralDBFromRecency( $flags );

		$res = $db->newSelectQueryBuilder()
			->queryInfo( CentralAuthUser::selectQueryInfo() )
			->where( [ 'gu_name' => array_map( 'strval', array_keys( $nameToId ) ) ] )
			->caller( __METHOD__ )
			->fetchResultSet();
		foreach ( $res as $row ) {
			$centralUser = CentralAuthUser::newFromRow( $row, [], $fromPrimaryDb );
			if ( $centralUser->getHiddenLevelInt() === CentralAuthUser::HIDDEN_LEVEL_NONE
				|| $audience === null || $audience->isAllowed( 'centralauth-suppress' )
			) {
				$nameToId[$centralUser->getName()] = $centralUser->getId();
			}
		}

		return $nameToId;
	}

	/** @inheritDoc */
	public function isAttached( UserIdentity $user, $wikiId = UserIdentity::LOCAL ): bool {
		$wikiId = $wikiId ?: WikiMap::getCurrentWikiId();
		$centralUser = CentralAuthUser::getInstance( $user );
		return $centralUser->exists() && $centralUser->attachedOn( $wikiId );
	}

	/** @inheritDoc */
	public function isOwned( UserIdentity $user, $wikiId = UserIdentity::LOCAL ): bool {
		$user->assertWiki( $wikiId );

		$centralUser = CentralAuthUser::getInstance( $user );

		$strictMode = $this->config->get( 'CentralAuthStrict' );
		if ( $centralUser->exists() && !$user->isRegistered() && $strictMode ) {
			// Even if the user doesn't exist locally, the username is reserved for the central user, as
			// it will be automatically attached on login, and can't be taken by any other user (T371340).
			// CentralAuthPrimaryAuthenticationProvider guarantees this.
			return true;
		}

		return $this->isAttached( $user, $wikiId );
	}

	/** @inheritDoc */
	public function centralIdFromLocalUser(
		UserIdentity $user, $audience = self::AUDIENCE_PUBLIC, $flags = IDBAccessObject::READ_NORMAL
	): int {
		// This is only an optimization to take advantage of cache in CentralAuthUser.
		// The result should be the same as calling the parent method.
		return $this->isAttached( $user ) ? CentralAuthUser::getInstance( $user )->getId() : 0;
	}

}
