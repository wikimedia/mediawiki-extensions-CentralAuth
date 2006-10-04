<?php

// --> disable account creations, password changes
// pass 0:
// * generate 'localuser' entries for each user on each wiki
// * generate 'globaluser' entries for each username
// --> enable 

require_once 'commandLine.inc';


/**
 * Copy user data for this wiki into the localuser table
 */
function migratePassZero() {
	global $wgDBname;
	$dbr = wfGetDB( DB_SLAVE ); // fixme for large dbs
	
	$start = microtime( true );
	
	// We're going to run two queries side-by-side here.
	// The first fetches user data from 'user'
	// The second fetches edit counts from 'revision'
	//
	// We combine these into an unholy chimera and send it to the
	// central authentication server, which in theory might be
	// on another continent.
	
	$user = $dbr->tableName( 'user' );
	$revision = $dbr->tableName( 'revision' );
	$result = $dbr->query(
		"SELECT
			user_id,
			user_name,
			user_password,
			user_newpassword,
			user_email,
			user_email_authenticated,
			COUNT(rev_user) AS user_editcount
		FROM $user
		LEFT OUTER JOIN $revision ON user_id=rev_user
		GROUP BY user_id" );
	
	$migrated = 0;
	while( $row = $dbr->fetchObject( $result ) ) {
		CentralAuthUser::storeLocalData( $wgDBname, $row, $row->user_editcount );
		if( ++$migrated % 100 == 0 ) {
			$delta = microtime( true ) - $start;
			$rate = ($delta == 0.0) ? 0.0 : $migrated / $delta;
			printf( "%d done in %0.1f secs (%0.3f accounts/sec).\n",
				$migrated, $delta, $rate );
		}
	}
	$dbr->freeResult( $result );
	
	$delta = microtime( true ) - $start;
	$rate = ($delta == 0.0) ? 0.0 : $migrated / $delta;
	printf( "%d done in %0.1f secs (%0.3f accounts/sec).\n",
		$migrated, $delta, $rate );
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

if( $wgCentralAuthState != 'premigrate' ) {
	if( $wgCentralAuthState == 'testing' ) {
		echo "WARNING: \$wgCentralAuthState is set to 'testing', generated data may be corrupt.\n";
	} else {
		wfDie( "\$wgCentralAuthState is '$wgCentralAuthState', please set to 'premigrate' to prevent conflicts.\n" );
	}
}

echo "CentralAuth migration pass 0:\n";
echo "$wgDBname preparing migration data...\n";
migratePassZero();
echo "done.\n";

?>