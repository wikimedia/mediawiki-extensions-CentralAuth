<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once( "$IP/maintenance/Maintenance.php" );

class GetCentralAuthDBInfo extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->mDescription = 'Display info for slave server hosting the CentralAuth DB';
	}

	public function execute() {
		global $wgDBadminuser;
		global $wgDBadminpassword;
		$db = CentralAuthUtils::getCentralSlaveDB();
		$host = $db->getServer();
		$this->output( "$host $wgDBadminuser $wgDBadminpassword\n" );
	}
}

$maintClass ="GetCentralAuthDBInfo";
require_once RUN_MAINTENANCE_IF_MAIN;
