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


/**
 * Initialization of the autoloaders, and special extension pages.
 */
$caBase = dirname( __FILE__ );
$wgAutoloadClasses['SpecialCentralAuth'] = "$caBase/SpecialCentralAuth.php";
$wgAutoloadClasses['SpecialMergeAccount'] = "$caBase/SpecialMergeAccount.php";
$wgAutoloadClasses['CentralAuthUser'] = "$caBase/CentralAuthUser.php";
$wgAutoloadClasses['CentralAuthPlugin'] = "$caBase/CentralAuthPlugin.php";
$wgAutoloadClasses['WikiMap'] = "$caBase/WikiMap.php";
$wgAutoloadClasses['WikiReference'] = "$caBase/WikiMap.php";

$wgExtensionFunctions[] = 'wfSetupCentralAuth';
$wgHooks['AuthPluginSetup'][] = 'wfSetupCentralAuthPlugin';
$wgHooks['PreferencesUserInformationPanel'][] = 'wfCentralAuthInformationPanel';

$wgGroupPermissions['steward']['centralauth-admin'] = true;

$wgSpecialPages['CentralAuth'] = 'SpecialCentralAuth';
$wgSpecialPages['MergeAccount'] = 'SpecialMergeAccount';

function wfSetupCentralAuth() {
	require dirname( __FILE__ ) . '/CentralAuth.i18n.php';
	global $wgCentralAuthMessages, $wgMessageCache;
	foreach( $wgCentralAuthMessages as $key => $messages ) {
		$wgMessageCache->addMessages( $messages, $key );
	}
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
	$skin = $wgUser->getSkin();
	$special = SpecialPage::getTitleFor( 'MergeAccount' );
	
	
	// Possible states:
	// - account not merged at all
	// - global accounts exists, but this local account is unattached
	// - this local account is attached, but migration incomplete
	// - all local accounts are attached
	
	$global = CentralAuthUser::newFromUser( $wgUser );
	if( $global ) {
		// Local is attached...
		$attached = count( $global->listAttached() );  // $attached var not used.
		$unattached = count( $global->listUnattached() );
		if( $unattached ) {
			// Migration incomplete
			$message = $global->getId() . " $unattached wikis left to migrate";
		} else {
			// Migration complete
			$message = $global->getId() . " Migration complete";
		}
	} else {
		$global = new CentralAuthUser( $wgUser->getName() );
		if( $global->exists() ) {
			// Account is in migration, but the local account is not attached
			$message = "This wiki has not been verified to the unified account";
		} else {
			// Not migrated.
			$message = "Not using unified account.";
		}
	}
	
	$html .= $prefsForm->addRow(
		wfMsgHtml( 'centralauth-globalid' ),
		$message . ' (' . $skin->makeKnownLinkObj( $special, 'merge your accounts' ) . ')' );
	return true;
}

?>