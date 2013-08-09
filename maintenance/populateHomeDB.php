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
				'globalnames',
				array( 'gn_name' ),
				array(
					'LIMIT' => $this->mBatchSize,
					'ORDER BY' => 'gn_name',
				),
				__METHOD__,
				$conds
			);

			foreach( $result as $row ) {
				$central = new CentralAuthUser( $row->gn_name );
				$central->mStateDirty = true;
				$central->saveSettings();
				$count++;
			}
			$this->output( "$count\n" );
			wfWaitForSlaves( false, 'centralauth' );
			if ( $count % $this->mBatchSize < $this->mBatchSize ) {
				break;
			}
			$conds = array( 'gn_name > ' . $db->addQuotes( $row->gn_name ) );
		} while ( true );
		$this->output( "done.\n" );
	}
}

$maintClass = 'PopulateHomeDB';
require_once( RUN_MAINTENANCE_IF_MAIN );
