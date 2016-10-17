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
							[ 'lu_wiki', 'lu_name', 'lu_local_id', 'gu_id' ],
							$dbr->makeList(
								[
									'gu_id >= ' . $globalId, // Start from where we left off in last batch
									'lu_wiki = ' . $ldbr->addQuotes( $wiki ),
									'lu_local_id is NULL' // Only pick records not already populated
								],
								LIST_AND
							),
							__METHOD__,
							[ 'LIMIT' => $this->mBatchSize, 'ORDER BY' => 'gu_id ASC' ],
							[ 'globaluser' => [ 'INNER_JOIN', [ 'gu_name=lu_name'  ] ] ]
				);
				foreach ( $rows as $row ) {
					$globalId = $row->gu_id; // Save this so we know where to fetch our next batch from
					$localId = $ldbr->selectField( 'user', 'user_id', [ 'user_name' => $name ] );
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
				// Break if we got less than expected records - we've reached the end
				if (  $numRows < $this->mBatchSize ) {
					break;
				}
			} while ( true );
			$lb->reuseConnection( $ldbr );
		}
		$this->output( "Done." );
	}

}

