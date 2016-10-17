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
							[ 'LIMIT' => $this->mBatchSize, 'ORDER BY' => 'gu_id ASC' ],
				);
				foreach ( $rows as $row ) {
					$globalId = $row->gu_id; // Save this so we know where to fetch our next batch from
					$localId = $ldbr->selectField( 'user', 'user_id', [ 'user_name' => $row->lu_name ] );
					$result = $dbw->update(
						'localuser',
						[ 'lu_local_id' => $localId, 'lu_global_id' => $row->gu_id ],
						[ 'lu_name' => $row->lu_name, 'lu_wiki' => $wiki ]
					);
					if ( !$result ) {
						$this->output( "Update failed for global user $globalId for wiki $wiki \n" );
					}
				}
				$numRows = $rows->numRows();
				$this->output( "Updated $numRows records. Last user: $globalId ; Wiki: $wiki \n" );
				wfWaitForSlaves();
			} while ( $numRows >= $this->mBatchSize );
			$lb->reuseConnection( $ldbr );
		}
		$this->output( "Done." );
	}

}

$maintClass = 'PopulateLocalAndGlobalIds';
require_once RUN_MAINTENANCE_IF_MAIN;

