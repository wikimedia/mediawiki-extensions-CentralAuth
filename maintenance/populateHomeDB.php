<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once( "$IP/maintenance/Maintenance.php" );

class PopulateHomeDB extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->mDescription = 'Populate the globaluser.gu_home_db field';
		$this->setBatchSize( 500 );
	}

	public function execute() {
		$db = CentralAuthUser::getCentralSlaveDB();
		$conds = array();
		$count = 0;
		do {
			$result = $db->select(
				'globaluser',
				array( 'gu_name' ),
				array_merge( $conds, array( 'gu_home_db IS NULL OR gu_home_db = ""' ) ),
				__METHOD__,
				array(
					'LIMIT' => $this->mBatchSize,
					'ORDER BY' => 'gu_name',
				)
			);

			foreach( $result as $row ) {
				$central = new CentralAuthUser( $row->gu_name, CentralAuthUser::READ_LATEST );
				$central->mStateDirty = true;
				$central->saveSettings();
				$count++;
			}
			$this->output( "$count\n" );
			CentralAuthUser::waitForSlaves();
			if ( $result->numRows() < $this->mBatchSize ) {
				break;
			}
			$conds = array( 'gu_name > ' . $db->addQuotes( $row->gu_name ) );
		} while ( true );
		$this->output( "done.\n" );
	}
}

$maintClass = 'PopulateHomeDB';
require_once( RUN_MAINTENANCE_IF_MAIN );
