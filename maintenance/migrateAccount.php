<?php

if ( PHP_SAPI != 'cli' ) {
	print "This script must be run from a shell";
	die();
}

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
		$this->safe = false;
		$this->dbBackground = null;

		$this->addOption( 'userlist', 'List of usernames to migrate', false, true );
		$this->addOption( 'username', 'The user name to migrate', false, true, 'u' );
		$this->addOption( 'safe', 'Only migrates accounts with one instance of the username across all wikis', false, false );
	}

	public function execute() {

		$this->dbBackground = CentralAuthUser::getCentralSlaveDB();

		// check to see if we are processing a single username
		if ( $this->getOption( 'username', false ) !== false ) {
			$username = $this->getOption( 'username' );
			$this->migrate( $username );

		} elseif ( $this->getOption( 'userlist', false ) !== false ) {
			$list = $this->getOption( 'userlist' );
			if ( !is_file( $list ) ) {
				$this->output( "ERROR - File not found: $list" );
				exit( 1 );
			}
			$file = fopen( $list, 'r' );
			if ( $file === false ) {
				$this->output( "ERROR - Could not open file: $list" );
				exit( 1 );
			}
			while( $username = fgets( $file ) ) {
				$username = trim( $username ); // trim the \n
				$this->migrate( $username );
			}
			fclose( $file );

		} else {
			$this->output( "ERROR - No username or list of usernames given" );
			exit( 1 );
		}

		$this->migratePassOneReport();
		$this->output( "done.\n" );

		echo "Waiting for slaves to catch up ... ";
		wfWaitForSlaves( false, 'centralauth' );
		echo "done\n";
	}

	function migrate( $username ) {
		$this->total++;
		$this->output( "CentralAuth account migration for: " . $username . "\n");

		$globalusers = $this->dbBackground->select(
			'globaluser',
			array( 'gu_name' ),
			array( 'gu_name' => $username ),
			__METHOD__
		);

		if ( $globalusers->numRows() > 0 ) {
			$this->output( "ERROR: A global account already exists for: $username\n" );
			return false;
		}

		$localusers = $this->dbBackground->select(
			'localnames',
			array( 'ln_name', 'ln_wiki' ),
			array( 'ln_name' => $username ),
			__METHOD__
		);

		if ( $localusers->numRows() == 0 ) {
			$this->output( "ERROR: No local accounts found for: $username\n" );
			return false;
		}

		if ( $this->safe ) {
			if ( $localusers->numRows() !== 1 ) {
				$this->output( "ERROR: More than 1 local user account found for username: $username\n" );
				foreach( $localusers as $row ) {
					$this->output( "\t" . $row->ln_name . "@" . $row->ln_wiki . "\n" );
				}
				return false;
			}
		}

		$central = new CentralAuthUser( $username );
		if ( $central->storeAndMigrate() ) {
			$this->migrated++;
			return true;
		}

		return false;
	}

	function migratePassOneReport() {
		$delta = microtime( true ) - $this->start;
		$this->output( sprintf( "%s processed %d usernames (%.1f/sec), %d (%.1f%%) fully migrated\n",
			wfTimestamp( TS_DB ),
			$this->total,
			$this->total / $delta,
			$this->migrated,
			$this->total > 0 ? ( $this->migrated / $this->total * 100.0 ) : 0
		) );
	}
}

$maintClass = "MigrateAccount";
require_once( RUN_MAINTENANCE_IF_MAIN );