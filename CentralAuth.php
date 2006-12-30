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
 * If true, existing unattached accounts will be automatically migrated
 * if possible at first login.
 *
 * Any new account creations will be required to attach.
 *
 * If false, unattached accounts will not be harassed unless the individual
 * account has opted in to migration.
 */
$wgCentralAuthAutoMigrate = false;

/**
 * If true, remaining accounts which have not been attached will be forbidden
 * from logging in until they are resolved.
 */
$wgCentralAuthStrict = false;


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
$wgHooks['PreferencesUserInformationPanel'][] = 'wfCentralAuthInformationPanel';

$wgGroupPermissions['steward']['centralauth-admin'] = true;

function wfSetupCentralAuth() {
	require dirname( __FILE__ ) . '/CentralAuth.i18n.php';
	global $wgCentralAuthMessages, $wgMessageCache;
	foreach( $wgCentralAuthMessages as $key => $messages ) {
		$wgMessageCache->addMessages( $messages, $key );
	}
	
	global $wgSpecialPages;
	$wgSpecialPages['MergeAccount'] = 'SpecialMergeAccount';
}

function wfSetupCentralAuthPlugin( &$auth ) {
	$auth = new StubObject( 'wgAuth', 'CentralAuthPlugin' );
	return true;
}

/**
 * Add a little pretty to the preferences user info section
 */
function wfCentralAuthInformationPanel( $prefsForm, &$html ) {
	global $wgUser;
	$global = CentralAuthUser::newFromUser( $wgUser );
	$html .= $prefsForm->addRow(
		wfMsgHtml( 'centralauth-globalid' ),
		$global
			? $global->getId()
			: 'not merged' );
	return true;
}

?>