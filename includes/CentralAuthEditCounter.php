<?php

namespace MediaWiki\Extension\CentralAuth;

use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\RawSQLValue;

class CentralAuthEditCounter {
	/** @var CentralAuthDatabaseManager */
	private $databaseManager;

	/** @var \WANObjectCache */
	private $wanCache;

	public function __construct(
		CentralAuthDatabaseManager $databaseManager,
		\WANObjectCache $wanCache
	) {
		$this->databaseManager = $databaseManager;
		$this->wanCache = $wanCache;
	}

	/**
	 * Get the global edit count for a user
	 *
	 * @param CentralAuthUser $centralUser
	 * @return int
	 */
	public function getCount( CentralAuthUser $centralUser ) {
		$userId = $centralUser->getId();
		$dbr = $this->databaseManager->getCentralReplicaDB();
		$count = $this->getCountFromDB( $dbr, $userId );
		if ( $count !== false ) {
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
		return $count;
	}

	/**
	 * Get the count by adding the user_editcount value across all attached wikis
	 *
	 * @param CentralAuthUser $centralUser
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
	 * @param IReadableDatabase $dbr
	 * @param int $userId
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
		// No need to populate when affectedRows() = 0, we can just wait for
		// getCount() to be called.
	}
}
