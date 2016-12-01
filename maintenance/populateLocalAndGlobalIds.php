<?php
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once( "$IP/maintenance/Maintenance.php" );

class PopulateLocalAndGlobalIds extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( "Populate the localuser.lu_local_id and localuser.lu_global_id fields" );
		$this->setBatchSize( 1000 );
	}

	public function execute() {
		if ( $wmgUseCentralAuth ) {
			$dbr = CentralAuthUtils::getCentralSlaveDB();
			$dbw = CentralAuthUtils::getCentralDB();
			$lastGlobalId = - 1;
			// Skip people in global rename queue
			$globalRenamesQueued = $dbr->select(
				'renameuser_queue',
				'rq_name',
				[ 'rq_status' => 'pending' ]
			);
			$globalRenames = array_column( $globalRenamesQueued, 'rq_name' );
			$lb = wfGetLB( $wgDBname );
			$ldbr = $lb->getConnection( DB_SLAVE, [], $wgDBname );
			do {
				$rows = $dbr->select(
					[ 'localuser', 'globaluser' ],
					[ 'lu_name', 'gu_id' ],
					[
						// Start from where we left off in last batch
						'gu_id >= ' . $lastGlobalId,
						'lu_wiki' => $wgDBname,
						// Only pick records not already populated
						'lu_local_id' => null,
						'gu_name = lu_name'
					],
					__METHOD__,
					[ 'LIMIT' => $this->mBatchSize, 'ORDER BY' => 'gu_id ASC' ]
				);
				$numRows = $rows->numRows();

				$globalUidToLocalName = [];
				foreach ( $rows as $row ) {
					if ( in_array( $row->lu_name, $globalRenames ) ) {
						continue;
					}
					$globalUidToLocalName[$row->gu_id] = $row->lu_name;
				}
				if ( !$globalUidToLocalName ) {
					$this->output( "All users migrated; Wiki: $wgDBname \n" );
					continue;
				}

				$localNameToUid = [];
				$localIds = $ldbr->select(
					'user',
					[ 'user_id', 'user_name' ],
					[ 'user_name' => array_values( $globalUidToLocalName ) ]
				);
				foreach ( $localIds as $lid ) {
					$localNameToUid[$lid->user_name] = $lid->user_id;
				}
				foreach ( $globalUidToLocalName as $gid => $uname ) {
					// Save progress so we know where to start our next batch
					$lastGlobalId = $gid;
					$result = $dbw->update(
						'localuser',
						[
							'lu_local_id' => $localNameToUid[$uname],
							'lu_global_id' => $gid
						],
						[ 'lu_name' => $uname, 'lu_wiki' => $wgDBname ]
					);
					if ( !$result ) {
						$this->output( "Update failed for global user $lastGlobalId for wiki $wgDBname \n" );
					}
				}
				$updated = count( $globalUidToLocalName ); // Count number of records actually updated
				$this->output( "Updated $updated records. Last user: $lastGlobalId; Wiki: $wgDBname \n" );
				CentralAuthUtils::waitForSlaves();
			} while ( $numRows >= $this->mBatchSize );
			$lb->reuseConnection( $ldbr );
			$this->output( "Completed $wgDBname \n" );
		}
	}

}

$maintClass = 'PopulateLocalAndGlobalIds';
require_once RUN_MAINTENANCE_IF_MAIN;

