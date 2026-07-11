<?php
// --> disable account creations, password changes
// pass 0:
// * generate 'localnames' entries for each user on each wiki
// --> enable

namespace MediaWiki\Extension\CentralAuth\Maintenance;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\MainConfigNames;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\WikiMap\WikiMap;

/**
 * Copy user data for this wiki into the localnames table
 */
class MigratePass0 extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CentralAuth' );
	}

	public function execute() {
		$databaseName = $this->getConfig()->get( MainConfigNames::DBname );
		$this->output( "CentralAuth migration pass 0:\n" );
		$this->output( "$databaseName preparing migration data...\n" );
		$this->doPassZero();
		$this->output( "done.\n" );
	}

	private function doPassZero() {
		$dbr = $this->getReplicaDB();
		$chunkSize = 1000;

		$start = microtime( true );
		$migrated = 0;
		$users = [];

		$caDbManager = CentralAuthServices::getDatabaseManager( $this->getServiceContainer() );

		// List all user accounts on this wiki in the migration table
		// on the central authentication server.

		$lastUser = $dbr->newSelectQueryBuilder()
			->select( 'MAX(user_id)' )
			->from( 'user' )
			->caller( __METHOD__ )
			->fetchField();

		for ( $min = 0; $min <= $lastUser; $min += $chunkSize ) {
			$max = $min + $chunkSize - 1;
			$result = $dbr->newSelectQueryBuilder()
				->select( [ 'user_id', 'user_name' ] )
				->from( 'user' )
				->where( [
					$dbr->expr( 'user_id', '>=', $min ),
					$dbr->expr( 'user_id', '<=', $max ),
				] )
				->caller( __METHOD__ )
				->fetchResultSet();

			foreach ( $result as $row ) {
				$users[] = [
					'ln_wiki' => WikiMap::getCurrentWikiId(),
					'ln_name' => $row->user_name,
				];
				++$migrated;
			}

			$caDbManager
				->getCentralPrimaryDB()
				->newInsertQueryBuilder()
				->insertInto( 'localnames' )
				->ignore()
				->rows( $users )
				->caller( __METHOD__ )
				->execute();

			// clear the array for the next pass
			$users = [];

			$delta = microtime( true ) - $start;
			$rate = $delta ? $migrated / $delta : 0.0;
			$this->output( sprintf( "%d (%0.1f%%) done in %0.1f secs (%0.3f accounts/sec).\n",
				$migrated,
				min( $max, $lastUser ) / $lastUser * 100.0,
				$delta,
				$rate ) );

			if ( ( $min + $chunkSize ) % ( $chunkSize * 10 ) == 0 ) {
				$this->output( "Waiting for replicas to catch up ... " );
				$this->waitForReplication();
				$this->output( "done\n" );
			}
		}
	}
}

// @codeCoverageIgnoreStart
$maintClass = MigratePass0::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
