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
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Logging\DatabaseLogEntry;
use MediaWiki\Maintenance\Maintenance;
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

	public function execute() {
		$fix = $this->hasOption( 'fix' );
		$changed = 0;

		$userFactory = $this->getServiceContainer()->getUserFactory();
		$databaseManager = CentralAuthServices::getDatabaseManager( $this->getServiceContainer() );
		$metaWikiDbr = $databaseManager->getLocalDB( DB_REPLICA, $this->getOption( 'logwiki' ) );

		// List the global 'gblrename' log entries
		$sqb = DatabaseLogEntry::newSelectQueryBuilder( $metaWikiDbr )
			->caller( __METHOD__ )
			->where( [
				'log_type' => 'gblrename',
				'log_action' => 'rename',
			] );
		$batches = new BatchRowIterator( $this->getReplicaDB(), $sqb, 'log_timestamp', $this->getBatchSize() );
		foreach ( $batches as $rows ) {
			$this->beginTransactionRound( __METHOD__ );
			foreach ( $rows as $globalRow ) {
				$globalLogEntry = DatabaseLogEntry::newFromRow( $globalRow );
				[ $localLogEntry, $localRow ] = $this->getLocalLogEntry( $globalLogEntry );
				if ( !$localLogEntry instanceof DatabaseLogEntry || !$localRow instanceof stdClass ) {
					continue;
				}

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

	/** @return array{?DatabaseLogEntry, ?stdClass} */
	private function getLocalLogEntry( DatabaseLogEntry $globalLogEntry ): array {
		$oldUserName = $globalLogEntry->getParameters()['4::olduser'];
		$newUserName = $globalLogEntry->getParameters()['5::newuser'];

		$localDb = $this->getReplicaDB();

		// For each global 'gblrename' log entry, try to find corresponding local 'renameuser' log entry,
		// based on the approximate time and the old and new user names.
		$result = null;
		$sqb = DatabaseLogEntry::newSelectQueryBuilder( $localDb )
			->caller( __METHOD__ )
			->where( [
				'log_type' => 'renameuser',
				'log_action' => 'renameuser',
				$localDb->expr( 'log_timestamp', '>=', $localDb->timestamp( $globalLogEntry->getTimestamp() ) ),
				'log_namespace' => NS_USER,
				// Old username may not be valid today, so don't try to parse it, just manually convert to dbkey format
				'log_title' => strtr( $oldUserName, ' ', '_' ),
			] );
		// We need to fiddle with the query, because DatabaseLogEntry does an INNER JOIN with `actor`,
		// but the actor row might not exist due to the bug we're trying to clean up after.
		$queryInfo = $sqb->getQueryInfo();
		$queryInfo['join_conds']['logging_actor'][0] = 'LEFT JOIN';
		$sqb = $localDb->newSelectQueryBuilder()->queryInfo( $queryInfo );
		foreach ( $sqb->fetchResultSet() as $localRow ) {
			$localLogEntry = DatabaseLogEntry::newFromRow( $localRow );
			if (
				$oldUserName === $localLogEntry->getParameters()['4::olduser'] &&
				$newUserName === $localLogEntry->getParameters()['5::newuser']
			) {
				if ( $result ) {
					$this->output( "More than one matching local log entry for global #{$globalLogEntry->getId()}\n" );
					return [ null, null ];
				}
				$result = [ $localLogEntry, $localRow ];
			}
		}
		if ( !$result ) {
			// If the renamed user exists on the local wiki, the lack of matching local log entry is weird.
			// Note that a log entry may exist even when the user does not exist (if it was renamed again).
			$centralUser = CentralAuthUser::getInstanceByName( $newUserName );
			if ( $centralUser->isAttached() ) {
				$this->output( "User exists, but no local log entry for global #{$globalLogEntry->getId()}\n" );
			}
			return [ null, null ];
		}
		return $result;
	}
}
