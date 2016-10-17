<?php
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once( "$IP/maintenance/Maintenance.php" );

class PopulateLocalAndGlobalIds extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->mDescription = "Populate the localuser.lu_local_id and localuser.lu_global_id fields";
		$this->setBatchSize( 100 );
	}

	public function execute() {
		$this->output( "Populating lu_local_id field...\n" );
		$dbr = CentralAuthUtils::getCentralSlaveDB();
		$dbw = CentralAuthUtils::getCentralDB();
		// TODO:Remove BRI stuff and do normal batching like other maintenance scripts
		$iterator = new BatchRowIterator(
			$dbr,
			'localuser',
			['lu_wiki', 'lu_name'],
			$this->mBatchSize
		);
		$processed = 0;
		foreach ( $iterator as $batch ) {
			foreach ( $batch as $row ) {
				$lName = $row->lu_name;
				$lWiki = $row->lu_wiki;
				$dbr = wfGetDB( DB_SLAVE, [], $lWiki );
				// TODO: Space around []
				$lId = $dbr->selectField( 'user', 'user_id', ['user_name' => $lName] );
				$result = $dbw->update( 'localuser', ['lu_local_id' => $lId], ['lu_name' => $lName, 'lu_wiki' => $lWiki] );
				if ( !$result ) {
					$this->output( "Update failed for user $lName for wiki $lWiki" );
				}
				$processed += $dbw->affectedRows();
			}
			$this->output( "Updated $processed records.\n" );
		}
		$this->output( "Done.\n" );
	}
}

