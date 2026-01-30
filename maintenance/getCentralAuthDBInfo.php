<?php

namespace MediaWiki\Extension\CentralAuth\Maintenance;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Maintenance\Maintenance;

class GetCentralAuthDBInfo extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CentralAuth' );
		$this->addDescription( 'Display info for replica server hosting the CentralAuth database' );
	}

	public function execute() {
		$this->output(
			CentralAuthServices::getDatabaseManager()->getCentralReplicaDB()->getServer() . "\n"
		);
	}
}

// @codeCoverageIgnoreStart
$maintClass = GetCentralAuthDBInfo::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
