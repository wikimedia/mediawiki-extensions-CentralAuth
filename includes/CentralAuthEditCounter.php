<?php

namespace MediaWiki\Extension\CentralAuth;

use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use Wikimedia\Rdbms\IDatabase;

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
		$dbr = $this->databaseManager->getCentralDB( DB_REPLICA );
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

		$dbw = $this->databaseManager->getCentralDB( DB_PRIMARY );
		$count = $this->getCountFromDB( $dbw, $userId );
		if ( $count !== false ) {
			return $count;
		}

		$dbw->startAtomic( __METHOD__ );
		// Lock the row
		$dbw->insert(
			'global_edit_count',
			[
				'gec_user' => $userId,
				'gec_count' => 0
			],
			__METHOD__,
			[ 'IGNORE' ]
		);
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

		$dbw->update(
			'global_edit_count',
			[ 'gec_count' => $count ],
			[ 'gec_user' => $userId ],
			__METHOD__
		);
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
	 * @param IDatabase $db
	 * @param int $userId
	 * @return int|false
	 */
	private function getCountFromDB( $db, $userId ) {
		$count = $db->newSelectQueryBuilder()
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
		$dbw = $this->databaseManager->getCentralDB( DB_PRIMARY );
		$dbw->update(
			'global_edit_count',
			[ 'gec_count = gec_count + ' . (int)$increment ],
			[ 'gec_user' => $centralUser->getId() ],
			__METHOD__
		);
		// No need to populate when affectedRows() = 0, we can just wait for
		// getCount() to be called.
	}
}
