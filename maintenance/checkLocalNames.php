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
use Wikimedia\Rdbms\SelectQueryBuilder;

class CheckLocalNames extends Maintenance {

	protected float $start;

	protected int $deleted = 0;

	protected int $total = 0;

	protected bool $dryrun;

	protected ?string $wiki;

	protected bool $verbose;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CentralAuth' );
		$this->addDescription( "Checks the contents of the localnames table and deletes " .
			"invalid entries" );

		$this->addOption( 'delete',
			'Performs delete operations on the offending entries', false, false
		);
		$this->addOption( 'wiki',
			'If specified, only runs against local names from this wiki', false, true, 'u'
		);
		$this->addOption( 'verbose', 'Prints more information', false, false, 'v' );
		$this->setBatchSize( 1000 );
	}

	public function execute() {
		$databaseManager = CentralAuthServices::getDatabaseManager();
		$centralPrimaryDb = $databaseManager->getCentralPrimaryDB();
		$centralReplica = $databaseManager->getCentralReplicaDB();

		$this->dryrun = $this->hasOption( 'delete' );
		$this->wiki = $this->getOption( 'wiki' );
		$this->verbose = $this->hasOption( 'verbose' );

		$this->start = microtime( true );

		// since the keys on localnames are not conducive to batch operations and
		// because of the database shards, grab a list of the wikis and we will
		// iterate from there
		if ( $this->wiki !== null ) {
			$wikis = [ $this->wiki ];
		} else {
			$wikis = $centralReplica->newSelectQueryBuilder()
				->select( 'ln_wiki' )
				->distinct()
				->from( 'localnames' )
				->orderBy( 'ln_wiki', SelectQueryBuilder::SORT_ASC )
				->caller( __METHOD__ )
				->fetchFieldValues();
		}

		// iterate through the wikis
		foreach ( $wikis as $wiki ) {
			$localdb = $databaseManager->getLocalDB( DB_REPLICA, $wiki );
			$lastUsername = "";

			$this->output( "Checking localnames for $wiki ...\n" );

			// batch query localnames from the wiki
			do {
				$this->output( "\t ... querying from '$lastUsername'\n" );
				$result = $centralReplica->newSelectQueryBuilder()
					->select( 'ln_name' )
					->from( 'localnames' )
					->where( [
						'ln_wiki' => $wiki,
						$centralReplica->expr( 'ln_name', '>', $lastUsername )
					] )
					->orderBy( 'ln_name', SelectQueryBuilder::SORT_ASC )
					->limit( $this->mBatchSize )
					->caller( __METHOD__ )
					->fetchResultSet();

				// iterate through each of the localnames to confirm that a local user
				foreach ( $result as $u ) {
					$localUser = $localdb->newSelectQueryBuilder()
						->select( 'user_name' )
						->from( 'user' )
						->where( [ 'user_name' => $u->ln_name ] )
						->caller( __METHOD__ )
						->fetchResultSet();

					// check to see if the user did not exist in the local user table
					if ( $localUser->numRows() == 0 ) {
						if ( $this->verbose ) {
							$this->output(
								"Local user not found for localname entry $u->ln_name@$wiki\n"
							);
						}
						$this->total++;
						if ( !$this->dryrun ) {
							// go ahead and delete the extraneous entry
							$centralPrimaryDb->newDeleteQueryBuilder()
								->deleteFrom( 'localnames' )
								->where( [
									'ln_wiki' => $wiki,
									'ln_name' => $u->ln_name
								] )
								->caller( __METHOD__ )
								->execute();
							// TODO: is there anyway to check the success of the delete?
							$this->deleted++;
						}
					}
					$lastUsername = $u->ln_name;
				}

			} while ( $result->numRows() > 0 );
		}

		$this->report();
		$this->output( "done.\n" );
	}

	private function report() {
		$this->output( sprintf( "%s found %d invalid localnames, %d (%.1f%%) deleted\n",
			wfTimestamp( TS_DB ),
			$this->total,
			$this->deleted,
			$this->total > 0 ? ( $this->deleted / $this->total * 100.0 ) : 0
		) );
	}

}

// @codeCoverageIgnoreStart
$maintClass = CheckLocalNames::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
