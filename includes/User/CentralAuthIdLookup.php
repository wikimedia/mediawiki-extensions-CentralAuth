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
use MediaWiki\Extension\CentralAuth\CentralAuthUserCache;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Permissions\Authority;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\UserIdentity;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\NormalizedException\NormalizedException;
use Wikimedia\Rdbms\DBAccessObjectUtils;
use Wikimedia\Rdbms\IDBAccessObject;
use Wikimedia\Rdbms\IReadableDatabase;

/**
 * Look up central IDs using CentralAuth
 */
class CentralAuthIdLookup extends CentralIdLookup {

	private Config $config;
	private CentralAuthDatabaseManager $databaseManager;
	private CentralAuthUserCache $userCache;

	/** @var array<string,bool> Names that cause a NormalizedException */
	private $badNameCache = [];

	public function __construct(
		Config $config,
		CentralAuthDatabaseManager $databaseManager,
		CentralAuthUserCache $userCache
	) {
		$this->config = $config;
		$this->databaseManager = $databaseManager;
		$this->userCache = $userCache;
	}

	/** @inheritDoc */
	public function lookupCentralIds(
		array $idToName, $audience = self::AUDIENCE_PUBLIC, $flags = IDBAccessObject::READ_NORMAL
	): array {
		if ( !$idToName ) {
			return [];
		}

		$audience = $this->checkAudience( $audience );
		$fromPrimaryDb = $this->shouldUsePrimary( $flags );
		$db = $this->getCentralDB( $flags );

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
		return $this->lookupUserNamesWithFilter( $nameToId, self::FILTER_NONE,
			$audience, $flags );
	}

	/** @inheritDoc */
	protected function lookupUserNamesWithFilter(
		array $nameToId, $filter, $audience = self::AUDIENCE_PUBLIC,
		$flags = IDBAccessObject::READ_NORMAL, $wikiId = UserIdentity::LOCAL
	): array {
		$audience = $this->checkAudience( $audience );
		$filter = $this->simplifyFilter( $filter );
		if ( $wikiId === UserIdentity::LOCAL
			|| $wikiId === WikiMap::getCurrentWikiId()
			|| $filter === self::FILTER_NONE
		) {
			return $this->lookupUserNamesWithLocalFilter( $nameToId, $filter, $audience, $flags );
		} else {
			return $this->lookupUserNamesWithForeignFilter( $nameToId, $filter, $wikiId, $audience, $flags );
		}
	}

	/**
	 * Look up central IDs for a list of usernames while checking attachment status
	 * on a wiki that is not the local wiki.
	 *
	 * @param array $nameToId
	 * @param string $filter The simplified (config-independent) filter, either
	 *   FILTER_ATTACHED or FILTER_OWNED
	 * @param string|false $wikiId
	 * @param Authority|null $audience The authority for viewing rows, or null for unauthenticated
	 * @param int $flags
	 * @return array
	 */
	private function lookupUserNamesWithForeignFilter(
		array $nameToId,
		$filter,
		$wikiId,
		?Authority $audience,
		int $flags
	): array {
		if ( !$nameToId ) {
			return [];
		}

		$db = $this->databaseManager->getCentralDBFromRecency( $flags );
		$qb = $db->newSelectQueryBuilder()
			->select( [ 'gu_name', 'gu_id', 'gu_hidden_level' ] )
			->from( 'globaluser' )
			->where( [ 'gu_name' => array_map( 'strval', array_keys( $nameToId ) ) ] );

		if ( $filter === self::FILTER_ATTACHED ) {
			$qb->join( 'localuser', null, 'lu_name=gu_name' )
				->where( [ 'lu_wiki' => $wikiId ] );
		} elseif ( $filter === self::FILTER_OWNED ) {
			// In strict mode, the user is owned if it is either attached or
			// if it is locally nonexistent.
			$qb->leftJoin( 'localnames', null,
					[ 'ln_name=gu_name', 'ln_wiki' => $wikiId ] )
				->leftJoin( 'localuser', null,
					[ 'lu_name=gu_name', 'lu_wiki' => $wikiId ] )
				->where(
					$db->expr( 'ln_name', '=', null )
						->or( 'lu_name', '!=', null )
				);
		}

		$res = $qb->caller( __METHOD__ )->fetchResultSet();

		foreach ( $res as $row ) {
			if ( $this->canView( $audience, $row->gu_hidden_level ) ) {
				$nameToId[$row->gu_name] = $row->gu_id;
			}
		}
		return $nameToId;
	}

