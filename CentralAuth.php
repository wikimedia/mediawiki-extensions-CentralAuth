<?php

/**
 * Database name you keep central auth data in.
 *
 * If this is not on the primary database connection, don't forget
 * to also set up $wgDBservers to have an entry with a groupLoads
 * setting for the 'CentralAuth' group.
 */
$wgCentralAuthDatabase = 'centralauth';

/**
 * For making pretty HTTPS links to other wikis
 */
$wgSecureUrlHost = 'secure.wikimedia.org';


/**
 * Migration states: [not yet implemented fully]
 * 'pass0':      Local 'user' tables are still used for authentication,
 *               but with certain operations disabled to prevent conflicts
 *               while data is migrated to the central auth server.
 *
 *               migratePass0.php should be run on each wiki while in this
 *               state, setting up the localuser table.
 *
 * 'pass1':      Local 'user' tables are still used for authentication,
 *               but with certain operations disabled to prevent conflicts
 *               while data is migrated to the central auth server.
 *
 *               migratePass1.php should be run once while in this state,
 *               setting up the globaluser table and performing automatic
 *               account merges where possible.
 *
 * 'testing':    Use to run tests of the pass-0 and pass-1 data generation.
 *               Performs no locking, so may leave inconsistent state such
 *               as accounts which aren't migratable.
 *
 * 'pass2':      Authentication is done against 'globaluser', with automatic
 *               password-based migration done transparently on login.
 *
 *               Special:MergeAccount is available in this state for additional
 *               manual merging.
 *
 * 'complete':   Any remaining non-migrated accounts are locked out.
 *               Special:MergeAccount becomes unavailable.
 */
$wgCentralAuthState = 'testing';



// -----
// initialization work...

$wgAutoloadClasses['SpecialMergeAccount'] =
	dirname( __FILE__ ) . "/SpecialMergeAccount.php";
$wgAutoloadClasses['CentralAuthUser'] =
	dirname( __FILE__ ) . "/CentralAuthUser.php";
$wgAutoloadClasses['CentralAuthPlugin'] =
	dirname( __FILE__ ) . "/CentralAuthPlugin.php";

$wgExtensionFunctions[] = 'wfSetupCentralAuth';

function wfSetupCentralAuth() {
	require dirname( __FILE__ ) . '/CentralAuth.i18n.php';
	global $wgCentralAuthMessages, $wgMessageCache;
	foreach( $wgCentralAuthMessages as $key => $messages ) {
		$wgMessageCache->addMessages( $messages, $key );
	}
	
	global $wgCentralAuthState, $wgSpecialPages;
	if( $wgCentralAuthState == 'pass2' ) {
		$wgSpecialPages['MergeAccount'] = 'SpecialMergeAccount';
	}
}


?>