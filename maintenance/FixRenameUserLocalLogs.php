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
	 * List the global 'gblrename' log entries, in batches of the specified size.
	 * @return iterable<iterable<stdClass>>
	 */
	private function getGlobalLogEntries() {
		$databaseManager = CentralAuthServices::getDatabaseManager( $this->getServiceContainer() );
		$metaWikiDbr = $databaseManager->getLocalDB( DB_REPLICA, $this->getOption( 'logwiki' ) );
		$sqb = DatabaseLogEntry::newSelectQueryBuilder( $metaWikiDbr )
			->caller( __METHOD__ )
			->where( [
				'log_type' => 'gblrename',
				'log_action' => 'rename',
			] );
		return new BatchRowIterator( $this->getReplicaDB(), $sqb, 'log_timestamp', $this->getBatchSize() );
	}

	/**
	 * List the local 'renameuser' log entries from the relevant period.
	 * @param iterable<stdClass> $globalLogRows
	 * @return iterable<stdClass>
	 */
	private function getLocalLogEntries( iterable $globalLogRows ) {
		$logTitleValues = [];
		$firstGlobalLogEntry = null;
		$lastGlobalLogEntry = null;
		foreach ( $globalLogRows as $row ) {
			$globalLogEntry = DatabaseLogEntry::newFromRow( $row );
			$firstGlobalLogEntry ??= $globalLogEntry;
			$lastGlobalLogEntry = $globalLogEntry;

			$oldUserName = $globalLogEntry->getParameters()['4::olduser'];
			// Old username may not be valid today, so don't try to parse it, just manually convert to dbkey format
			$logTitleValues[] = strtr( $oldUserName, ' ', '_' );
		}
		if ( !$firstGlobalLogEntry || !$lastGlobalLogEntry ) {
			return [];
		}

		$localDb = $this->getReplicaDB();
		$sqb = DatabaseLogEntry::newSelectQueryBuilder( $localDb )
			->caller( __METHOD__ )
			->where( [
				'log_type' => 'renameuser',
				'log_action' => 'renameuser',
				// Sometimes the local log entry has a timestamp a few seconds before the global one... (10 seconds)
				// Beware: ->sub() and ->add() modify MWTimestamp in-place (it's mutable).
				$localDb->expr( 'log_timestamp', '>=', $localDb->timestamp(
					( new MWTimestamp( $firstGlobalLogEntry->getTimestamp() ) )->sub( 'PT10S' )->getTimestamp( TS_MW )
				) ),
				// If we're doing ugly date math already, also limit how far in the future we might look (1 week)
				$localDb->expr( 'log_timestamp', '<', $localDb->timestamp(
					( new MWTimestamp( $lastGlobalLogEntry->getTimestamp() ) )->add( 'P1W' )->getTimestamp( TS_MW )
				) ),
				'log_namespace' => NS_USER,
				'log_title' => $logTitleValues,
			] );
		// We need to fiddle with the query, because DatabaseLogEntry does an INNER JOIN with `actor`,
		// but the actor row might not exist due to the bug we're trying to clean up after.
		$queryInfo = $sqb->getQueryInfo();
		$queryInfo['join_conds']['logging_actor'][0] = 'LEFT JOIN';
		$sqb = $localDb->newSelectQueryBuilder()->queryInfo( $queryInfo );
		return $sqb->fetchResultSet();
	}

	/**
	 * List the global user attachment timestamps on the current wiki for the users involved,
	 * as a map from username to timestamp (or null if not attached).
	 * @param iterable<stdClass> $globalLogRows
	 * @return array<string, ?string>
	 */
	private function getUserAttachmentTimestamps( iterable $globalLogRows ) {
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
	 * @param DatabaseLogEntry $globalLogEntry
	 * @param iterable<stdClass> $localLogRows
	 * @return list<array{DatabaseLogEntry, stdClass}>
	 */
	private function findMatchingLogEntries( DatabaseLogEntry $globalLogEntry, iterable $localLogRows ): array {
		$oldUserName = $globalLogEntry->getParameters()['4::olduser'];
		$newUserName = $globalLogEntry->getParameters()['5::newuser'];

		$matchingResults = [];
		// For each global 'gblrename' log entry, try to find corresponding local 'renameuser' log entry.
		foreach ( $localLogRows as $localRow ) {
			$localLogEntry = DatabaseLogEntry::newFromRow( $localRow );
			if (
				$oldUserName === $localLogEntry->getParameters()['4::olduser'] &&
				$newUserName === $localLogEntry->getParameters()['5::newuser']
			) {
				$matchingResults[] = [ $localLogEntry, $localRow ];
			}
		}
		return $matchingResults;
	}

	private function reportNoMatchingEntry( DatabaseLogEntry $globalLogEntry, int $count, array $attachedTimestamps ) {
		if ( $count > 1 ) {
			$this->output( "More than one matching local log entry for global #{$globalLogEntry->getId()}\n" );
		} elseif ( $count < 1 ) {
			// If the renamed user has existed on the local wiki at the time of the rename, the lack of
			// matching local log entry is weird.
			// Note that a log entry may exist even when the user does not exist (if it was renamed again).
			$attachTime = $attachedTimestamps[ $globalLogEntry->getParameters()['5::newuser'] ];
			if (
				$attachTime &&
				wfTimestamp( TS_UNIX, $globalLogEntry->getTimestamp() ) > wfTimestamp( TS_UNIX, $attachTime )
			) {
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

				$matchingResults = $this->findMatchingLogEntries( $globalLogEntry, $localLogRows );
				if ( count( $matchingResults ) !== 1 ) {
					$this->reportNoMatchingEntry( $globalLogEntry, count( $matchingResults ), $attachedTimestamps );
					continue;
				}
				[ $localLogEntry, $localRow ] = $matchingResults[0];

				// Find the local account of the user who really performed the rename.
				// Do not use $globalLogEntry->getPerformerIdentity() on a log entry loaded from a different wiki,
				// as it will poison global actor cache with actor IDs from that wiki (T398177)
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
						->caller( __METHOD__ )
						->update( 'logging' )
						->where( [ 'log_id' => $localLogEntry->getId() ] )
						->set( [ 'log_actor' => $newPerformer->getActorId() ] )
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