	/**
	 * Look up central IDs for a list of usernames, either with no filter or
	 * with an attachment filter on the local wiki. Use the CentralAuthUserCache
	 * for caching.
	 *
	 * @param array $nameToId
	 * @param string $filter The simplified config-independent filter
	 * @param Authority|null $audience The authority for viewing rows, or null for unauthenticated
	 * @param int $flags
	 * @return array
	 */
	private function lookupUserNamesWithLocalFilter(
		array $nameToId,
		$filter,
		?Authority $audience,
		int $flags
	): array {
		$fromPrimaryDb = $this->shouldUsePrimary( $flags );

		$namesToLoad = [];
		foreach ( $nameToId as $name => $_ ) {
			$centralUser = $this->userCache->get( $name, $fromPrimaryDb );
			if ( $centralUser ) {
				if ( $filter === self::FILTER_ATTACHED ) {
					$filterPass = $centralUser->isAttached();
				} elseif ( $filter === self::FILTER_OWNED ) {
					if ( $centralUser->getId() === 0 ) {
						// Absent from globaluser
						$filterPass = false;
					} elseif ( $centralUser->isAttached() ) {
						// If it's attached, it's definitely owned, that's the normal case
						$filterPass = true;
					} elseif ( $centralUser->isLocalExistenceLoaded() ) {
						$filterPass = !$centralUser->existsLocally();
					} else {
						// Have to do a DB query
						$filterPass = false;
						$namesToLoad[] = (string)$name;
					}
				} else {
					$filterPass = true;
				}
				if ( $filterPass && $this->canView( $audience, $centralUser->getHiddenLevelInt() ) ) {
					if ( $centralUser->getId() ) {
						$nameToId[$name] = $centralUser->getId();
					}
				}
			} elseif ( isset( $this->badNameCache[$name] ) ) {
				// Do not try to load bad names
			} else {
				$namesToLoad[] = (string)$name;
			}
		}

		if ( !$namesToLoad ) {
			return $nameToId;
		}

		$db = $this->databaseManager->getCentralDBFromRecency( $flags );
		$res = $db->newSelectQueryBuilder()
			->queryInfo( CentralAuthUser::selectQueryInfo() )
			->where( [ 'gu_name' => $namesToLoad ] )
			->caller( __METHOD__ )->fetchResultSet();

		$loadedNames = [];
		foreach ( $res as $row ) {
			$centralUser = CentralAuthUser::newFromRow( $row, [], $fromPrimaryDb );
			$this->userCache->set( $centralUser );
			if ( $filter === self::FILTER_ATTACHED ) {
				$filterPass = $centralUser->isAttached();
			} elseif ( $filter === self::FILTER_OWNED ) {
				$filterPass = $centralUser->isAttached() || !$centralUser->existsLocally();
			} else {
				$filterPass = true;
			}
			if ( $filterPass && $this->canView( $audience, $centralUser->getHiddenLevelInt() ) ) {
				$nameToId[$centralUser->getName()] = $centralUser->getId();
			}
			$loadedNames[] = $row->gu_name;
		}

		$missingNames = array_diff( $namesToLoad, $loadedNames );
		foreach ( $missingNames as $name ) {
			try {
				$centralUser = CentralAuthUser::newUnattached( $name, $fromPrimaryDb );
				$this->userCache->set( $centralUser );
			} catch ( NormalizedException $e ) {
				$this->badNameCache[$name] = true;
			}
		}

		return $nameToId;
	}

