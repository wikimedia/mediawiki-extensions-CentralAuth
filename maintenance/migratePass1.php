<?php
// pass 1:
// * generate 'globaluser' entries for each username
// * go through all usernames in 'globalnames' and for those
//   that can be automatically migrated, go ahead and do it.

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once( "$IP/maintenance/Maintenance.php" );

class MigratePass1 extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->mDescription = "Migrates local users to global users where possible";
		$this->start = microtime( true );
		$this->migrated = 0;
		$this->total = 0;
		$this->fromPrefix = '';
	}

	public function execute() {
		$this->output( "CentralAuth migration pass 1:\n" );
		$this->output( "Finding accounts which can be migrated without interaction...\n" );

		$dbBackground = CentralAuthUser::getCentralSlaveDB();
		$result = $dbBackground->select(
			'globalnames',
			array( 'gn_name' ),
			array(),
			__METHOD__ );
		foreach( $result as $row ) {
			$this->fromPrefix = $row->gn_name;
			$central = new CentralAuthUser( $row->gn_name, CentralAuthUser::READ_LATEST );
			if ( $central->storeAndMigrate() ) {
				$this->migrated++;
			}
			if ( ++$this->total % 1000 == 0 ) {
				$this->migratePassOneReport();
			}
		}
		$this->migratePassOneReport();
		$this->output( "done.\n" );
	}

	function migratePassOneReport() {
		$delta = microtime( true ) - $this->start;
		$this->output( sprintf( "%s processed %d usernames (%.1f/sec), %d (%.1f%%) fully migrated (@ %s)\n",
			wfTimestamp( TS_DB ),
			$this->total,
			$this->total / $delta,
			$this->migrated,
			$this->migrated / $this->total * 100.0,
			$this->fromPrefix
		) );
	}
}

$maintClass = "MigratePass1";
require_once( RUN_MAINTENANCE_IF_MAIN );
