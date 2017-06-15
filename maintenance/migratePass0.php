<?php
// --> disable account creations, password changes
// pass 0:
// * generate 'globalnames' and 'localnames' entries for each user on each wiki
// --> enable

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/commandLine.inc";

/**
 * Copy user data for this wiki into the globalnames and localnames table
 */
function migratePassZero() {
	global $wgDBname;
	$dbr = wfGetDB( DB_REPLICA );
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
		printf( "%s %d (%0.1f%%) done in %0.1f secs (%0.3f accounts/sec).\n",
			$wgDBname,
			$migrated,
			min( $max, $lastUser ) / $lastUser * 100.0,
			$delta,
			$rate );

		if ( ( $min + $chunkSize ) % ( $chunkSize * 10 ) == 0 ) {
			echo "Waiting for slaves to catch up ... ";
			CentralAuthUtils::waitForSlaves();
			echo "done\n";
		}
	}
}

/**
 * @param int $userId
 * @return int
 */
function getEditCount( $userId ) {
	return countEdits( $userId, 'revision', 'rev_user' );
}

/**
 * @param int $userId
 * @param string $table
 * @param string $field
 * @return int
 */
function countEdits( $userId, $table, $field ) {
	$dbr = wfGetDB( DB_REPLICA );
	$count = $dbr->selectField( $table, 'COUNT(*)',
		[ $field => $userId ],
		__METHOD__ );
	return intval( $count );
}

echo "CentralAuth migration pass 0:\n";
echo "$wgDBname preparing migration data...\n";
migratePassZero();
echo "done.\n";
