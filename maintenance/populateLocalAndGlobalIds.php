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
		if ( class_exists( 'CentralAuthUtils' ) ) {
			$dbr = CentralAuthUtils::getCentralSlaveDB();
			$dbw = CentralAuthUtils::getCentralDB();
			$lastGlobalId = -1;

			// Skip people in global rename queue
			$wiki = wfWikiID();
			$globalRenames = [];
			$rows = $dbr->select(
				'renameuser_status',
				'ru_oldname'
			);
			foreach ( $rows as $row ) {
				$globalRenames[] = $row->ru_oldname;
			}

			$lb = wfGetLB( $wiki );
			$ldbr = $lb->getConnection( DB_SLAVE, [], $wiki );
			do {
				$rows = $dbr->select(
					[ 'localuser', 'globaluser' ],
					[ 'lu_name', 'gu_id' ],
					[
						// Start from where we left off in last batch
						'gu_id >= ' . $lastGlobalId,
						'lu_wiki' => $wiki,
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
					$notUpdated = count( $globalRenames );
					if ( $notUpdated > 0 ) {
						$this->output( "Users not in rename queue migrated; $notUpdated not migrated; Wiki: $wiki \n" );
					} else {
						$this->output( "All users migrated; Wiki: $wiki \n" );
					}
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
						[ 'lu_name' => $uname, 'lu_wiki' => $wiki ]
					);
					if ( !$result ) {
						$this->output( "Update failed for global user $lastGlobalId for wiki $wiki \n" );
					}
				}
				$updated = count( $globalUidToLocalName ); // Count number of records actually updated
				$this->output( "Updated $updated records. Last user: $lastGlobalId; Wiki: $wiki \n" );
				CentralAuthUtils::waitForSlaves();
			} while ( $numRows >= $this->mBatchSize );
			$lb->reuseConnection( $ldbr );
			$this->output( "Completed $wiki \n" );
		} else {
			$this->error( "This script requires that the CentralAuth extension is enabled. Please enable it and try again.", 1 );
		}
	}

}

$maintClass = 'PopulateLocalAndGlobalIds';
require_once RUN_MAINTENANCE_IF_MAIN;

