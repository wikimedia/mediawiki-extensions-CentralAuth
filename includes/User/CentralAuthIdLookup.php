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

use MediaWiki\Config\Config;
use MediaWiki\DAO\WikiAwareEntity;
use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\UserIdentity;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Rdbms\DBAccessObjectUtils;
use Wikimedia\Rdbms\IDBAccessObject;

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
		return self::isAttachedOn( $user, $wikiId, IDBAccessObject::READ_NORMAL );
	}

	/** @inheritDoc */
	public function isOwned( UserIdentity $user, $wikiId = UserIdentity::LOCAL ): bool {
		$user->assertWiki( $wikiId );

		$centralUser = CentralAuthUser::getInstance( $user );

		$strictMode = $this->config->get( CAMainConfigNames::CentralAuthStrict );
		if ( $centralUser->exists() && !$user->isRegistered() && $strictMode ) {
			// Even if the user doesn't exist locally, the username is reserved for the central user, as
			// it will be automatically attached on login, and can't be taken by any other user (T371340).
			// CentralAuthPrimaryAuthenticationProvider guarantees this.
			return true;
		}

		return self::isAttachedOn( $user, $wikiId, IDBAccessObject::READ_NORMAL );
	}

	/** @inheritDoc */
	public function centralIdFromLocalUser(
		UserIdentity $user, $audience = self::AUDIENCE_PUBLIC, $flags = IDBAccessObject::READ_NORMAL
	): int {
		// This is only an optimization to take advantage of cache in CentralAuthUser.
		// The result should be the same as calling the parent method.
		if ( self::isAttachedOn( $user, WikiAwareEntity::LOCAL, $flags ) ) {
			return self::getCentralUserInstance( $user, $flags )->getId();
		}

		return 0;
	}

	/**
	 * Check whether an user is attached on the given wiki, reading from the primary DB if needed.
	 *
	 * @param UserIdentity $user The user whose attachment status to look up.
	 * @param string|false $wikiId The DB name of the wiki to check, or `false` to use the local wiki.
	 * @param int $flags Bitmask of IDBAccessObject::READ_* constants.
	 *
	 * @return bool `true` if the given user is attached to a central user on the given wiki,
	 * `false` otherwise.
	 */
	private static function isAttachedOn( UserIdentity $user, $wikiId, int $flags ): bool {
		$wikiId = $wikiId ?: WikiMap::getCurrentWikiId();
		$centralUser = self::getCentralUserInstance( $user, $flags );

		return $centralUser->exists() && $centralUser->attachedOn( $wikiId );
	}

	/**
	 * Get a potentially cached central user instance for the given user, reading from the primary DB if needed.
	 *
	 * @param UserIdentity $user The user to fetch the corresponding central user for.
	 * @param int $flags Bitmask of IDBAccessObject::READ_* constants.
	 *
	 * @return CentralAuthUser
	 */
	private static function getCentralUserInstance( UserIdentity $user, int $flags ): CentralAuthUser {
		return $flags & IDBAccessObject::READ_LATEST
			? CentralAuthUser::getPrimaryInstance( $user )
			: CentralAuthUser::getInstance( $user );
	}

}
