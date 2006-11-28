<?php

// pass 1: go through all usernames in 'localuser' and create 'globaluser' rows
//         for those that can be automatically migrated, go ahead and do it.

require dirname(__FILE__) . '/../../maintenance/commandLine.inc';

function migratePassOne() {
	$dbBackground = wfGetDB( DB_SLAVE, 'CentralAuth' ); // fixme for large dbs
	$result = $dbBackground->select(
		'localuser',
		array( 'lu_migrated_name' ),
		'',
		__METHOD__,
		array( 'GROUP BY' => 'lu_migrated_name' ) );
	while( $row = $dbBackground->fetchObject( $result ) ) {
		$name = $row->lu_migrated_name;
		$central = new CentralAuthUser( $name );
		if( $central->storeAndMigrate() ) {
			echo "Migrated '$name'\n";
		} else {
			echo "Incomplete '$name'\n";
		}
	}
	$dbBackground->freeResult( $result );
}

if( $wgCentralAuthState != 'pass1' ) {
	if( $wgCentralAuthState == 'testing' ) {
		echo "WARNING: \$wgCentralAuthState is set to 'testing', generated data may be corrupt.\n";
	} else {
		wfDie( "\$wgCentralAuthState is '$wgCentralAuthState', please set to 'premigrate'.\n" );
	}
}

echo "CentralAuth migration pass 1:\n";
echo "Finding accounts which can be migrated without interaction...\n";
migratePassOne();
echo "done.\n";

?>