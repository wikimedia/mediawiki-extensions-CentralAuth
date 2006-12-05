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
 * 'pass1':      Central 'globaluser' tables are used for authentication.
 *               Core accounts are set up from migration data in the
 *               'migrateuser' table on demand, or by batch operation.
 *
 *               migratePass1.php should be run once while in this state,
 *               setting up the globaluser table and performing automatic
 *               account merges where possible.
 *
 *               Special:MergeAccount is available in this state for additional
 *               manual merging.
 *
 *
 * 'testing':    Use to run tests of the pass-0 and pass-1 data generation.
 *               Performs no locking, so may leave inconsistent state such
 *               as accounts which aren't migratable.
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
$wgHooks['AuthPluginSetup'][] = 'wfSetupCentralAuthPlugin';

$wgGroupPermissions['steward']['centralauth-admin'] = true;

function wfSetupCentralAuth() {
	require dirname( __FILE__ ) . '/CentralAuth.i18n.php';
	global $wgCentralAuthMessages, $wgMessageCache;
	foreach( $wgCentralAuthMessages as $key => $messages ) {
		$wgMessageCache->addMessages( $messages, $key );
	}
	
	global $wgCentralAuthState, $wgSpecialPages;
	if( $wgCentralAuthState == 'pass1' ) {
		$wgSpecialPages['MergeAccount'] = 'SpecialMergeAccount';
	}
}

function wfSetupCentralAuthPlugin( &$auth ) {
	global $wgCentralAuthState;
	switch( $wgCentralAuthState ) {
	case 'testing':
		// Pass through regular single-wiki behavior.
		// This state is to do tests of migration scripts on live
		// production data without interfering with behavior of
		// the running wikis.
		return true;
	case 'pass0':
		// FIXME
		// Should disable some operations ... ?
		return true;
	case 'pass1':
		// Will run on-demand migrations...
	case 'complete':
		$class = 'CentralAuthPlugin';
		break;
	default:
		throw new MWException( "Unexpected \$wgCentralAuthState value." );
	}
	$auth = new StubObject( 'wgAuth', $class );
	return true;
}

?>