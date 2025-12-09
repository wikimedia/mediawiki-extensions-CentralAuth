<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\User;

use LogPage;
use MediaWiki\Logging\DatabaseLogEntry;
use MediaWiki\Logging\LogEntry;
use MediaWiki\Permissions\Authority;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\SelectQueryBuilder;

class CentralAuthUserStatusLookupService {

	/** @internal Create using CentralAuthUserStatusLookupFactory */
	public function __construct(
		private readonly IConnectionProvider $connectionProvider,
		private readonly string|false $remoteWikiId = false
	) {
	}

	/**
	 * Returns the timestamp of the last time the user was locked. If the user is not locked,
	 * or the log entry is not visible to the given authority, null is returned.
	 *
	 * No permission checks are performed if the authority is null.
	 */
	public function getUserLockedTimestamp( string $userName, ?Authority $authority ): ?string {
		$logEntry = $this->getLogForUserStatusTransition(
			$userName,
			[ 'added' => [ 'locked' ], 'removed' => [] ],
			$authority
		);
		return $logEntry?->getTimestamp();
	}

	/**
	 * Finds the most recent log entry for the given target user that matches the given
	 * state transition. If such entry cannot be found, null is returned. Null is also
	 * returned if the given authority does not have sufficient permissions to see the
	 * action in the relevant log entry, so that no information is leaked.
	 *
	 * For the transitions to match, the $transition parameter must be fully contained
	 * within the 'added' and 'removed' parameters of the log entry.
	 *
	 * If authority is not provided, no permission checks are performed. It's assumed
	 * then that the caller is allowed to see all the log entries.
	 *
	 * @internal
	 * @param string $userName
	 * @param array $transition It should be an array with two keys: 'added' and 'removed'.
	 * Each of these keys should have an array of strings that are looked up for in the
	 * log entries.
	 * @param Authority|null $authority
	 */
	public function getLogForUserStatusTransition(
		string $userName, array $transition, ?Authority $authority
	): ?LogEntry {
		$logEntry = $this->getLogForUserStatusTransitionInternal( $userName, $transition );
		if ( !$logEntry ) {
			return null;
		}

		if ( $authority ) {
			if ( !$this->isAuthorityAllowedToSeeLogAction( $authority, $logEntry ) ) {
				return null;
			}
			if (
				$logEntry->getType() === 'suppress' &&
				!$this->isAuthorityAllowedToSeeSuppressLog( $authority )
			) {
				return null;
			}
		}

		return $logEntry;
	}

	private function getLogForUserStatusTransitionInternal( string $userName, array $transition ): ?LogEntry {
		// There are usually very few entries in globalauth/setstatus log for a given user,
		// so this limit is just a precaution for stewards' test accounts. They don't exceed
		// 100 entries at the time of writing, but with a very limited set of possible statuses,
		// it is unlikely that we will need to go beyond 50 entries in order to find the most
		// recent transition of the desired type.
		$limit = 50;

		// Normalize the username to the form used in log titles
		$userName = str_replace( ' ', '_', $userName );

		$dbr = $this->connectionProvider->getReplicaDatabase( $this->remoteWikiId );
		$rows = DatabaseLogEntry::newSelectQueryBuilder( $dbr )
			->where( [
				'log_type' => [ 'globalauth', 'suppress' ],
				'log_action' => 'setstatus',
				'log_namespace' => NS_USER,
				'log_title' => $userName . '@global',
			] )
			->orderBy( 'log_timestamp', SelectQueryBuilder::SORT_DESC )
			->limit( $limit )
			->caller( __METHOD__ )
			->fetchResultSet();

		foreach ( $rows as $row ) {
			$logEntry = DatabaseLogEntry::newFromRow( $row );
			[ $added, $removed ] = $this->extractLogParameters( $logEntry );

			if (
				!array_diff( $transition['added'], $added ) &&
				!array_diff( $transition['removed'], $removed )
			) {
				return $logEntry;
			}
		}
		return null;
	}

	/**
	 * Extracts the added and removed statuses from the log entry parameters
	 * and returns them as an array of arrays: [ added, removed ].
	 */
	private function extractLogParameters( DatabaseLogEntry $logEntry ): array {
		$logParams = $logEntry->getParameters();
		if ( !$logEntry->isLegacy() ) {
			$added = $logParams['added'] ?? [];
			$removed = $logParams['removed'] ?? [];
			return [ $added, $removed ];
		}

		if ( $logParams[0] === '(none)' ) {
			$added = [];
		} else {
			$added = explode( ', ', $logParams[0] );
		}

		if ( $logParams[1] === '(none)' ) {
			$removed = [];
		} else {
			$removed = explode( ', ', $logParams[1] );
		}
		return [ $added, $removed ];
	}

	/**
	 * Checks whether the given authority has sufficient permissions to see the logged action.
	 * It's not checked whether the performer or comment can be seen, as this information
	 * is not relevant for this service.
	 */
	private function isAuthorityAllowedToSeeLogAction( Authority $authority, LogEntry $logEntry ): bool {
		if ( !$authority->isAllowed( 'deletedhistory' ) ) {
			return !$logEntry->isDeleted( LogPage::DELETED_ACTION );
		} elseif ( !$authority->isAllowedAny( 'suppressrevision', 'viewsuppressed' ) ) {
			return !$logEntry->isDeleted( LogPage::SUPPRESSED_ACTION );
		}
		return true;
	}

	private function isAuthorityAllowedToSeeSuppressLog( Authority $authority ): bool {
		$log = new LogPage( 'suppress' );
		if ( !$log->isRestricted() ) {
			return true;
		}

		$logRight = $log->getRestriction();
		return $authority->isAllowed( $logRight );
	}
}
