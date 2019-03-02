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
		$dbr = wfGetDB( DB_REPLICA );
		$rows = $dbr->select(
			[ 'logging', 'log_search' ],
			[ 'log_id', 'log_params' ],
			[
				'log_type' => 'gblrename',
				'log_action' => 'rename',
				'ls_field IS NULL'
			],
			__METHOD__,
			[],
			[ 'log_search' => [ 'LEFT JOIN', 'log_id=ls_log_id' ] ]
		);

		$insert = [];

		foreach ( $rows as $row ) {
			$params = unserialize( $row->log_params );
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

	private function insert( array $rows ) {
		$count = count( $rows );
		$this->output( "Inserting $count rows into log_search\n" );
		$dbw = wfGetDB( DB_MASTER );
		$dbw->insert( 'log_search', $rows, __METHOD__, [ 'IGNORE' ] );
		wfWaitForSlaves();
	}
}

$maintClass = PopulateGlobalRenameLogSearch::class;
require_once RUN_MAINTENANCE_IF_MAIN;
