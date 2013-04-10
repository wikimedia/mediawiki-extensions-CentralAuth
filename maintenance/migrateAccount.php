<?php

if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../..';
}
require_once( "$IP/maintenance/Maintenance.php" );

class MigrateAccount extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->mDescription = "Migrates the specified local account to a global account";
		$this->start = microtime( true );
		$this->migrated = 0;
		$this->total = 0;
		$this->username = '';
		$this->safe = false;

		$this->addOption( 'username', 'The user name to migrate', true, true, 'u' );
		$this->addOption( 'safe', 'Only migrates accounts with one instance of the username across all wikis', false, false );
	}

	public function execute() {
		$this->username = $this->getOption( 'username' );
		$this->safe = $this->getOption( 'safe' );

		$this->output( "CentralAuth account migration for: " . $this->username . "\n");

		$dbBackground = CentralAuthUser::getCentralSlaveDB();

		$globalusers = $dbBackground->select(
			'globaluser',
			array( 'gu_name' ),
			array( 'gu_name' => $this->username ),
			__METHOD__
		);

		if ( $globalusers->numRows() > 0 ) {
			$this->output( "ERROR: A global account already exists for: $this->username\n" );
			exit( 1 );
		}

		$localusers = $dbBackground->select(
			'localnames',
			array( 'ln_name', 'ln_wiki' ),
			array( 'ln_name' => $this->username ),
			__METHOD__
		);

		if ( $localusers->numRows() == 0 ) {
			$this->output( "ERROR: No local accounts found for: $this->username\n" );
			exit( 1 );
		}

		if ( $this->safe ) {
			if ( $localusers->numRows() !== 1 ) {
				$this->output( "ERROR: More than 1 local user account found for username:\n" );
				foreach( $localusers as $row ) {
					$this->output( "\t" . $row->ln_name . "@" . $row->ln_wiki . "\n" );
				}
				$this->output( "ABORTING\n" );
				exit( 1 );
			}
		}

		$this->total++;

		$central = new CentralAuthUser( $this->username );
		if ( $central->storeAndMigrate() ) {
			$this->migrated++;
		}

		$this->migratePassOneReport();
		$this->output( "done.\n" );
	}

	function migratePassOneReport() {
		$delta = microtime( true ) - $this->start;
		$this->output( sprintf( "%s processed %d usernames (%.1f/sec), %d (%.1f%%) fully migrated (username: %s)\n",
			wfTimestamp( TS_DB ),
			$this->total,
			$this->total / $delta,
			$this->migrated,
			( $this->migrated / $this->total * 100.0 ) ? $this->total > 0 : 0 ,
			$this->username
		) );
	}
}

$maintClass = "MigrateAccount";
require_once( RUN_MAINTENANCE_IF_MAIN );