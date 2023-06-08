<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/CommandLineInc.php";

use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\WikiMap\WikiMap;

echo "Populating global groups table with stewards...\n";

// Fetch local stewards
$dbl = wfGetDB( DB_REPLICA ); // Get local database
$localStewards = $dbl->newSelectQueryBuilder()
	->select( 'user_name' )
	->from( 'user' )
	->join( 'user_groups', null, 'user_id = ug_user' )
	->where( [
		'ug_group' => 'steward',
		'ug_expiry IS NULL OR ug_expiry >= ' . $dbl->addQuotes( $dbl->timestamp() )
	] )
	->caller( 'migrateStewards.php' )
	->fetchFieldValues();

echo "Fetched " . count( $localStewards ) . " from local database... Checking for attached ones\n";
$dbg = CentralAuthServices::getDatabaseManager()->getCentralPrimaryDB();
$globalStewards = [];
$result = $dbg->newSelectQueryBuilder()
	->select( [ 'gu_name', 'gu_id' ] )
	->from( 'globaluser' )
	->join( 'localuser', null, 'gu_name = lu_name' )
	->where( [
		'lu_wiki' => WikiMap::getCurrentWikiId(),
		'gu_name IN (' . $dbg->makeList( $localStewards ) . ')',
	] )
	->caller( 'migrateStewards.php' )
	->fetchResultSet();
foreach ( $result as $row ) {
	$globalStewards[$row->gu_name] = $row->gu_id;
}

echo "Fetched " . count( $localStewards ) . " SULed stewards... Adding them in group\n";
foreach ( $globalStewards as $user => $id ) {
	$dbg->insert( 'global_user_groups',
		[
			'gug_user' => $id,
			'gug_group' => 'steward' ],
		'migrateStewards.php' );
	// Using id as a hack for phan-taint-check.
	echo "Added user id " . ( (int)$id ) . "\n";

	$u = new CentralAuthUser( $user );
	$u->quickInvalidateCache(); // Don't bother regenerating the steward's cache.
}
