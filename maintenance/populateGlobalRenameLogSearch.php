<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Script to back-populate log_search for global rename entries
 */
class PopulateGlobalRenameLogSearch extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CentralAuth' );
		$this->setBatchSize( 50 );
	}

	public function execute() {
		$dbr = $this->getDB( DB_REPLICA );
		$rows = $dbr->newSelectQueryBuilder()
			->select( [ 'log_id', 'log_params' ] )
			->from( 'logging' )
			->leftJoin( 'log_search', null, 'log_id=ls_log_id' )
			->where( [
				'log_type' => 'gblrename',
				'log_action' => 'rename',
				'ls_field' => null,
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$insert = [];

		foreach ( $rows as $row ) {
			$params = LogEntryBase::extractParams( $row->log_params );
			$insert[] = [
				'ls_field' => 'oldname',
				'ls_value' => $params['4::olduser'],
				'ls_log_id' => $row->log_id,
			];
			if ( count( $insert ) >= $this->mBatchSize ) {
				$this->insert( $insert );
				$insert = [];
			}
		}
		if ( $insert ) {
			$this->insert( $insert );
		}
	}

	/**
	 * @param array $rows
	 */
	private function insert( array $rows ) {
		$count = count( $rows );
		$this->output( "Inserting $count rows into log_search\n" );
		$dbw = $this->getPrimaryDB();
		$dbw->newInsertQueryBuilder()
			->insertInto( 'log_search' )
			->ignore()
			->rows( $rows )
			->caller( __METHOD__ )
			->execute();
		$this->waitForReplication();
	}
}

$maintClass = PopulateGlobalRenameLogSearch::class;
require_once RUN_MAINTENANCE_IF_MAIN;
