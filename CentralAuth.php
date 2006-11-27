<?php

$wgCentralAuthDatabase = 'authtest';

/**
 * Migration states: [not yet implemented fully]
 * 'premigrate': Local 'user' tables are still used for authentication,
 *               but with certain operations disabled to prevent conflicts
 *               while data is migrated to the central auth server.
 *
 * 'migration': Authentication is done against 'globaluser', with automatic
 *              transparent migration on login.
 *
 * 'production': Any remaining non-migrated accounts are locked out.
 *
 * 'testing': As 'premigrate', but no locking is done. Use to run tests
 *            of the pass-0 data generation.
 */
$wgCentralAuthState = 'disabled';



// -----
// initialization work...

$wgSpecialPages['MergeAccount'] = 'SpecialMergeAccount';

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
}


?>