	/**
	 * Determine if the authority can view a row with a given hidden level
	 *
	 * @param Authority|null $authority
	 * @param int $hiddenLevel
	 * @return bool
	 */
	private function canView( ?Authority $authority, int $hiddenLevel ): bool {
		return $hiddenLevel === CentralAuthUser::HIDDEN_LEVEL_NONE
			|| !$authority || $authority->isAllowed( 'centralauth-suppress' );
	}

	/**
	 * Resolve a filter type to a config-independent type.
	 * FILTER_OWNED in non-strict mode is just an attachment check, so we
	 * return FILTER_ATTACHED in that case. FILTER_OWNED is only returned
	 * in strict mode to indicate that unattached users are allowed.
	 *
	 * @param string $filter
	 * @return string
	 */
	private function simplifyFilter( $filter ) {
		// FILTER_OWNED in non-strict mode is just an attachment check
		$strictMode = $this->config->get( CAMainConfigNames::CentralAuthStrict );
		if ( !$strictMode && $filter === self::FILTER_OWNED ) {
			$filter = self::FILTER_ATTACHED;
		}
		return $filter;
	}

	/** @inheritDoc */
	public function isAttached( UserIdentity $user, $wikiId = UserIdentity::LOCAL ): bool {
		return $this->isAttachedOn( $user, $wikiId, IDBAccessObject::READ_NORMAL );
	}

	/** @inheritDoc */
	public function isOwned( UserIdentity $user, $wikiId = UserIdentity::LOCAL ): bool {
		$user->assertWiki( $wikiId );

		$centralUser = $this->getCentralUserInstance( $user );

		$strictMode = $this->config->get( CAMainConfigNames::CentralAuthStrict );
		if ( $centralUser->exists() && !$user->isRegistered() && $strictMode ) {
			// Even if the user doesn't exist locally, the username is reserved for the central user, as
			// it will be automatically attached on login, and can't be taken by any other user (T371340).
			// CentralAuthPrimaryAuthenticationProvider guarantees this.
			return true;
		}

		return $this->isAttachedOn( $user, $wikiId, IDBAccessObject::READ_NORMAL );
	}

	/** @inheritDoc */
	public function centralIdFromLocalUser(
		UserIdentity $user, $audience = self::AUDIENCE_PUBLIC, $flags = IDBAccessObject::READ_NORMAL
	): int {
		// This is only an optimization to take advantage of cache in CentralAuthUser.
		// The result should be the same as calling the parent method.
		if ( $this->isAttachedOn( $user, WikiAwareEntity::LOCAL, $flags ) ) {
			return $this->getCentralUserInstance( $user, $flags )->getId();
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
	private function isAttachedOn( UserIdentity $user, $wikiId, int $flags ): bool {
		$wikiId = $wikiId ?: WikiMap::getCurrentWikiId();
		$centralUser = $this->getCentralUserInstance( $user, $flags );

		return $centralUser->exists() && $centralUser->attachedOn( $wikiId );
	}

	private function shouldUsePrimary( int $flags ): bool {
		return DBAccessObjectUtils::hasFlags( $flags, IDBAccessObject::READ_LATEST )
			|| $this->databaseManager->centralLBHasRecentPrimaryChanges();
	}

	private function getCentralDB( int $flags ): IReadableDatabase {
		return $this->shouldUsePrimary( $flags )
			? $this->databaseManager->getCentralPrimaryDB()
			: $this->databaseManager->getCentralReplicaDB();
	}

	/**
	 * Get a potentially cached central user instance for the given user, reading from the primary DB if needed.
	 *
	 * @param UserIdentity $user The user to fetch the corresponding central user for.
	 * @param int $flags Bitmask of IDBAccessObject::READ_* constants.
	 *
	 * @return CentralAuthUser
	 */
	private function getCentralUserInstance(
		UserIdentity $user,
		int $flags = IDBAccessObject::READ_NORMAL
	): CentralAuthUser {
		return $this->shouldUsePrimary( $flags )
			? CentralAuthUser::getPrimaryInstance( $user )
			: CentralAuthUser::getInstance( $user );
	}

}
