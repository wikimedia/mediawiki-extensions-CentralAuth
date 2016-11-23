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
		$this->setBatchSize( 100 );
	}

	public function execute() {
		global $wgLocalDatabases;
		$dbr = CentralAuthUtils::getCentralSlaveDB();
		$dbw = CentralAuthUtils::getCentralDB();
		foreach( $wgLocalDatabases as $wiki ) {
			// Temporarily skipping English Wikipedia
			if ( $wiki == 'enwiki' ) {
				continue;
			}
			$lastGlobalId = -1;
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
				$globalUidToLocalName = [];
				foreach ( $rows as $row ) {
					$globalUidToLocalName[$row->gu_id] = $row->lu_name;
				}
				if ( !$globalUidToLocalName ) {
					$this->output( "All users migrated; Wiki: $wiki \n" );
					continue;
				}
				$numRows = $rows->numRows();

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
				$this->output( "Updated $numRows records. Last user: $lastGlobalId; Wiki: $wiki \n" );
				wfWaitForSlaves();
			} while ( $numRows >= $this->mBatchSize );
			$lb->reuseConnection( $ldbr );
		}
		$this->output( "Done.\n" );
	}

}

$maintClass = 'PopulateLocalAndGlobalIds';
require_once RUN_MAINTENANCE_IF_MAIN;

