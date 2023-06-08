<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

use MediaWiki\Extension\CentralAuth\CentralAuthServices;

/**
 * Removes attached accounts from the users_to_rename
 * table
 */
class UpdateUsersToRename extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CentralAuth' );
		$this->setBatchSize( 100 );
	}

	public function execute() {
		$databaseManager = CentralAuthServices::getDatabaseManager();
		$dbw = $databaseManager->getCentralPrimaryDB();
		$dbr = $databaseManager->getCentralReplicaDB();
		$total = 0;
		do {
			$rows = $dbr->newSelectQueryBuilder()
				->select( [ 'utr_id', 'utr_name', 'utr_wiki' ] )
				->from( 'users_to_rename' )
				->join( 'localuser', null, 'utr_wiki=lu_wiki AND utr_name=lu_name' )
				->limit( $this->mBatchSize )
				->caller( __METHOD__ )
				->fetchResultSet();

			$ids = [];
			foreach ( $rows as $row ) {
				$ids[] = $row->utr_id;
				$this->output( "{$row->utr_name}@{$row->utr_wiki} is now attached!\n" );
			}
			if ( $ids ) {
				$count = count( $ids );
				$this->output( "Deleting $count users...\n" );
				$dbw->delete(
					'users_to_rename',
					[ 'utr_id' => $ids ],
					__METHOD__
				);
				$total += $count;
			}
			$databaseManager->waitForReplication();
		} while ( $rows->numRows() >= $this->mBatchSize );
		$this->output( "Removed $total users in total.\n" );
	}
}

$maintClass = UpdateUsersToRename::class;
require_once RUN_MAINTENANCE_IF_MAIN;
