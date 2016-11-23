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
			$globalId = -1;
			$lb = wfGetLB( $wiki );
			$ldbr = $lb->getConnection( DB_SLAVE, [], $wiki );
			do {
				$rows = $dbr->select(
					[ 'localuser', 'globaluser' ],
					[ 'lu_name', 'gu_id' ],
					[
						'gu_id >= ' . $globalId, // Start from where we left off in last batch
						'lu_wiki' => $wiki,
						'lu_local_id' => null, // Only pick records not already populated
						'gu_name = lu_name'
					],
					__METHOD__,
					[ 'LIMIT' => $this->mBatchSize, 'ORDER BY' => 'gu_id ASC' ]
				);
				$userList = [];
				foreach ( $rows as $row ) {
					$userList[$row->gu_id] = $row->lu_name;
				}

				$userMappings = [];
				$localIds = $ldbr->select( 'user', [ 'user_id', 'user_name' ], [ 'user_name' => array_values( $userList ) ] );
				foreach ( $localIds as $lid ) {
					$userMappings[$lid->user_name] = $lid->user_id;
				}
				foreach ( $userList as $id => $name ) {
					$globalId = $id; // Save this so we know where to fetch our next batch from
					$result = $dbw->update(
						'localuser',
						[ 'lu_local_id' => $userMappings[$name], 'lu_global_id' => $id ],
						[ 'lu_name' => $name, 'lu_wiki' => $wiki ]
					);
					if ( !$result ) {
						$this->output( "Update failed for global user $globalId for wiki $wiki \n" );
					}
				}
				$numRows = $rows->numRows();
				$this->output( "Updated $numRows records. Last user: $globalId; Wiki: $wiki \n" );
				wfWaitForSlaves();
			} while ( $numRows >= $this->mBatchSize );
			$lb->reuseConnection( $ldbr );
		}
		$this->output( "Done.\n" );
	}

}

$maintClass = 'PopulateLocalAndGlobalIds';
require_once RUN_MAINTENANCE_IF_MAIN;

