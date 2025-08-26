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
					if ( $fix ) {
						$this->output( "Corrected edit count for '$newUserName': from $oldCount to $newCount\n" );
					} else {
						$this->output( "Would correct edit count for '$newUserName': from $oldCount to $newCount\n" );
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
