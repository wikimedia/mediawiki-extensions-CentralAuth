<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class ImportMissingLocalNames extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CentralAuth' );
		$this->setBatchSize( 500 );
	}

	public function execute() {
		$dbw = CentralAuthUtils::getCentralDB();

		$lastUser = $dbw->selectField( 'globaluser', 'MAX(gu_id)', '', __METHOD__ );
		$chunkSize = 10000;

		$count = 0;

		for ( $min = 0; $min <= $lastUser; $min += $chunkSize ) {
			$max = $min + $chunkSize - 1;
			$this->output( "Now processing global users with id between $min and $max...\n" );

			while ( true ) {
				$results = $dbw->select(
					[ 'globaluser', 'globalnames' ],
					[ 'gu_id', 'gu_name', 'gn_name' ],
					[ 'gn_name' => null, "gu_id BETWEEN $min AND $max" ],
					__METHOD__,
					[ 'LIMIT' => $this->mBatchSize ],
					[ 'globalnames' => [ 'LEFT JOIN', 'gn_name = gu_name' ] ]
				);

				if ( $results->numRows() === 0 ) {
					// done with this chunk of 10k users, move to the next chunk
					break;
				}

				foreach ( $results as $row ) {
					CentralAuthUser::getMasterInstanceByName( $row->gu_name )->importLocalNames();
					$count += 1;
				}

				$this->output( "Processed $count global users so far. Waiting for replicas to catch up... " );
				CentralAuthUtils::waitForReplicas();
				$this->output( "done\n" );
			}
		}

		$this->output( "Done.\n" );
	}
}

$maintClass = ImportMissingLocalNames::class;
require_once RUN_MAINTENANCE_IF_MAIN;
