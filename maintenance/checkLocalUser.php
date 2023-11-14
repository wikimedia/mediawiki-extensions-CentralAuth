<?php

use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\MediaWikiServices;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Rdbms\SelectQueryBuilder;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class CheckLocalUser extends Maintenance {

	/** @var float */
	protected $start;

	/** @var int */
	protected $deleted;

	/** @var int */
	protected $total;

	/** @var bool */
	protected $dryrun;

	/** @var string|null */
	protected $wiki;

	/** @var string|null|false */
	protected $user;

	/** @var bool */
	protected $verbose;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CentralAuth' );
		$this->addDescription( 'Checks the contents of the localuser table and deletes invalid entries' );
		$this->start = microtime( true );
		$this->deleted = 0;
		$this->total = 0;
		$this->dryrun = true;
		$this->wiki = null;
		$this->user = null;
		$this->verbose = false;

		$this->addOption( 'delete',
			'Performs delete operations on the offending entries', false, false
		);
		$this->addOption( 'delete-nowiki',
			'Delete entries associated with invalid wikis', false, false
		);
		$this->addOption( 'wiki',
			'If specified, only runs against local names from this wiki', false, true, 'u'
		);
		$this->addOption( 'allwikis', 'If specified, checks all wikis', false, false );
		$this->addOption( 'user', 'If specified, only checks the given user', false, true );
		$this->addOption( 'verbose', 'Prints more information', false, true, 'v' );
		$this->setBatchSize( 1000 );
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
			$userNameUtils = MediaWikiServices::getInstance()->getUserNameUtils();
			$this->user = $userNameUtils->getCanonical( $user );
		}

		if ( $this->getOption( 'verbose', false ) !== false ) {
			$this->verbose = true;
		}
	}

	public function execute() {
		$this->initialize();

		$centralPrimaryDb = CentralAuthServices::getDatabaseManager()->getCentralPrimaryDB();

		// since the keys on localnames are not conducive to batch operations and
		// because of the database shards, grab a list of the wikis and we will
		// iterate from there
		foreach ( $this->getWikis() as $wiki ) {
			$this->output( "Checking localuser for $wiki ...\n" );

			if ( !WikiMap::getWiki( $wiki ) ) {
				// localuser record is left over from some wiki that has been disabled
				if ( !$this->dryrun ) {
					if ( $this->getOption( 'delete-nowiki' ) ) {
						$this->output( "$wiki does not exist, deleting entries...\n" );
						$conds = [ 'lu_wiki' => $wiki ];
						if ( $this->user ) {
							$conds['lu_name'] = $this->user;
						}
						$centralPrimaryDb->delete( 'localuser', $conds, __METHOD__ );
						$this->deleted ++;
					} else {
						$this->output(
							"$wiki does not exist, use --delete-nowiki to delete entries...\n"
						);
					}
				} else {
					$this->output( "$wiki does not exist\n" );
				}
				continue;
			}

			$localdb = wfGetDB( DB_REPLICA, [], $wiki );

			// batch query local users from the wiki; iterate through and verify each one
			foreach ( $this->getUsers( $wiki ) as $username ) {
				$localUser = $localdb->newSelectQueryBuilder()
					->select( 'user_name' )
					->from( 'user' )
					->where( [ 'user_name' => $username ] )
					->caller( __METHOD__ )
					->fetchResultSet();

				// check to see if the user did not exist in the local user table
				if ( $localUser->numRows() == 0 ) {
					if ( $this->verbose ) {
						$this->output(
							"Local user not found for localuser entry $username@$wiki\n"
						);
					}
					$this->total++;
					if ( !$this->dryrun ) {
						// go ahead and delete the extraneous entry
						$centralPrimaryDb->delete(
							'localuser',
							[
								"lu_wiki" => $wiki,
								"lu_name" => $username
							],
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

	private function report() {
		$this->output( sprintf( "%s found %d invalid localuser, %d (%.1f%%) deleted\n",
			wfTimestamp( TS_DB ),
			$this->total,
			$this->deleted,
			$this->total > 0 ? ( $this->deleted / $this->total * 100.0 ) : 0
		) );
	}

	protected function getWikis() {
		$centralReplica = CentralAuthServices::getDatabaseManager()->getCentralReplicaDB();

		if ( $this->wiki !== null ) {
			return [ $this->wiki ];
		} else {
			$conds = [];
			if ( $this->user !== null ) {
				$conds['lu_name'] = $this->user;
			}
			return $centralReplica->newSelectQueryBuilder()
				->select( 'lu_wiki' )
				->distinct()
				->from( 'localuser' )
				->where( $conds )
				->orderBy( 'lu_wiki', SelectQueryBuilder::SORT_ASC )
				->caller( __METHOD__ )
				->fetchFieldValues();
		}
	}

	protected function getUsers( $wiki ) {
		if ( $this->user !== null ) {
			$this->output( "\t ... querying '$this->user'\n" );
			yield $this->user;
			return;
		}

		$centralReplica = CentralAuthServices::getDatabaseManager()->getCentralReplicaDB();
		$lastUsername = '';
		do {
			$this->output( "\t ... querying from '$lastUsername'\n" );
			$result = $centralReplica->newSelectQueryBuilder()
				->select( 'lu_name' )
				->from( 'localuser' )
				->where( [
					'lu_wiki' => $wiki,
					$centralReplica->expr( 'lu_name', '>', $lastUsername ),
				] )
				->orderBy( 'lu_name', SelectQueryBuilder::SORT_ASC )
				->limit( $this->mBatchSize )
				->caller( __METHOD__ )
				->fetchResultSet();

			foreach ( $result as $u ) {
				yield $u->lu_name;
			}

			$lastUsername = $u->lu_name ?? null;
		} while ( $result->numRows() > 0 );
	}
}

$maintClass = CheckLocalUser::class;
require_once RUN_MAINTENANCE_IF_MAIN;
