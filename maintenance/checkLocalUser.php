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
		$this->user = null;
		$this->verbose = false;
		$this->batchSize = 1000;

		$this->addOption( 'delete', 'Performs delete operations on the offending entries', false, false );
		$this->addOption( 'wiki', 'If specified, only runs against local names from this wiki', false, true, 'u' );
		$this->addOption( 'allwikis', 'If specified, checks all wikis', false, false );
		$this->addOption( 'user', 'If specified, only checks the given user', false, true );
		$this->addOption( 'verbose', 'Prints more information', false, true, 'v' );
	}

	protected function initialize() {
		if ( $this->getOption( 'delete', false ) !== false ) {
			$this->dryrun = false;
		}

		$wiki = $this->getOption( 'wiki', false );
		if ( $wiki !== false && !$this->getOption( 'allwikis' ) ) {
			$this->wiki = $wiki;
		}

		$user = $this->getOption( 'user', false );
		if ( $user !== false ) {
			$this->user = User::getCanonicalName( $user );
		}

		if ( $this->getOption( 'verbose', false ) !== false ) {
			$this->verbose = true;
		}
	}

	public function execute() {
		$this->initialize();

		$centralMaster = CentralAuthUtils::getCentralDB();

		// since the keys on localnames are not conducive to batch operations and
		// because of the database shards, grab a list of the wikis and we will
		// iterate from there
		foreach( $this->getWikis() as $wiki ) {
			$this->output( "Checking localuser for $wiki ...\n" );

			$localdb = wfGetDB( DB_SLAVE , array(), $wiki );

			// batch query local users from the wiki; iterate through and verify each one
			foreach ( $this->getUsers( $wiki ) as $username ) {
				$localUser = $localdb->select(
					'user',
					array( 'user_name' ),
					array( 'user_name' => $username ),
					__METHOD__
				);

				// check to see if the user did not exist in the local user table
				if( $localUser->numRows() == 0 ) {
					if( $this->verbose ) {
						$this->output( "Local user not found for localuser entry $username@$wiki\n" );
					}
					$this->total++;
					if( !$this->dryrun ){
						// go ahead and delete the extraneous entry
						$deleted = $centralMaster->delete(
							'localuser',
							array(
								 "lu_wiki" => $wiki,
								 "lu_name" => $username
							),
							__METHOD__
						);
						// TODO: is there anyway to check the success of the delete?
						$this->deleted++;
					}
				}
			}
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

	protected function getWikis() {
		$centralSlave = CentralAuthUtils::getCentralSlaveDB();
		$wikis = array();

		if ( !is_null( $this->wiki ) ) {
			$wikis[] = $this->wiki;
		} else {
			$conds = array();
			if ( !is_null( $this->user ) ) {
				$conds['lu_name'] = $this->user;
			}
			$result = $centralSlave->select(
				'localuser',
				array( 'lu_wiki' ),
				$conds,
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

		return $wikis;
	}

	protected function getUsers( $wiki ) {
		if ( !is_null( $this->user ) ) {
			$this->output( "\t ... querying '$this->user'\n" );
			yield $this->user;
			return;
		}

		$centralSlave = CentralAuthUtils::getCentralSlaveDB();
		$lastUsername = '';
		do {
			$this->output( "\t ... querying from '$lastUsername'\n" );
			$result = $centralSlave->select(
				'localuser',
				array( 'lu_name' ),
				array(
					'lu_wiki' => $wiki,
					'lu_name > ' . $centralSlave->addQuotes( $lastUsername ),
				),
				__METHOD__,
				array(
					"LIMIT" => $this->batchSize,
					"ORDER BY" => "lu_name ASC"
				)
			);
			foreach( $result as $u ) {
				yield $u->lu_name;
			}

			$lastUsername = $u->lu_name;
		} while ( $result->numRows() > 0 );
	}
}

$maintClass = "CheckLocalUser";
require_once( RUN_MAINTENANCE_IF_MAIN );
