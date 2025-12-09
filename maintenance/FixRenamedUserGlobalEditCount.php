<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\Maintenance;

use BatchRowIterator;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Logging\DatabaseLogEntry;
use MediaWiki\Maintenance\Maintenance;

class FixRenamedUserGlobalEditCount extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'CentralAuth' );
		$this->addDescription( "Recalculate the global edit count for users who have been renamed, " .
			"as once upon a time that corrupted the edit counts (T313900).\n\n" .
			"Run this on the wiki that has 'gblrename' log entries (e.g. 'metawiki').\n\n" .
			"This script performs a dry run by default." );
		$this->addOption( 'since', 'Only process renames since (timestamp)', false, true );
		$this->addOption( 'until', 'Only process renames until (timestamp)', false, true );
		$this->addOption( 'fix', 'Save the changes to the database' );
		$this->setBatchSize( 100 );
	}

	public function execute() {
		$fix = $this->hasOption( 'fix' );
		$changed = 0;

		$counter = CentralAuthServices::getEditCounter( $this->getServiceContainer() );

		$dbr = $this->getReplicaDB();
		$sqb = DatabaseLogEntry::newSelectQueryBuilder( $dbr )
			->caller( __METHOD__ )
			->where( [
				'log_type' => 'gblrename',
				'log_action' => 'rename',
			] );
		if ( $this->getOption( 'since' ) ) {
			$sqb->where( $dbr->expr(
				'log_timestamp', '>=', $dbr->timestamp( $this->getOption( 'since' ) )
			) );
		}
		if ( $this->getOption( 'until' ) ) {
			$sqb->where( $dbr->expr(
				'log_timestamp', '<', $dbr->timestamp( $this->getOption( 'until' ) )
			) );
		}
		$batches = new BatchRowIterator( $dbr, $sqb, 'log_timestamp', $this->getBatchSize() );
		foreach ( $batches as $rows ) {
			$this->beginTransactionRound( __METHOD__ );
			foreach ( $rows as $row ) {
				$logEntry = DatabaseLogEntry::newFromRow( $row );
				$newUserName = $logEntry->getParameters()['5::newuser'];
				if ( !$newUserName ) {
					$this->output( "Log entry parameters missing for log ID {$logEntry->getId()}\n" );
					continue;
				}
				$centralUser = CentralAuthUser::getInstanceByName( $newUserName );
				if ( !$centralUser->exists() ) {
					$this->output( "Renamed user '$newUserName' not found (maybe renamed again)\n" );
					continue;
				}

				$oldCount = $counter->getCount( $centralUser );
				if ( $fix ) {
					$newCount = $counter->recalculate( $centralUser );
				} else {
					$newCount = $counter->getCountFromWikis( $centralUser );
				}
				if ( $oldCount === $newCount ) {
					$this->output( "Edit count already correct for '$newUserName'\n" );
				} else {
					$changed++;
					$details = sprintf( 'from %d to %d (%+d; %0.2fx)',
						$oldCount, $newCount, $newCount - $oldCount, $oldCount ? ( $newCount / $oldCount ) : 0 );
					if ( $fix ) {
						$this->output( "Corrected edit count for '$newUserName': $details\n" );
					} else {
						$this->output( "Would correct edit count for '$newUserName': $details\n" );
					}
				}
			}
			$this->commitTransactionRound( __METHOD__ );
		}

		if ( $fix ) {
			$this->output( "Done, corrected $changed edit counts\n" );
		} else {
			$this->output( "Dry run done, would correct $changed edit counts\n" );
		}
	}
}
