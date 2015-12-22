<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once( "$IP/maintenance/Maintenance.php" );

class CheckLocalUser extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->mDescription = "Checks the contents of the localuser table and deletes invalid entries";
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

		$centralMaster = CentralAuthUtils::getCentralDB();
		$centralSlave = CentralAuthUtils::getCentralSlaveDB();

		if ( $this->getOption( 'delete', false ) !== false ) {
			$this->dryrun = false;
		}

		$wiki = $this->getOption( 'wiki', false );
		if ( $wiki !== false ) {
			$this->wiki = $wiki;
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
				'localuser',
				array( 'lu_wiki' ),
				"",
				__METHOD__,
				array(
					 "DISTINCT",
					 "ORDER BY" => "lu_wiki ASC"
				)
			);

			foreach( $result as $row ) {
				$wikis[] = $row->lu_wiki;
			}
		}

		// iterate through the wikis
		foreach( $wikis as $wiki ) {
			$localdb = wfGetDB( DB_SLAVE , array(), $wiki );
			$lastUsername = "";

			$this->output( "Checking localuser for $wiki ...\n" );

			// batch query localnames from the wiki
			do{
				$this->output( "\t ... querying from '$lastUsername'\n" );
				$result = $centralSlave->select(
					'localuser',
					array( 'lu_name' ),
					array(
						 "lu_wiki" => $wiki,
						 "lu_name > " . $centralSlave->addQuotes( $lastUsername )
					),
					__METHOD__,
					array(
						 "LIMIT" => $this->batchSize,
						 "ORDER BY" => "lu_name ASC"
					)
				);

				// iterate through each of the localuser to confirm that a local user
				foreach( $result as $u ){
					$localUser = $localdb->select(
						'user',
						array( 'user_name' ),
						array( "user_name" => $u->lu_name ),
						__METHOD__
					);

					// check to see if the user did not exist in the local user table
					if( $localUser->numRows() == 0 ) {
						if( $this->verbose ) {
							$this->output( "Local user not found for localuser entry $u->lu_name@$wiki\n" );
						}
						$this->total++;
						if( !$this->dryrun ){
							// go ahead and delete the extraneous entry
							$deleted = $centralMaster->delete(
								'localuser',
								array(
									 "lu_wiki" => $wiki,
									 "lu_name" => $u->lu_name
								),
								__METHOD__
							);
							// TODO: is there anyway to check the success of the delete?
							$this->deleted++;
						}
					}
					$lastUsername = $u->lu_name;
				}

			} while ( $result->numRows() > 0 );
		}

		$this->report();
		$this->output( "done.\n" );
	}

	function report() {
		$this->output( sprintf( "%s found %d invalid localuser, %d (%.1f%%) deleted\n",
			wfTimestamp( TS_DB ),
			$this->total,
			$this->deleted,
			$this->total > 0 ? ( $this->deleted / $this->total * 100.0 ) : 0
		) );
	}

}

$maintClass = "CheckLocalUser";
require_once( RUN_MAINTENANCE_IF_MAIN );
