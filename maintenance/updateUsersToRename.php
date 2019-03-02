<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

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
		$dbw = CentralAuthUtils::getCentralDB();
		$dbr = CentralAuthUtils::getCentralSlaveDB();
		$total = 0;
		do {
			$rows = $dbr->select(
				[ 'users_to_rename', 'localuser' ],
				[ 'utr_id', 'utr_name', 'utr_wiki' ],
				[],
				__METHOD__,
				[ 'LIMIT' => $this->mBatchSize ],
				[ 'localuser' => [ 'INNER JOIN', 'utr_wiki=lu_wiki AND utr_name=lu_name' ] ]
			);
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
			CentralAuthUtils::waitForSlaves();
		} while ( $rows->numRows() >= $this->mBatchSize );
		$this->output( "Removed $total users in total.\n" );
	}
}

$maintClass = UpdateUsersToRename::class;
require_once RUN_MAINTENANCE_IF_MAIN;
