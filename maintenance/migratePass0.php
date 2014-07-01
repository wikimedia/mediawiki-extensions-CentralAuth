<?php
// --> disable account creations, password changes
// pass 0:
// * generate 'globalnames' and 'localnames' entries for each user on each wiki
// --> enable

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once( "$IP/maintenance/Maintenance.php" );

/**
 * Copy user data for this wiki into the localuser table
 */
class MigratePassZero extends Maintenance {

	function execute() {
		global $wgDBname;
		$this->output( "CentralAuth migration pass 0:" );
		$this->output( "$wgDBname preparing migration data..." );
		$this->migratePassZero();
		$this->output( "done." );

	}

	function migratePassZero() {
		global $wgDBname;
		$dbr = $this->getDB( DB_SLAVE );
		$chunkSize = 1000;

		$start = microtime( true );
		$migrated = 0;
		$users = array();

		// List all user accounts on this wiki in the migration table
		// on the central authentication server.

		$lastUser = $dbr->selectField( 'user', 'MAX(user_id)', '', __FUNCTION__ );
		for ( $min = 0; $min <= $lastUser; $min += $chunkSize ) {
			$max = $min + $chunkSize - 1;
			$result = $dbr->select( 'user',
				array( 'user_id', 'user_name' ),
				"user_id BETWEEN $min AND $max",
				__FUNCTION__ );

			foreach( $result as $row ) {
				$users[intval( $row->user_id )] = $row->user_name;
				++$migrated;
			}

			CentralAuthUser::storeMigrationData( $wgDBname, $users );
			$users = array(); // clear the array for the next pass

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
				CentralAuthUser::waitForSlaves();
				$this->output( "done" );
			}
		}
	}

	/**
	 * @param $userId
	 * @return int
	 */
	function getEditCount( $userId ) {
		return $this->countEdits( $userId, 'revision', 'rev_user' );
	}

	/**
	 * @param $userId
	 * @param $table
	 * @param $field
	 * @return int
	 */
	function countEdits( $userId, $table, $field ) {
		$dbr = wfGetDB( DB_SLAVE );
		$count = $dbr->selectField( $table, 'COUNT(*)',
			array( $field => $userId ),
			__METHOD__ );
		return intval( $count );
	}

}

$maintClass = 'MigratePassZero';
require_once( RUN_MAINTENANCE_IF_MAIN );