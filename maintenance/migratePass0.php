<?php
// --> disable account creations, password changes
// pass 0:
// * generate 'globalnames' and 'localnames' entries for each user on each wiki
// --> enable

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Copy user data for this wiki into the globalnames and localnames table
 */
class MigratePass0 extends Maintenance {

	public function execute() {
		global $wgDBname;
		$this->output( "CentralAuth migration pass 0:" );
		$this->output( "$wgDBname preparing migration data..." );
		$this->doPassZero();
		$this->output( "done." );
	}

	private function doPassZero() {
		global $wgDBname;
		$dbr = $this->getDB( DB_REPLICA );
		$chunkSize = 1000;

		$start = microtime( true );
		$migrated = 0;
		$users = [];

		// List all user accounts on this wiki in the migration table
		// on the central authentication server.

		$lastUser = $dbr->selectField( 'user', 'MAX(user_id)', '', __FUNCTION__ );
		for ( $min = 0; $min <= $lastUser; $min += $chunkSize ) {
			$max = $min + $chunkSize - 1;
			$result = $dbr->select( 'user',
				[ 'user_id', 'user_name' ],
				"user_id BETWEEN $min AND $max",
				__FUNCTION__ );

			foreach ( $result as $row ) {
				$users[intval( $row->user_id )] = $row->user_name;
				++$migrated;
			}

			CentralAuthUser::storeMigrationData( $wgDBname, $users );
			$users = []; // clear the array for the next pass

			$delta = microtime( true ) - $start;
			$rate = ( $delta == 0.0 ) ? 0.0 : $migrated / $delta;
			$this->output( sprintf( "%s %d (%0.1f%%) done in %0.1f secs (%0.3f accounts/sec).",
				$wgDBname,
				$migrated,
				min( $max, $lastUser ) / $lastUser * 100.0,
				$delta,
				$rate ) );

			if ( ( $min + $chunkSize ) % ( $chunkSize * 10 ) == 0 ) {
				$this->output( "Waiting for slaves to catch up ... " );
				CentralAuthUtils::waitForSlaves();
				$this->output( "done" );
			}
		}
	}
}

$maintClass = MigratePass0::class;
require_once RUN_MAINTENANCE_IF_MAIN;
