<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

use MediaWiki\Extension\CentralAuth\CentralAuthServices;

class GetCentralAuthDBInfo extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CentralAuth' );
		$this->addDescription( 'Display info for replica server hosting the CentralAuth database' );
	}

	public function execute() {
		$db = CentralAuthServices::getDatabaseManager()->getCentralReplicaDB();
		$host = $db->getServer();
		$this->output( "$host\n" );
	}
}

$maintClass = GetCentralAuthDBInfo::class;
require_once RUN_MAINTENANCE_IF_MAIN;
