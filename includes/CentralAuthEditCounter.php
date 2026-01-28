<?php

namespace MediaWiki\Extension\CentralAuth;

use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\RawSQLValue;

class CentralAuthEditCounter {

	private CentralAuthDatabaseManager $databaseManager;
	private WANObjectCache $wanCache;

	/**
	 * @var int[] Mapping of central user id to global edit count for instance caching to avoid
	 *   repeated calls to to read rows from the global_edit_count table
	 */
	private array $userEditCountCache = [];

	public function __construct(
		CentralAuthDatabaseManager $databaseManager,
		WANObjectCache $wanCache
	) {
		$this->databaseManager = $databaseManager;
		$this->wanCache = $wanCache;
	}

	/**
	 * Get the global edit count for a user, falling back to initialise.
	 *
	 * Note that this method has a instance cache so that {@link self::preloadGetCountCache} can work as
	 * intended. This instance cache is cleared when {@link self::increment} or {@link self::recalculate}
	 * is called.
	 *
	 * @stable to call - since 1.46
	 */
	public function getCount( CentralAuthUser $centralUser ): int {
		$userId = $centralUser->getId();
		if ( !$userId ) {
			return 0;
		}

		if ( isset( $this->userEditCountCache[$userId] ) ) {
			return $this->userEditCountCache[$userId];
		}

		$dbr = $this->databaseManager->getCentralReplicaDB();
		$count = $this->getCountFromDB( $dbr, $userId );
		if ( $count !== false ) {
			$this->userEditCountCache[$userId] = $count;
			return $count;
		}

		if ( $this->databaseManager->isReadOnly() ) {
			// Don't try DB_PRIMARY since that will throw an exception
			return $this->wanCache->getWithSetCallback(
				$this->wanCache->makeGlobalKey( 'centralauth-editcount', $centralUser->getId() ),
				5 * $this->wanCache::TTL_MINUTE,
				function () use ( $centralUser ) {
					return $this->getCountFromWikis( $centralUser );
				}
			);
		}

		$dbw = $this->databaseManager->getCentralPrimaryDB();
		$count = $this->getCountFromDB( $dbw, $userId );
		if ( $count !== false ) {
			$this->userEditCountCache[$userId] = $count;
			return $count;
		}

		$dbw->startAtomic( __METHOD__ );
		// Lock the row
		$dbw->newInsertQueryBuilder()
			->insertInto( 'global_edit_count' )
			->ignore()
			->row( [
				'gec_user' => $userId,
				'gec_count' => 0
			] )
			->caller( __METHOD__ )
			->execute();
		if ( !$dbw->affectedRows() ) {
			// Try one more time after the lock wait
			$dbw->endAtomic( __METHOD__ );
			$count = $this->getCountFromDB( $dbw, $userId );
			if ( $count !== false ) {
				$this->userEditCountCache[$userId] = $count;
				return $count;
			}
			$dbw->startAtomic( __METHOD__ );
		}
		$count = $this->getCountFromWikis( $centralUser );

		$dbw->newUpdateQueryBuilder()
			->update( 'global_edit_count' )
			->set( [ 'gec_count' => $count ] )
			->where( [ 'gec_user' => $userId ] )
			->caller( __METHOD__ )
			->execute();
		$dbw->endAtomic( __METHOD__ );
		$this->userEditCountCache[$userId] = $count;
		return $count;
	}

	/**
	 * Preloads the internal global edit count instance cache for the given
	 * {@link CentralAuthUser} objects
	 *
	 * Use this when calls to {@link self::getCount} are expected for multiple users,
	 * so that the queries can be batched instead of performing one query per user.
	 *
	 * Unlike {@link self::getCount}, this will not try to initialise the global edit count
	 * for a user if it is not defined and will not attempt to read this data from a primary
	 * DB connection. In both of these cases, a call to {@link self::getCount} will act as if
	 * the global edit count has not been cached.
	 *
	 * @param CentralAuthUser[] $users
	 * @since 1.46
	 * @stable to call
	 */
	public function preloadGetCountCache( array $users ): void {
		$userIds = [];

		foreach ( $users as $user ) {
			if ( $user->exists() ) {
				$userIds[] = $user->getId();
			}
		}

		$userIds = array_unique( $userIds );

		$dbr = $this->databaseManager->getCentralReplicaDB();

		foreach ( array_chunk( $userIds, 500 ) as $userIdsBatch ) {
			$rows = $dbr->newSelectQueryBuilder()
				->select( [ 'gec_user', 'gec_count' ] )
				->from( 'global_edit_count' )
				->where( [ 'gec_user' => $userIdsBatch ] )
				->caller( __METHOD__ )
				->fetchResultSet();

			foreach ( $rows as $row ) {
				$this->userEditCountCache[(int)$row->gec_user] = (int)$row->gec_count;
			}
		}
	}

	/**
	 * Get the count by adding the user_editcount value across all attached wikis
	 *
	 * @return int
	 */
	public function getCountFromWikis( CentralAuthUser $centralUser ) {
		$count = 0;
		foreach ( $centralUser->queryAttached() as $acc ) {
			if ( isset( $acc['editCount'] ) ) {
				$count += (int)$acc['editCount'];
			}
		}
		return $count;
	}

	/**
	 * @return int|false
	 */
	private function getCountFromDB( IReadableDatabase $dbr, int $userId ) {
		$count = $dbr->newSelectQueryBuilder()
			->select( 'gec_count' )
			->from( 'global_edit_count' )
			->where( [ 'gec_user' => $userId ] )
			->caller( __METHOD__ )
			->fetchField();
		return is_string( $count ) ? (int)$count : $count;
	}

	/**
	 * Increment a global edit count
	 *
	 * @param CentralAuthUser $centralUser
	 * @param int $increment
	 */
	public function increment( CentralAuthUser $centralUser, $increment ) {
		if ( !$increment || $this->databaseManager->isReadOnly() ) {
			return;
		}
		$dbw = $this->databaseManager->getCentralPrimaryDB();
		$dbw->newUpdateQueryBuilder()
			->update( 'global_edit_count' )
			->set( [ 'gec_count' => new RawSQLValue( 'gec_count + ' . (int)$increment ) ] )
			->where( [ 'gec_user' => $centralUser->getId() ] )
			->caller( __METHOD__ )
			->execute();
		$this->clearUserEditCountCache( $centralUser );
		// No need to populate when affectedRows() = 0, we can just wait for
		// getCount() to be called.
	}

	public function recalculate( CentralAuthUser $centralUser ): int {
		$dbw = $this->databaseManager->getCentralPrimaryDB();
		$currentCount = $this->getCountFromDB( $dbw, $centralUser->getId() );
		if ( $currentCount === false ) {
			// Edit count is not cached, no need to do anything
			return 0;
		}
		$newCount = $this->getCountFromWikis( $centralUser );
		if ( $newCount === $currentCount ) {
			return $currentCount;
		}
		$dbw->newUpdateQueryBuilder()
			->update( 'global_edit_count' )
			->set( [ 'gec_count' => $newCount ] )
			->where( [ 'gec_user' => $centralUser->getId() ] )
			->caller( __METHOD__ )
			->execute();
		$this->clearUserEditCountCache( $centralUser );
		return $newCount;
	}

	/**
	 * Clears the internal global edit count instance cache for a given {@link CentralAuthUser}
	 */
	private function clearUserEditCountCache( CentralAuthUser $centralUser ): void {
		unset( $this->userEditCountCache[$centralUser->getId()] );
	}
}
