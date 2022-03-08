<?php

namespace MediaWiki\Extension\CentralAuth\Maintenance;

use Maintenance;
use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\MediaWikiServices;
use RuntimeException;
use Wikimedia\Rdbms\LBFactory;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class PopulateGlobalEditCount extends Maintenance {
	private const READ_BATCH_SIZE = 1000;

	/** @var LBFactory */
	private $lbFactory;

	/** @var CentralAuthDatabaseManager */
	private $databaseManager;

	/** @var int */
	private $lastReportTime = 0;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CentralAuth' );

		// Batch size for write queries
		$this->setBatchSize( 100 );
		$this->addOption( 'start', 'gu_id value to start at', false, true );
	}

	private function init() {
		$services = MediaWikiServices::getInstance();
		$this->lbFactory = $services->getDBLoadBalancerFactory();
		$this->databaseManager = CentralAuthServices::getDatabaseManager( $services );
	}

	public function execute() {
		$this->init();
		$dbcr = $this->databaseManager->getCentralDB( DB_REPLICA );
		$dbcw = $this->databaseManager->getCentralDB( DB_PRIMARY );
		$lastId = (int)$dbcr->newSelectQueryBuilder()
			->select( 'MAX(gu_id)' )
			->from( 'globaluser' )
			->caller( __METHOD__ )
			->fetchField();

		$numGlobalAccounts = 0;
		$numUpdated = 0;

		$start = (int)$this->getOption( 'start', 0 );
		for ( $batchStartId = $start; $batchStartId < $lastId; $batchStartId += self::READ_BATCH_SIZE ) {
			$this->showProgress( $batchStartId, $lastId );
			$batchEndId = $batchStartId + self::READ_BATCH_SIZE - 1;
			$res = $dbcr->newSelectQueryBuilder()
				->select( [
					'lu_global_id',
					'lu_local_id',
					'lu_wiki',
					'gec_count'
				] )
				// The globaluser table is just needed for batch ordering
				->from( 'globaluser' )
				->join( 'localuser', null, [ 'lu_name=gu_name' ] )
				->leftJoin( 'global_edit_count', null, [ 'gu_id=gec_user' ] )
				->where( [
					"gu_id BETWEEN $batchStartId AND $batchEndId",
					"lu_global_id <> 0",
				] )
				->orderBy( [ 'gu_id', 'lu_wiki' ] )
				->caller( __METHOD__ )
				->fetchResultSet();

			/** @var int[][] $localIds All local IDs by wiki */
			$localIds = [];
			/** @var int[][] $globalIds The global ID for each wiki/local ID */
			$globalIds = [];
			/** @var int[] $originalCounts The gec_count for each global ID */
			$originalCounts = [];
			/** @var int[] $countsByGlobalId The final computed counts */
			$countsByGlobalId = [];

			// Read the global result into arrays
			foreach ( $res as $row ) {
				$localIds[$row->lu_wiki][] = (int)$row->lu_local_id;
				if ( !isset( $originalCounts[(int)$row->lu_global_id] ) ) {
					$count = $row->gec_count === null ? null : (int)$row->gec_count;
					$originalCounts[(int)$row->lu_global_id] = $count;
				}
				$globalIds[$row->lu_wiki][(int)$row->lu_local_id] = (int)$row->lu_global_id;
				$countsByGlobalId[(int)$row->lu_global_id] = 0;
			}

			$numGlobalAccounts += count( $originalCounts );

			// Get the edit counts on each wiki using a batch query
			foreach ( $localIds as $wiki => $ids ) {
				$dblr = $this->lbFactory->getMainLB( $wiki )
					->getConnectionRef( DB_REPLICA, [], $wiki );
				$res = $dblr->newSelectQueryBuilder()
					->select( [
						'user_id',
						'user_editcount'
					] )
					->from( 'user' )
					->where( [ 'user_id' => $ids ] )
					->caller( __METHOD__ )
					->fetchResultSet();

				foreach ( $res as $row ) {
					if ( !isset( $globalIds[$wiki][$row->user_id] ) ) {
						throw new RuntimeException( "lost user ID {$row->user_id}" );
					}
					$countsByGlobalId[$globalIds[$wiki][$row->user_id]] += (int)$row->user_editcount;
				}
			}

			// Find users with a count mismatch
			$updates = [];
			$inserts = [];
			foreach ( $countsByGlobalId as $id => $count ) {
				if ( $originalCounts[$id] !== $count ) {
					if ( $originalCounts[$id] === null ) {
						$inserts[] = [
							'gec_user' => $id,
							'gec_count' => $count
						];
					} else {
						$updates[$id] = $count;
					}
				}
			}

			// Do the writes in small batches
			foreach ( array_chunk( $inserts, $this->getBatchSize() ) as $insertBatch ) {
				$this->beginTransaction( $dbcw, __METHOD__ );
				$dbcw->insert(
					'global_edit_count',
					$insertBatch,
					__METHOD__
				);
				$this->commitTransaction( $dbcw, __METHOD__ );
			}

			foreach ( array_chunk( $updates, $this->getBatchSize(), true ) as $updateBatch ) {
				$this->beginTransaction( $dbcw, __METHOD__ );
				foreach ( $updateBatch as $id => $count ) {
					$dbcw->update(
						'global_edit_count',
						[ 'gec_count' => $count ],
						[ 'gec_user' => $id ],
						__METHOD__
					);
				}
				$this->commitTransaction( $dbcw, __METHOD__ );
			}

			$numUpdated += count( $updates ) + count( $inserts );
		}

		$this->showProgress( $lastId, $lastId );
		$this->output( "Complete. Updated $numUpdated of $numGlobalAccounts edit counts.\n" );
	}

	private function showProgress( $position, $end ) {
		$now = time();
		if ( !$this->isQuiet() && $now - $this->lastReportTime >= 10 ) {
			printf(
				"... %d / %d (%-.2f%%)\n",
				$position,
				$end,
				$position / $end * 100
			);
			$this->lastReportTime = $now;
		}
	}
}

$maintClass = PopulateGlobalEditCount::class;
require RUN_MAINTENANCE_IF_MAIN;
