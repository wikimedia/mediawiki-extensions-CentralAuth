<?php

// --> disable account creations, password changes
// pass 0:
// * generate 'localuser' entries for each user on each wiki
// * generate 'globaluser' entries for each username
// --> enable 

require dirname(__FILE__) . '/../../maintenance/commandLine.inc';


/**
 * Copy user data for this wiki into the localuser table
 */
function migratePassZero() {
	global $wgDBname;
	$dbr = wfGetDB( DB_SLAVE );
	$chunkSize = 1000;
	
	$start = microtime( true );
	$migrated = 0;
	
	// We're going to run two queries side-by-side here.
	// The first fetches user data from 'user'
	// The second fetches edit counts from 'revision'
	//
	// We combine these into an unholy chimera and send it to the
	// central authentication server, which in theory might be
	// on another continent.
	
	$lastUser = $dbr->selectField( 'user', 'MAX(user_id)', '', __FUNCTION__ );
	for( $min = 0; $min <= $lastUser; $min += $chunkSize ) {
		$max = $min + $chunkSize;
		$user = $dbr->tableName( 'user' );
		$result = $dbr->query(
			"SELECT
				user_id,
				user_name,
				user_password,
				user_newpassword,
				user_email,
				user_email_authenticated,
				user_editcount
			FROM $user
			WHERE user_id > $min AND user_id <= $max
			GROUP BY user_id",
			__FUNCTION__ );
		
		while( $row = $dbr->fetchObject( $result ) ) {
			CentralAuthUser::storeMigrationData( $wgDBname, $row, $row->user_editcount );
			++$migrated;
		}
		$dbr->freeResult( $result );
		
		$delta = microtime( true ) - $start;
		$rate = ($delta == 0.0) ? 0.0 : $migrated / $delta;
		printf( "%s %d (%0.1f%%) done in %0.1f secs (%0.3f accounts/sec).\n",
			$wgDBname,
			$migrated,
			min( $max, $lastUser ) / $lastUser * 100.0,
			$delta,
			$rate );
	}
}

function getEditCount( $userId ) {
	return countEdits( $userId, 'revision', 'rev_user' );
}

function countEdits( $userId, $table, $field ) {
	$dbr = wfGetDB( DB_SLAVE );
	$count = $dbr->selectField( $table, 'COUNT(*)',
		array( $field => $userId ),
		__METHOD__ );
	return intval( $count );
}

if( $wgCentralAuthState != 'pass0' ) {
	if( $wgCentralAuthState == 'testing' ) {
		echo "WARNING: \$wgCentralAuthState is set to 'testing', generated data may be corrupt.\n";
	} else {
		wfDie( "\$wgCentralAuthState is '$wgCentralAuthState', please set to 'pass0' to prevent conflicts.\n" );
	}
}

echo "CentralAuth migration pass 0:\n";
echo "$wgDBname preparing migration data...\n";
migratePassZero();
echo "done.\n";

?>