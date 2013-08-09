<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once( "$IP/maintenance/Maintenance.php" );

class PopulateHomeDB extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->mDescription = "Populate the globaluser.gu_home_db field";
	}

	public function execute() {
		$db = CentralAuthUser::getCentralSlaveDB();
		$result = $db->select(
			'globalnames',
			array( 'gn_name' ),
			array(),
			__METHOD__
		);
		$count = 0;
		foreach( $result as $row ) {
			$central = new CentralAuthUser( $row->gn_name );
			$central->saveSettings();
			if ( ++$count % 100 == 0 ) {
				$this->output( "$count\n" );
				wfWaitForSlaves( false, 'centralauth' );
			}
		}
		$this->output( "done.\n" );
	}
}

$maintClass = "PopulateHomeDB";
require_once( RUN_MAINTENANCE_IF_MAIN );
