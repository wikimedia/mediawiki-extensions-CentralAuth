<?php
// pass 1:
// * generate 'globaluser' entries for each username
// * go through all usernames in 'globalnames' and for those
//   that can be automatically migrated, go ahead and do it.

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once( "$IP/maintenance/commandLine.inc" );

$stats = array(
	"migrated" => 0,
	"total" => 0,
	"chunksize" => 1000,
	"start" => microtime( true ),
	"startPrefix" => ''
);

function migratePassOne() {
	global $stats;

	if ( $stats["chunksize"] < 1 ) {
		echo "You must select a positive chunk size or infinite recursion will ensue";
		die( 1 );
	}

	doMigratePassOne();

	migratePassOneReport( $stats['migrated'], $stats['total'], $stats['start'] );
	echo "DONE\n";
}

function doMigratePassOne( $prefix='' ) {
	global $stats;

	// enable us to continue where we left off
	if ( $prefix < $stats['startPrefix'] ) {
		// check to see if start is a proper subset of startPrefix
		if ( strpos( $stats['startPrefix'], $prefix ) !== 0 ) {
			return; // skip this prefix
		}
	}

	$keys = splitPrefixIntoKeys( $prefix );

	print_r( $keys );

	foreach ( $keys as $key => $count) {

		print $key . ' - '. $count . "\n";

		if ( $count > $stats['chunksize'] ) {
			doMigratePassOne( $prefix . $key );
		} else {
			doMigratePassOneSubset( $prefix . $key );
		}
	}
}

/**
 * Executes the migratePass1 for the specified subset of globalnames.
 *
 * This function assumes that all checks for chunk size have already been performed
 * and any over-sized chunks split into smaller parts.
 *
 * @param string $prefix The prefix used as a limiting factor
 */
function doMigratePassOneSubset( $prefix='' ) {
	global $stats;

	echo "-- Starting $prefix --\n";

	$dbBackground = CentralAuthUser::getCentralSlaveDB();

	$result = $dbBackground->select(
		'globalnames',
		array( 'gn_name', ),
		$prefix == '' ? '' : "gn_name like '$prefix%'",
		__METHOD__
	);

	foreach( $result as $row ) {
		$name = $row->gn_name;
		$central = new CentralAuthUser( $name );
		if ( $central->storeAndMigrate() ) {
			$stats['migrated']++;
		}
		if ( ++$stats['total'] % $stats['chunksize'] == 0 ) {
			migratePassOneReport( $stats['migrated'], $stats['total'], $stats['start'] );
		}
	}
	migratePassOneReport( $stats['migrated'], $stats['total'], $stats['start'] );
	echo "-- Done $prefix --\n";
}

function splitPrefixIntoKeys( $prefix='' ) {
	$subStart = strlen( $prefix ) + 1; // MySQL is 1-indexed

	$dbBackground = CentralAuthUser::getCentralSlaveDB();

	$result = $dbBackground->select(
		'globalnames',
		array( "substring(gn_name, $subStart, 1) as letter", 'count(*) as count' ),
		$prefix == '' ? '' : "gn_name like '$prefix%'",
		__METHOD__ ,
		array( 'ORDER BY' => 'letter ASC', 'GROUP BY' => 'letter' )
	);

	$keys = array();

	foreach ( $result as $row ) {
		$keys[$row->letter] = $row->count;
	}

	return $keys;
}

/**
 * @param $migrated
 * @param $total
 * @param $start
 */
function migratePassOneReport( $migrated, $total, $start ) {
	$delta = microtime( true ) - $start;
	printf( "%s processed %d usernames (%.1f/sec), %d (%.1f%%) fully migrated\n",
		wfTimestamp( TS_DB ),
		$total,
		$total / $delta,
		$migrated,
		$migrated / $total * 100.0 );
}

echo "CentralAuth migration pass 1:\n";
echo "Finding accounts which can be migrated without interaction...\n";
migratePassOne();
echo "done.\n";
