<?php

namespace MediaWiki\Extension\CentralAuth\Maintenance;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Rdbms\SelectQueryBuilder;

class PopulateLocalAndGlobalIds extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CentralAuth' );
		$this->addDescription(
			"Populate the localuser.lu_local_id and localuser.lu_global_id fields"
		);
		$this->setBatchSize( 1000 );
	}

	public function execute() {
		$databaseManager = CentralAuthServices::getDatabaseManager();
		$dbr = $databaseManager->getCentralReplicaDB();
		$dbw = $databaseManager->getCentralPrimaryDB();
		$lastlu_name = '';
		$updated = 0;

		// Skip people in global rename queue
		$wiki = WikiMap::getCurrentWikiId();
		$globalRenames = $dbr->newSelectQueryBuilder()
			->select( 'ru_oldname' )
			->from( 'renameuser_status' )
			->caller( __METHOD__ )
			->fetchFieldValues();

		$ldbr = $databaseManager->getLocalDB( DB_REPLICA, $wiki );

		$this->output( "Populating fields for wiki $wiki...\n" );
		do {
			$rows = $dbr->newSelectQueryBuilder()
				->select( [ 'lu_name', 'lu_local_id', 'lu_global_id', 'gu_id' ] )
				->from( 'localuser' )
				->leftJoin( 'globaluser', null, 'gu_name = lu_name' )
				->where( [
					// Start from where we left off in the last batch
					$dbr->andExpr( [
						// PK for localuser
						'lu_wiki' => $wiki,
						$dbr->expr( 'lu_name', '>=', $lastlu_name ),
					] ),
					$dbr->orExpr(
						[
							// Pick records not already populated
							$dbr->expr( 'lu_local_id', '=', null ),
							// T303590 for other irregular cases
							$dbr->expr( 'lu_local_id', '=', 0 ),
							$dbr->expr( 'lu_global_id', '=', 0 ),
							$dbr->expr( 'lu_global_id', '=', null ),
						]
					)
				] )
				->orderBy( 'gu_id', SelectQueryBuilder::SORT_ASC )
				->limit( $this->mBatchSize )
				->caller( __METHOD__ )
				->fetchResultSet();

			$numRows = $rows->numRows();

			$localNames = [];
			foreach ( $rows as $row ) {
				if ( in_array( $row->lu_name, $globalRenames ) ) {
					$this->output(
						"User " . $row->lu_name . " not migrated (pending rename).\n"
					);
					continue;
				}
				$localNames[$row->lu_name] = [
					'gu_id' => $row->gu_id,
					'lu_local_id' => $row->lu_local_id,
					'lu_global_id' => $row->lu_global_id,
				];
			}
			if ( !$localNames ) {
				continue;
			}

			$localNameToUid = [];
			$localIds = $ldbr->newSelectQueryBuilder()
				->select( [ 'user_id', 'user_name' ] )
				->from( 'user' )
				->where( [ 'user_name' => array_keys( $localNames ) ] )
				->caller( __METHOD__ )
				->fetchResultSet();

			foreach ( $localIds as $lid ) {
				$localNameToUid[$lid->user_name] = $lid->user_id;
			}

			foreach ( $localNames as $uname => $attrs ) {
				// Save progress so we know where to start our next batch
				$lastlu_name = $uname;

				$set = [];

				if ( $attrs['gu_id'] === null ) {
					$this->output(
						"No username to global user id mapping for \"$uname\" on $wiki.\n"
					);
				} elseif ( $attrs['lu_global_id'] !== $attrs['gu_id'] ) {
					$set['lu_global_id'] = $attrs['gu_id'];
				}

				if ( !isset( $localNameToUid[$uname] ) ) {
					$this->output(
						"No local name to user id mapping for \"$uname\" on $wiki.\n"
					);
				} elseif ( $attrs['lu_local_id'] !== $localNameToUid[$uname] ) {
					$set['lu_local_id'] = $localNameToUid[$uname];
				}

				if ( $set === [] ) {
					$this->output(
						"Nothing to update for \"$uname\" on $wiki...\n"
					);
					// Nothing to update for this user
					continue;
				}

				$dbw->newUpdateQueryBuilder()
					->update( 'localuser' )
					->set( $set )
					->where( [ 'lu_name' => $uname, 'lu_wiki' => $wiki ] )
					->caller( __METHOD__ )
					->execute();
				if ( !$dbw->affectedRows() ) {
					$this->output(
						"Update failed for global user $lastlu_name for wiki $wiki.\n"
					);
				} else {
					// Count the number of records actually updated
					$updated++;
				}
			}
			$this->output(
				"Updated $updated records. Last user: $lastlu_name; Wiki: $wiki.\n"
			);
			$this->waitForReplication();
		} while ( $numRows >= $this->mBatchSize );
		$this->output( "Completed $wiki.\n" );
	}

}

// @codeCoverageIgnoreStart
$maintClass = PopulateLocalAndGlobalIds::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
