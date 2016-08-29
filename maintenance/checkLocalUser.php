<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once( "$IP/maintenance/Maintenance.php" );

class CheckLocalUser extends Maintenance {
	protected $table = 'localuser';
	protected $tablePrefix = 'lu';
	protected $start;
	protected $deleted = 0;
	protected $total = 0;
	protected $dryrun = true;
	protected $wiki = null;
	protected $user = null;
	protected $verbose = false;

	public function __construct() {
		parent::__construct();
		$this->start = microtime( true );
		$this->mBatchSize = 1000;

		$this->addDescription( 'Checks the contents of the localuser or localnames table and deletes invalid entries' );
		$this->addOption( 'table', 'Which table to fix (localuser/localnames), default: localuser', false, true );
		$this->addOption( 'delete', 'Performs delete operations on the offending entries', false, false );
		$this->addOption( 'delete-nowiki', 'Delete entries associated with invalid wikis', false, false );
		$this->addOption( 'wiki', 'If specified, only runs against local names from this wiki', false, true, 'u' );
		$this->addOption( 'allwikis', 'If specified, checks all wikis', false, false );
		$this->addOption( 'user', 'If specified, only checks the given user', false, true );
		$this->addOption( 'verbose', 'Prints more information', false, true, 'v' );
	}

	protected function initialize() {
		$table = $this->getOption( 'table' );
		if ( !in_array( $table, [ null, 'localuser', 'localnames' ], true ) ) {
			$this->error( "table must be one of 'localuser', 'localnames'", 1 );
		}
		if ( $table === 'localnames' ) {
			$this->table = 'localnames';
			$this->tablePrefix = 'ln';
		}

		if ( $this->getOption( 'delete', false ) !== false || $this->getOption( 'delete-nowiki', false ) !== false ) {
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

		// since the keys on localuser/localnames are not conducive to batch operations and
		// because of the database shards, grab a list of the wikis and we will
		// iterate from there
		foreach( $this->getWikis() as $wiki ) {
			$this->output( "Checking $this->table for $wiki ...\n" );

			if ( !WikiMap::getWiki( $wiki ) ) {
				// record is left over from some wiki that has been disabled
				if ( !$this->dryrun ) {
					if ( $this->getOption( 'delete-nowiki' ) ) {
						$this->output( "$wiki does not exist, deleting entries...\n" );
						$conds = [ $this->tablePrefix . '_wiki' => $wiki ];
						if ( $this->user ) {
							$conds[$this->tablePrefix . '_name'] = $this->user;
						}
						$centralMaster->delete( $this->table, $conds, __METHOD__ );
						$this->deleted ++;
					} else {
						$this->output( "$wiki does not exist, use --delete-nowiki to delete entries...\n" );
					}
				} else {
					$this->output( "$wiki does not exist\n" );
				}
				continue;
			}

			$localdb = wfGetDB( DB_SLAVE , [], $wiki );

			// batch query local users from the wiki; iterate through and verify each one
			foreach ( $this->getUsers( $wiki ) as $username ) {
				$localUser = $localdb->select(
					'user',
					[ 'user_name' ],
					[ 'user_name' => $username ],
					__METHOD__
				);

				// check to see if the user did not exist in the local user table
				if( $localUser->numRows() == 0 ) {
					if( $this->verbose ) {
						$this->output( "Local user not found for $this->table entry $username@$wiki\n" );
					}
					$this->total++;
					if( !$this->dryrun ){
						// go ahead and delete the extraneous entry
						$deleted = $centralMaster->delete(
							$this->table,
							[
								$this->tablePrefix . '_wiki' => $wiki,
								$this->tablePrefix . '_name' => $username,
							],
							__METHOD__
						);
						if ( $deleted->numRows() ) {
							$this->deleted++;
						} else {
							$this->error( "Failed to delete $username@$wiki from $this->table!\n" );
						}
					}
				}
			}
		}

		$this->report();
		$this->output( "done.\n" );
	}

	function report() {
		$this->output( sprintf( "%s found %d invalid $this->table, %d (%.1f%%) deleted\n",
			wfTimestamp( TS_DB ),
			$this->total,
			$this->deleted,
			$this->total > 0 ? ( $this->deleted / $this->total * 100.0 ) : 0
		) );
	}

	protected function getWikis() {
		$centralSlave = CentralAuthUtils::getCentralSlaveDB();
		$wikis = [];

		if ( !is_null( $this->wiki ) ) {
			$wikis[] = $this->wiki;
		} else {
			$conds = [];
			if ( !is_null( $this->user ) ) {
				$conds[$this->tablePrefix . '_name'] = $this->user;
			}
			$result = $centralSlave->select(
				$this->table,
				[ $this->tablePrefix . '_wiki' ],
				$conds,
				__METHOD__,
				[
					'DISTINCT',
					'ORDER BY' => $this->tablePrefix . '_wiki ASC',
				]
			);

			foreach( $result as $row ) {
				$wikis[] = $row->{$this->tablePrefix . '_wiki'};
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
		while ( true ) {
			$this->output( "\t ... querying from '$lastUsername'\n" );
			$result = $centralSlave->select(
				$this->table,
				[ $this->tablePrefix . '_name' ],
				[
					$this->tablePrefix . '_wiki' => $wiki,
					$this->tablePrefix . '_name > ' . $centralSlave->addQuotes( $lastUsername ),
				],
				__METHOD__,
				[
					'LIMIT' => $this->mBatchSize,
					'ORDER BY' => $this->tablePrefix . '_name ASC',
				]
			);

			if ( $result->numRows() === 0 ) {
				break;
			}

			foreach( $result as $u ) {
				yield $u->{$this->tablePrefix . '_name'};
			}
			$lastUsername = $u->{$this->tablePrefix . '_name'};
		}
	}
}

$maintClass = "CheckLocalUser";
require_once( RUN_MAINTENANCE_IF_MAIN );
