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

class CheckLocalNames extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->mDescription = "Checks the contents of the localnames table and deletes invalid entries";
		$this->start = microtime( true );
		$this->deleted = 0;
		$this->total = 0;
		$this->dryrun = true;
		$this->wiki = null;
		$this->verbose = false;
		$this->batchSize = 1000;

		$this->addOption( 'delete', 'Performs delete operations on the offending entries', false, false );
		$this->addOption( 'wiki', 'If specified, only runs against local names from this wiki', false, true, 'u' );
		$this->addOption( 'verbose', 'Prints more information', false, true, 'v' );
	}

	public function execute() {

		$centralMaster = CentralAuthUser::getCentralDB();
		$centralSlave = CentralAuthUser::getCentralSlaveDB();

		if ( $this->getOption( 'delete', false ) === true ) {
			$this->dryrun = false;
		}
		if ( $this->getOption( 'wiki', false ) !== false ) {
			$this->wiki = true;
		}
		if ( $this->getOption( 'verbose', false ) !== false ) {
			$this->verbose = true;
		}

		// since the keys on localnames are not conducive to batch operations and
		// because of the database shards, grab a list of the wikis and we will
		// iterate from there
		$wikis = array();
		if ( !is_null( $this->wiki ) ) {
			$wikis[] = $this->wiki;
		} else {
			$result = $centralSlave->select(
				'localnames',
				array( 'ln_wiki' ),
				"",
				__FUNCTION__,
				array( "distinct", "order by ln_wiki ASC" )
			);

			foreach( $result as $row ) {
				$wikis[] = $row->ln_wiki;
			}
		}

		// iterate through the wikis
		foreach( $wikis as $wiki ) {
			$localdb = wfGetLB( $wiki )->getConnection( DB_SLAVE , array(), $wiki );
			$lastUsername = "";

			$this->output( "Checking localnames for $wiki ...\n" );

			// batch query localnames from the wiki
			do{
				$this->output( "\t ... querying from '$lastUsername'\n" );
				$result = $centralSlave->select(
					'localnames',
					array( 'ln_user' ),
					array(
						 "ln_wiki = " . $centralSlave->addQuotes( $wiki ),
						 "ln_user > " . $centralSlave->addQuotes( $lastUsername )
					),
					__FUNCTION__,
					array(
						 "limit $this->batchSize",
						 "order by ln_name ASC"
					)
				);

				// iterate through each of the localnames to confirm that a local user
				foreach( $result as $u ){
					$localUser = $localdb->select(
						'user',
						array( 'user_name' ),
						array( "user_name" => $u->user_name ),
						__FUNCTION__
					);

					// check to see if the user did not exist in the local user table
					if( $localUser->numRows() == 0 ) {
						if( $this->verbose ) {
							$this->output( "Local user not found for localname entry $u->user_name@$wiki\n" );
						}
						$this->total++;
						if( !$this->dryrun ){
							// go ahead and delete the extraneous entry
							$deleted = $centralMaster->delete(
								'localnames',
								array(
									 "ln_wiki" => $wiki,
									 "ln_user" => $u->user_name
								),
								__FUNCTION__
							);
							// TODO: is there anyway to check the success of the delete?
							$this->deleted++;
						}
					}
					$lastUsername = $u->user_name;
				}

			} while ( $result->numRows() > 0 );
		}

		$this->report();
		$this->output( "done.\n" );
	}

	function report() {
		$this->output( sprintf( "%s found %d invalid localnames, %d (%.1f%%) deleted\n",
			wfTimestamp( TS_DB ),
			$this->total,
			$this->deleted,
			$this->total > 0 ? ( $this->deleted / $this->total * 100.0 ) : 0
		) );
	}

}

$maintClass = "CheckLocalNames";
require_once( RUN_MAINTENANCE_IF_MAIN );