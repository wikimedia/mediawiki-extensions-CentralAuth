<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\Maintenance;

use BatchRowIterator;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Logging\DatabaseLogEntry;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Utils\MWTimestamp;
use MediaWiki\WikiMap\WikiMap;
use stdClass;

class FixRenameUserLocalLogs extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'CentralAuth' );
		$this->addDescription( "Reset the performer actor ID in local 'renameuser' logs based on " .
			"the global 'gblrename' logs, as once upon a time it was set wrongly (T398177).\n\n" .
			"Run this on each wiki of the wiki farm.\n\n" .
			"This script performs a dry run by default." );
		$this->addOption( 'logwiki', "Wiki where the global log entries live (e.g. 'metawiki')", true, true );
		$this->addOption( 'fix', 'Save the changes to the database' );
		$this->setBatchSize( 100 );
	}

	/**
	 * Parse a timestamp string into a Unix timestamp, optionally modifying it.
	 */
	private function ts( mixed $timestamp, ?string $modify = null ): int {
		$mwTimestamp = new MWTimestamp( $timestamp );
		if ( $modify !== null && str_starts_with( $modify, '+' ) ) {
			$mwTimestamp->add( substr( $modify, 1 ) );
		} elseif ( $modify !== null && str_starts_with( $modify, '-' ) ) {
			$mwTimestamp->sub( substr( $modify, 1 ) );
		} elseif ( $modify !== null ) {
			throw new \LogicException( "Incorrect parameter $modify" );
		}
		return (int)$mwTimestamp->getTimestamp( TS_UNIX );
	}

	/**
	 * List the global 'gblrename' log entries, in batches of the specified size.
	 */
	private function getGlobalLogEntries(): BatchRowIterator {
		$databaseManager = CentralAuthServices::getDatabaseManager( $this->getServiceContainer() );
		$metaWikiDbr = $databaseManager->getLocalDB( DB_REPLICA, $this->getOption( 'logwiki' ) );
		$sqb = DatabaseLogEntry::newSelectQueryBuilder( $metaWikiDbr )
			->caller( __METHOD__ )
			->where( [
				'log_type' => 'gblrename',
				'log_action' => 'rename',
			] );
		// Also select the timestamp of the next rename of the same user, if there was any,
		// to avoid mismatches later when querying local log entries
		$sqb->field(
			'(' . $sqb->newSubquery()
				->from( 'logging', 'x' )
				->field( 'log_timestamp' )
				->where( [
					'x.log_namespace = logging.log_namespace',
					'x.log_title = logging.log_title',
					'x.log_timestamp > logging.log_timestamp',
				] )
				->orderBy( 'x.log_timestamp', $sqb::SORT_ASC )
				->limit( 1 )
				->getSQL() . ')',
			'_next_log_timestamp'
		);
		return new BatchRowIterator( $this->getReplicaDB(), $sqb, 'log_timestamp', $this->getBatchSize() );
	}

	/**
	 * List the local 'renameuser' log entries from the relevant period.
	 * @param iterable<stdClass> $globalLogRows
	 * @return stdClass[][][] Result rows grouped by old and new username
	 */
	private function getLocalLogEntries( iterable $globalLogRows ) {
		$firstGlobalLogEntry = null;
		$lastGlobalLogEntry = null;
		foreach ( $globalLogRows as $row ) {
			$globalLogEntry = DatabaseLogEntry::newFromRow( $row );
			$firstGlobalLogEntry ??= $globalLogEntry;
			$lastGlobalLogEntry = $globalLogEntry;
		}
		if ( !$firstGlobalLogEntry || !$lastGlobalLogEntry ) {
			return [];
		}

		// Sometimes the local log entry has a timestamp a few seconds before the global one... (10 seconds)
		$minTimestamp = $this->ts( $firstGlobalLogEntry->getTimestamp(), '-PT10S' );
		// Assume that renames were completed (unblocked, if stuck) within 1 week
		$maxTimestamp = $this->ts( $lastGlobalLogEntry->getTimestamp(), '+P1W' );

		// We can't query by the target user, since `log_title` may have been overwritten by subsequent
		// renames (T200731, T231829). Just select all log entries in the relevant time range.
		$localDb = $this->getReplicaDB();
		$sqb = DatabaseLogEntry::newSelectQueryBuilder( $localDb )
			->where( [
				'log_type' => 'renameuser',
				'log_action' => 'renameuser',
				$localDb->expr( 'log_timestamp', '>=', $localDb->timestamp( $minTimestamp ) ),
				$localDb->expr( 'log_timestamp', '<', $localDb->timestamp( $maxTimestamp ) ),
			] );

		// We need to fiddle with the query, because DatabaseLogEntry does an INNER JOIN with `actor`,
		// but the actor row might not exist due to the bug we're trying to clean up after.
		$queryInfo = $sqb->getQueryInfo();
		$queryInfo['join_conds']['logging_actor'][0] = 'LEFT JOIN';
		$sqb = $localDb->newSelectQueryBuilder()->queryInfo( $queryInfo );
		$rows = $sqb->caller( __METHOD__ )->fetchResultSet();

		// Group the results by old and new username to avoid scanning the whole list repeatedly later
		$rowsByOldNewUsername = [];
		foreach ( $rows as $row ) {
			$localLogEntry = DatabaseLogEntry::newFromRow( $row );
			$oldUserName = $localLogEntry->getParameters()['4::olduser'];
			$newUserName = $localLogEntry->getParameters()['5::newuser'];
			$rowsByOldNewUsername[$oldUserName][$newUserName][] = $row;
		}
		return $rowsByOldNewUsername;
	}

	/**
	 * List the global user attachment timestamps on the current wiki for the users involved,
	 * as a map from username to timestamp (or null if not attached).
	 * @param iterable<stdClass> $globalLogRows
	 * @return array<string, ?string>
	 */
	private function getUserAttachmentTimestamps( iterable $globalLogRows ): array {
		$db = CentralAuthServices::getDatabaseManager()->getCentralReplicaDB();

		$userNames = [];
		$attachedTimestamps = [];
		foreach ( $globalLogRows as $row ) {
			$globalLogEntry = DatabaseLogEntry::newFromRow( $row );
			$newUserName = $globalLogEntry->getParameters()['5::newuser'];
			$userNames[] = $newUserName;
			$attachedTimestamps[$newUserName] = null;
		}

		$rows = $db->newSelectQueryBuilder()
			->select( [ 'lu_name', 'lu_attached_timestamp' ] )
			->from( 'localuser' )
			->where( [
				'lu_name' => $userNames,
				'lu_wiki' => WikiMap::getCurrentWikiId(),
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		foreach ( $rows as $row ) {
			$attachedTimestamps[$row->lu_name] = $row->lu_attached_timestamp;
		}
		return $attachedTimestamps;
	}

	/**
	 * @param stdClass $globalRow
	 * @param stdClass[][][] $localLogRowsByOldNewUsername Result rows grouped by old and new username
	 * @return list<stdClass>
	 */
	private function findMatchingLogEntries( stdClass $globalRow, array $localLogRowsByOldNewUsername ): array {
		$globalLogEntry = DatabaseLogEntry::newFromRow( $globalRow );
		$oldUserName = $globalLogEntry->getParameters()['4::olduser'];
		$newUserName = $globalLogEntry->getParameters()['5::newuser'];

		// See getLocalLogEntries() for explanation of these values
		$minTimestamp = $this->ts( $globalLogEntry->getTimestamp(), '-PT10S' );
		$maxTimestamp = $this->ts( $globalLogEntry->getTimestamp(), '+P1W' );
		// See getGlobalLogEntries() for explanation of this condition
		if ( $globalRow->_next_log_timestamp !== null ) {
			$maxTimestamp = min( $maxTimestamp, $this->ts( $globalRow->_next_log_timestamp ) );
		}

		$matchingResults = [];
		// For each global 'gblrename' log entry, try to find corresponding local 'renameuser' log entry.
		foreach ( $localLogRowsByOldNewUsername[$oldUserName][$newUserName] ?? [] as $localRow ) {
			$localLogEntry = DatabaseLogEntry::newFromRow( $localRow );
			if (
				$this->ts( $localLogEntry->getTimestamp() ) >= $this->ts( $minTimestamp ) &&
				$this->ts( $localLogEntry->getTimestamp() ) < $this->ts( $maxTimestamp )
			) {
				$matchingResults[] = $localRow;
			}
		}
		return $matchingResults;
	}

	private function reportNoMatchingEntry(
		DatabaseLogEntry $globalLogEntry, int $count, array $attachedTimestamps
	): void {
		if ( $count > 1 ) {
			$this->output( "More than one matching local log entry for global #{$globalLogEntry->getId()}\n" );
		} elseif ( $count < 1 ) {
			// If the renamed user has existed on the local wiki at the time of the rename, the lack of
			// matching local log entry is weird.
			// Note that a log entry may exist even when the user does not exist (if it was renamed again).
			$attachTime = $attachedTimestamps[ $globalLogEntry->getParameters()['5::newuser'] ];
			if ( $attachTime && $this->ts( $globalLogEntry->getTimestamp() ) > $this->ts( $attachTime ) ) {
				$this->output( "User has existed, but no local log entry for global #{$globalLogEntry->getId()}\n" );
			}
		}
	}

	public function execute() {
		$fix = $this->hasOption( 'fix' );
		$changed = 0;

		$userFactory = $this->getServiceContainer()->getUserFactory();

		foreach ( $this->getGlobalLogEntries() as $globalLogRows ) {
			$localLogRows = $this->getLocalLogEntries( $globalLogRows );
			$attachedTimestamps = $this->getUserAttachmentTimestamps( $globalLogRows );

			$this->beginTransactionRound( __METHOD__ );
			foreach ( $globalLogRows as $globalRow ) {
				$globalLogEntry = DatabaseLogEntry::newFromRow( $globalRow );

				$matchingResults = $this->findMatchingLogEntries( $globalRow, $localLogRows );
				if ( count( $matchingResults ) !== 1 ) {
					$this->reportNoMatchingEntry( $globalLogEntry, count( $matchingResults ), $attachedTimestamps );
					continue;
				}
				$localRow = $matchingResults[0];
				$localLogEntry = DatabaseLogEntry::newFromRow( $localRow );

				if ( $localRow->log_user_text === 'Global rename script' ) {
					// These rows were created when the global performer's account did not exist locally at
					// the time of the rename. No need to do anything about these rows, as they can't be
					// affected by this bug, and we probably shouldn't retroactively attribute them to the
					// global performer if their account was created locally in the meantime.
					continue;
				}
				if ( $localRow->log_actor !== $globalRow->log_actor ) {
					// Not affected by T398177, nothing to do
					continue;
				}
				// Find the local account of the user who really performed the rename.
				// Do not use $globalLogEntry->getPerformerIdentity() on a log entry loaded from a different wiki,
				// as it will poison global actor cache with actor IDs from that wiki (that's what caused T398177)
				$newPerformer = $userFactory->newFromName( $globalRow->log_user_text );
				if ( !$newPerformer || !$newPerformer->isRegistered() ) {
					$this->output( "Global performer {$globalRow->log_user_text} does not exist locally\n" );
					continue;
				}
				if ( $localRow->log_user_text !== null ) {
					// Do not use $localLogEntry->getPerformerIdentity() on this log entry either,
					// as it will try to look up the actor ID, which might be garbage
					$oldPerformer = $userFactory->newFromName( $localRow->log_user_text );
					if ( !$oldPerformer ) {
						$this->output( "Local log entry #{$localLogEntry->getId()} has an invalid user name\n" );
						continue;
					}
					if ( $oldPerformer->getActorId() === $newPerformer->getActorId() ) {
						// Correct log entry, this is normal and common, so no output here
						continue;
					}
					// Local log entry points to an `actor` row for a different performer
					$oldPerformerName = $oldPerformer->getName();
				} else {
					// Local log entry points to a non-existent `actor` row
					$oldPerformerName = '<INVALID>';
				}

				$changed++;
				if ( $fix ) {
					$this->getPrimaryDB()->newUpdateQueryBuilder()
						->update( 'logging' )
						->where( [ 'log_id' => $localLogEntry->getId() ] )
						->set( [ 'log_actor' => $newPerformer->getActorId() ] )
						->caller( __METHOD__ )
						->execute();
				}
				$this->output( ( $fix ? "Updated" : "Would update" ) . " performer for " .
					"local #{$localLogEntry->getId()} based on global #{$globalLogEntry->getId()} " .
					"from '{$oldPerformerName}' to '{$newPerformer->getName()}'\n" );
			}
			$this->commitTransactionRound( __METHOD__ );
		}

		if ( $fix ) {
			$this->output( "Done, corrected $changed actor IDs\n" );
		} else {
			$this->output( "Dry run done, would correct $changed actor IDs\n" );
		}
	}
}
