<?php

namespace MediaWiki\Extension\CentralAuth\Maintenance;

use Generator;
use MediaWiki\Extension\CentralAuth\CentralAuthReadOnlyError;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Rdbms\SelectQueryBuilder;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

class CheckLocalUser extends Maintenance {

	protected string $table = 'localuser';
	protected string $tablePrefix = 'lu';

	protected float $start;

	protected int $deleted = 0;

	protected int $total = 0;

	protected bool $dryrun;

	protected ?string $wiki = null;

	protected ?string $user = null;

	protected bool $verbose = false;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CentralAuth' );
		$this->addDescription( 'Checks the contents of the localuser or localnames table and deletes invalid entries' );

		$this->addOption( 'table', 'Which table to fix (localuser/localnames), default: localuser', false, true );
		$this->addOption( 'delete',
			'Performs delete operations on the offending entries',
		);
		$this->addOption( 'delete-nowiki',
			'Delete entries associated with invalid wikis'
		);
		$this->addOption( 'wiki',
			'If specified, only runs against local names from this wiki', false, true, 'u'
		);
		$this->addOption( 'allwikis', 'If specified, checks all wikis', false, false );
		$this->addOption( 'user', 'If specified, only checks the given user', false, true );
		$this->addOption( 'verbose', 'Prints more information', false, false, 'v' );
		$this->setBatchSize( 1000 );
	}

	/**
	 * @throws CentralAuthReadOnlyError
	 */
	public function execute() {
		$table = $this->getOption( 'table' );
		if ( !in_array( $table, [ null, 'localuser', 'localnames' ], true ) ) {
			$this->fatalError( "table must be one of 'localuser', 'localnames'" );
		}
		if ( $table === 'localnames' ) {
			$this->table = 'localnames';
			$this->tablePrefix = 'ln';
		}

		if ( $this->hasOption( 'delete' ) || $this->hasOption( 'delete-nowiki' ) ) {
			$this->dryrun = false;
		}

		$wiki = $this->getOption( 'wiki', false );
		if ( $wiki !== false && !$this->hasOption( 'allwikis' ) ) {
			$this->wiki = $wiki;
		}

		if ( $this->hasOption( 'user' ) ) {
			$this->user = $this->getServiceContainer()->getUserNameUtils()
				->getCanonical( $this->getOption( 'user' ) );
		}

		$this->verbose = $this->hasOption( 'verbose' );

		$centralPrimaryDb = CentralAuthServices::getDatabaseManager()->getCentralPrimaryDB();

		$this->start = microtime( true );

		// since the keys on localuser/localnames are not conducive to batch operations and
		// because of the database shards, grab a list of the wikis, and we will
		// iterate from there
		foreach ( $this->getWikis() as $wiki ) {
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
						$centralPrimaryDb->newDeleteQueryBuilder()
							->deleteFrom( $this->table )
							->where( $conds )
							->caller( __METHOD__ )
							->execute();
						$this->deleted++;
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

			$localdb = CentralAuthServices::getDatabaseManager()->getLocalDB( DB_REPLICA, $wiki );

			// batch query local users from the wiki; iterate through and verify each one
			foreach ( $this->getUsers( $wiki ) as $username ) {
				$localUser = $localdb->newSelectQueryBuilder()
					->select( 'user_name' )
					->from( 'user' )
					->where( [ 'user_name' => $username ] )
					->caller( __METHOD__ )
					->fetchResultSet();

				// check to see if the user did not exist in the local user table
				if ( $localUser->numRows() === 0 ) {
					if ( $this->verbose ) {
						$this->output(
							"Local user not found for $this->table entry $username@$wiki\n"
						);
					}
					$this->total++;
					if ( !$this->dryrun ) {
						// go ahead and delete the extraneous entry
						$centralPrimaryDb->newDeleteQueryBuilder()
							->deleteFrom( $this->table )
							->where( [
								$this->tablePrefix . '_wiki' => $wiki,
								$this->tablePrefix . '_name' => $username
							] )
							->caller( __METHOD__ )
							->execute();
						// TODO: is there anyway to check the success of the delete?
						$this->deleted++;
					}
				}
			}
		}

		$this->report();
		$this->output( "done.\n" );
	}

	private function report(): void {
		$this->output( sprintf( "%s found %d invalid $this->table (%.1f%%) deleted\n",
			wfTimestamp( TS_DB ),
			$this->total,
			$this->deleted,
			$this->total > 0 ? ( $this->deleted / $this->total * 100.0 ) : 0
		) );
	}

	/**
	 * @return array|null[]|string[]
	 */
	protected function getWikis(): array {
		$centralReplica = CentralAuthServices::getDatabaseManager()->getCentralReplicaDB();

		if ( $this->wiki !== null ) {
			return [ $this->wiki ];
		}

		$conds = [];
		if ( $this->user !== null ) {
			$conds[$this->tablePrefix . '_name'] = $this->user;
		}

		return $centralReplica->newSelectQueryBuilder()
			->select( $this->tablePrefix . '_wiki' )
			->distinct()
			->from( $this->table )
			->where( $conds )
			->orderBy( $this->tablePrefix . '_wiki', SelectQueryBuilder::SORT_ASC )
			->caller( __METHOD__ )
			->fetchFieldValues();
	}

	protected function getUsers( string $wiki ): Generator {
		if ( $this->user !== null ) {
			$this->output( "\t ... querying '$this->user'\n" );
			yield $this->user;
			return;
		}

		$centralReplica = CentralAuthServices::getDatabaseManager()->getCentralReplicaDB();
		$lastUsername = '';
		while ( true ) {
			$this->output( "\t ... querying from '$lastUsername'\n" );
			$result = $centralReplica->newSelectQueryBuilder()
				->select( $this->tablePrefix . '_name' )
				->from( $this->table )
				->where( [
					$this->tablePrefix . '_wiki' => $wiki,
					$centralReplica->expr( $this->tablePrefix . '_name', '>', $lastUsername ),
				] )
				->orderBy( $this->tablePrefix . '_name', SelectQueryBuilder::SORT_ASC )
				->limit( $this->mBatchSize )
				->caller( __METHOD__ )
				->fetchResultSet();

			if ( $result->numRows() === 0 ) {
				break;
			}

			foreach ( $result as $u ) {
				yield $u->{$this->tablePrefix . '_name'};
			}
			$lastUsername = $u->{$this->tablePrefix . '_name'} ?? null;
		}
	}
}

// @codeCoverageIgnoreStart
$maintClass = CheckLocalUser::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
